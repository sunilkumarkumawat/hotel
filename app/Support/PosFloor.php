<?php

namespace App\Support;

use App\Models\Master\Room;
use App\Models\Pos\Outlet;
use App\Models\Pos\PosOrder;
use App\Models\Pos\PosTableGroup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What the floor is doing right now.
 *
 * The table view asks one question — "which of my tables are busy, how long
 * have they been busy, and how much is on them" — and it must answer it in a
 * fixed number of queries however many tables there are. So the orders are read
 * once and keyed, not looked up per tile.
 *
 * The same class answers it for rooms, because room service is the same
 * question with a different kind of seat: a room is occupied by a guest rather
 * than by a party, and what is running on it is an order all the same.
 */
class PosFloor
{
    /** The states a tile can be in, in the order a legend prints them. */
    public const STATES = [
        'free' => 'Free',
        'running' => 'Running',
        'due' => 'Payment Due',
        'blocked' => 'Not in use',
    ];

    public function __construct(
        private int $branchId,
        private Outlet $outlet,
    ) {}

    public static function for(int $branchId, Outlet $outlet): self
    {
        return new self($branchId, $outlet);
    }

    /**
     * The seating plan, section by section, with whatever is running on it.
     *
     * @return Collection<int, object{group: PosTableGroup, tables: Collection}>
     */
    public function sections(): Collection
    {
        $groups = PosTableGroup::query()
            ->forBranch($this->branchId)
            ->where('outlet_id', $this->outlet->id)
            ->where('status', 1)
            ->with(['tables' => fn ($q) => $q->where('status', 1)])
            ->orderBy('sort')
            ->orderBy('name')
            ->get();

        $orders = $this->liveOrders();

        return $groups->map(fn (PosTableGroup $group) => (object) [
            'group' => $group,
            'tables' => $group->tables->map(fn ($table) => $this->tile(
                $table->id,
                $table->name,
                (int) $table->capacity,
                $orders->get($table->id)
            )),
        ]);
    }

    /**
     * Rooms with a guest in them, and any room-service order running.
     *
     * Driven by RoomBoard so "occupied" means here exactly what it means on the
     * front desk's calendar — the till cannot decide for itself that a room is
     * free while the desk says a guest is asleep in it.
     *
     * @return Collection<int, object>
     */
    public function rooms(): Collection
    {
        $rooms = Room::query()
            ->forBranch($this->branchId)
            ->where('status', 1)
            ->with('type')
            ->orderBy('room_no')
            ->get();

        $states = RoomBoard::states($rooms, now()->toDateString(), $this->branchId);
        $orders = $this->liveRoomOrders();

        return $rooms
            ->filter(fn (Room $room) => in_array(
                $states[$room->id]['state'] ?? 'clean',
                RoomBoard::OCCUPIED,
                true
            ))
            ->map(function (Room $room) use ($states, $orders) {
                $stay = $states[$room->id]['check_in'] ?? null;
                $order = $stay ? $orders->get($stay->id) : null;

                $tile = $this->tile($room->id, $room->room_no, (int) $room->max_pax, $order);
                $tile->guest = $stay?->guest_name;
                $tile->folio = $stay?->folio_no;
                $tile->check_in_id = $stay?->id;
                $tile->kind = $room->type?->name;

                return $tile;
            })
            ->values();
    }

    /**
     * A count of each state, for the strip above the grid.
     *
     * @return array<string, int>
     */
    public function summary(Collection $tiles): array
    {
        $counts = array_fill_keys(array_keys(self::STATES), 0);

        foreach ($tiles as $tile) {
            $counts[$tile->state] = ($counts[$tile->state] ?? 0) + 1;
        }

        return $counts;
    }

    /** Every tile on the floor, flattened — what summary() wants. */
    public function tiles(Collection $sections): Collection
    {
        return $sections->flatMap(fn ($section) => $section->tables);
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * One tile.
     *
     * `state` is what colours it, and it is worked out here rather than in the
     * view so the grid, the legend and the counts can never disagree.
     */
    private function tile(int $id, string $name, int $capacity, ?object $order): object
    {
        $state = 'free';

        if ($order) {
            $state = $order->status === 'billed' ? 'due' : 'running';
        }

        return (object) [
            'id' => $id,
            'name' => $name,
            'capacity' => $capacity,
            'state' => $state,
            'order_id' => $order->id ?? null,
            'order_no' => $order->order_no ?? null,
            'amount' => (float) ($order->net_amount ?? 0),
            'items' => (int) ($order->item_count ?? 0),
            'kots' => (int) ($order->kot_count ?? 0),
            'pax' => (int) ($order->pax ?? 0),
            'steward' => $order->steward_name ?? null,
            // An epoch, so the browser can run the clock without a round trip
            // and without trusting the till's own clock to match the server's.
            'opened_at' => isset($order->opened_at) ? strtotime($order->opened_at) : null,
            'guest' => $order->guest_name ?? null,
            'folio' => null,
            'check_in_id' => $order->check_in_id ?? null,
            'kind' => null,
        ];
    }

    /** The live orders on this outlet's tables, keyed by table. */
    private function liveOrders(): Collection
    {
        return $this->orderQuery()
            ->whereNotNull('o.pos_table_id')
            ->get()
            ->keyBy('pos_table_id');
    }

    /** The live room-service orders for this outlet, keyed by stay. */
    private function liveRoomOrders(): Collection
    {
        return $this->orderQuery()
            ->whereNotNull('o.check_in_id')
            ->get()
            ->keyBy('check_in_id');
    }

    private function orderQuery()
    {
        return DB::table('pos_orders as o')
            ->leftJoin('pos_stewards as s', 's.id', '=', 'o.pos_steward_id')
            ->where('o.branch_id', $this->branchId)
            ->where('o.outlet_id', $this->outlet->id)
            ->whereIn('o.status', PosOrder::HOLDS_TABLE)
            ->select([
                'o.id', 'o.order_no', 'o.status', 'o.net_amount', 'o.kot_count',
                'o.pax', 'o.opened_at', 'o.pos_table_id', 'o.check_in_id', 'o.guest_name',
                's.name as steward_name',
            ])
            ->selectSub(
                DB::table('pos_order_items')->selectRaw('count(*)')->whereColumn('pos_order_id', 'o.id'),
                'item_count'
            )
            ->orderBy('o.id');
    }
}
