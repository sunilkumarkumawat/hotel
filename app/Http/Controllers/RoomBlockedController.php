<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Models\HouseKeeping\RoomBlock;
use App\Models\Master\Room;
use App\Models\Master\RoomCategory;
use Carbon\CarbonImmutable;
use App\Support\Notify;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Room Blocked — taking rooms off sale, and putting them back.
 *
 * Pick a date and search; tick the rooms; press Block. **Bulk Block** does the
 * same thing for a run of room numbers without ticking anything, which is what
 * a floor going under renovation actually looks like.
 *
 * A block is the third thing that can hold a room, alongside a booking and a
 * check-in, and every availability test in the app already reads `room_blocks`
 * (`Room::scopeAvailableBetween`, `Availability`, `RoomTimeline`,
 * `MonthlyPosition`). So blocking here takes the room off the tape chart, out
 * of the status view and out of the room dropdown on the booking form, all on
 * its own — there is nothing else to switch off.
 *
 * A **maintenance** block also marks the room Repair in house keeping, because
 * a room with its carpet up must not be made up and put back on the board.
 * A **management** block — held for the owner, kept back for a group — leaves
 * the status alone: the room is fine, it is simply not for sale.
 */
class RoomBlockedController extends Controller
{
    /** GET house-keeping/room-blocked */
    public function index(Request $request): View
    {
        $branchId = Helper::getActiveBranchId();

        $date = rescue(
            fn () => CarbonImmutable::parse($request->string('date')->toString() ?: 'today')->toDateString(),
            today()->toDateString(),
            false
        );

        $filters = [
            'date' => $date,
            'category' => $request->integer('category'),
            'room' => $request->integer('room'),
            'floor' => $request->string('floor')->toString(),
            'status' => $request->string('status')->toString(),
        ];

        $rooms = Room::query()
            ->forBranch()
            ->active()
            ->with(['category', 'type'])
            ->when($filters['category'], fn ($q, $id) => $q->where('room_category_id', $id))
            ->when($filters['room'], fn ($q, $id) => $q->where('id', $id))
            ->when($filters['floor'], fn ($q, $f) => $q->where('floor', $f))
            ->orderBy('room_no')
            ->get();

        $blocks = $this->blocksOn($branchId, $date, $rooms->pluck('id'));
        $busy = $this->busyOn($branchId, $date, $rooms->pluck('id'));

        // "Blocked only" / "Free only" is the filter a supervisor works from,
        // and it has to be applied after the blocks are known.
        if ($filters['status'] === 'blocked') {
            $rooms = $rooms->filter(fn (Room $r) => $blocks->has($r->id))->values();
        } elseif ($filters['status'] === 'open') {
            $rooms = $rooms->filter(fn (Room $r) => ! $blocks->has($r->id))->values();
        }

        return view('house-keeping.room-blocked', [
            'rooms' => $rooms,
            'blocks' => $blocks,
            'busy' => $busy,
            'filters' => $filters,
            'date' => $date,
            'tomorrow' => CarbonImmutable::parse($date)->addDay()->toDateString(),
            'types' => RoomBlock::TYPES,
            // Blocking and releasing are separate permissions; the tick boxes
            // are worth showing to anybody who has one of them.
            'mayPick' => can_do('house-keeping/room-blocked', 'add')
                || can_do('house-keeping/room-blocked', 'delete'),
            'statuses' => Room::HOUSEKEEPING,
            'categories' => RoomCategory::query()->forBranch()->active()->orderBy('name')->pluck('name', 'id'),
            'floors' => Room::query()->forBranch()->active()
                ->whereNotNull('floor')->distinct()->orderBy('floor')->pluck('floor'),
            'allRooms' => Room::query()->forBranch()->active()->orderBy('room_no')->get(['id', 'room_no']),
            'counts' => $this->counts($rooms, $blocks),
        ]);
    }

