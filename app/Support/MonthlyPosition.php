<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reservation Calendar Monthly — the position of the house, day by day.
 *
 * One column per date, one row per number the front office actually reads:
 * how many rooms exist, who is arriving, who is staying on, who is leaving,
 * what is blocked, and what is therefore still sellable.
 *
 * Built in five queries however long the run of dates is — rooms, bookings,
 * check-ins, blocks, waiting list — then the matrix is filled in PHP.
 *
 * The same half-open night rule as everywhere else: a stay covers
 * arrival_date .. checkout_date - 1, so a guest leaving on the 9th frees the
 * room for the 9th. That is why "Expected Checkout" is counted on the date
 * the stay ends while "Occupancy" is not.
 */
class MonthlyPosition
{
    /** The rows down the left, in the order the old system printed them. */
    public const ROWS = [
        'available' => 'Available Rooms',
        'expected_checkin' => 'Expected Checkin',
        'stay_on' => 'Stay On (InHouse)',
        'occupancy' => 'Occupancy',
        'expected_checkout' => 'Expected Checkout',
        'management_block' => 'Management Block',
        'maintenance_block' => 'Maintanance Block',
        'position' => 'Position',
        'wait_list' => 'Wait List',
    ];

    /** Rows that are a count of rooms and so can simply be added up. */
    private const COUNTED = [
        'expected_checkin', 'stay_on', 'occupancy', 'expected_checkout',
        'management_block', 'maintenance_block', 'wait_list',
    ];

    public function __construct(
        private int $branchId,
        private CarbonImmutable $start,
        private int $days = 30,
    ) {}

    public static function for(int $branchId, string $start, int $days = 30): self
    {
        return new self($branchId, CarbonImmutable::parse($start)->startOfDay(), $days);
    }

    public function dates(): Collection
    {
        return collect(range(0, $this->days - 1))->map(fn (int $i) => $this->start->addDays($i));
    }

    public function endsOn(): CarbonImmutable
    {
        return $this->start->addDays($this->days - 1);
    }

