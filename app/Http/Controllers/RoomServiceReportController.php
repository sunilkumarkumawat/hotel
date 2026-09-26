<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Models\Pos\Outlet;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * What each room was charged for, beyond the room itself.
 *
 * Two questions that sound the same and are not, so they are two screens:
 *
 *   **Room Wise Services** — everything posted to a room's folio that is not a
 *   room night: laundry, an airport pickup, an extra bed, a sundry. This is the
 *   report a manager reads to find out which rooms actually spend.
 *
 *   **Room Service Orders** — food and drink the kitchen sent up, taken on the
 *   POS as a room-service order. Same rooms, different till, and the money is
 *   on the folio only once the bill is signed to the room.
 *
 * Neither is derived from the other. A guest can order a club sandwich and
 * never have it signed to the room (they paid cash at the door), and a room can
 * carry a laundry charge that never went near the POS.
 */
class RoomServiceReportController extends Controller
{
    /**
     * GET front-office/room-wise-services
     *
     * Folio charges that are not room nights, room by room.
     */
    public function services(Request $request): View
    {
        $branchId = (int) Helper::getActiveBranchId();
        [$from, $to] = $this->range($request);

        $rows = DB::table('folio_charges as fc')
            ->join('check_ins as ci', 'ci.id', '=', 'fc.check_in_id')
            ->leftJoin('rooms as r', 'r.id', '=', 'ci.room_id')
            ->leftJoin('services as s', 's.id', '=', 'fc.service_id')
            ->where('fc.branch_id', $branchId)
            // Room rent is not a service — it is what the room IS.
            ->where('fc.charge_type', '!=', 'room')
            ->whereDate('fc.charge_date', '>=', $from)
            ->whereDate('fc.charge_date', '<=', $to)
            ->when($request->integer('room'), fn ($q, $id) => $q->where('ci.room_id', $id))
            ->orderBy('r.room_no')
            ->orderBy('fc.charge_date')
            ->get([
                'fc.id', 'fc.charge_date', 'fc.charge_type', 'fc.particulars',
                'fc.qty', 'fc.price', 'fc.tax_amount', 'fc.total_amount',
                'ci.id as check_in_id', 'ci.guest_name', 'ci.folio_no',
                'r.id as room_id',
                DB::raw("COALESCE(r.room_no, '—') as room_no"),
                's.name as service_name',
            ]);

        return view('reports.room-wise-services', $this->shape($rows, $from, $to) + [
            'rooms' => $this->roomFilter($branchId),
            'room' => $request->integer('room') ?: null,
            'types' => \App\Models\FrontOffice\FolioCharge::TYPES,
        ]);
    }

    /**
     * GET point-of-sale/room-service-orders
     *
     * POS orders that went to a room, room by room.
     */
    public function orders(Request $request): View
    {
        $branchId = (int) Helper::getActiveBranchId();
        [$from, $to] = $this->range($request);

        $rows = DB::table('pos_order_items as oi')
            ->join('pos_orders as o', 'o.id', '=', 'oi.pos_order_id')
            ->leftJoin('rooms as r', 'r.id', '=', 'o.room_id')
            ->leftJoin('outlets as ot', 'ot.id', '=', 'o.outlet_id')
            ->where('o.branch_id', $branchId)
            ->where('o.order_type', 'room_service')
            ->where('o.status', '!=', 'cancelled')
            ->whereDate('o.opened_at', '>=', $from)
            ->whereDate('o.opened_at', '<=', $to)
            ->when($request->integer('outlet'), fn ($q, $id) => $q->where('o.outlet_id', $id))
            ->when($request->integer('room'), fn ($q, $id) => $q->where('o.room_id', $id))
            ->orderBy('r.room_no')
            ->orderBy('o.opened_at')
            ->get([
                'oi.id', 'oi.item_name', 'oi.qty', 'oi.price', 'oi.tax_amount', 'oi.total_amount',
                'o.id as order_id', 'o.order_no', 'o.opened_at as charge_date', 'o.status',
                'o.guest_name', 'o.table_no',
                'r.id as room_id',
                DB::raw("COALESCE(r.room_no, o.table_no, '—') as room_no"),
                'ot.name as outlet_name',
            ]);

        return view('reports.room-service-orders', $this->shape($rows, $from, $to) + [
            'rooms' => $this->roomFilter($branchId),
            'room' => $request->integer('room') ?: null,
            'outlets' => Outlet::query()->forBranch($branchId)->sellable()->orderBy('name')->get(),
            'outlet' => $request->integer('outlet') ?: null,
        ]);
    }