    /**
     * POST house-keeping/room-blocked — block the ticked rooms.
     */
    public function store(Request $request): RedirectResponse
    {
        $branchId = Helper::getActiveBranchId();

        $data = $request->validate([
            'rooms' => 'required|array|min:1',
            'rooms.*' => 'integer',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after:from_date',
            'block_type' => ['required', Rule::in(array_keys(RoomBlock::TYPES))],
            'reason' => 'nullable|string|max:255',
        ], [
            'rooms.required' => 'Tick the rooms you want to block first.',
            'to_date.after' => 'A block has to cover at least one night — the To date is the day the room comes back.',
        ]);

        $rooms = Room::query()->forBranch()->whereIn('id', $data['rooms'])->orderBy('room_no')->get();

        if ($rooms->isEmpty()) {
            return back()->with('error', 'None of those rooms are in this branch.');
        }

        return $this->blockThese($request, $rooms, $data, $branchId);
    }

    /**
     * POST house-keeping/room-blocked/bulk — block a run of room numbers.
     *
     * "201 to 210, the whole of next week." Nothing is ticked; the range names
     * the rooms.
     */
    public function bulk(Request $request): RedirectResponse
    {
        $branchId = Helper::getActiveBranchId();

        $data = $request->validate([
            'from_room' => 'required|string|max:30',
            'to_room' => 'required|string|max:30',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after:from_date',
            'block_type' => ['required', Rule::in(array_keys(RoomBlock::TYPES))],
            'reason' => 'nullable|string|max:255',
        ], [
            'to_date.after' => 'A block has to cover at least one night.',
        ]);

        $rooms = $this->roomsInRange($branchId, $data['from_room'], $data['to_room']);

        if ($rooms->isEmpty()) {
            return back()->withInput()->with('error', sprintf(
                'No rooms between %s and %s — check the two numbers against the room list.',
                $data['from_room'],
                $data['to_room']
            ));
        }

        return $this->blockThese($request, $rooms, $data, $branchId, sprintf(
            '%s → %s',
            $data['from_room'],
            $data['to_room']
        ));
    }