    /**
     * @return array{
     *     keys: Collection,
     *     rows: array<string, array<string, int|float>>,
     *     totals: array<string, int|float>,
     *     occupancy_percent: array<string, float>,
     *     types: Collection,
     *     typewise: array<string, array<string, int>>,
     *     rooms: int
     * }
     */
    public function build(): array
    {
        $keys = $this->dates()->map(fn ($d) => $d->toDateString());
        $rooms = $this->totalRooms();
        $types = $this->roomTypes();

        $blank = fn () => $keys->mapWithKeys(fn (string $k) => [$k => 0])->all();

        $rows = collect(array_keys(self::ROWS))
            ->mapWithKeys(fn (string $row) => [$row => $blank()])
            ->all();

        // Every room exists on every date — a room is not sold, it is let.
        foreach ($keys as $key) {
            $rows['available'][$key] = $rooms;
        }

        $typewise = $types->mapWithKeys(fn ($t) => [$t->key => $blank()])->all();

        // Start each type at its full count and take the taken ones off.
        foreach ($types as $type) {
            foreach ($keys as $key) {
                $typewise[$type->key][$key] = $type->rooms;
            }
        }

        $this->applyBookings($rows, $typewise, $keys);
        $this->applyCheckIns($rows, $typewise, $keys);
        $this->applyBlocks($rows, $typewise, $keys);
        $this->applyWaitList($rows, $keys);

        foreach ($keys as $key) {
            // Rooms with somebody in them tonight: those arriving today plus
            // those who were already here. Departures are not counted — they
            // are out by 11am and the room can be sold again.
            $rows['occupancy'][$key] = $rows['expected_checkin'][$key] + $rows['stay_on'][$key];

            // Deliberately NOT clamped at zero. A negative here means the
            // house is oversold for that night, which is the single most
            // useful thing this report can tell anybody — clamping it to 0
            // would hide it behind a number that looks fine.
            $rows['position'][$key] = $rows['available'][$key]
                - $rows['occupancy'][$key]
                - $rows['management_block'][$key]
                - $rows['maintenance_block'][$key];
        }

        return [
            'keys' => $keys,
            'rows' => $rows,
            'totals' => $this->totals($rows, $keys),
            'occupancy_percent' => $this->occupancyPercent($rows, $keys),
            'types' => $types,
            'typewise' => $typewise,
            'rooms' => $rooms,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    private function totalRooms(): int
    {
        return (int) DB::table('rooms')
            ->where('branch_id', $this->branchId)
            ->where('status', 1)
            ->count();
    }

    private function roomTypes(): Collection
    {
        return DB::table('rooms')
            ->leftJoin('room_type as rt', 'rt.id', '=', 'rooms.room_type_id')
            ->where('rooms.branch_id', $this->branchId)
            ->where('rooms.status', 1)
            ->groupBy('rooms.room_type_id', 'rt.name')
            ->orderBy('rt.name')
            ->get([
                DB::raw('rooms.room_type_id as id'),
                DB::raw("COALESCE(rt.name, 'Uncategorised') as name"),
                DB::raw('COUNT(rooms.id) as rooms'),
            ])
            ->map(function ($row) {
                $row->rooms = (int) $row->rooms;
                $row->key = $row->id === null ? 'none' : (string) $row->id;

                return $row;
            });
    }

    /**
     * Bookings nobody has arrived for yet.
     *
     * A booking whose guest has checked in is counted from `check_ins`
     * instead, so a room is never counted twice.
     */
    private function applyBookings(array &$rows, array &$typewise, Collection $keys): void
    {
        $bookings = DB::table('reservation_rooms as rr')
            ->join('reservations as r', 'r.id', '=', 'rr.reservation_id')
            ->where('r.branch_id', $this->branchId)
            ->whereIn('r.status', ['confirmed', 'tentative'])
            ->where('rr.arrival_date', '<', $this->endsOn()->addDay()->toDateString())
            ->where('rr.checkout_date', '>', $this->start->toDateString())
            ->get(['rr.arrival_date', 'rr.checkout_date', 'rr.no_of_rooms', 'rr.room_type_id', 'rr.id']);

        // Rooms of a booking line whose guests have already arrived.
        $arrived = DB::table('check_ins')
            ->where('branch_id', $this->branchId)
            ->whereNot('status', 'cancelled')
            ->whereNotNull('reservation_room_id')
            ->groupBy('reservation_room_id')
            ->pluck(DB::raw('COUNT(*) as n'), 'reservation_room_id');

        foreach ($bookings as $booking) {
            $count = max(0, (int) $booking->no_of_rooms - (int) ($arrived[$booking->id] ?? 0));

            if ($count === 0) {
                continue;
            }

            $from = substr($booking->arrival_date, 0, 10);
            $to = substr($booking->checkout_date, 0, 10);
            $typeKey = $booking->room_type_id === null ? 'none' : (string) $booking->room_type_id;

            foreach ($this->nightsIn($from, $to, $keys) as $date) {
                // The first night of the stay is the arrival; the rest is
                // this guest staying on.
                $rows[$date === $from ? 'expected_checkin' : 'stay_on'][$date] += $count;

                if (isset($typewise[$typeKey])) {
                    $typewise[$typeKey][$date] -= $count;
                }
            }

            if ($keys->contains($to)) {
                $rows['expected_checkout'][$to] += $count;
            }
        }
    }

    /** Guests who have actually arrived. One row per room. */
    private function applyCheckIns(array &$rows, array &$typewise, Collection $keys): void
    {
        $stays = DB::table('check_ins as ci')
            ->leftJoin('rooms as ro', 'ro.id', '=', 'ci.room_id')
            ->where('ci.branch_id', $this->branchId)
            ->whereNot('ci.status', 'cancelled')
            ->where('ci.checkin_date', '<', $this->endsOn()->addDay()->toDateString())
            ->whereRaw('COALESCE(ci.actual_checkout_date, ci.expected_checkout_date) > ?', [$this->start->toDateString()])
            ->get([
                'ci.checkin_date',
                DB::raw('COALESCE(ci.actual_checkout_date, ci.expected_checkout_date) as leaves_on'),
                'ro.room_type_id',
            ]);

        foreach ($stays as $stay) {
            $from = substr($stay->checkin_date, 0, 10);
            $to = substr($stay->leaves_on, 0, 10);
            $typeKey = $stay->room_type_id === null ? 'none' : (string) $stay->room_type_id;

            foreach ($this->nightsIn($from, $to, $keys) as $date) {
                $rows[$date === $from ? 'expected_checkin' : 'stay_on'][$date]++;

                if (isset($typewise[$typeKey])) {
                    $typewise[$typeKey][$date]--;
                }
            }

            if ($keys->contains($to)) {
                $rows['expected_checkout'][$to]++;
            }
        }
    }

    /** Rooms out of the pool, split the way the old report split them. */
    private function applyBlocks(array &$rows, array &$typewise, Collection $keys): void
    {
        $blocks = DB::table('room_blocks as b')
            ->join('rooms as ro', 'ro.id', '=', 'b.room_id')
            ->where('b.branch_id', $this->branchId)
            ->where('b.status', 'blocked')
            ->where('b.from_date', '<', $this->endsOn()->addDay()->toDateString())
            ->where('b.to_date', '>', $this->start->toDateString())
            ->get(['b.from_date', 'b.to_date', 'b.block_type', 'ro.room_type_id']);

        foreach ($blocks as $block) {
            $row = $block->block_type === 'maintenance' ? 'maintenance_block' : 'management_block';
            $typeKey = $block->room_type_id === null ? 'none' : (string) $block->room_type_id;

            foreach ($this->nightsIn(substr($block->from_date, 0, 10), substr($block->to_date, 0, 10), $keys) as $date) {
                $rows[$row][$date]++;

                if (isset($typewise[$typeKey])) {
                    $typewise[$typeKey][$date]--;
                }
            }
        }
    }

    /**
     * Waiting-list bookings.
     *
     * These hold no room — they are the queue for one — so they are counted
     * on their own line and never come off Position.
     */
    private function applyWaitList(array &$rows, Collection $keys): void
    {
        $waiting = DB::table('reservation_rooms as rr')
            ->join('reservations as r', 'r.id', '=', 'rr.reservation_id')
            ->where('r.branch_id', $this->branchId)
            ->where('r.reservation_type', 'waiting')
            ->whereNotIn('r.status', ['cancelled', 'no_show'])
            ->where('rr.arrival_date', '<', $this->endsOn()->addDay()->toDateString())
            ->where('rr.checkout_date', '>', $this->start->toDateString())
            ->get(['rr.arrival_date', 'rr.checkout_date', 'rr.no_of_rooms']);

        foreach ($waiting as $row) {
            foreach ($this->nightsIn(substr($row->arrival_date, 0, 10), substr($row->checkout_date, 0, 10), $keys) as $date) {
                $rows['wait_list'][$date] += (int) $row->no_of_rooms;
            }
        }
    }

    /** @return array<string, int> */
    private function totals(array $rows, Collection $keys): array
    {
        $out = [];

        foreach (array_keys(self::ROWS) as $row) {
            // Available and Position are room-nights across the whole run,
            // which is what the old report totalled too.
            $out[$row] = (int) collect($keys)->sum(fn (string $key) => $rows[$row][$key]);
        }

        $out['counted'] = array_sum(array_map(fn ($r) => $out[$r], self::COUNTED));

        return $out;
    }

    /**
     * Occupancy as a percentage of the rooms that existed that day.
     *
     * The total column is the whole run's occupancy over the whole run's
     * room-nights, not an average of percentages — those are not the same
     * number once a day has no rooms at all.
     *
     * @return array<string, float>
     */
    private function occupancyPercent(array $rows, Collection $keys): array
    {
        $out = [];

        foreach ($keys as $key) {
            $out[$key] = $rows['available'][$key] > 0
                ? round($rows['occupancy'][$key] / $rows['available'][$key] * 100, 1)
                : 0.0;
        }

        $available = collect($keys)->sum(fn (string $k) => $rows['available'][$k]);
        $occupied = collect($keys)->sum(fn (string $k) => $rows['occupancy'][$k]);

        $out['total'] = $available > 0 ? round($occupied / $available * 100, 1) : 0.0;

        return $out;
    }

    /**
     * The dates in [$from, $to) that are also on screen.
     *
     * @return list<string>
     */
    private function nightsIn(string $from, string $to, Collection $keys): array
    {
        return $keys->filter(fn (string $key) => $key >= $from && $key < $to)->values()->all();
    }
}
