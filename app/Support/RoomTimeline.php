<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The tape chart: one row per physical room, one column per night.
 *
 * Everything a booking or a block covers becomes a "bar" — a start column and
 * a span — which the view renders as a single table cell with a colspan. That
 * keeps the whole chart inside one ordinary table, so the sticky room column
 * and the horizontal scroll behave without any absolute positioning.
 *
 * Same half-open night rule as everywhere else: a stay covers
 * arrival_date .. checkout_date - 1, so a guest leaving on the 9th frees the
 * room for the 9th.
 */
class RoomTimeline
{
    /** How a bar is drawn. Keys double as CSS modifiers and legend keys. */
    public const KINDS = [
        'checked_in' => 'Checked in',
        'confirmed' => 'Reservation',
        'tentative' => 'Tentative',
        'blocked' => 'Blocked',
    ];

    public function __construct(
        private int $branchId,
        private CarbonImmutable $start,
        private int $days = 15,
    ) {}

    public static function for(int $branchId, string $start, int $days = 15): self
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
     *     categories: Collection,
     *     rows: array<int, list<array<string, mixed>>>,
     *     conflicts: list<string>,
     *     unassigned: Collection,
     *     footer: array<string, array{occupied:int, free:int, occupancy:float}>,
     *     legend: array<string, int>,
     *     rooms: int
     * }
     */
    public function build(): array
    {
        $rooms = $this->rooms();
        $keys = $this->dates()->map(fn ($d) => $d->toDateString());
        $index = $keys->flip();                      // date => column number

        $bars = $this->bars();
        $rows = [];
        $conflicts = [];

        foreach ($rooms as $room) {
            [$rows[$room->id], $clash] = $this->layOut($bars->get($room->id, collect()), $keys, $index);

            foreach ($clash as $message) {
                $conflicts[] = "Room {$room->room_no}: {$message}";
            }
        }

        return [
            'categories' => $rooms->groupBy(fn ($r) => $r->category_name ?? 'Uncategorised'),
            'rows' => $rows,
            'conflicts' => $conflicts,
            'unassigned' => $this->unassigned(),
            'footer' => $this->footer($rooms, $rows, $keys),
            'legend' => $this->legend($rooms, $rows),
            'rooms' => $rooms->count(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    private function rooms(): Collection
    {
        return DB::table('rooms')
            ->leftJoin('room_category as rc', 'rc.id', '=', 'rooms.room_category_id')
            ->leftJoin('room_type as rt', 'rt.id', '=', 'rooms.room_type_id')
            ->where('rooms.branch_id', $this->branchId)
            ->where('rooms.status', 1)
            ->orderBy('rc.sort')
            ->orderBy('rc.name')
            ->orderBy('rooms.room_no')
            ->get([
                'rooms.id', 'rooms.room_no', 'rooms.floor', 'rooms.housekeeping_status',
                'rooms.room_category_id', 'rc.name as category_name', 'rt.name as type_name',
            ]);
    }

    /** Bookings and blocks that touch the window, grouped by room. */
    private function bars(): Collection
    {
        $from = $this->start->toDateString();
        $to = $this->endsOn()->addDay()->toDateString();

        $bookings = DB::table('reservation_rooms as rr')
            ->join('reservations as r', 'r.id', '=', 'rr.reservation_id')
            ->where('r.branch_id', $this->branchId)
            ->whereNotNull('rr.room_id')
            ->whereIn('r.status', ['confirmed', 'tentative', 'checked_in'])
            ->where('rr.arrival_date', '<', $to)
            ->where('rr.checkout_date', '>', $from)
            ->orderBy('rr.arrival_date')
            ->get([
                'rr.id as line_id', 'rr.room_id', 'rr.arrival_date', 'rr.checkout_date',
                'r.id as reservation_id', 'r.reservation_no', 'r.status',
                'r.title', 'r.first_name', 'r.last_name', 'r.mobile',
            ])
            ->map(fn ($b) => (object) [
                'room_id' => $b->room_id,
                'kind' => $b->status,
                'from' => substr($b->arrival_date, 0, 10),
                'to' => substr($b->checkout_date, 0, 10),
                'label' => trim(($b->title ? $b->title . ' ' : '') . $b->first_name . ' ' . $b->last_name),
                'reservation_id' => $b->reservation_id,
                'reservation_no' => $b->reservation_no,
                'mobile' => $b->mobile,
                'block_id' => null,
                'line_id' => $b->line_id,
                // A guest already in the room cannot have their stay dragged
                // somewhere else; that is a room transfer, not a date change.
                'movable' => $b->status !== 'checked_in',
            ]);

        $blocks = DB::table('room_blocks as b')
            ->join('rooms as ro', 'ro.id', '=', 'b.room_id')
            ->where('b.branch_id', $this->branchId)
            ->where('b.status', 'blocked')
            ->where('b.from_date', '<', $to)
            ->where('b.to_date', '>', $from)
            ->orderBy('b.from_date')
            ->get(['b.id', 'b.room_id', 'b.from_date', 'b.to_date', 'b.reason'])
            ->map(fn ($b) => (object) [
                'room_id' => $b->room_id,
                'kind' => 'blocked',
                'from' => substr($b->from_date, 0, 10),
                'to' => substr($b->to_date, 0, 10),
                'label' => $b->reason ?: 'Blocked',
                'reservation_id' => null,
                'reservation_no' => null,
                'mobile' => null,
                'block_id' => $b->id,
                'line_id' => null,
                'movable' => false,
            ]);

        return $bookings->concat($blocks)
            ->sortBy('from')
            ->groupBy('room_id');
    }

    /**
     * Turn one room's bars into an ordered run of cells.
     *
     * Walks the columns left to right emitting either a one-column empty cell
     * or a bar with a colspan, so the row always adds up to exactly `days`
     * columns however the bars fall.
     *
     * @return array{0: list<array<string, mixed>>, 1: list<string>}
     */
    private function layOut(Collection $bars, Collection $keys, Collection $index): array
    {
        $cells = [];
        $conflicts = [];
        $cursor = 0;
        $total = $keys->count();

        foreach ($bars as $bar) {
            // Clip to what is on screen.
            $startKey = max($bar->from, $keys->first());
            $endKey = min($bar->to, $keys->last());          // exclusive end, clipped

            $startCol = $index[$startKey] ?? null;
            $endCol = $index[$endKey] ?? $total;             // runs past the right edge

            if ($startCol === null) {
                continue;
            }

            // If the stay ends after the window, it fills to the edge.
            if ($bar->to > $keys->last()) {
                $endCol = $total;
            }

            $span = max(1, $endCol - $startCol);

            if ($startCol < $cursor) {
                $conflicts[] = "{$bar->label} overlaps another booking from {$bar->from}";

                continue;
            }

            for ($i = $cursor; $i < $startCol; $i++) {
                $cells[] = ['type' => 'free', 'date' => $keys[$i]];
            }

            $cells[] = [
                'type' => 'bar',
                'kind' => $bar->kind,
                'span' => min($span, $total - $startCol),
                'label' => $bar->label,
                'from' => $bar->from,
                'to' => $bar->to,
                'nights' => Money::nights($bar->from, $bar->to),
                'reservation_id' => $bar->reservation_id,
                'reservation_no' => $bar->reservation_no,
                'mobile' => $bar->mobile,
                'block_id' => $bar->block_id,
                'line_id' => $bar->line_id,
                // A stay running off either edge is only partly on screen, so
                // dragging it would move it by a distance the clerk cannot see.
                'movable' => $bar->movable
                    && $bar->from >= $keys->first()
                    && $bar->to <= $keys->last(),
                'continues_left' => $bar->from < $keys->first(),
                'continues_right' => $bar->to > $keys->last(),
            ];

            $cursor = $startCol + min($span, $total - $startCol);
        }

        for ($i = $cursor; $i < $total; $i++) {
            $cells[] = ['type' => 'free', 'date' => $keys[$i]];
        }

        return [$cells, $conflicts];
    }

    /** Bookings with no room allotted yet — the "Assign Room" queue. */
    private function unassigned(): Collection
    {
        return DB::table('reservation_rooms as rr')
            ->join('reservations as r', 'r.id', '=', 'rr.reservation_id')
            ->leftJoin('room_type as rt', 'rt.id', '=', 'rr.room_type_id')
            ->leftJoin('room_category as rc', 'rc.id', '=', 'rr.room_category_id')
            ->where('r.branch_id', $this->branchId)
            ->whereNull('rr.room_id')
            ->whereIn('r.status', ['confirmed', 'tentative', 'checked_in'])
            ->where('rr.arrival_date', '<', $this->endsOn()->addDay()->toDateString())
            ->where('rr.checkout_date', '>', $this->start->toDateString())
            ->orderBy('rr.arrival_date')
            ->get([
                'rr.id', 'rr.arrival_date', 'rr.checkout_date', 'rr.no_of_rooms',
                'rr.room_type_id', 'rr.room_category_id',
                'rt.name as room_type', 'rc.name as category',
                'r.id as reservation_id', 'r.reservation_no', 'r.status',
                'r.title', 'r.first_name', 'r.last_name', 'r.mobile',
            ]);
    }

    /**
     * Room Availability and Occupancy rows.
     *
     * Counted off the laid-out cells, so what the footer says always matches
     * the bars actually drawn above it.
     *
     * @return array<string, array{occupied:int, free:int, occupancy:float}>
     */
    private function footer(Collection $rooms, array $rows, Collection $keys): array
    {
        $occupied = $keys->mapWithKeys(fn ($k) => [$k => 0])->all();

        foreach ($rows as $cells) {
            $col = 0;

            foreach ($cells as $cell) {
                if ($cell['type'] === 'free') {
                    $col++;

                    continue;
                }

                for ($i = 0; $i < $cell['span']; $i++) {
                    $occupied[$keys[$col + $i]]++;
                }

                $col += $cell['span'];
            }
        }

        $total = max(1, $rooms->count());

        return collect($occupied)
            ->map(fn (int $n) => [
                'occupied' => $n,
                'free' => $rooms->count() - $n,
                'occupancy' => round(($n / $total) * 100, 1),
            ])
            ->all();
    }

    /**
     * The counts along the top: housekeeping states plus what is on the chart.
     *
     * @return array<string, int>
     */
    private function legend(Collection $rooms, array $rows): array
    {
        $counts = [
            'clean' => 0, 'dirty' => 0, 'inspected' => 0, 'out_of_order' => 0,
            'checked_in' => 0, 'confirmed' => 0, 'tentative' => 0, 'blocked' => 0,
        ];

        foreach ($rooms as $room) {
            $counts[$room->housekeeping_status] = ($counts[$room->housekeeping_status] ?? 0) + 1;
        }

        foreach ($rows as $cells) {
            foreach ($cells as $cell) {
                if ($cell['type'] === 'bar') {
                    $counts[$cell['kind']]++;
                }
            }
        }

        return $counts;
    }
}