    /**
     * POST house-keeping/room-blocked/release — put rooms back on sale.
     */
    public function release(Request $request): RedirectResponse
    {
        $branchId = Helper::getActiveBranchId();

        $data = $request->validate([
            'blocks' => 'required|array|min:1',
            'blocks.*' => 'integer',
        ], [
            'blocks.required' => 'Tick a blocked room first.',
        ]);

        $blocks = RoomBlock::query()
            ->where('branch_id', $branchId)
            ->live()
            ->whereIn('id', $data['blocks'])
            ->with('room')
            ->get();

        if ($blocks->isEmpty()) {
            return back()->with('error', 'Those blocks have already been released.');
        }

        DB::transaction(function () use ($blocks, $branchId) {
            RoomBlock::whereIn('id', $blocks->pluck('id'))
                ->update(['status' => 'released', 'updated_at' => now()]);

            /*
             * A maintenance block put the room into Repair, so releasing it has
             * to take it back out — otherwise House Keeping Status would go on
             * refusing to make the room up, and it would sit there clean,
             * sellable and marked broken. Dirty rather than Clean, because
             * nobody has been in to look at it yet.
             */
            $repaired = $blocks
                ->filter(fn (RoomBlock $b) => $b->isMaintenance())
                ->pluck('room_id')
                ->filter()
                ->unique();

            if ($repaired->isEmpty()) {
                return;
            }

            /*
             * Unless another maintenance block is still holding the room. A
             * room booked out for two separate weeks of work is still out of
             * service after the first week is released, and marking it dirty
             * would let a supervisor make it up and put it back on the board.
             */
            $stillHeld = RoomBlock::query()
                ->where('branch_id', $branchId)
                ->live()
                ->whereIn('room_id', $repaired)
                ->whereIn('block_type', RoomBlock::MARKS_ROOM_REPAIR)
                ->whereNotIn('id', $blocks->pluck('id'))
                ->pluck('room_id')
                ->unique();

            $repaired = $repaired->diff($stillHeld);

            if ($repaired->isNotEmpty()) {
                Room::whereIn('id', $repaired)
                    ->where('housekeeping_status', 'out_of_order')
                    ->update(['housekeeping_status' => 'dirty', 'updated_at' => now()]);
            }
        });

        $numbers = $blocks->pluck('room.room_no')->filter()->unique()->implode(', ');

        Notify::event('room.released')
            ->title($blocks->count() . ' room(s) back on sale')
            ->body($numbers ?: '')
            ->url(route('house-keeping.room-blocked'))
            ->send();

        return back()->with('status', sprintf(
            '%d room(s) released — %s can be sold again.',
            $blocks->count(),
            $numbers ?: 'they'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * Write the blocks, skipping anything that is not actually free.
     *
     * A room with a guest in it, or one somebody has booked, is not blocked
     * quietly — it is named in the message, because a supervisor who thinks
     * the whole floor is off sale and finds a guest walking into 204 has been
     * told a lie by the screen.
     */
    private function blockThese(Request $request, Collection $rooms, array $data, int $branchId, ?string $range = null): RedirectResponse
    {
        $from = CarbonImmutable::parse($data['from_date'])->toDateString();
        $to = CarbonImmutable::parse($data['to_date'])->toDateString();

        $done = [];
        $doneIds = [];
        $skipped = [];

        DB::transaction(function () use ($rooms, $from, $to, $data, $branchId, $request, &$done, &$doneIds, &$skipped) {
            foreach ($rooms as $room) {
                if ($why = $this->clashOn($branchId, $room->id, $from, $to)) {
                    $skipped[] = $room->room_no . ' (' . $why . ')';

                    continue;
                }

                RoomBlock::create([
                    'branch_id' => $branchId,
                    'room_id' => $room->id,
                    'from_date' => $from,
                    'to_date' => $to,
                    // A room list can hold rows shared by every branch, so the
                    // remark falls back to the block's own kind rather than
                    // relying on the form having sent one.
                    'reason' => ($data['reason'] ?? null) ?: RoomBlock::TYPES[$data['block_type']],
                    'block_type' => $data['block_type'],
                    'status' => 'blocked',
                    'created_by' => $request->user()->user_id,
                ]);

                $done[] = $room->room_no;
                $doneIds[] = $room->id;
            }

            // Maintenance takes the room out of service; management does not.
            // Keyed on id, never on room_no: a room shared across branches has
            // a null branch_id and is exempt from the number's unique index, so
            // matching by number could mark the wrong property's room 101.
            //
            // Only when the block has actually started: a block dated to begin
            // next week must not take a perfectly sellable room off the board
            // today. RoomBoard and HousekeepingBoard already read room_blocks
            // by date for "is it blocked today", so this column only needs to
            // catch up once the block's own start date arrives.
            if ($doneIds !== [] && in_array($data['block_type'], RoomBlock::MARKS_ROOM_REPAIR, true) && $from <= today()->toDateString()) {
                Room::whereIn('id', $doneIds)
                    ->update(['housekeeping_status' => 'out_of_order', 'updated_at' => now()]);
            }
        });

        if ($done === []) {
            return back()->withInput()->with('error', sprintf(
                'Nothing was blocked — %s',
                $skipped === [] ? 'no rooms matched.' : 'every room is busy: ' . implode(', ', $skipped)
            ));
        }

        $nights = CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse($to));

        Notify::event('room.blocked')
            ->title(count($done) . ' room(s) taken off sale for ' . $nights . ' night(s)')
            ->body(implode(', ', $done))
            ->url(route('house-keeping.room-blocked'))
            ->send();

        return back()->with('status', sprintf(
            '%d room(s) blocked for %d night(s) — %s%s%s',
            count($done),
            $nights,
            implode(', ', $done),
            $range ? " (from {$range})" : '',
            $skipped === [] ? '.' : '. Left alone because they are busy: ' . implode(', ', $skipped) . '.'
        ));
    }

    /**
     * Why this room is not free over those nights, or null.
     *
     * The same three holds every other screen tests, in the same half-open
     * shape: a booking, a guest already in the room, and an existing block.
     */
    private function clashOn(int $branchId, int $roomId, string $from, string $to): ?string
    {
        $booking = DB::table('reservation_rooms as rr')
            ->join('reservations as r', 'r.id', '=', 'rr.reservation_id')
            ->where('r.branch_id', $branchId)
            ->where('rr.room_id', $roomId)
            ->whereNotIn('r.status', ['cancelled', 'no_show', 'checked_out'])
            ->where('rr.arrival_date', '<', $to)
            ->where('rr.checkout_date', '>', $from)
            ->value('r.reservation_no');

        if ($booking) {
            return "booked on {$booking}";
        }

        $guest = DB::table('check_ins')
            ->where('branch_id', $branchId)
            ->where('room_id', $roomId)
            ->where('status', 'in_house')
            ->where('checkin_date', '<', $to)
            ->whereRaw('COALESCE(actual_checkout_date, expected_checkout_date) > ?', [$from])
            ->value('guest_name');

        if ($guest) {
            return "{$guest} is in it";
        }

        $blocked = DB::table('room_blocks')
            ->where('branch_id', $branchId)
            ->where('room_id', $roomId)
            ->where('status', 'blocked')
            ->where('from_date', '<', $to)
            ->where('to_date', '>', $from)
            ->exists();

        return $blocked ? 'already blocked' : null;
    }

    /**
     * The live block on each room for one day, keyed by room id.
     *
     * @return Collection<int, RoomBlock>
     */
    private function blocksOn(int $branchId, string $date, $roomIds): Collection
    {
        return RoomBlock::query()
            ->where('branch_id', $branchId)
            ->live()
            ->covering($date)
            ->whereIn('room_id', $roomIds)
            ->orderBy('id')
            ->get()
            ->keyBy('room_id');
    }

    /**
     * Who is in each room that day, so the screen can say why a room cannot
     * be blocked before the clerk finds out by pressing the button.
     *
     * @return Collection<int, string>
     */
    private function busyOn(int $branchId, string $date, $roomIds): Collection
    {
        $next = CarbonImmutable::parse($date)->addDay()->toDateString();

        $stays = DB::table('check_ins')
            ->where('branch_id', $branchId)
            ->where('status', 'in_house')
            ->whereIn('room_id', $roomIds)
            ->where('checkin_date', '<', $next)
            ->whereRaw('COALESCE(actual_checkout_date, expected_checkout_date) >= ?', [$next])
            ->pluck('guest_name', 'room_id');

        $bookings = DB::table('reservation_rooms as rr')
            ->join('reservations as r', 'r.id', '=', 'rr.reservation_id')
            ->where('r.branch_id', $branchId)
            ->whereIn('rr.room_id', $roomIds)
            ->whereIn('r.status', ['confirmed', 'tentative', 'checked_in'])
            ->where('rr.arrival_date', '<', $next)
            ->where('rr.checkout_date', '>=', $next)
            ->pluck('r.reservation_no', 'rr.room_id');

        return collect($roomIds)
            ->mapWithKeys(fn ($id) => [$id => $stays[$id] ?? ($bookings[$id] ?? null)])
            ->filter();
    }

    /**
     * Rooms whose number falls between two others.
     *
     * Compared as numbers when both ends are numbers, because a hotel with
     * rooms 102 and 1016 sorts them the wrong way round as text — '1016'
     * comes before '102' alphabetically. Anything else falls back to a plain
     * text range, which is what a cottage called C1 needs.
     */
    private function roomsInRange(int $branchId, string $from, string $to): Collection
    {
        $rooms = Room::query()->forBranch($branchId)->active()->orderBy('room_no')->get();

        $from = trim($from);
        $to = trim($to);

        if (ctype_digit($from) && ctype_digit($to)) {
            [$low, $high] = [min((int) $from, (int) $to), max((int) $from, (int) $to)];

            return $rooms->filter(function (Room $room) use ($low, $high) {
                $no = trim((string) $room->room_no);

                return ctype_digit($no) && (int) $no >= $low && (int) $no <= $high;
            })->values();
        }

        // Ordered with the same comparator the filter uses. min()/max() compare
        // byte by byte, so 'C2' sorts before 'c1' and the range comes out
        // backwards for a mixed-case pair.
        if (strcasecmp($from, $to) > 0) {
            [$from, $to] = [$to, $from];
        }

        return $rooms->filter(function (Room $room) use ($from, $to) {
            $no = trim((string) $room->room_no);

            return strcasecmp($no, $from) >= 0 && strcasecmp($no, $to) <= 0;
        })->values();
    }

    /**
     * The four tiles.
     *
     * Counted from the rooms actually on screen, not from the whole branch —
     * "Rooms listed 4" next to "Blocked 17" reads like a bug even when both
     * numbers are true.
     *
     * @return array<string, int>
     */
    private function counts($rooms, Collection $blocks): array
    {
        $shown = $blocks->only($rooms->pluck('id')->all());

        return [
            'rooms' => $rooms->count(),
            'blocked' => $shown->count(),
            'maintenance' => $shown->where('block_type', 'maintenance')->count(),
            'management' => $shown->where('block_type', 'management')->count(),
        ];
    }
}
