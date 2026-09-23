<?php

namespace App\Support;

use App\Models\FrontOffice\BusinessDay;
use App\Models\FrontOffice\CheckIn;
use App\Models\Master\Room;
use App\Models\Reservation\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The night audit: the hotel's day, closed.
 *
 * Once every twenty-four hours somebody walks the house, charges the night's
 * rent to every occupied room, writes off the bookings that never turned up,
 * takes the day's figures and moves the hotel's date forward. Until that is
 * done the day is still being traded and its numbers still move; after it, the
 * numbers are a photograph nobody can change by opening a screen.
 *
 * Three rules hold this together:
 *
 *   **It posts nothing itself.** Room rent is posted by App\Support\Folio,
 *   which is the same code the check-in and checkout screens use. An audit
 *   that charged rent its own way would be a second opinion on what a night
 *   costs, and the two would drift apart within a month.
 *
 *   **It can be run twice.** Folio::postRoomCharges() already refuses to
 *   charge a night it has charged before, the no-show sweep only touches
 *   bookings still waiting, and the day row is claimed under a lock. A clerk
 *   who presses Run, loses the wifi and presses it again gets one night's
 *   rent, not two.
 *
 *   **It never runs ahead of itself.** The business date is the day after the
 *   last one closed, and it stops at today: a hotel that has not audited for a
 *   week closes those seven days one at a time, in order, each with its own
 *   figures — rather than one enormous close that says nothing about any of them.
 */
class NightAudit
{
    /**
     * The date the hotel is trading on — the next night waiting to be closed.
     *
     * The day after the last close, or today if the hotel has never run one.
     * Never later than today: tonight cannot be audited until it is over.
     */
    public static function businessDate(int $branchId): string
    {
        $today = CarbonImmutable::parse(today()->toDateString());
        $last = BusinessDay::lastClosed($branchId);

        if (! $last) {
            return $today->toDateString();
        }

        $next = CarbonImmutable::parse($last->business_date->toDateString())->addDay();

        return $next->greaterThan($today) ? $today->toDateString() : $next->toDateString();
    }

    /** How many nights are waiting, when nobody has run the audit for a while. */
    public static function pendingNights(int $branchId): int
    {
        $date = CarbonImmutable::parse(self::businessDate($branchId));

        return max(0, (int) $date->diffInDays(CarbonImmutable::parse(today()->toDateString())) + 1);
    }

    /*
    |--------------------------------------------------------------------------
    | Before the close
    |--------------------------------------------------------------------------
    */

    /**
     * What the audit is about to do, and what the desk should fix first.
     *
     * Writes nothing. Every check is a question a duty manager would ask on
     * the walk round, with the number of rooms it applies to and the screen
     * that fixes it. None of them stop the audit — a hotel that cannot close
     * its day because one guest is late paying has a worse problem than an
     * untidy report — but each one is a thing that will be wrong tomorrow if
     * it is left alone tonight.
     *
     * @return array{
     *     date: string, closed: bool, day: ?BusinessDay, checks: list<array<string, mixed>>,
     *     blocking: int, figures: array<string, mixed>, stays: int
     * }
     */
    public static function preview(int $branchId, ?string $date = null): array
    {
        $date = $date ?: self::businessDate($branchId);
        $day = self::dayRow($branchId, $date);

        $checks = self::checks($branchId, $date);

        return [
            'date' => $date,
            'closed' => (bool) $day?->isClosed(),
            'day' => $day,
            'checks' => $checks,
            'blocking' => collect($checks)->where('tone', 'danger')->sum('count'),
            'figures' => $day?->isClosed() ? ($day->figures ?? []) : self::figures($branchId, $date),
            'stays' => self::staysOn($branchId, $date)->count(),
        ];
    }