    /** GET front-office/room-wise-services/export */
    public function exportServices(Request $request): StreamedResponse
    {
        return $this->export($request, 'services');
    }

    /** GET point-of-sale/room-service-orders/export */
    public function exportOrders(Request $request): StreamedResponse
    {
        return $this->export($request, 'orders');
    }

    /** The same rows the screen shows, as a spreadsheet. */
    private function export(Request $request, string $which): StreamedResponse
    {
        $branchId = (int) Helper::getActiveBranchId();
        [$from, $to] = $this->range($request);

        $view = $which === 'orders' ? $this->orders($request) : $this->services($request);
        $rows = collect($view->getData()['rows'] ?? collect())->flatMap(fn ($group) => $group->lines);

        $name = ($which === 'orders' ? 'room-service-orders-' : 'room-wise-services-') . $from . '-to-' . $to . '.csv';

        return response()->streamDownload(function () use ($rows, $which) {
            $out = fopen('php://output', 'w');

            fputcsv($out, $which === 'orders'
                ? ['Room', 'Date', 'Order', 'Outlet', 'Item', 'Qty', 'Rate', 'Tax', 'Amount']
                : ['Room', 'Date', 'Folio', 'Guest', 'Particulars', 'Qty', 'Rate', 'Tax', 'Amount']);

            foreach ($rows as $line) {
                fputcsv($out, $which === 'orders'
                    ? [
                        $line->room_no,
                        substr((string) $line->charge_date, 0, 10),
                        $line->order_no,
                        $line->outlet_name,
                        $line->item_name,
                        $line->qty,
                        $line->price,
                        $line->tax_amount,
                        $line->total_amount,
                    ]
                    : [
                        $line->room_no,
                        substr((string) $line->charge_date, 0, 10),
                        $line->folio_no,
                        $line->guest_name,
                        $line->particulars,
                        $line->qty,
                        $line->price,
                        $line->tax_amount,
                        $line->total_amount,
                    ]);
            }

            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * Group flat rows by room and add each room's total.
     *
     * Both reports are read the same way — "which room, and how much" — so they
     * are shaped the same way and the two views are the same table with
     * different column headings.
     *
     * @return array<string, mixed>
     */
    private function shape(Collection $rows, string $from, string $to): array
    {
        $grouped = $rows
            ->groupBy('room_no')
            ->map(fn (Collection $lines, $roomNo) => (object) [
                'room_no' => $roomNo,
                'room_id' => $lines->first()->room_id ?? null,
                'lines' => $lines->values(),
                'count' => $lines->count(),
                'qty' => round($lines->sum(fn ($l) => (float) $l->qty), 2),
                'tax' => round($lines->sum(fn ($l) => (float) $l->tax_amount), 2),
                'total' => round($lines->sum(fn ($l) => (float) $l->total_amount), 2),
            ])
            // Busiest room first: the question is usually "who is spending".
            ->sortByDesc('total');

        return [
            'rows' => $grouped,
            'from' => $from,
            'to' => $to,
            'grand' => [
                'rooms' => $grouped->count(),
                'lines' => (int) $grouped->sum('count'),
                'tax' => round($grouped->sum('tax'), 2),
                'total' => round($grouped->sum('total'), 2),
            ],
        ];
    }

    /** Rooms that could be picked, for the filter. */
    private function roomFilter(int $branchId)
    {
        return DB::table('rooms')
            ->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId))
            ->orderBy('room_no')
            ->pluck('room_no', 'id');
    }

    /** @return array{0: string, 1: string} */
    private function range(Request $request): array
    {
        $from = rescue(
            fn () => CarbonImmutable::parse($request->string('from')->toString() ?: 'x')->toDateString(),
            now()->startOfMonth()->toDateString(),
            false
        );

        $to = rescue(
            fn () => CarbonImmutable::parse($request->string('to')->toString() ?: 'x')->toDateString(),
            now()->toDateString(),
            false
        );

        // A range typed backwards is a typo, not a reason to show nothing.
        return $from <= $to ? [$from, $to] : [$to, $from];
    }
}
