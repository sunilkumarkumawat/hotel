<?php

namespace App\Support;

use App\Helpers\Helper;
use App\Jobs\SendQueuedStaffMail;
use App\Jobs\SendQueuedWhatsApp;
use App\Mail\NotificationMail;
use App\Models\Notification\AppNotification;
use App\Models\Notification\NotificationDelivery;
use App\Models\Notification\NotificationSetting;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Telling the hotel something happened.
 *
 * One line at the call site:
 *
 *     Notify::event('reservation.created')
 *         ->title('New booking — ' . $reservation->reservation_no)
 *         ->body($guest . ', ' . $nights . ' nights from ' . $arrival)
 *         ->url(route('reservation.show', $reservation))
 *         ->send();
 *
 * Three rules this class keeps, and they are the whole design:
 *
 * **It never throws.** A booking is not allowed to fail because a WhatsApp
 * token expired. Everything below the database write is wrapped, and a failure
 * becomes a row in `notification_deliveries` with the reason on it.
 *
 * **It never blocks for long.** Mail and WhatsApp go out on the queue when one
 * is configured (`QUEUE_CONNECTION` anything but `sync`) and inline when it is
 * not, which is the normal case on a hotel's own server. The HTTP timeout on
 * the WhatsApp call is what bounds the inline case.
 *
 * **What is switched off does not happen.** The bell is always written — it
 * costs one insert and it is what the desk actually reads — but mail and
 * WhatsApp only go if the branch's settings row says so, and a branch with no
 * settings row falls back to the defaults in config/notifications.php.
 */
class Notify
{
    private string $event;

    private ?int $branchId = null;

    private ?int $userId = null;

    private string $title = '';

    private ?string $body = null;

    private ?string $url = null;

    private ?string $icon = null;

    private ?string $level = null;

    private array $data = [];

    /** @var array<int, string> */
    private array $extraMail = [];

    /** @var array<int, string> */
    private array $extraWhatsApp = [];

    private bool $force = false;

    /**
     * Channels this one send is limited to, or null for "whatever the settings
     * say". Only the Test button sets it: a test of the email side should not
     * write a WhatsApp failure into the delivery log for a channel nobody was
     * testing.
     *
     * @var array<int, string>|null
     */
    private ?array $only = null;

    private function __construct(string $event)
    {
        $this->event = $event;
    }

    public static function event(string $event): self
    {
        return new self($event);
    }

    /**
     * The short form, for a call site that does not need the builder.
     *
     * @param  array{body?: string, url?: string, level?: string, icon?: string, user_id?: int, branch_id?: int, data?: array, mail?: array|string, whatsapp?: array|string}  $options
     */
    public static function fire(string $event, string $title, array $options = []): ?AppNotification
    {
        $notify = self::event($event)->title($title);

        foreach (['body', 'url', 'level', 'icon'] as $field) {
            if (isset($options[$field])) {
                $notify->{$field}($options[$field]);
            }
        }

        if (isset($options['user_id'])) {
            $notify->to((int) $options['user_id']);
        }

        if (isset($options['branch_id'])) {
            $notify->branch((int) $options['branch_id']);
        }

        if (isset($options['data'])) {
            $notify->data($options['data']);
        }

        if (isset($options['mail'])) {
            $notify->mail($options['mail']);
        }

        if (isset($options['whatsapp'])) {
            $notify->whatsapp($options['whatsapp']);
        }

        return $notify->send();
    }

    /* ── The builder ──────────────────────────────────────────────────── */

    public function title(string $title): self
    {
        $this->title = trim($title);

        return $this;
    }

    public function body(?string $body): self
    {
        $this->body = $body ? trim($body) : null;

        return $this;
    }

