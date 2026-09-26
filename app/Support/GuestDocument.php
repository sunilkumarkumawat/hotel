<?php

namespace App\Support;

use App\Helpers\Helper;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * The PDF that rides along with a guest's WhatsApp message.
 *
 * A message is read once. The PDF is what gets kept, forwarded to whoever is
 * paying, and shown at the desk — so it carries the same facts laid out
 * properly, under the hotel's own name.
 *
 * What it says lives in config/guest-documents.php, keyed by the same event as
 * the message, so a hotel changes its paperwork by editing a config file rather
 * than by asking anybody to change code.
 *
 * **The one thing that stops this working.** The gateway does not receive a
 * file; it receives a link and goes and fetches it. A link to
 * http://127.0.0.1:8000 points at the gateway's own machine, where there is
 * nothing, so on a laptop install no attachment can ever arrive. Rather than
 * send a broken link, {@see reachable()} checks first and the message goes out
 * on its own — and the settings screen says why, because a silently missing
 * attachment is the kind of thing somebody spends an afternoon on.
 */
class GuestDocument
{
    /** Where the files live, under storage/app. */
    private const FOLDER = 'guest-docs';

    /**
     * Build the PDF for an event and return the link to hand the gateway.
     *
     * Null means no attachment — no definition for this event, PDFs switched
     * off, the address unreachable, or something went wrong. In every one of
     * those cases the message still goes; only the attachment is skipped.
     *
     * @param  array<string, mixed>  $data  the same placeholders the message uses
     */
    public static function urlFor(string $event, array $data, ?int $branchId = null): ?string
    {
        $built = self::build($event, $data, $branchId);

        return $built && self::reachable()
            ? self::base() . '/guest-doc/' . $built['token']
            : null;
    }

    /**
     * Draw the document and return where it is on disk.
     *
     * Deliberately separate from the link. A laptop install cannot hand
     * WhatsApp a link to itself, but it can perfectly well attach the same PDF
     * to an email — so the file is always made, and only the link is
     * conditional. That is what makes the PDF work on a laptop and on a real
     * server without anything being configured differently.
     *
     * @param  array<string, mixed>  $data
     * @return array{path: string, token: string, name: string}|null
     */
    public static function build(string $event, array $data, ?int $branchId = null): ?array
    {
        try {
            if (! config('pms.guest_pdf', true)) {
                return null;
            }

            $definition = config('guest-documents')[$event] ?? null;

            if (! is_array($definition)) {
                return null;
            }

            $token = self::write($definition, $data, $branchId,$event);

            if (! $token) {
                return null;
            }

            /*
             * A filename somebody can find again in a downloads folder.
             * "Booking Confirmation RSV-1-0001.pdf" beats forty random
             * characters when the guest goes looking for it a week later.
             */
            $name = trim(($definition['title'] ?? 'Document') . ' ' . self::fill((string) ($definition['reference'] ?? ''), $data));
            $name = preg_replace('/[^A-Za-z0-9 .\-_]+/', '', $name) ?: 'Document';

            return [
                'path' => storage_path('app/' . self::FOLDER . '/' . $token . '.pdf'),
                'token' => $token,
                'name' => trim($name) . '.pdf',
            ];
        } catch (Throwable $e) {
            // A document that could not be built is never a reason for the
            // message not to go.
            Log::warning('[GuestDocument] ' . $event . ': ' . $e->getMessage());

            return null;
        }
    }

    /**
     * The address a guest's link is built on.
     *
     * PMS_PUBLIC_URL wins when it is set, which is the whole point of it: a
     * hotel running this on a laptop can put a tunnel's address there and have
     * WhatsApp attachments work without pretending the whole application lives
     * at that address.
     */
    public static function base(): string
    {
        return rtrim((string) (config('pms.public_url') ?: config('app.url')), '/');
    }

    /**
     * Can the gateway actually fetch a link to this installation?
     *
     * A private or loopback address is reachable from the hotel's own desk and
     * from nowhere else, which is exactly the case a laptop install is in.
     */
    public static function reachable(): bool
    {
        return self::unreachableBecause() === null;
    }

