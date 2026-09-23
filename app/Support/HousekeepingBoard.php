<?php

namespace App\Support;

use App\Models\Master\Room;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The house, seen as work rather than as rooms.
 *
 * The Housekeeping Status list answers "what is room 312?". This answers the
 * supervisor's actual question, which is "what is left to do, and who is on
 * it?" — and those turn out to need a different shape of data entirely.
 *
 * ──────────────────────────────────────────────────────────────────────────
 * THE FOUR STAGES
 * ──────────────────────────────────────────────────────────────────────────
 * A room's *housekeeping status* and its *stage on the board* are not the same
 * thing, and conflating them is what makes these boards wrong:
 *
 *   occupied   a guest is in it right now, and nobody has said it needs doing.
 *              Not work — yet.
 *   dirty      it needs doing. Either the guest left, or somebody marked it.
 *   cleaning   somebody is in there doing it. This is the stage the old list
 *              had no room for, which is why a supervisor could never tell a
 *              room nobody had started from one being made up.
 *   ready      clean, empty, sellable.
 *
 * A room that is out of order is on none of them: it is held by a block with a
 * reason and dates, and housekeeping cannot quietly put it back on sale.
 */
class HousekeepingBoard
{
    /** The columns, left to right, in the order the work flows. */
    public const STAGES = [
        'occupied' => 'Occupied',
        'dirty' => 'Dirty',
        'cleaning' => 'Cleaning',
        'ready' => 'Ready',
    ];

    /**
     * What a supervisor is allowed to drag where.
     *
     * Deliberately short. Dragging is for the two moves that are *observations*
     * — "that room is dirty now" — and everything else is a button, because
     * starting and finishing a clean are events with a time attached and a
     * drag is a poor way to record a time.
     *
     * `cleaning` is not a key: a room being cleaned cannot be dragged anywhere.
     * Finishing is the Done button, and abandoning is Mark dirty.
     */
    public const DRAGGABLE = [
        'occupied' => ['dirty'],
        'ready' => ['dirty'],
    ];

    /**
     * Every move the board can make, drag or button, and what it does.
     *
     * `status` is what gets written to the room. `needs_empty` refuses a move
     * that only makes sense for a room with nobody in it.
     */
    public const MOVES = [
        'dirty' => ['status' => 'dirty', 'label' => 'Marked dirty', 'needs_empty' => false],
        'cleaning' => ['status' => 'cleaning', 'label' => 'Being cleaned', 'needs_empty' => false],
        'ready' => ['status' => 'clean', 'label' => 'Ready to sell', 'needs_empty' => false],
        'inspected' => ['status' => 'inspected', 'label' => 'Inspected', 'needs_empty' => false],
        'touch_up' => ['status' => 'touch_up', 'label' => 'Needs a touch up', 'needs_empty' => false],
    ];

    /**
     * The whole board for a branch on a date.
     *
     * One pass over the rooms and three cheap lookups — the same three the
     * Room Calendar uses, so the two screens can never disagree about whether
     * 203 has somebody in it.
     *
     * @return array<string, mixed>
     */
    public static function build(int $branchId, ?string $date = null, array $filters = []): array
    {
        $date ??= today()->toDateString();

        $rooms = Room::query()
            ->forBranch($branchId)
            ->active()
            ->with(['type', 'category', 'housekeeper'])
            ->when($filters['type'] ?? null, fn ($q, $id) => $q->where('room_type_id', $id))
            ->when($filters['floor'] ?? null, fn ($q, $f) => $q->where('floor', $f))
            ->when($filters['housekeeper'] ?? null, fn ($q, $id) => $q->where('housekeeper_id', $id))
            ->when($filters['q'] ?? null, fn ($q, $t) => $q->where('room_no', 'like', "%{$t}%"))
            ->orderBy('room_no')
            ->get();

        $occupancy = self::occupancy($rooms, $date, $branchId);

        $tiles = $rooms->map(fn (Room $room) => self::tile($room, $occupancy[$room->id] ?? []));

        return [
            'date' => $date,
            'tiles' => $tiles,
            // Room View is grouped by type, which is how a supervisor allots:
            // "do the four suites first, they check in at two".
            'byType' => $tiles->groupBy(fn (array $tile) => $tile['type'] ?: 'Unassigned type'),
            'byStage' => self::stageBuckets($tiles),
            'counts' => self::counts($tiles),
            'stages' => self::STAGES,
        ];
    }

    /**
     * One room, as the board sees it.
     *
     * @return array<string, mixed>
     */
    public static function tile(Room $room, array $state): array
    {
        $stage = self::stageOf($room, $state);

        return [
            'id' => (int) $room->id,
            'room_no' => (string) $room->room_no,
            'floor' => (string) ($room->floor ?? ''),
            'type' => (string) ($room->type?->name ?? ''),
            'category' => (string) ($room->category?->name ?? ''),
            'status' => (string) $room->housekeeping_status,
            'status_label' => Room::HOUSEKEEPING[$room->housekeeping_status] ?? ucfirst((string) $room->housekeeping_status),
            'stage' => $stage,
            'stage_label' => self::STAGES[$stage] ?? ucfirst($stage),
            'guest' => $state['guest'] ?? null,
            'pax' => (int) ($state['pax'] ?? 0),
            'occupied' => (bool) ($state['occupied'] ?? false),
            'departing' => (bool) ($state['departing'] ?? false),
            'arriving' => (bool) ($state['arriving'] ?? false),
            'housekeeper' => $room->housekeeper?->name,
            'remark' => $room->housekeeping_remark,
            'since' => $room->cleaning_started_at
                ? CarbonImmutable::parse((string) $room->cleaning_started_at)->diffForHumans(short: true)
                : null,
            // Only a stage in DRAGGABLE can be picked up at all, and then only
            // onto the stages it names. The browser reads this; the server
            // checks it again on the way in.
            'draggable' => array_key_exists($stage, self::DRAGGABLE),
            'drop_targets' => self::DRAGGABLE[$stage] ?? [],
            'blocked' => $stage === 'blocked',
        ];
    }

