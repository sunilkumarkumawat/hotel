<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What every room in the house is doing on a given day.
 *
 * The Room Calendar, the dashboard and the month calendar all used to be
 * entitled to their own opinion about whether room 203 was "occupied". They are
 * not any more: they all call this, so a colour means the same thing on every
 * screen, and a bug fixed here is fixed everywhere.
 *
 * A room's state is worked out from three facts at once — the guest in it, the
 * booking holding it, and the housekeeping flag — because a room can be both
 * reserved and dirty, and the desk needs to see both.
 *
 * **Every date test is `< tomorrow` or `>= tomorrow`, never `<= today`.** A
 * date column can come back carrying a time, and the string
 * `'2026-09-09 00:00:00'` is not `<= '2026-09-09'` — which would quietly empty
 * the whole board on the day a guest arrives.
 */
class RoomBoard
{
    /** Tile states, in the order a legend prints them. */
    public const STATES = [
        'clean' => 'Cleaned',
        'checkin' => 'Checkin',
        'reservation' => 'Reservation',
        'dirty' => 'Dirty',
        'checkout' => 'Checkout',
        'repair' => 'Repair',
        'inspect' => 'Inspect',
        'blocked' => 'Blocked',
        'reserved_dirty' => 'Reserved + Dirty',
        'checkin_dirty' => 'Checkin + Dirty',
    ];

    /** The states that mean "a guest is sleeping here tonight". */
    public const OCCUPIED = ['checkin', 'checkin_dirty'];

    /** The states that mean "this room cannot be sold today". */
    public const UNSELLABLE = ['checkin', 'checkin_dirty', 'blocked', 'repair'];

    /**
     * @param  Collection<int, \App\Models\Master\Room>  $rooms
     * @return array<int, array{state: string, check_in: ?object, booking: ?object}>
     */
    public static function states(Collection $rooms, string $date, int $branchId): array
    {
        $next = CarbonImmutable::parse($date)->addDay()->toDateString();

        $stays = DB::table('check_ins')
            ->where('branch_id', $branchId)
            ->where('status', 'in_house')
            ->whereNotNull('room_id')
            ->where('checkin_date', '<', $next)
            ->whereRaw('COALESCE(actual_checkout_date, expected_checkout_date) >= ?', [$next])
            ->get(['id', 'room_id', 'guest_name', 'mobile', 'folio_no', 'expected_checkout_date'])
            ->keyBy('room_id');

        $bookings = DB::table('reservation_rooms as rr')
            ->join('reservations as r', 'r.id', '=', 'rr.reservation_id')
            ->where('r.branch_id', $branchId)
            ->whereNotNull('rr.room_id')
            ->whereIn('r.status', ['confirmed', 'tentative'])
            ->where('rr.arrival_date', '<', $next)
            ->where('rr.checkout_date', '>=', $next)
            ->get([
                'rr.room_id', 'rr.arrival_date', 'rr.checkout_date',
                'r.id as reservation_id', 'r.reservation_no', 'r.title',
                'r.first_name', 'r.last_name', 'r.mobile',
            ])
            ->keyBy('room_id');

        $blocked = DB::table('room_blocks')
            ->where('branch_id', $branchId)
            ->where('status', 'blocked')
            ->where('from_date', '<', $next)
            ->where('to_date', '>=', $next)
            ->pluck('room_id')
            ->flip();

        // A stay that ends today is a departure, not an occupancy.
        $leaving = DB::table('check_ins')
            ->where('branch_id', $branchId)
            ->where('status', 'in_house')
            ->whereNotNull('room_id')
            ->whereRaw('COALESCE(actual_checkout_date, expected_checkout_date) >= ?', [$date])
            ->whereRaw('COALESCE(actual_checkout_date, expected_checkout_date) < ?', [$next])
            ->pluck('room_id')
            ->flip();

        $out = [];

        foreach ($rooms as $room) {
            $house = $room->housekeeping_status;
            $stay = $stays->get($room->id);
            $booking = $bookings->get($room->id);
            $dirty = $house === 'dirty';

            $state = match (true) {
                $blocked->has($room->id) => 'blocked',
                $house === 'out_of_order' => 'repair',
                (bool) $stay => $dirty ? 'checkin_dirty' : 'checkin',
                $leaving->has($room->id) => 'checkout',
                (bool) $booking => $dirty ? 'reserved_dirty' : 'reservation',
                $house === 'inspected' => 'inspect',
                $dirty => 'dirty',
                default => 'clean',
            };

            $out[$room->id] = ['state' => $state, 'check_in' => $stay, 'booking' => $booking];
        }

        return $out;
    }