    /**
     * Why attachments are off, in one sentence — or null when they are on.
     *
     * The settings screen prints this. It is the difference between "the PDF
     * did not arrive" and "the PDF cannot arrive until this system is on a real
     * address, and here is why".
     */
    public static function unreachableBecause(): ?string
    {
        if (! config('pms.guest_pdf', true)) {
            return 'PDF attachments are switched off — PMS_GUEST_PDF=false in .env.';
        }

        $url = self::base();
        $host = parse_url($url, PHP_URL_HOST);

        if (! $host) {
            return 'APP_URL in .env is not a web address, so there is nothing to link a PDF to.';
        }

        $host = strtolower($host);

        if (in_array($host, ['localhost', '127.0.0.1', '0.0.0.0', '::1'], true)) {
            return 'WhatsApp cannot carry the PDF because APP_URL is ' . $url . '. WhatsApp '
                . 'fetches the file itself, and that address only means anything on this computer. '
                . 'The same PDF still goes out by email, attached, which works from here. To get '
                . 'it on WhatsApp too, put a public address on APP_URL — or on PMS_PUBLIC_URL if '
                . 'the application itself has to stay on 127.0.0.1.';
        }

        // 10.x, 192.168.x and 172.16–31.x are office addresses: reachable from
        // the next desk, and from nothing on the internet.
        if (filter_var($host, FILTER_VALIDATE_IP) && ! filter_var(
            $host,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        )) {
            return 'WhatsApp cannot carry the PDF because ' . $url . ' is an address on your own '
                . 'network, which WhatsApp cannot reach from the internet. The same PDF still goes '
                . 'out by email, attached. Put the hotel\'s public address on APP_URL — or on '
                . 'PMS_PUBLIC_URL — to get it on WhatsApp too.';
        }

        return null;
    }

    /** The file behind a link, or null when there is none. */
    public static function path(string $token): ?string
    {
        // A token is 40 letters and digits and nothing else, so a crafted one
        // cannot walk out of the folder.
        if (! preg_match('/^[A-Za-z0-9]{40}$/', $token)) {
            return null;
        }

        $path = storage_path('app/' . self::FOLDER . '/' . $token . '.pdf');

        return is_file($path) ? $path : null;
    }

    /*
    |--------------------------------------------------------------------------
    | Building
    |--------------------------------------------------------------------------
    */

    /**
     * Draw the document and put it where the link can find it.
     *
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $event
     */
    private static function write(array $definition, array $data, ?int $branchId, ? string $event): ?string
    {
        $hotel = self::hotel($branchId);
        $data = $data + $hotel;

        $pdf = new Pdf;

        self::header($pdf, $definition, $data, $hotel);
        self::body($pdf, $definition, $data, $event);
        self::footer($pdf, $hotel);

        $folder = storage_path('app/' . self::FOLDER);

        if (! is_dir($folder) && ! @mkdir($folder, 0755, true) && ! is_dir($folder)) {
            return null;
        }

        self::tidy($folder);

        $token = Str::random(40);

        return file_put_contents($folder . '/' . $token . '.pdf', $pdf->render()) === false
            ? null
            : $token;
    }