    /**
     * Which stage a room is on.
     *
     * Order matters. Out of order beats everything — a room held by a work
     * order is not "ready" no matter how clean it is. Then the two states a
     * housekeeper set by hand, because those are somebody's deliberate
     * statement about the room and outrank what the booking system thinks.
     * Only then does occupancy decide.
     */
    public static function stageOf(Room $room, array $state): string
    {
        if ($room->housekeeping_status === 'out_of_order' || ($state['blocked'] ?? false)) {
            return 'blocked';
        }

        if ($room->housekeeping_status === 'cleaning') {
            return 'cleaning';
        }

        if (in_array($room->housekeeping_status, ['dirty', 'touch_up'], true)) {
            return 'dirty';
        }

        return ($state['occupied'] ?? false) ? 'occupied' : 'ready';
    }

    /**
     * Tiles in their columns, with the out-of-order ones kept aside.
     *
     * Blocked rooms get their own bucket rather than being dropped: a
     * supervisor who cannot see that 204 is out of order will keep asking why
     * nobody has cleaned it.
     *
     * @return Collection<string, Collection>
     */
    private static function stageBuckets(Collection $tiles): Collection
    {
        $buckets = collect(array_keys(self::STAGES))
            ->mapWithKeys(fn (string $stage) => [$stage => collect()]);

        $buckets['blocked'] = collect();

        foreach ($tiles as $tile) {
            $key = $buckets->has($tile['stage']) ? $tile['stage'] : 'blocked';

            $buckets[$key] = $buckets[$key]->push($tile);
        }

        return $buckets;
    }

    /** @return array<string, int> */
    private static function counts(Collection $tiles): array
    {
        return [
            'rooms' => $tiles->count(),
            'occupied' => $tiles->where('stage', 'occupied')->count(),
            'dirty' => $tiles->where('stage', 'dirty')->count(),
            'cleaning' => $tiles->where('stage', 'cleaning')->count(),
            'ready' => $tiles->where('stage', 'ready')->count(),
            'blocked' => $tiles->where('stage', 'blocked')->count(),
            'departing' => $tiles->where('departing', true)->count(),
            'arriving' => $tiles->where('arriving', true)->count(),
            'unassigned' => $tiles->whereNull('housekeeper')->where('stage', 'dirty')->count(),
        ];
    }

    /**
     * Who is in each room, who is leaving it, and who is arriving into it.
     *
     * The departing and arriving flags are what turn a list of dirty rooms into
     * a priority order: a room somebody checks into at two o'clock is the one
     * to do first, and nothing else on the screen can tell you that.
     *
     * Written as `< $next` / `>= $next` rather than `<= $date` because a date
     * column can come back with 00:00:00 attached.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function occupancy(Collection $rooms, string $date, int $branchId): array
    {
        $next = CarbonImmutable::parse($date)->addDay()->toDateString();

        $stays = DB::table('check_ins')
            ->where('branch_id', $branchId)
            ->where('status', 'in_house')
            ->whereNotNull('room_id')
            ->where('checkin_date', '<', $next)
            ->whereRaw('COALESCE(actual_checkout_date, expected_checkout_date) >= ?', [$date])
            ->get(['room_id', 'guest_name', 'male', 'female', 'child', 'expected_checkout_date', 'actual_checkout_date'])
            ->keyBy('room_id');

        $arrivals = DB::table('reservation_rooms as rr')
            ->join('reservations as r', 'r.id', '=', 'rr.reservation_id')
            ->where('r.branch_id', $branchId)
            ->whereNotNull('rr.room_id')
            ->whereIn('r.status', ['confirmed', 'tentative'])
            ->whereDate('rr.arrival_date', $date)
            ->pluck('rr.room_id')
            ->flip();

        $blocked = DB::table('room_blocks')
            ->where('branch_id', $branchId)
            ->where('status', 'blocked')
            ->where('from_date', '<', $next)
            ->where('to_date', '>=', $next)
            ->pluck('room_id')
            ->flip();

        $out = [];

        foreach ($rooms as $room) {
            $stay = $stays->get($room->id);

            $leaves = $stay
                ? substr((string) ($stay->actual_checkout_date ?: $stay->expected_checkout_date), 0, 10)
                : null;

            $out[$room->id] = [
                'occupied' => (bool) $stay,
                'guest' => $stay->guest_name ?? null,
                'pax' => $stay ? (int) $stay->male + (int) $stay->female + (int) $stay->child : 0,
                'departing' => $leaves === $date,
                'arriving' => $arrivals->has($room->id),
                'blocked' => $blocked->has($room->id),
            ];
        }

        return $out;
    }
}