    /**
     * How many rooms are counted under each state.
     *
     * @param  Collection<int, \App\Models\Master\Room>  $rooms
     * @param  array<int, array{state: string}>  $state
     * @return array<string, int>
     */
    public static function legend(Collection $rooms, array $state): array
    {
        $counts = array_fill_keys(array_keys(self::STATES), 0);

        foreach ($rooms as $room) {
            $counts[$state[$room->id]['state']]++;
        }

        return $counts;
    }

    /**
     * A month of nights, each with what was sold and what was moving.
     *
     * Written as three counting queries rather than thirty state passes: a
     * month calendar only needs numbers per day, and asking the board thirty
     * times would be thirty times the work for an answer nobody reads that
     * closely.
     *
     * @return array<string, array{sold: int, arrivals: int, departures: int, blocked: int}>
     *         keyed by Y-m-d, one entry for every day of the month
     */
    public static function month(int $branchId, string $anyDayInMonth, int $totalRooms): array
    {
        $first = CarbonImmutable::parse($anyDayInMonth)->startOfMonth();
        $last = $first->endOfMonth();
        $afterLast = $last->addDay()->toDateString();

        $days = [];

        for ($day = $first; $day <= $last; $day = $day->addDay()) {
            $days[$day->toDateString()] = ['sold' => 0, 'arrivals' => 0, 'departures' => 0, 'blocked' => 0];
        }

        /*
         * A stay covers its nights half-open: arrive the 4th, leave the 6th, and
         * the 4th and 5th are sold — not the 6th. Every loop below walks that
         * range rather than trusting a BETWEEN, because the two ends mean
         * different things.
         */
        $stays = DB::table('check_ins')
            ->where('branch_id', $branchId)
            ->whereIn('status', ['in_house', 'checked_out'])
            ->whereNotNull('room_id')
            ->where('checkin_date', '<', $afterLast)
            ->whereRaw('COALESCE(actual_checkout_date, expected_checkout_date) >= ?', [$first->toDateString()])
            ->get(['checkin_date', 'actual_checkout_date', 'expected_checkout_date']);

        foreach ($stays as $stay) {
            $from = CarbonImmutable::parse($stay->checkin_date);
            $to = CarbonImmutable::parse($stay->actual_checkout_date ?: $stay->expected_checkout_date);

            self::mark($days, $from, $to);
        }

        $bookings = DB::table('reservation_rooms as rr')
            ->join('reservations as r', 'r.id', '=', 'rr.reservation_id')
            ->where('r.branch_id', $branchId)
            ->whereIn('r.status', ['confirmed', 'tentative'])
            ->where('rr.arrival_date', '<', $afterLast)
            ->where('rr.checkout_date', '>=', $first->toDateString())
            // A booking that has already been checked in is counted once, as a
            // stay — otherwise the night would be sold twice.
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('check_ins as ci')
                    ->whereColumn('ci.reservation_room_id', 'rr.id')
                    ->whereIn('ci.status', ['in_house', 'checked_out']);
            })
            ->get(['rr.arrival_date', 'rr.checkout_date']);

        foreach ($bookings as $booking) {
            self::mark($days, CarbonImmutable::parse($booking->arrival_date), CarbonImmutable::parse($booking->checkout_date));
        }

        $blocks = DB::table('room_blocks')
            ->where('branch_id', $branchId)
            ->where('status', 'blocked')
            ->where('from_date', '<', $afterLast)
            ->where('to_date', '>=', $first->toDateString())
            ->get(['from_date', 'to_date']);

        foreach ($blocks as $block) {
            $from = CarbonImmutable::parse($block->from_date);
            $to = CarbonImmutable::parse($block->to_date);

            for ($day = $from; $day < $to; $day = $day->addDay()) {
                $key = $day->toDateString();

                if (isset($days[$key])) {
                    $days[$key]['blocked']++;
                }
            }
        }

        // Nothing can be more than full, however the numbers were counted.
        foreach ($days as $key => $row) {
            $days[$key]['sold'] = min($row['sold'], max($totalRooms, 1));
        }

        return $days;
    }

    /**
     * Add one stay to the month: a night for each date it covers, plus an
     * arrival on the day it starts and a departure on the day it ends.
     *
     * @param  array<string, array{sold: int, arrivals: int, departures: int, blocked: int}>  $days
     */
    private static function mark(array &$days, CarbonImmutable $from, CarbonImmutable $to): void
    {
        for ($day = $from; $day < $to; $day = $day->addDay()) {
            $key = $day->toDateString();

            if (isset($days[$key])) {
                $days[$key]['sold']++;
            }
        }

        if (isset($days[$from->toDateString()])) {
            $days[$from->toDateString()]['arrivals']++;
        }

        if (isset($days[$to->toDateString()])) {
            $days[$to->toDateString()]['departures']++;
        }

        // A zero-night stay — in and out the same day — still arrived and still
        // left, and the loop above gave it no night at all. That is correct.
    }
}
