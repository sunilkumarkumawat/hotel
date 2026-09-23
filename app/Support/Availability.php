<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Room availability across a run of dates.
 *
 * Builds the whole grid in three queries — one for the room counts, one for
 * the bookings, one for the blocks — then fills the matrix in PHP. Asking the
 * database once per cell would be 10 categories × 15 days = 150 queries for a
 * single screen.
 *
 * A stay occupies the nights `arrival_date` up to but NOT including
 * `checkout_date`: a guest leaving on the 9th frees the room for the 9th. The
 * same half-open rule is used for room blocks, and it matches
 * Room::scopeAvailableBetween(), so the calendar and the booking form can
 * never disagree.
 */
class Availability
{
    /** Reservation statuses that hold a room. */
    public const HOLDS_ROOM = ['confirmed', 'checked_in'];

    /** Statuses that hold a room only provisionally. */
    public const TENTATIVE = ['tentative'];

    /** The guest is in the building on this night. */
    public const CURRENT = ['checked_in'];

    /** Booked but not arrived yet — an advance booking. */
    public const ADVANCE = ['confirmed', 'tentative'];

    public function __construct(
        private int $branchId,
        private CarbonImmutable $start,
        private int $days = 15,
    ) {}

    public static function for(int $branchId, string $start, int $days = 15): self
    {
        return new self($branchId, CarbonImmutable::parse($start)->startOfDay(), $days);
    }

    /** The dates across the top of the grid. */
    public function dates(): Collection
    {
        return collect(range(0, $this->days - 1))
            ->map(fn (int $i) => $this->start->addDays($i));
    }

    public function endsOn(): CarbonImmutable
    {
        return $this->start->addDays($this->days - 1);
    }

    /**
     * The whole grid.
     *
     * @return array{
     *     categories: Collection,
     *     rows: array<int|string, array<string, array{booked:int, tentative:int, blocked:int, available:int, current:int, advance:int}>>,
     *     totals: array<string, array{booked:int, tentative:int, blocked:int, available:int, current:int, advance:int}>,
     *     rooms: int
     * }
     */
    public function grid(): array
    {
        $categories = $this->categories();
        $keys = $this->dates()->map(fn ($d) => $d->toDateString());

        // Start every cell at zero so the view never has to guard for nulls.
        $blank = fn () => $keys
            ->mapWithKeys(fn (string $key) => [$key => [
                'booked' => 0, 'tentative' => 0, 'blocked' => 0, 'available' => 0,
                // The same rooms, cut a different way: `current` is the guest
                // already in the room, `advance` is the one still to arrive.
                // The Reservation Calendar shows these two; the Status View
                // shows booked / tentative. Both come off the one query.
                'current' => 0, 'advance' => 0,
            ]])
            ->all();

        $rows = $categories->mapWithKeys(fn ($c) => [$c->key => $blank()])->all();
        $totals = $blank();

        $this->applyBookings($rows, $keys);
        $this->applyBlocks($rows, $keys);

        // What is left over is what can still be sold.
        foreach ($categories as $category) {
            foreach ($keys as $key) {
                $cell = &$rows[$category->key][$key];

                $cell['available'] = max(
                    0,
                    $category->rooms - $cell['booked'] - $cell['tentative'] - $cell['blocked']
                );

                foreach (['booked', 'tentative', 'blocked', 'available', 'current', 'advance'] as $field) {
                    $totals[$key][$field] += $cell[$field];
                }

                unset($cell);
            }
        }

        return [
            'categories' => $categories,
            'rows' => $rows,
            'totals' => $totals,
            'rooms' => (int) $categories->sum('rooms'),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /** Room categories with how many rooms each one has. */
    private function categories(): Collection
    {
        return DB::table('rooms')
            ->leftJoin('room_category', 'room_category.id', '=', 'rooms.room_category_id')
            ->where('rooms.branch_id', $this->branchId)
            ->where('rooms.status', 1)
            ->groupBy('rooms.room_category_id', 'room_category.name', 'room_category.sort')
            ->orderBy('room_category.sort')
            ->orderBy('room_category.name')
            ->get([
                DB::raw('rooms.room_category_id as id'),
                DB::raw("COALESCE(room_category.name, 'Uncategorised') as name"),
                DB::raw('COUNT(rooms.id) as rooms'),
            ])
            ->map(function ($row) {
                $row->rooms = (int) $row->rooms;
                $row->key = $row->id === null ? 'none' : (string) $row->id;

                return $row;
            });
    }

    /** Spread each booking across the nights it covers. */
    private function applyBookings(array &$rows, Collection $keys): void
    {
        $bookings = DB::table('reservation_rooms as rr')
            ->join('reservations as r', 'r.id', '=', 'rr.reservation_id')
            ->where('r.branch_id', $this->branchId)
            ->whereIn('r.status', array_merge(self::HOLDS_ROOM, self::TENTATIVE))
            // "< the day after" rather than "<= the day", because a date column
            // can come back with a 00:00:00 time attached and the string
            // '2026-09-14 00:00:00' is NOT <= '2026-09-14'. This form is right
            // whether or not the time is there.
            ->where('rr.arrival_date', '<', $this->endsOn()->addDay()->toDateString())
            ->where('rr.checkout_date', '>', $this->start->toDateString())
            ->get([
                'rr.room_category_id',
                'rr.arrival_date',
                'rr.checkout_date',
                'rr.no_of_rooms',
                'r.status',
            ]);

        foreach ($bookings as $booking) {
            $field = in_array($booking->status, self::TENTATIVE, true) ? 'tentative' : 'booked';
            $side = in_array($booking->status, self::CURRENT, true) ? 'current' : 'advance';
            $key = $booking->room_category_id === null ? 'none' : (string) $booking->room_category_id;

            if (! isset($rows[$key])) {
                continue;   // a category with no active rooms left
            }

            foreach ($this->nightsIn($booking->arrival_date, $booking->checkout_date, $keys) as $date) {
                $rows[$key][$date][$field] += (int) $booking->no_of_rooms;
                $rows[$key][$date][$side] += (int) $booking->no_of_rooms;
            }
        }
    }

    /** Rooms taken out of service — maintenance, held for an owner, and so on. */
    private function applyBlocks(array &$rows, Collection $keys): void
    {
        $blocks = DB::table('room_blocks as b')
            ->join('rooms as ro', 'ro.id', '=', 'b.room_id')
            ->where('b.branch_id', $this->branchId)
            ->where('b.status', 'blocked')
            ->where('b.from_date', '<', $this->endsOn()->addDay()->toDateString())
            ->where('b.to_date', '>', $this->start->toDateString())
            ->get(['ro.room_category_id', 'b.from_date', 'b.to_date']);

        foreach ($blocks as $block) {
            $key = $block->room_category_id === null ? 'none' : (string) $block->room_category_id;

            if (! isset($rows[$key])) {
                continue;
            }

            foreach ($this->nightsIn($block->from_date, $block->to_date, $keys) as $date) {
                $rows[$key][$date]['blocked']++;
            }
        }
    }

    /**
     * The dates in [$from, $to) that are also on screen.
     *
     * @return list<string>
     */
    private function nightsIn(string $from, string $to, Collection $keys): array
    {
        $from = substr($from, 0, 10);
        $to = substr($to, 0, 10);

        return $keys->filter(fn (string $key) => $key >= $from && $key < $to)->values()->all();
    }
}
