<?php

namespace App\Http\Controllers\Pos;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Pos\PosDepartment;
use App\Models\Pos\PosOrder;
use App\Models\Pos\PosOrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class KitchenController extends Controller
{
    public function index(Request $request): View
    {
        $branchId = (int) Helper::getActiveBranchId();

        $outlets = PosController::outlets($branchId);
        $outlet = $outlets->firstWhere('id', $request->integer('outlet'));

        $departments = PosDepartment::query()->forBranch($branchId)->active()->orderBy('name')->get();
        $department = $departments->firstWhere('id', $request->integer('department'));

        return view('pos.kitchen.board', [
            'outlets' => $outlets,
            'outlet' => $outlet,
            'departments' => $departments,
            'department' => $department,
            'tickets' => $this->tickets($branchId, $outlet?->id, $department?->id),
            'columns' => ['pending' => 'New', 'preparing' => 'Preparing', 'ready' => 'Ready'],
            'warnAt' => (int) config('pms.kds_warn_minutes', 10) * 60,
            'lateAt' => (int) config('pms.kds_late_minutes', 20) * 60,
            'refresh' => (int) config('pms.kds_refresh_seconds', 20),
        ]);
    }

    public function feed(Request $request): JsonResponse
    {
        $branchId = (int) Helper::getActiveBranchId();

        $wanted = $request->integer('outlet') ?: null;
        $allowed = PosController::outlets($branchId)->pluck('id')->all();

        if ($wanted && ! in_array($wanted, $allowed, false)) {
            abort(403);
        }

        $tickets = $this->tickets(
            $branchId,
            $wanted,
            $request->integer('department') ?: null
        );

        return response()->json([
            'at' => now()->timestamp,
            'tickets' => $tickets->values(),
        ]);
    }

    public function advance(Request $request): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();

        $data = $request->validate([
            'order_id' => 'required|integer',
            'kot_no' => 'required|integer|min:1',
            'to' => 'required|in:pending,preparing,ready,served',
        ]);

        $order = PosOrder::query()->where('branch_id', $branchId)->findOrFail($data['order_id']);

        PosOrderItem::query()
            ->where('pos_order_id', $order->id)
            ->where('kot_no', $data['kot_no'])
            ->update(['kitchen_status' => $data['to'], 'updated_at' => now()]);

        $label = PosOrderItem::KITCHEN_STATUSES[$data['to']];

        return back()->with('status', "{$order->where_label} · KOT {$data['kot_no']} marked {$label}.");
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function tickets(int $branchId, ?int $outletId, ?int $departmentId): Collection
    {
        $lines = PosOrderItem::query()
            ->sent()
            ->where('kitchen_status', '!=', 'served')
            ->when($departmentId, fn ($q) => $q->where('pos_department_id', $departmentId))
            ->whereHas('order', fn ($q) => $q
                ->where('branch_id', $branchId)
                ->where('status', '!=', 'cancelled')
                ->when($outletId, fn ($inner) => $inner->where('outlet_id', $outletId)))
            ->with(['order.outlet', 'order.steward', 'department'])
            ->orderBy('fired_at')
            ->orderBy('id')
            ->get();

        return $lines
            ->groupBy(fn (PosOrderItem $line) => $line->pos_order_id . ':' . $line->kot_no)
            ->map(function (Collection $group) {
                $first = $group->first();
                $order = $first->order;

                return [
                    'key' => $order->id . ':' . $first->kot_no,
                    'order_id' => $order->id,
                    'order_no' => $order->order_no,
                    'kot_no' => (int) $first->kot_no,
                    'where' => $order->where_label,
                    'type' => PosOrder::TYPES[$order->order_type] ?? $order->order_type,
                    'outlet' => $order->outlet?->name,
                    'steward' => $order->steward?->name,
                    'pax' => (int) $order->pax,
                    'fired_at' => $first->fired_at?->timestamp,
                    'status' => $this->ticketStatus($group),
                    'lines' => $group->map(fn (PosOrderItem $line) => [
                        'id' => $line->id,
                        'name' => $line->item_name,
                        'qty' => rtrim(rtrim(number_format((float) $line->qty, 2, '.', ''), '0'), '.'),
                        'remark' => $line->remark,
                        'department' => $line->department?->name,
                        'nc' => (bool) $line->is_nc,
                    ])->values(),
                ];
            })
            ->sortBy('fired_at')
            ->values();
    }
    private function ticketStatus(Collection $lines): string
    {
        foreach (['pending', 'preparing', 'ready'] as $status) {
            if ($lines->contains('kitchen_status', $status)) {
                return $status;
            }
        }

        return 'ready';
    }
}