    /**
     * The walk round, as a list.
     *
     * @return list<array{key: string, label: string, count: int, tone: string, hint: string, url: ?string}>
     */
    public static function checks(int $branchId, string $date): array
    {
        $arrivalsDue = DB::table('reservation_rooms as rr')
            ->join('reservations as r', 'r.id', '=', 'rr.reservation_id')
            ->where('r.branch_id', $branchId)
            ->whereIn('r.status', ['confirmed', 'tentative'])
            ->where('rr.arrival_date', '<=', $date)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('check_ins as ci')
                    ->whereColumn('ci.reservation_id', 'r.id')
                    ->where('ci.status', '!=', 'cancelled');
            })
            ->count();

        $overstays = DB::table('check_ins')
            ->where('branch_id', $branchId)
            ->where('status', 'in_house')
            ->whereRaw('COALESCE(actual_checkout_date, expected_checkout_date) <= ?', [$date])
            ->count();

        $openOrders = DB::table('pos_orders')
            ->where('branch_id', $branchId)
            ->where('status', 'open')
            ->whereRaw('DATE(opened_at) <= ?', [$date])
            ->count();

        $dirty = Room::query()
            ->forBranch($branchId)
            ->active()
            ->whereIn('housekeeping_status', ['dirty', 'cleaning'])
            ->count();

        return [
            [
                'key' => 'arrivals',
                'label' => 'Arrivals not checked in',
                'count' => $arrivalsDue,
                'tone' => $arrivalsDue ? 'danger' : 'success',
                'hint' => $arrivalsDue
                    ? 'These bookings will be marked No Show and their rooms released. Check in anyone who is actually here first.'
                    : 'Every booking due by this date has either arrived or been dealt with.',
                'url' => 'front-office/check-in-guest',
            ],
            [
                'key' => 'departures',
                'label' => 'Departures still in house',
                'count' => $overstays,
                'tone' => $overstays ? 'warning' : 'success',
                'hint' => $overstays
                    ? 'Their checkout date has passed. Check them out, or extend the stay so tonight\'s rent is charged.'
                    : 'Nobody is staying past their checkout date.',
                'url' => 'front-office/check-out-guest',
            ],
            [
                'key' => 'pos',
                'label' => 'Restaurant orders still open',
                'count' => $openOrders,
                'tone' => $openOrders ? 'warning' : 'success',
                'hint' => $openOrders
                    ? 'An open KOT is not in the day\'s sale yet. Bill or cancel them before closing.'
                    : 'Every order is billed or settled.',
                'url' => 'pos/order',
            ],
            [
                'key' => 'housekeeping',
                'label' => 'Rooms not yet ready',
                'count' => $dirty,
                'tone' => $dirty ? 'info' : 'success',
                'hint' => $dirty
                    ? 'Not the audit\'s business, but tomorrow\'s arrivals need somewhere to sleep.'
                    : 'The whole house is ready to sell.',
                'url' => 'house-keeping/board',
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | The close
    |--------------------------------------------------------------------------
    */

    /**
     * Close the night.
     *
     * Everything happens inside one transaction, so a failure halfway leaves
     * the day exactly as it was rather than half-charged. The day row is
     * claimed with a row lock before any of it: two clerks pressing Run at the
     * same second is precisely how a night gets charged twice.
     *
     * `$userId` is null when nobody ran it — the scheduled command has no
     * signed-in user, and recording it against whoever happened to be last is
     * worse than recording nothing.
     *
     * @throws PostingRefused when the night has already been audited
     */
    public static function run(int $branchId, ?int $userId = null, ?string $note = null, ?string $date = null): BusinessDay
    {
        $date = $date ?: self::businessDate($branchId);

        return DB::transaction(function () use ($branchId, $userId, $note, $date) {
            $day = self::claim($branchId, $date);

            if ($day->isClosed()) {
                throw new PostingRefused(
                    'The night of ' . CarbonImmutable::parse($date)->format('d M Y') . ' has already been audited.'
                );
            }

            // 1. Tonight's rent, on every room with somebody in it.
            $nights = 0;

            foreach (self::staysOn($branchId, $date) as $checkIn) {
                $nights += Folio::for($checkIn)->postRoomCharges($userId);
            }

            // 2. The bookings that never turned up.
            $noShows = self::markNoShows($branchId, $date);

            // 3. The figures, taken after the posting so they include it.
            $figures = self::figures($branchId, $date);

            $day->update([
                'status' => 'closed',
                'nights_posted' => $nights,
                'no_shows' => $noShows,
                'rooms_sold' => (int) ($figures['rooms']['sold'] ?? 0),
                'figures' => $figures,
                'note' => $note,
                'closed_by' => $userId,
                'closed_at' => now(),
            ]);

            return $day->refresh();
        });
    }

    /**
     * Tell whoever is subscribed that the night is closed.
     *
     * Deliberately outside the transaction and deliberately swallowed: a mail
     * server that is down must not roll back a night that was correctly
     * audited. The figures are safely in the table by the time this runs, so
     * the worst case is a report nobody was emailed — which the screen still
     * shows, and which the next audit's mail will not compound.
     */
    public static function announce(BusinessDay $day): void
    {
        $figures = $day->figures ?? [];

        $body = sprintf(
            '%d of %d rooms sold (%s%% occupancy). Room revenue Rs. %s, ADR Rs. %s, collected Rs. %s. '
                . '%d room night%s posted, %d no show%s marked.',
            (int) data_get($figures, 'rooms.sold', 0),
            (int) data_get($figures, 'rooms.available', 0),
            data_get($figures, 'rooms.occupancy', 0),
            number_format((float) data_get($figures, 'revenue.room', 0), 2),
            number_format((float) data_get($figures, 'performance.adr', 0), 2),
            number_format((float) data_get($figures, 'collection.total', 0), 2),
            (int) $day->nights_posted, $day->nights_posted === 1 ? '' : 's',
            (int) $day->no_shows, $day->no_shows === 1 ? '' : 's',
        );

        rescue(fn () => Notify::fire('audit.closed', 'Night of ' . $day->business_date->format('d M Y') . ' closed', [
            'body' => $body,
            'branch_id' => (int) $day->branch_id,
            'url' => route('front-office.night-audit.show', $day),
            'data' => ['business_day_id' => $day->id, 'business_date' => $day->business_date->toDateString()],
        ]), null, false);

        Audit::note('audit_closed', 'The night of ' . $day->business_date->format('d M Y') . ' was closed — '
            . $body, [
                'area' => 'system',
                'branch_id' => (int) $day->branch_id,
                'subject_type' => BusinessDay::class,
                'subject_id' => $day->id,
                'subject_label' => $day->business_date->format('d M Y'),
            ]);
    }

    /**
     * Open the last closed night again.
     *
     * Only ever the last one: reopening Tuesday while Wednesday and Thursday
     * are closed on top of it would leave two days' figures resting on numbers
     * that are free to move. The charges the audit posted are deliberately
     * left alone — they are real money owed, and the re-run will find them
     * already posted and not charge them twice.
     *
     * @throws PostingRefused when that night is not the most recent close
     */
    public static function reopen(int $branchId, int $dayId, ?int $userId = null): BusinessDay
    {
        return DB::transaction(function () use ($branchId, $dayId, $userId) {
            $last = BusinessDay::lastClosed($branchId);

            if (! $last || $last->id !== $dayId) {
                throw new PostingRefused('Only the most recently audited night can be opened again.');
            }

            $who = $userId
                ? (\App\Models\User::where('user_id', $userId)->value('name') ?: 'user #' . $userId)
                : 'the system';

            $last->update([
                'status' => 'open',
                'closed_by' => null,
                'closed_at' => null,
                // Kept on the row rather than only in a log: the next person to
                // look at this night should be able to see it was reopened.
                'note' => trim(trim((string) $last->note) . ' | Reopened by ' . $who . ' on ' . now()->format('d/m/Y H:i'), ' |'),
            ]);

            Audit::note('audit_reopened', 'The night of '
                . $last->business_date->format('d M Y') . ' was opened again by ' . $who, [
                    'area' => 'system',
                    'branch_id' => $branchId,
                    'subject_type' => BusinessDay::class,
                    'subject_id' => $last->id,
                    'subject_label' => $last->business_date->format('d M Y'),
                ]);

            return $last->refresh();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | The manager's report
    |--------------------------------------------------------------------------
    */

    /**
     * Every figure for one night.
     *
     * Rooms sold is counted off the folio rather than off the check-ins, so it
     * can never disagree with the room revenue beside it: a room sold is a room
     * the hotel charged rent for that night. That also makes the average rate
     * an average of real charges rather than of what somebody was quoted.
     *
     * @return array<string, mixed>
     */
    public static function figures(int $branchId, string $date): array
    {
        $available = (int) Room::query()->forBranch($branchId)->active()->count();

        $room = DB::table('folio_charges as fc')
            ->join('check_ins as ci', 'ci.id', '=', 'fc.check_in_id')
            ->where('ci.branch_id', $branchId)
            ->where('fc.charge_type', 'room')
            ->whereDate('fc.charge_date', $date)
            ->selectRaw('COUNT(DISTINCT fc.check_in_id) as rooms, COALESCE(SUM(fc.amount), 0) as net, '
                . 'COALESCE(SUM(fc.tax_amount), 0) as tax, COALESCE(SUM(fc.total_amount), 0) as gross')
            ->first();

        $sold = (int) ($room->rooms ?? 0);
        $roomNet = round((float) ($room->net ?? 0), 2);

        $other = DB::table('folio_charges as fc')
            ->join('check_ins as ci', 'ci.id', '=', 'fc.check_in_id')
            ->where('ci.branch_id', $branchId)
            ->whereIn('fc.charge_type', ['service', 'misc'])
            ->whereDate('fc.charge_date', $date)
            ->selectRaw('COALESCE(SUM(fc.amount), 0) as net, COALESCE(SUM(fc.tax_amount), 0) as tax, '
                . 'COALESCE(SUM(fc.total_amount), 0) as gross')
            ->first();

        $pos = DB::table('pos_orders')
            ->where('branch_id', $branchId)
            ->whereIn('status', ['billed', 'settled'])
            ->whereRaw('DATE(COALESCE(closed_at, opened_at)) = ?', [$date])
            ->selectRaw('COUNT(*) as orders, COALESCE(SUM(net_amount), 0) as net, '
                . 'COALESCE(SUM(tax_total), 0) as tax')
            ->first();

        $settled = DB::table('settlements')
            ->where('branch_id', $branchId)
            ->whereDate('settle_date', $date)
            ->sum('amount');

        $deposits = DB::table('advance_deposits')
            ->where('branch_id', $branchId)
            ->whereDate('deposit_date', $date)
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'refund' THEN -amount ELSE amount END), 0) as net")
            ->value('net');

        $guests = (int) DB::table('check_ins')
            ->where('branch_id', $branchId)
            ->where('status', '!=', 'cancelled')
            ->whereDate('checkin_date', '<=', $date)
            ->whereRaw('COALESCE(actual_checkout_date, expected_checkout_date) > ?', [$date])
            ->selectRaw('COALESCE(SUM(male + female + child), 0) as pax')
            ->value('pax');

        $roomRevenue = $roomNet;
        $otherRevenue = round((float) ($other->net ?? 0), 2);
        $posRevenue = round((float) ($pos->net ?? 0), 2);

        return [
            'date' => $date,
            'taken_at' => now()->toDateTimeString(),

            'rooms' => [
                'available' => $available,
                'sold' => $sold,
                'free' => max(0, $available - $sold),
                'occupancy' => $available ? round($sold / $available * 100, 1) : 0.0,
                'guests' => $guests,
            ],

            'movement' => [
                'arrivals' => (int) DB::table('check_ins')
                    ->where('branch_id', $branchId)
                    ->where('status', '!=', 'cancelled')
                    ->whereDate('checkin_date', $date)
                    ->count(),
                'departures' => (int) DB::table('check_ins')
                    ->where('branch_id', $branchId)
                    ->where('status', 'checked_out')
                    ->whereDate('actual_checkout_date', $date)
                    ->count(),
                'in_house' => (int) DB::table('check_ins')
                    ->where('branch_id', $branchId)
                    ->where('status', '!=', 'cancelled')
                    ->whereDate('checkin_date', '<=', $date)
                    ->whereRaw('COALESCE(actual_checkout_date, expected_checkout_date) > ?', [$date])
                    ->count(),
                'no_shows' => (int) DB::table('reservations')
                    ->where('branch_id', $branchId)
                    ->where('status', 'no_show')
                    ->whereDate('cancelled_on', $date)
                    ->count(),
            ],

            'revenue' => [
                'room' => $roomRevenue,
                'room_tax' => round((float) ($room->tax ?? 0), 2),
                'other' => $otherRevenue,
                'other_tax' => round((float) ($other->tax ?? 0), 2),
                'pos' => $posRevenue,
                'pos_tax' => round((float) ($pos->tax ?? 0), 2),
                'pos_orders' => (int) ($pos->orders ?? 0),
                'total' => round($roomRevenue + $otherRevenue + $posRevenue, 2),
            ],

            'collection' => [
                'settlements' => round((float) $settled, 2),
                'deposits' => round((float) $deposits, 2),
                'total' => round((float) $settled + (float) $deposits, 2),
            ],

            /*
             * The three numbers a hotel is actually judged on. ADR is the
             * average a sold room fetched; RevPAR spreads the same revenue
             * over every room the hotel HAS, which is the one that tells an
             * owner whether the discounting was worth it.
             */
            'performance' => [
                'adr' => $sold ? round($roomRevenue / $sold, 2) : 0.0,
                'revpar' => $available ? round($roomRevenue / $available, 2) : 0.0,
                'arr_with_tax' => $sold ? round((float) ($room->gross ?? 0) / $sold, 2) : 0.0,
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | The pieces
    |--------------------------------------------------------------------------
    */

    /**
     * Every stay with somebody in the room on the given night.
     *
     * The check-in date alone is the test — Folio decides which nights are
     * owed and refuses the ones already charged, so a stay that departed this
     * morning simply has nothing left to post.
     *
     * @return Collection<int, CheckIn>
     */
    public static function staysOn(int $branchId, string $date): Collection
    {
        return CheckIn::query()
            ->where('branch_id', $branchId)
            ->where('status', 'in_house')
            ->whereDate('checkin_date', '<=', $date)
            ->with(['room', 'plan'])
            ->orderBy('id')
            ->get();
    }

    /**
     * Write off the bookings that never turned up.
     *
     * A booking is a no-show when its first room was due before tonight is
     * over and nobody on it ever arrived. Marking it releases the room it was
     * holding, which is the whole point: a fortnight of unmarked no-shows is a
     * fortnight of rooms the calendar thinks are sold.
     *
     * @return int how many bookings were marked
     */
    public static function markNoShows(int $branchId, string $date): int
    {
        $ids = DB::table('reservations as r')
            ->where('r.branch_id', $branchId)
            ->whereIn('r.status', ['confirmed', 'tentative'])
            ->whereExists(function ($q) use ($date) {
                $q->select(DB::raw(1))->from('reservation_rooms as rr')
                    ->whereColumn('rr.reservation_id', 'r.id')
                    ->where('rr.arrival_date', '<=', $date);
            })
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('check_ins as ci')
                    ->whereColumn('ci.reservation_id', 'r.id')
                    ->where('ci.status', '!=', 'cancelled');
            })
            ->pluck('r.id');

        if ($ids->isEmpty()) {
            return 0;
        }

        $reason = 'No show - marked by the night audit of ' . CarbonImmutable::parse($date)->format('d/m/Y');

        foreach ($ids->chunk(200) as $chunk) {
            $rows = $chunk->all();

            Reservation::whereIn('id', $rows)->update([
                'status' => 'no_show',
                'cancelled_on' => $date,
                'updated_at' => now(),
            ]);

            /*
             * A reason somebody typed is theirs, not the audit's. Only the
             * bookings that had none get the audit's wording, so a note like
             * "guest phoned, flight cancelled" survives the sweep.
             */
            Reservation::whereIn('id', $rows)
                ->where(fn ($q) => $q->whereNull('cancel_reason')->orWhere('cancel_reason', ''))
                ->update(['cancel_reason' => $reason]);
        }

        return $ids->count();
    }

    /** The day row, claimed for writing. Created on first sight. */
    private static function claim(int $branchId, string $date): BusinessDay
    {
        BusinessDay::firstOrCreate(
            ['branch_id' => $branchId, 'business_date' => $date],
            ['status' => 'open']
        );

        return BusinessDay::query()
            ->forBranch($branchId)
            ->whereDate('business_date', $date)
            ->lockForUpdate()
            ->firstOrFail();
    }

    public static function dayRow(int $branchId, string $date): ?BusinessDay
    {
        return BusinessDay::query()
            ->forBranch($branchId)
            ->whereDate('business_date', $date)
            ->first();
    }

    /** The nights already closed, newest first. */
    public static function history(int $branchId, int $limit = 30): Collection
    {
        return BusinessDay::query()
            ->forBranch($branchId)
            ->closed()
            ->with('closer')
            ->orderByDesc('business_date')
            ->limit($limit)
            ->get();
    }
}
