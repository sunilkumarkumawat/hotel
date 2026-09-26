<?php

namespace App\Support;

use App\Models\Master\Room;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Every number on the front-desk dashboard, worked out once.
 *
 * The screen answers four questions, in the order a duty manager asks them:
 * what is the house doing right now, what is moving today, what is each room
 * doing, and what does the month ahead look like.
 *
 * Rooms are read once and shared. Asking "how many are occupied" and "which
 * ones are occupied" as two separate queries is how a dashboard ends up
 * disagreeing with itself, so both come off the same board.
 */
class HotelDashboard
{
    private Collection $rooms;

    /** @var array<int, array{state: string, check_in: ?object, booking: ?object}> */
    private array $board;

    private string $date;

    public function __construct(private int $branchId, ?string $date = null)
    {
        $this->date = CarbonImmutable::parse($date ?: 'today')->toDateString();

        $this->rooms = Room::query()
            ->forBranch($this->branchId)
            ->active()
            ->with(['category', 'type'])
            ->orderBy('room_no')
            ->get();

        $this->board = RoomBoard::states($this->rooms, $this->date, $this->branchId);
    }

    public function date(): string
    {
        return $this->date;
    }

    public function rooms(): Collection
    {
        return $this->rooms;
    }

    /** @return array<int, array{state: string, check_in: ?object, booking: ?object}> */
    public function board(): array
    {
        return $this->board;
    }

    /*
    |--------------------------------------------------------------------------
    | The tiles
    |--------------------------------------------------------------------------
    */

    /** @return array<string, int> */
    public function headline(): array
    {
        $next = CarbonImmutable::parse($this->date)->addDay()->toDateString();
        $states = collect($this->board)->pluck('state');

        $occupied = $states->filter(fn ($s) => in_array($s, RoomBoard::OCCUPIED, true))->count();
        $unsellable = $states->filter(fn ($s) => in_array($s, RoomBoard::UNSELLABLE, true))->count();

        return [
            'rooms' => $this->rooms->count(),
            'occupied' => $occupied,
            // "Vacant" is what the desk may still sell tonight — not simply
            // "not occupied", which would count a room under repair as sellable.
            'vacant' => max(0, $this->rooms->count() - $unsellable),
            'blocked' => $states->filter(fn ($s) => in_array($s, ['blocked', 'repair'], true))->count(),

            'expected_arrival' => DB::table('reservation_rooms as rr')
                ->join('reservations as r', 'r.id', '=', 'rr.reservation_id')
                ->where('r.branch_id', $this->branchId)
                ->whereIn('r.status', ['confirmed', 'tentative'])
                ->where('rr.arrival_date', '>=', $this->date)
                ->where('rr.arrival_date', '<', $next)
                ->count(),

            'expected_departure' => DB::table('check_ins')
                ->where('branch_id', $this->branchId)
                ->where('status', 'in_house')
                ->whereRaw('COALESCE(actual_checkout_date, expected_checkout_date) >= ?', [$this->date])
                ->whereRaw('COALESCE(actual_checkout_date, expected_checkout_date) < ?', [$next])
                ->count(),

            'checked_in' => DB::table('check_ins')
                ->where('branch_id', $this->branchId)
                ->whereIn('status', ['in_house', 'checked_out'])
                ->where('checkin_date', '>=', $this->date)
                ->where('checkin_date', '<', $next)
                ->count(),

            'checked_out' => DB::table('check_ins')
                ->where('branch_id', $this->branchId)
                ->where('status', 'checked_out')
                ->where('actual_checkout_date', '>=', $this->date)
                ->where('actual_checkout_date', '<', $next)
                ->count(),
        ];
    }

    /**
     * The in-house picture: heads on pillows, not rooms.
     *
     * @return array<string, int>
     */
    public function overview(): array
    {
        $inHouse = DB::table('check_ins')
            ->where('branch_id', $this->branchId)
            ->where('status', 'in_house')
            ->selectRaw('COUNT(*) as stays, COALESCE(SUM(male + female + child), 0) as guests, '
                . 'COUNT(DISTINCT reservation_id) as groups')
            ->first();

        return [
            'guests' => (int) ($inHouse->guests ?? 0),
            'stays' => (int) ($inHouse->stays ?? 0),
            // A group is one reservation holding more than one room.
            'groups' => (int) DB::table('check_ins')
                ->where('branch_id', $this->branchId)
                ->where('status', 'in_house')
                ->whereNotNull('reservation_id')
                ->groupBy('reservation_id')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('reservation_id')
                ->count(),
            'pending' => $this->unsettled(),
        ];
    }

