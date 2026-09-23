<?php

namespace App\Support;

use App\Helpers\Helper;
use App\Jobs\SendQueuedGuestMail;
use App\Jobs\SendQueuedWhatsApp;
use App\Mail\GuestMail;
use App\Models\Notification\NotificationDelivery;
use App\Models\Notification\NotificationSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * The WhatsApp messages that go to the GUEST.
 *
 * Staff notifications — the bell, the manager's email — are {@see Notify}.
 * This is the other half: the message that lands on the guest's own phone when
 * their booking is confirmed, when they check in, when the car is on its way.
 *
 * Three rules, and they are the whole design:
 *
 * **It never throws.** A booking must not fail because a gateway is down.
 * Everything is caught, and the failure becomes a row in
 * `notification_deliveries` with the gateway's own words on it — which is the
 * screen somebody looks at when a guest says "I never got anything".
 *
 * **A message nobody switched on is not sent.** Each one is an event on
 * Administration → Notification Settings with WhatsApp as its channel. Turn the
 * event off and it stops, without anybody editing a file.
 *
 * **An empty placeholder takes its line with it.** A booking with no arrival
 * time should not send a message with a dangling "Time:" on a line of its own.
 * Rendering removes the whole line rather than printing a blank.
 */
class GuestMessage
{
    /** Said on every row that was written to a log file instead of a phone. */
    public const LOG_ONLY = 'Not sent. WHATSAPP_DRIVER=log in .env, so the message went to '
        . 'storage/logs/laravel.log instead of the phone. Put WHATSAPP_DRIVER=chatway in .env '
        . 'and run php artisan config:clear.';