    public function url(?string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function icon(?string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function level(?string $level): self
    {
        $this->level = in_array($level, ['info', 'success', 'warning', 'danger'], true) ? $level : null;

        return $this;
    }

    /** Aim it at one person. Left alone, everybody in the branch sees it. */
    public function to(?int $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    public function branch(?int $branchId): self
    {
        $this->branchId = $branchId;

        return $this;
    }

    public function data(array $data): self
    {
        $this->data = $data;

        return $this;
    }

    /** @param  array<int, string>|string  $addresses */
    public function mail(array|string $addresses): self
    {
        $this->extraMail = array_merge(
            $this->extraMail,
            is_array($addresses) ? $addresses : NotificationSetting::split($addresses)
        );

        return $this;
    }

    /** @param  array<int, string>|string  $numbers */
    public function whatsapp(array|string $numbers): self
    {
        $this->extraWhatsApp = array_merge(
            $this->extraWhatsApp,
            is_array($numbers) ? $numbers : NotificationSetting::split($numbers)
        );

        return $this;
    }

    /**
     * Send it even though the branch has this event switched off.
     *
     * The Test button on the settings screen uses this, and nothing else
     * should: an event a manager turned off is an event they do not want.
     */
    public function force(bool $force = true): self
    {
        $this->force = $force;

        return $this;
    }

    /**
     * Send on these channels and no others.
     *
     * @param  array<int, string>  $channels
     */
    public function only(array $channels): self
    {
        $this->only = array_values(array_intersect(['mail', 'whatsapp'], $channels));

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Sending
    |--------------------------------------------------------------------------
    */

    public function send(): ?AppNotification
    {
        try {
            return $this->write();
        } catch (Throwable $e) {
            // A notification failing is never a reason for the thing that
            // caused it to fail. It goes in the log and the work carries on.
            Log::warning('[Notify] ' . $this->event . ' could not be raised: ' . $e->getMessage());

            return null;
        }
    }

    private function write(): ?AppNotification
    {
        $branchId = $this->branchId ?: (int) Helper::getActiveBranchId();

        if (! $branchId || $this->title === '') {
            return null;
        }

        $meta = event_meta($this->event);
        $setting = $this->setting($branchId);

        if (! $this->force && $setting && (int) $setting->status !== 1) {
            return null;
        }

        $notification = AppNotification::create([
            'branch_id' => $branchId,
            'user_id' => $this->userId,
            'event' => $this->event,
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'icon' => $this->icon ?: ($meta['icon'] ?? 'bell'),
            'level' => $this->level ?: ($meta['level'] ?? 'info'),
            'data' => $this->data ?: null,
            'created_by' => Auth::check() ? Auth::user()->user_id : null,
        ]);

        $channels = $this->channels($setting, $meta);

        if ($this->wants('mail', $channels)) {
            $this->postMail($notification, $setting, $branchId);
        }

        if ($this->wants('whatsapp', $channels)) {
            $this->postWhatsApp($notification, $setting, $branchId);
        }

        return $notification;
    }

    /** @param  array<int, string>  $channels */
    private function wants(string $channel, array $channels): bool
    {
        if ($this->only !== null) {
            return in_array($channel, $this->only, true);
        }

        return $this->force || in_array($channel, $channels, true);
    }

    /** @return array<int, string> */
    private function channels(?NotificationSetting $setting, array $meta): array
    {
        if ($setting) {
            return $setting->channelList();
        }

        return NotificationSetting::split($meta['channels'] ?? 'app');
    }

    private function setting(int $branchId): ?NotificationSetting
    {
        return NotificationSetting::query()
            ->where('branch_id', $branchId)
            ->where('event', $this->event)
            ->first();
    }

    /*
    |--------------------------------------------------------------------------
    | Channels
    |--------------------------------------------------------------------------
    */

    private function postMail(AppNotification $notification, ?NotificationSetting $setting, int $branchId): void
    {
        $hotel = Helper::activeBranch()?->branch_name;

        $addresses = array_unique(array_filter(array_merge(
            $setting?->mailAddresses() ?? [],
            NotificationSetting::defaults($branchId)?->mailAddresses() ?? [],
            $this->extraMail,
            $this->subscribers($branchId, 'notify_mail', 'email'),
        ), fn ($address) => filter_var($address, FILTER_VALIDATE_EMAIL)));

        if ($addresses === []) {
            NotificationDelivery::create([
                'app_notification_id' => $notification->id,
                'branch_id' => $branchId,
                'event' => $notification->event,
                'audience' => 'staff',
                'channel' => 'mail',
                'target' => 'nobody',
                'status' => 'failed',
                'error' => 'Email is ticked for this event but there is no address to send to. '
                    . 'Put one in "Send every staff notification to" at the top of Notification '
                    . 'Settings — that one box covers every event.',
            ]);

            return;
        }

        foreach ($addresses as $address) {
            $delivery = NotificationDelivery::create([
                'app_notification_id' => $notification->id,
                'branch_id' => $branchId,
                'event' => $notification->event,
                'audience' => 'staff',
                'channel' => 'mail',
                'target' => $address,
                'status' => 'pending',
            ]);

            /*
             * The SMTP conversation itself — the slow, network-bound part —
             * used to happen right here, inline, while the request that
             * raised this event (a reservation, a checkout, a shift close)
             * sat waiting for it. It now happens in SendQueuedStaffMail,
             * off the request thread; this row stays 'pending' until that
             * job actually runs and updates it, same status/error fields as
             * before.
             */
            SendQueuedStaffMail::dispatch($delivery->id, $notification->id, $address, $hotel);
        }
    }

    private function postWhatsApp(AppNotification $notification, ?NotificationSetting $setting, int $branchId): void
    {
        /*
         * Three places a staff number can come from, and the standing list is
         * the one that matters in practice: nobody types the manager's number
         * into fifty-four boxes, so without it every event is ticked and none
         * of them has anywhere to go.
         */
        $numbers = array_unique(array_filter(array_merge(
            $setting?->whatsappNumbers() ?? [],
            NotificationSetting::defaults($branchId)?->whatsappNumbers() ?? [],
            $this->extraWhatsApp,
            $this->subscribers($branchId, null, 'whatsapp_no'),
        )));

        $message = $this->messageText($notification);

        /*
         * WhatsApp ticked and not one number to send to is the commonest way
         * this looks broken: the event is on, the box is ticked, and the loop
         * below runs zero times, so the delivery log stays empty and the screen
         * has nothing to explain the silence. One row saying so is worth more
         * than a hundred correct ones.
         */
        if ($numbers === []) {
            NotificationDelivery::create([
                'app_notification_id' => $notification->id,
                'branch_id' => $branchId,
                'event' => $notification->event,
                'audience' => 'staff',
                'channel' => 'whatsapp',
                'target' => 'nobody',
                'status' => 'failed',
                'error' => 'WhatsApp is ticked for this event but there is no number to send to. '
                    . 'Put one in "Send every staff notification to" at the top of Notification '
                    . 'Settings — that one box covers every event.',
            ]);

            return;
        }

        foreach ($numbers as $number) {
            $delivery = NotificationDelivery::create([
                'app_notification_id' => $notification->id,
                'branch_id' => $branchId,
                'event' => $notification->event,
                'audience' => 'staff',
                'channel' => 'whatsapp',
                'target' => WhatsApp::number($number),
                'status' => 'pending',
            ]);

            /*
             * As with mail above: the actual gateway call — bounded by
             * WHATSAPP_TIMEOUT, 12 seconds by default — used to block the
             * request that raised this event. SendQueuedWhatsApp makes the
             * same call (and folds a 'logged' response into the same
             * failed-with-reason row via GuestMessage::markSent()) from the
             * queue instead.
             */
            SendQueuedWhatsApp::dispatch($delivery->id, $number, $message);
        }
    }

    /** Title, body and link — the shape of every WhatsApp message this sends. */
    private function messageText(AppNotification $notification): string
    {
        $hotel = Helper::activeBranch()?->branch_name;

        return trim(implode("\n", array_filter([
            $hotel ? '*' . $hotel . '*' : null,
            $notification->title,
            $notification->body,
            $notification->url,
        ])));
    }

    /**
     * Users in the branch who asked to be told.
     *
     * `$flag` is the column that has to be 1, or null when having the value at
     * all is the opt-in — a user who typed a WhatsApp number wants WhatsApp.
     *
     * @return array<int, string>
     */
    private function subscribers(int $branchId, ?string $flag, string $column): array
    {
        // A notification aimed at one person does not go to the whole branch.
        if ($this->userId) {
            $user = User::query()->where('user_id', $this->userId)->first();

            return $user && filled($user->{$column}) && (! $flag || (int) $user->{$flag} === 1)
                ? [(string) $user->{$column}]
                : [];
        }

        return User::query()
            ->where('branch_id', $branchId)
            ->where('status', 1)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->when($flag, fn ($q) => $q->where($flag, 1))
            ->pluck($column)
            ->map(fn ($value) => (string) $value)
            ->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Housekeeping of the notifications themselves
    |--------------------------------------------------------------------------
    */

    /**
     * Throw away anything older than `pms.notify_keep_days`.
     *
     * Called from the bell's own feed rather than from a scheduler, because a
     * hotel running this on XAMPP has no cron and a table that grows forever is
     * a support call in eighteen months. One delete per feed request at most,
     * and only when the day has turned.
     */
    public static function prune(): void
    {
        $days = (int) config('pms.notify_keep_days', 30);

        if ($days < 1) {
            return;
        }

        $cutoff = now()->subDays($days);

        try {
            $ids = AppNotification::query()
                ->where('created_at', '<', $cutoff)
                ->limit(500)
                ->pluck('id');

            if ($ids->isEmpty()) {
                return;
            }

            NotificationDelivery::whereIn('app_notification_id', $ids)->delete();
            AppNotification::whereIn('id', $ids)->delete();
        } catch (Throwable $e) {
            Log::warning('[Notify] prune failed: ' . $e->getMessage());
        }
    }
}