    /**
     * In-house folios that still owe something.
     *
     * Charged minus settled, per stay. Anything still positive is money the
     * hotel has not collected — the one number on this screen a manager will
     * act on today.
     */
    private function unsettled(): int
    {
        $charged = DB::table('folio_charges')
            ->whereIn('check_in_id', function ($q) {
                $q->select('id')->from('check_ins')
                    ->where('branch_id', $this->branchId)
                    ->where('status', 'in_house');
            })
            ->groupBy('check_in_id')
            ->selectRaw('check_in_id, COALESCE(SUM(amount), 0) as total')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->check_in_id => (float) $row->total]);

        $settled = DB::table('settlements')
            ->whereNotNull('check_in_id')
            ->whereIn('check_in_id', $charged->keys()->all() ?: [0])
            ->groupBy('check_in_id')
            ->selectRaw('check_in_id, COALESCE(SUM(amount), 0) as total')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->check_in_id => (float) $row->total]);

        // A rupee of float error is not a debt; anything under one is settled.
        return $charged->filter(fn (float $due, int $id) => $due - ($settled[$id] ?? 0) >= 1)->count();
    }

    /**
     * Rooms grouped the way the desk thinks about them.
     *
     * Categories in name order, and inside each the rooms in room-number order
     * with the state they are in. A category with nothing in it is dropped —
     * an empty accordion row is a question nobody asked.
     *
     * @return Collection<int, array{id: ?int, name: string, rooms: Collection, counts: array<string, int>, occupancy: float}>
     */
    public function categories(): Collection
    {
        return $this->rooms
            ->groupBy(fn (Room $room) => $room->category?->name ?: 'Uncategorised')
            ->map(function (Collection $rooms, string $name) {
                $states = $rooms->map(fn (Room $r) => $this->board[$r->id]['state']);
                $occupied = $states->filter(fn ($s) => in_array($s, RoomBoard::OCCUPIED, true))->count();
                $unsellable = $states->filter(fn ($s) => in_array($s, RoomBoard::UNSELLABLE, true))->count();

                return [
                    'id' => $rooms->first()->room_category_id,
                    'name' => $name,
                    'rooms' => $rooms->values(),
                    'counts' => [
                        'total' => $rooms->count(),
                        'occupied' => $occupied,
                        'vacant' => max(0, $rooms->count() - $unsellable),
                        'blocked' => $states->filter(fn ($s) => in_array($s, ['blocked', 'repair'], true))->count(),
                    ],
                    'occupancy' => $rooms->count() ? round($occupied / $rooms->count() * 100) : 0.0,
                ];
            })
            ->sortKeys()
            ->values();
    }

    /**
     * Rooms free to sell on each of the next fortnight's nights.
     *
     * @return list<array{date: string, label: string, day: string, free: int}>
     */
    public function availability(int $nights = 14): array
    {
        $total = $this->rooms->count();
        $start = CarbonImmutable::parse($this->date);
        $out = [];

        $month = RoomBoard::month($this->branchId, $this->date, $total);
        $nextMonth = RoomBoard::month($this->branchId, $start->addMonth()->toDateString(), $total);
        $all = $month + $nextMonth;

        for ($i = 0; $i < $nights; $i++) {
            $day = $start->addDays($i);
            $key = $day->toDateString();
            $row = $all[$key] ?? ['sold' => 0, 'blocked' => 0];

            $out[] = [
                'date' => $key,
                'label' => $day->format('d M'),
                'day' => $day->format('D'),
                'free' => max(0, $total - $row['sold'] - $row['blocked']),
            ];
        }

        return $out;
    }

    /** @return array<string, array{sold: int, arrivals: int, departures: int, blocked: int}> */
    public function month(?string $anyDay = null): array
    {
        return RoomBoard::month($this->branchId, $anyDay ?: $this->date, $this->rooms->count());
    }

    /**
     * Who is due in today, and who is due out.
     *
     * @return array{arrivals: Collection, departures: Collection}
     */
    public function movements(): array
    {
        $next = CarbonImmutable::parse($this->date)->addDay()->toDateString();

        $arrivals = DB::table('reservation_rooms as rr')
            ->join('reservations as r', 'r.id', '=', 'rr.reservation_id')
            ->leftJoin('rooms as ro', 'ro.id', '=', 'rr.room_id')
            ->where('r.branch_id', $this->branchId)
            ->whereIn('r.status', ['confirmed', 'tentative'])
            ->where('rr.arrival_date', '>=', $this->date)
            ->where('rr.arrival_date', '<', $next)
            ->orderBy('r.reservation_no')
            ->limit(12)
            ->get([
                'rr.id as rr_id', 'r.id as reservation_id', 'r.reservation_no', 'r.title',
                'r.first_name', 'r.last_name', 'r.mobile',
                'ro.room_no', 'rr.arrival_date', 'rr.checkout_date',
            ]);

        // A multi-room booking row never gets rr.room_id filled in — see the
        // comment in CheckInController::store() — so a booking still on this
        // widget because some of its rooms haven't arrived yet reads as
        // unallotted even when the guests who did arrive already have a
        // real room each. Same bulk-lookup shape Reports::reportArrivals()
        // uses for the identical case.
        $checkedInRooms = DB::table('check_ins as ci')
            ->join('rooms as cr', 'cr.id', '=', 'ci.room_id')
            ->whereIn('ci.reservation_room_id', $arrivals->pluck('rr_id'))
            ->orderBy('cr.room_no')
            ->get(['ci.reservation_room_id', 'cr.room_no'])
            ->groupBy('reservation_room_id')
            ->map(fn ($g) => $g->pluck('room_no')->unique()->implode(', '));

        $arrivals = $arrivals->map(function ($row) use ($checkedInRooms) {
            $row->room_no = ($checkedInRooms[$row->rr_id] ?? $row->room_no) ?: null;

            return $row;
        });

        return [
            'arrivals' => $arrivals,

            'departures' => collect(DB::table('check_ins as ci')
                ->leftJoin('rooms as ro', 'ro.id', '=', 'ci.room_id')
                ->where('ci.branch_id', $this->branchId)
                ->where('ci.status', 'in_house')
                ->whereRaw('COALESCE(ci.actual_checkout_date, ci.expected_checkout_date) >= ?', [$this->date])
                ->whereRaw('COALESCE(ci.actual_checkout_date, ci.expected_checkout_date) < ?', [$next])
                ->orderBy('ro.room_no')
                ->limit(12)
                ->get([
                    'ci.id', 'ci.folio_no', 'ci.guest_name', 'ci.mobile',
                    'ro.room_no', 'ci.checkin_date', 'ci.expected_checkout_date',
                ])),
        ];
    }
}