    /**
     * Tell the guest, on every channel that is switched on.
     *
     * Two channels, one set of facts. WhatsApp gets the short version with a
     * link to the PDF; email gets the same thing laid out properly with the PDF
     * attached. They are deliberately independent: one failing never stops the
     * other, and each leaves its own row in the delivery log, so "did the guest
     * get it?" has a per-channel answer rather than a shrug.
     *
     * The guest's email address rides in `$data['guest_email']`, which keeps
     * this signature the same for the thirteen places that call it and lets a
     * call site opt in by adding one line.
     *
     * @param  string  $event  a key from config/guest-messages.php
     * @param  string|null  $mobile  however the desk typed it
     * @param  array<string, mixed>  $data  the placeholders
     * @return bool  true when at least one channel took it
     */
    public static function send(
        string $event,
        ?string $mobile,
        array $data,
        ?int $branchId = null,
        ?string $fileUrl = null
    ): bool {
        try {
            $branchId ??= (int) Helper::getActiveBranchId();

            $wantsWhatsApp = self::isOn($event, $branchId, 'whatsapp');
            $wantsMail = self::isOn($event, $branchId, 'mail');

            // Both switched off is a decision somebody made, so it leaves no
            // trace at all.
            if (! $wantsWhatsApp && ! $wantsMail) {
                return false;
            }

            $email = trim((string) ($data['guest_email'] ?? ''));
            $email = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';

            /*
             * The PDF is built once and used twice: WhatsApp is handed a link
             * to it, email carries the bytes. Building it here rather than
             * inside each channel is what stops a guest being sent two
             * different copies of the same confirmation.
             */
            $document = GuestDocument::build($event, $data, $branchId);

            // A link only when this installation can actually be reached from
            // the internet — see GuestDocument for why that is not always true.
            $fileUrl ??= $document && GuestDocument::reachable()
                ? GuestDocument::base() . '/guest-doc/' . $document['token']
                : null;

            $sent = false;

            if ($wantsWhatsApp) {
                $sent = self::whatsapp($event, $mobile, $data, $branchId, $fileUrl, $document !== null) || $sent;
            }

            if ($wantsMail && $email !== '') {
                $sent = self::mail($event, $email, $data, $branchId, $document) || $sent;
            }

            return $sent;
        } catch (Throwable $e) {
            // Nothing about telling a guest something is allowed to break the
            // thing that happened. It goes in the log and the work carries on.
            Log::warning('[GuestMessage] ' . $event . ' could not be raised: ' . $e->getMessage());

            return false;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | The two channels
    |--------------------------------------------------------------------------
    */

    /**
     * The short version, on the guest's phone.
     *
     * @param  array<string, mixed>  $data
     */
    private static function whatsapp(
        string $event,
        ?string $mobile,
        array $data,
        int $branchId,
        ?string $fileUrl,
        bool $hasDocument
    ): bool {
        if (blank($mobile)) {
            self::markFailed(
                self::logRow('no number', '', $event, $branchId),
                'Nothing was sent: this booking has no mobile number on it.'
            );

            return false;
        }

        $template = guest_template($event);

        if (! $template) {
            Log::warning('[GuestMessage] no template for ' . $event);

            self::markFailed(
                self::logRow(WhatsApp::number($mobile), '', $event, $branchId),
                'Nothing was sent: there is no message template called "' . $event
                . '" in config/guest-messages.php.'
            );

            return false;
        }

        /*
         * {attachment} in a template becomes a sentence about wherever the PDF
         * actually went, and nothing at all when there is no PDF — so no
         * message ever promises a document that was not sent. A laptop install
         * cannot put the file on WhatsApp but can still email it, and the
         * sentence says so rather than leaving the guest looking for a file
         * that is not there.
         */
        $data['attachment'] ??= match (true) {
            $fileUrl !== null => 'Your copy is attached to this message.',
            $hasDocument => 'A copy has been emailed to you.',
            default => null,
        };

        $text = self::render($template, $data + self::hotel($branchId));

        if (trim($text) === '') {
            self::markFailed(
                self::logRow(WhatsApp::number($mobile), '', $event, $branchId),
                'Nothing was sent: the "' . $event . '" template came out empty once the '
                . 'blanks were filled in.'
            );

            return false;
        }

        $delivery = self::logRow(WhatsApp::number($mobile), $fileUrl ? 'with PDF' : '', $event, $branchId);

        /*
         * The gateway call itself used to happen right here, inline, while
         * whatever the guest was waiting on (their booking, their bill)
         * waited with it. SendQueuedWhatsApp makes the same call — and marks
         * this same row sent/failed the same way — from the queue instead,
         * so this method returns the moment the row is written.
         */
        SendQueuedWhatsApp::dispatch($delivery->id, $mobile, $text, $fileUrl);

        return true;
    }

    /**
     * The same thing in the guest's inbox, with the PDF attached.
     *
     * This is the channel that works everywhere. Email carries the file itself
     * rather than a link to it, so a hotel running this on a laptop still sends
     * its guests a proper confirmation with a document they can keep.
     *
     * @param  array<string, mixed>  $data
     * @param  array{path: string, token: string, name: string}|null  $document
     */
    private static function mail(
        string $event,
        string $email,
        array $data,
        int $branchId,
        ?array $document
    ): bool {
        $definition = config('guest-documents')[$event] ?? null;

        if (! is_array($definition)) {
            // No document definition means there is nothing to lay out as an
            // email. The WhatsApp message still went.
            return false;
        }

        $hotel = self::hotel($branchId);
        $delivery = self::logRow($email, $document ? 'with PDF' : '', $event, $branchId, 'mail');

        $payload = $data + $hotel;
        $payload['__reference'] = self::plain((string) ($definition['reference'] ?? ''), $payload);

        /*
         * As with WhatsApp above: building $payload is cheap (string work,
         * no network) and stays here; the SMTP conversation is the slow part
         * and moves into SendQueuedGuestMail, off the request thread.
         */
        SendQueuedGuestMail::dispatch($delivery->id, $email, $definition, $payload, $document, $hotel['hotel']);

        return true;
    }

    /** A pattern with its braces filled in, or '' when any of them was empty. */
    private static function plain(string $pattern, array $data): string
    {
        if ($pattern === '') {
            return '';
        }

        $empty = false;

        $filled = preg_replace_callback('/\{(\w+)\}/', function (array $match) use ($data, &$empty) {
            $value = $data[$match[1]] ?? null;

            if ($value === null || $value === '' || $value === false) {
                $empty = true;

                return '';
            }

            return (string) $value;
        }, $pattern) ?? '';

        return $empty ? '' : trim($filled);
    }

    /**
     * Fill a template in.
     *
     * A placeholder with nothing behind it is not replaced with an empty
     * string — the whole line goes, unless the line has other words on it that
     * still make sense. That is the difference between a message that reads
     * like a person wrote it and one that reads like software.
     *
     * @param  array<string, mixed>  $data
     */
    public static function render(string $template, array $data): string
    {
        $lines = [];

        foreach (explode("\n", $template) as $line) {
            $blank = false;

            $filled = preg_replace_callback('/\{(\w+)\}/', function (array $m) use ($data, &$blank) {
                $value = $data[$m[1]] ?? null;

                if ($value === null || $value === '' || $value === false) {
                    $blank = true;

                    return '';
                }

                return (string) $value;
            }, $line);

            if ($blank) {
                // Nothing but punctuation left — the line was only the
                // placeholder, so it goes.
                if (trim(preg_replace('/[^\p{L}\p{N}]+/u', '', $filled)) === '') {
                    continue;
                }

                /*
                 * A label line — "Room:", "*Check-in:*", "Driver mobile:" —
                 * with nothing after the colon goes too. Tested by the colon
                 * rather than by what is in front of it, because the labels in
                 * these templates carry hyphens and spaces and any list of
                 * allowed characters would eventually miss one.
                 *
                 * WhatsApp's own markup is stripped before the test: a bold
                 * label is written *Check-in:* and its closing star sits after
                 * the colon, so testing the raw line would find a star and keep
                 * a row reading "*Check-in:*" with nothing in it.
                 */
                if (preg_match('/:[\s*_~`]*$/u', $filled)) {
                    continue;
                }

                // "Welcome to {hotel}, {guest}" with no guest still says
                // something, so the line stays — but not its dangling comma,
                // and not a bold marker left hanging past it either.
                $filled = preg_replace('/[,;:\-–—]\s*([*_~`]*)$/u', '$1', rtrim($filled));
            }

            $lines[] = rtrim($filled);
        }

        // Collapse the runs of blank lines a dropped line can leave behind.
        return trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)));
    }

    /**
     * Is this message switched on for this branch?
     *
     * A branch that has never opened the settings screen falls back to the
     * default in config/notifications.php, which is how a hotel gets guest
     * messages working by pasting a token and nothing else.
     */
    public static function isOn(string $event, int $branchId, string $channel = 'whatsapp'): bool
    {
        $setting = NotificationSetting::query()
            ->where('branch_id', $branchId)
            ->where('event', $event)
            ->first();

        if ($setting) {
            return $setting->uses($channel);
        }

        $channels = NotificationSetting::split(event_meta($event)['channels'] ?? '');

        return in_array($channel, $channels, true);
    }

    /*
    |--------------------------------------------------------------------------
    | The delivery log
    |--------------------------------------------------------------------------
    | Written before the send, not after, so a request that dies mid-call
    | leaves a `pending` row rather than no row at all. A message with no
    | record of it is the one failure nobody can investigate.
    */

    public static function logRow(
        string $target,
        string $note = '',
        string $event = 'guest.direct',
        ?int $branchId = null,
        string $channel = 'whatsapp'
    ): ?NotificationDelivery {
        try {
            return NotificationDelivery::create([
                'app_notification_id' => null,
                'branch_id' => $branchId ?: (int) Helper::getActiveBranchId(),
                'event' => $event,
                'audience' => 'guest',
                'channel' => $channel,
                'target' => $target . ($note ? ' (' . $note . ')' : ''),
                'status' => 'pending',
            ]);
        } catch (Throwable $e) {
            // A missing table on an un-migrated database must not stop the
            // message going out. The send still happens; only the log is lost.
            return null;
        }
    }

    /**
     * What the sender returned, turned into a row somebody can trust.
     *
     * `logged` is what comes back when WHATSAPP_DRIVER=log: the message was
     * written to storage/logs/laravel.log and no phone ever saw it. Writing
     * that down as "sent" is how a hotel ends up believing guests were
     * messaged when nobody was — which is precisely the bug this log exists to
     * prevent — so it is recorded as not sent, with the reason on the row.
     */
    public static function markSent(?NotificationDelivery $delivery, string $response): void
    {
        if ($response === 'logged') {
            self::markFailed($delivery, self::LOG_ONLY);

            return;
        }

        $delivery?->update([
            'status' => 'sent',
            'sent_at' => now(),
            'error' => $response === 'sent' ? null : $response,
        ]);
    }

    public static function markFailed(?NotificationDelivery $delivery, string $error): void
    {
        $delivery?->update(['status' => 'failed', 'error' => $error]);
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * The hotel's own details, available to every template.
     *
     * @return array<string, string>
     */
    private static function hotel(?int $branchId): array
    {
        $branch = Helper::activeBranch();

        return [
            'hotel' => (string) ($branch?->branch_name ?? config('app.name')),
            'phone' => (string) ($branch?->mobile_number ?? ''),
            'email' => (string) ($branch?->email ?? ''),
            'address' => (string) ($branch?->address ?? ''),
        ];
    }
}