    /**
     * The coloured band: the hotel on the left, what this is on the right.
     *
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $hotel
     */
    private static function header(Pdf $pdf, array $definition, array $data, array $hotel): void
    {
        $isBooking = ($definition['title'] ?? '') === 'Booking Confirmation Voucher';

        if ($isBooking) {
            $teal = [0.00, 0.31, 0.27];
            $gold = [0.72, 0.49, 0.12];
            $soft = [0.91, 0.96, 0.94];
            $white = [1, 1, 1];

            $pdf->box(0, 0, Pdf::A4_WIDTH, 118, $teal);
            $pdf->textAt('H', $pdf->left(), 20, 34, true, 'left', $gold, 34);
            $pdf->textAt($hotel['hotel'], $pdf->left() + 42, 26, 17, true, 'left', $white, 250);
            $pdf->textAt('STAY MORE, LIVE BETTER', $pdf->left() + 43, 50, 7.5, false, 'left', $soft, 250);
            $pdf->textAt('BOOKING', 330, 23, 8.5, true, 'right', $soft, 205);
            $pdf->textAt('CONFIRMATION VOUCHER', 330, 37, 12, true, 'right', $white, 205);
            $reference = self::fill((string) ($definition['reference'] ?? ''), $data);
            $pdf->textAt($reference, 330, 59, 9.5, false, 'right', $soft, 205);
            $pdf->box(405, 78, 130, 25, [0.93, 0.98, 0.96]);
            $pdf->textAt('CONFIRMED', 405, 85, 9.5, true, 'center', $teal, 130);
            return;
        }

        // Every other document: the same teal-and-gold family as the booking
        // voucher, so every PDF a guest gets looks like it came from one
        // hotel — just without the two-tier "BOOKING / CONFIRMATION VOUCHER"
        // split, which only ever made sense for that one document.
        $teal = [0.00, 0.31, 0.27];
        $gold = [0.72, 0.49, 0.12];
        $soft = [0.91, 0.96, 0.94];
        $white = [1, 1, 1];

        $pdf->box(0, 0, Pdf::A4_WIDTH, 118, $teal);
        $initial = strtoupper(substr(trim((string) $hotel['hotel']), 0, 1)) ?: 'H';
        $pdf->textAt($initial, $pdf->left(), 20, 34, true, 'left', $gold, 34);
        $pdf->textAt($hotel['hotel'], $pdf->left() + 42, 26, 17, true, 'left', $white, 250);
        $under = array_filter([$hotel['address'], $hotel['phone']]);
        if ($under) {
            $pdf->textAt(implode('  ·  ', $under), $pdf->left() + 43, 50, 7.5, false, 'left', $soft, 250);
        }
        $pdf->textAt(strtoupper((string) ($definition['title'] ?? 'Document')), 330, 30, 13, true, 'right', $white, 205);
        $reference = self::fill((string) ($definition['reference'] ?? ''), $data);
        if ($reference !== '') {
            $pdf->textAt($reference, 330, 50, 9.5, false, 'right', $soft, 205);
        }
        $pdf->textAt('Issued ' . now()->format('d M Y, h:i A'), 330, 66, 7.5, false, 'right', $soft, 205);

        /*
         * A document about money says, at a glance, whether the guest still
         * owes anything — the first thing the desk itself would tell them.
         * Only kicks in when the call site actually put a balance in the
         * data (checkout, a payment, an advance): an event with nothing to
         * settle, like a registration slip or a kitchen order, keeps
         * whatever badge its config names instead.
         */
        $badge = strtoupper((string) ($definition['badge'] ?? 'CONFIRMED'));
        $badgeInk = $teal;
        $badgeBg = [0.93, 0.98, 0.96];

        if (array_key_exists('balance', $data)) {
            $due = self::fill('{balance}', $data) !== '';
            $badge = $due ? 'BALANCE DUE' : 'SETTLED';
            if ($due) {
                $badgeInk = [0.62, 0.14, 0.09];
                $badgeBg = [0.99, 0.93, 0.91];
            }
        }

        $badgeW = max(96, min(200, $pdf->textWidth($badge, 9.5, true) + 40));
        $pdf->box($pdf->right() - $badgeW, 78, $badgeW, 25, $badgeBg);
        $pdf->textAt($badge, $pdf->right() - $badgeW, 85, 9.5, true, 'center', $badgeInk, $badgeW);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $data
     */
    private static function body(Pdf $pdf, array $definition, array $data, string $event): void
    {
        if ($event === 'guest.booking') {
            self::bookingBody($pdf, $data);
            return;
        }

        self::genericBody($pdf, $definition, $data);
    }

    /**
     * Every document except the booking voucher: the same card-and-highlight
     * language as {@see bookingBody()}, laid out from whatever `sections`
     * (and optionally `highlight`) config/guest-documents.php gives the
     * event, rather than a fixed set of fields hand-placed for one event.
     * That is what makes a rich, on-brand PDF available to every event a
     * hotel adds a section to, not only the one document somebody once sat
     * down and designed by hand.
     *
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $data
     */
    private static function genericBody(Pdf $pdf, array $definition, array $data): void
    {
        $teal = [0.00, 0.31, 0.27];
        $ink = [0.10, 0.13, 0.16];
        $muted = [0.38, 0.42, 0.43];
        $faint = [0.55, 0.55, 0.60];
        $pale = [0.93, 0.97, 0.95];

        $pdf->at(140);

        if ($greeting = self::fill((string) ($definition['greeting'] ?? ''), $data)) {
            $pdf->text($greeting, 13, true, 'left', $ink);
            $pdf->gap(1);
        }

        if ($lead = self::fill((string) ($definition['lead'] ?? ''), $data)) {
            $pdf->paragraph($lead, 9.5, false, $muted);
        }

        $labelW = $pdf->width() * 0.32;
        $valueW = $pdf->width() - $labelW - 24;

        foreach ((array) ($definition['sections'] ?? []) as $heading => $rows) {
            $filled = [];
            foreach ((array) $rows as $label => $pattern) {
                $value = self::fill((string) $pattern, $data);
                if ($value !== '') $filled[$label] = $value;
            }
            if ($filled === []) continue;

            /*
             * Measured before anything is drawn. This class draws top-down
             * with no way to go back and stretch a box once something has
             * been placed below it, and a row's value — an item list most of
             * all — can wrap onto more than one line, where the old flat
             * layout silently kept only the first.
             */
            $rowLines = [];
            $bodyH = 0;
            foreach ($filled as $label => $value) {
                $lines = $pdf->wrap($value, $valueW, 8.6, true) ?: [''];
                $rowLines[$label] = $lines;
                $bodyH += max(20, count($lines) * 12 + 8);
            }

            $top = $pdf->cursor() + 10;
            $boxH = 30 + $bodyH;

            $pdf->box($pdf->left(), $top, $pdf->width(), $boxH, [0.98, 0.99, 0.99]);
            $pdf->box($pdf->left(), $top, $pdf->width(), 24, $pale);
            $pdf->textAt(strtoupper((string) $heading), $pdf->left() + 12, $top + 7, 8.5, true, 'left', $teal, $pdf->width() - 24);

            $y = $top + 24 + 10;
            foreach ($filled as $label => $value) {
                $lines = $rowLines[$label];
                $pdf->textAt((string) $label, $pdf->left() + 12, $y, 8.4, false, 'left', $muted, $labelW);
                foreach ($lines as $i => $line) {
                    $pdf->textAt($line, $pdf->left() + $labelW + 12, $y + $i * 12, 8.6, true, 'left', $ink, $valueW);
                }
                $y += max(20, count($lines) * 12 + 8);
            }

            $pdf->at($top + $boxH + 14);
        }

        if ($highlight = $definition['highlight'] ?? null) {
            self::highlightBox($pdf, (array) $highlight, $data);
        }

        if ($note = self::fill((string) ($definition['note'] ?? ''), $data)) {
            $pdf->gap(8)->rule();
            $pdf->paragraph($note, 9, false, $faint);
        }
    }

    /**
     * The gold callout every money-bearing document gets — the same shape as
     * the TOTAL AMOUNT box on the booking voucher, driven by config instead
     * of being that one document's own hand-placed code.
     *
     * @param  array<string, mixed>  $highlight  'label', 'value' ({pattern}) and an optional
     *                                            'sub' — a label => {pattern} map, e.g.
     *                                            ['Paid' => '{paid}', 'Balance' => '{balance}'].
     *                                            Each piece is filled and dropped on its own, the
     *                                            same as a section row — so "Balance: {balance}"
     *                                            with nothing paid off does not leave a dangling
     *                                            "Balance:" the way one combined string would.
     * @param  array<string, mixed>  $data
     */
    private static function highlightBox(Pdf $pdf, array $highlight, array $data): void
    {
        $value = self::fill((string) ($highlight['value'] ?? ''), $data);

        if ($value === '') {
            return;
        }

        $teal = [0.00, 0.31, 0.27];
        $gold = [0.72, 0.49, 0.12];
        $muted = [0.38, 0.42, 0.43];
        $pale = [0.93, 0.97, 0.95];

        $subParts = [];
        foreach ((array) ($highlight['sub'] ?? []) as $subLabel => $pattern) {
            $piece = self::fill((string) $pattern, $data);
            if ($piece !== '') {
                $subParts[] = $subLabel . ': ' . $piece;
            }
        }
        $sub = implode('   ', $subParts);

        $top = $pdf->cursor() + 4;
        $boxH = $sub !== '' ? 78 : 62;

        $pdf->box($pdf->left(), $top, $pdf->width(), $boxH, [0.98, 0.99, 0.99]);
        $pdf->box($pdf->left() + $pdf->width() - 220, $top + 8, 208, $boxH - 16, $pale);

        $label = strtoupper((string) ($highlight['label'] ?? 'TOTAL'));
        $pdf->textAt($label, $pdf->left() + $pdf->width() - 208, $top + 18, 8, true, 'left', $teal, 190);
        $pdf->textAt($value, $pdf->left() + $pdf->width() - 208, $top + 36, 18, true, 'left', $gold, 190);

        if ($sub !== '') {
            $pdf->textAt($sub, $pdf->left() + $pdf->width() - 208, $top + 58, 7.5, false, 'left', $muted, 190);
        }

        $pdf->at($top + $boxH + 14);
    }

    /** Branded one-page booking confirmation layout. */
    private static function bookingBody(Pdf $pdf, array $data): void
    {
        $teal = [0.00, 0.31, 0.27];
        $gold = [0.72, 0.49, 0.12];
        $ink = [0.10, 0.13, 0.16];
        $muted = [0.38, 0.42, 0.43];
        $pale = [0.93, 0.97, 0.95];

        $pdf->at(132);
        $guest = self::fill('{guest}', $data);
        $pdf->text($guest !== '' ? 'Dear ' . $guest . ',' : 'Dear Guest,', 12, true, 'left', $ink);
        $pdf->gap(1);
        $pdf->paragraph('Your reservation has been successfully confirmed. We look forward to welcoming you.', 9.5, false, $muted);

        $top = 184;
        $gap = 14;
        $cardW = ($pdf->width() - $gap) / 2;
        $cardH = 170;
        $left = $pdf->left();
        $right = $left + $cardW + $gap;

        $pdf->box($left, $top, $cardW, $cardH, [0.98, 0.99, 0.99]);
        $pdf->box($right, $top, $cardW, $cardH, [0.98, 0.99, 0.99]);
        $pdf->box($left, $top, $cardW, 26, $pale);
        $pdf->box($right, $top, $cardW, 26, $pale);
        $pdf->textAt('GUEST DETAILS', $left + 12, $top + 8, 9, true, 'left', $teal, $cardW - 24);
        $pdf->textAt('BOOKING DETAILS', $right + 12, $top + 8, 9, true, 'left', $teal, $cardW - 24);

        $y = $top + 40;
        $guestRows = [
            'Guest Name' => self::fill('{guest}', $data),
            'Phone' => self::fill('{guest_phone}', $data),
            'Email' => self::fill('{guest_email}', $data),
            'Address' => self::fill('{guest_address}', $data),
        ];
        foreach ($guestRows as $label => $value) {
            if ($value === '') continue;
            $pdf->textAt($label, $left + 12, $y, 8.2, false, 'left', $muted, 70);

            // A long address can run to several lines — the card has fixed
            // headroom for four before it would crowd Room Details below, so
            // anything longer is trimmed with an ellipsis rather than having
            // its last part vanish with no sign it was ever there.
            $lines = $pdf->wrap($value, $cardW - 98, 8.2);
            $maxLines = 4;
            $shown = array_slice($lines, 0, $maxLines);
            if (count($lines) > $maxLines) {
                $last = $shown[$maxLines - 1];
                while ($last !== '' && $pdf->textWidth($last . '...', 8.2) > $cardW - 98) {
                    $last = rtrim(mb_substr($last, 0, -1));
                }
                $shown[$maxLines - 1] = $last . '...';
            }
            foreach ($shown as $i => $line) {
                $pdf->textAt($line, $left + 86, $y + ($i * 10), 8.2, false, 'left', $ink, $cardW - 98);
            }
            $y += 28 + (count($shown) - 1) * 10;
        }

        $y = $top + 40;
        $bookingRows = [
            'Booking ID' => self::fill('{reservation_no}', $data),
            'Booking Date' => self::fill('{booking_date}', $data),
            'Check-in' => self::fill('{arrival} {arrival_time}', $data),
            'Check-out' => self::fill('{departure} {departure_time}', $data),
            'Total Nights' => self::fill('{nights}', $data),
            'Guests' => self::fill('{guests}', $data),
        ];
        foreach ($bookingRows as $label => $value) {
            if ($value === '') continue;
            $pdf->textAt($label, $right + 12, $y, 8.2, false, 'left', $muted, 75);
            $pdf->textAt($value, $right + 91, $y, 8.2, true, 'left', $ink, $cardW - 103);
            $y += 22;
        }

        $roomTop = $top + $cardH + 16;
        $pdf->box($left, $roomTop, $pdf->width(), 76, [0.99, 0.99, 0.99]);
        $pdf->box($left, $roomTop, $pdf->width(), 26, $pale);
        $pdf->textAt('ROOM DETAILS', $left + 12, $roomTop + 8, 9, true, 'left', $teal, $pdf->width() - 24);
        $pdf->textAt('Room Type', $left + 12, $roomTop + 39, 8.2, false, 'left', $muted, 70);
        $pdf->textAt(self::fill('{room_type}', $data), $left + 86, $roomTop + 39, 8.8, true, 'left', $ink, 135);
        $pdf->textAt('Room No.', $left + 235, $roomTop + 39, 8.2, false, 'left', $muted, 55);
        $pdf->textAt(self::fill('{room_no}', $data), $left + 292, $roomTop + 39, 8.8, true, 'left', $ink, 95);
        // Meal Plan gets its own line rather than a cramped third column — a
        // descriptive plan name ("CP (Breakfast Included)") needs more room
        // than the ~50pt a same-row column would leave before the page edge.
        $mealValue = self::fill('{meal_plan}', $data);
        if ($mealValue !== '') {
            $pdf->textAt('Meal Plan', $left + 12, $roomTop + 53, 8.2, false, 'left', $muted, 70);
            $mealW = $pdf->width() - 98;
            $mealLines = $pdf->wrap($mealValue, $mealW, 8.8, true);
            $mealShown = array_slice($mealLines, 0, 2);
            if (count($mealLines) > 2) {
                $last = $mealShown[1];
                while ($last !== '' && $pdf->textWidth($last . '...', 8.8, true) > $mealW) {
                    $last = rtrim(mb_substr($last, 0, -1));
                }
                $mealShown[1] = $last . '...';
            }
            foreach ($mealShown as $i => $line) {
                $pdf->textAt($line, $left + 86, $roomTop + 53 + ($i * 10), 8.8, true, 'left', $ink, $mealW);
            }
        }

        $payTop = $roomTop + 92;
        $pdf->box($left, $payTop, $pdf->width(), 92, [0.98, 0.99, 0.99]);
        $pdf->textAt('PAYMENT DETAILS', $left + 12, $payTop + 10, 9, true, 'left', $teal, 250);
        $pdf->textAt('Payment Mode', $left + 12, $payTop + 35, 8.2, false, 'left', $muted, 90);
        $pdf->textAt(self::fill('{payment_mode}', $data), $left + 100, $payTop + 35, 8.8, true, 'left', $ink, 125);
        $pdf->textAt('Status', $left + 12, $payTop + 57, 8.2, false, 'left', $muted, 90);
        $pdf->textAt(self::fill('{payment_status}', $data), $left + 100, $payTop + 57, 8.8, true, 'left', $teal, 125);
        $pdf->box($left + 285, $payTop + 10, $pdf->width() - 297, 68, $pale);
        $pdf->textAt('TOTAL AMOUNT', $left + 300, $payTop + 20, 8, true, 'left', $teal, $pdf->width() - 327);
        $pdf->textAt(self::fill('{amount}', $data), $left + 300, $payTop + 36, 17, true, 'left', $gold, $pdf->width() - 327);
        $pdf->textAt('Advance: ' . self::fill('{advance}', $data) . '   Balance: ' . self::fill('{balance}', $data), $left + 300, $payTop + 61, 7.5, false, 'left', $muted, $pdf->width() - 327);

        $infoTop = $payTop + 108;
        $pdf->box($left, $infoTop, $pdf->width(), 86, [0.98, 0.99, 0.99]);
        $pdf->textAt('IMPORTANT INFORMATION', $left + 12, $infoTop + 10, 9, true, 'left', $teal, $pdf->width() - 24);
        $bullets = [
            'Check-in: 02:00 PM  |  Check-out: 11:00 AM',
            'Please carry a valid photo ID for every adult guest.',
            'Early check-in and late check-out are subject to availability.',
            'For changes or cancellation, please contact the hotel support team.',
        ];
        $y = $infoTop + 29;
        foreach ($bullets as $bullet) {
            $pdf->textAt('- ' . $bullet, $left + 14, $y, 7.7, false, 'left', $muted, $pdf->width() - 28);
            $y += 13;
        }

        $pdf->textAt('Thank You', $left, 700, 14, true, 'right', $gold, $pdf->width());
        $pdf->textAt('For choosing ' . self::fill('{hotel}', $data), $left, 720, 7.8, false, 'right', $muted, $pdf->width());
    }

    /** @param  array<string, string>  $hotel */
    private static function footer(Pdf $pdf, array $hotel): void
    {
        $faint = [0.55, 0.55, 0.60];
        $bottom = Pdf::A4_HEIGHT - 72;

        $pdf->box($pdf->left(), $bottom, $pdf->width(), 0.6, [0.85, 0.85, 0.87]);

        $pdf->textAt($hotel['hotel'], $pdf->left(), $bottom + 12, 9, true, 'left', $faint);

        $contact = array_filter([$hotel['phone'], $hotel['email']]);

        if ($contact) {
            $pdf->textAt(implode('  ·  ', $contact), $pdf->left(), $bottom + 12, 9, false, 'right', $faint, $pdf->width());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * Fill the braces in, and say the line is empty if any of them were.
     *
     * Same rule the message uses: half a sentence is worse than none, so a
     * pattern with an unfilled placeholder in it produces nothing at all.
     *
     * @param  array<string, mixed>  $data
     */
    private static function fill(string $pattern, array $data): string
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

        if (! $empty) {
            return trim($filled);
        }

        // A sentence that still reads without its missing piece keeps going;
        // one that was only a placeholder does not.
        $left = trim(preg_replace('/[^\p{L}\p{N}]+/u', '', $filled) ?? '');

        return $left === '' ? '' : trim(preg_replace('/\s{2,}/', ' ', $filled) ?? '');
    }

    /**
     * The hotel's own details.
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

    /**
     * Throw away documents nobody is going to ask for again.
     *
     * A guest opens the link within minutes of the message; a folder of every
     * bill the hotel has ever sent is a year of PDFs nobody reads and one more
     * thing to worry about in a backup.
     */
    private static function tidy(string $folder): void
    {
        $days = max(1, (int) config('pms.guest_doc_days', 30));
        $cutoff = time() - $days * 86400;

        foreach (glob($folder . '/*.pdf') ?: [] as $file) {
            if (@filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
    }
}
