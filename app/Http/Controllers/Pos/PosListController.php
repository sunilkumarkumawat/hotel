<?php

namespace App\Http\Controllers\Pos;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Pos\Outlet;
use App\Models\Pos\PosInvoice;
use App\Models\Pos\PosOrder;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The six screens across the top of the till, beside Dine In.
 *
 * They are all the same shape — pick an outlet, pick a day, read one table —
 * so they are one controller rather than six near-identical ones. What differs
 * between them is the question each answers, and that is worth writing down:
 *
 * - **Live Orders** — what the kitchen is working on right now.
 * - **Unsettled Invoices** — bills printed and not paid for. The one screen a
 *   manager should look at before going home.
 * - **Cash Balance** — what should be in the drawer.
 * - **Invoices** — every bill, settled or not, with its payments.
 * - **Outlet Orders** — the day by outlet and order type.
 * - **Collections** — the money, broken down by how it arrived.
 */
class PosListController extends Controller
{
    /** GET point-of-sale/pos/live-orders */
    public function live(Request $request): View
    {
        [$branchId, $outlet, $outlets] = $this->context($request);

        $orders = PosOrder::query()
            ->where('branch_id', $branchId)
            ->when($outlet, fn ($q) => $q->where('outlet_id', $outlet->id))
            ->onFloor()
            ->with(['outlet', 'steward', 'items'])
            ->orderBy('opened_at')
            ->get();

        return $this->render('pos.till.live-orders', $request, $outlet, $outlets, [
            'orders' => $orders,
            'oldest' => $orders->first()?->openSeconds() ?? 0,
            'running' => $orders->where('status', 'open')->count(),
            'awaiting' => $orders->where('status', 'billed')->count(),
            'value' => round($orders->sum(fn (PosOrder $o) => (float) $o->net_amount), 2),
        ]);
    }

    /** GET point-of-sale/pos/unsettled */
    public function unsettled(Request $request): View
    {
        [$branchId, $outlet, $outlets] = $this->context($request);

        $invoices = PosInvoice::query()
            ->where('branch_id', $branchId)
            ->when($outlet, fn ($q) => $q->where('outlet_id', $outlet->id))
            ->unsettled()
            ->with(['outlet', 'order'])
            ->orderBy('invoice_at')
            ->get();

        return $this->render('pos.till.unsettled', $request, $outlet, $outlets, [
            'invoices' => $invoices,
            'owed' => round($invoices->sum(fn (PosInvoice $i) => $i->balance()), 2),
            'oldest' => $invoices->first()?->invoice_at,
        ]);
    }

    /**
     * GET point-of-sale/pos/cash-balance
     *
     * What should be in the drawer at the end of a shift: the cash taken,
     * mode by mode, for one day. Card and UPI are shown beside it because the
     * question a cashier is really answering is "does the day add up", and
     * cash alone never does.
     */
    public function cash(Request $request): View
    {
        [$branchId, $outlet, $outlets] = $this->context($request);
        $date = $this->date($request);

        $rows = $this->payments($branchId, $outlet, $date, $date);

        return $this->render('pos.till.cash-balance', $request, $outlet, $outlets, [
            'date' => $date,
            'rows' => $rows,
            'total' => round($rows->sum('amount'), 2),
            'cash' => round($rows->filter(fn ($r) => $this->looksLikeCash($r->mode))->sum('amount'), 2),
            'toRoom' => round(PosInvoice::query()
                ->where('branch_id', $branchId)
                ->when($outlet, fn ($q) => $q->where('outlet_id', $outlet->id))
                ->whereNotNull('folio_charge_id')
                ->whereDate('invoice_at', $date)
                // What was signed, not the face value: a guest can pay part in
                // cash and sign the rest.
                ->sum('folio_amount'), 2),
            'owed' => round(PosInvoice::query()
                ->where('branch_id', $branchId)
                ->when($outlet, fn ($q) => $q->where('outlet_id', $outlet->id))
                ->unsettled()
                ->whereDate('invoice_at', $date)
                ->sum(DB::raw('net_amount - paid_amount')), 2),
        ]);
    }

    /** GET point-of-sale/pos/invoices */
    public function invoices(Request $request): View
    {
        [$branchId, $outlet, $outlets] = $this->context($request);
        [$from, $to] = $this->range($request);

        $invoices = PosInvoice::query()
            ->where('branch_id', $branchId)
            ->when($outlet, fn ($q) => $q->where('outlet_id', $outlet->id))
            ->whereDate('invoice_at', '>=', $from)
            ->whereDate('invoice_at', '<=', $to)
            ->when($term = trim($request->string('q')->toString()), fn ($q) => $q
                ->where(fn ($inner) => $inner
                    ->where('invoice_no', 'like', "%{$term}%")
                    ->orWhere('guest_name', 'like', "%{$term}%")))
            ->with(['outlet', 'order'])
            ->orderByDesc('invoice_at')
            ->paginate(50)
            ->withQueryString();

        return $this->render('pos.till.invoices', $request, $outlet, $outlets, [
            'invoices' => $invoices,
            'from' => $from,
            'to' => $to,
            'term' => $term,
            'statuses' => PosInvoice::STATUSES,
        ]);
    }

    /**
     * GET point-of-sale/pos/outlet-orders
     *
     * The day by outlet and by how it was sold. Deliberately not filtered to
     * one outlet — this is the screen you open to compare them.
     */
    public function outletOrders(Request $request): View
    {
        [$branchId, $outlet, $outlets] = $this->context($request);
        [$from, $to] = $this->range($request);

        $rows = DB::table('pos_orders as o')
            ->leftJoin('outlets as t', 't.id', '=', 'o.outlet_id')
            ->where('o.branch_id', $branchId)
            ->where('o.status', '!=', 'cancelled')
            ->whereDate('o.opened_at', '>=', $from)
            ->whereDate('o.opened_at', '<=', $to)
            ->groupBy('o.outlet_id', 't.name', 'o.order_type')
            ->select([
                'o.outlet_id',
                DB::raw("COALESCE(t.name, 'Unassigned') as outlet_name"),
                'o.order_type',
                DB::raw('count(*) as orders'),
                DB::raw('sum(o.net_amount) as amount'),
                DB::raw('sum(o.pax) as covers'),
            ])
            ->orderBy('outlet_name')
            ->get();

        return $this->render('pos.till.outlet-orders', $request, $outlet, $outlets, [
            'rows' => $rows->groupBy('outlet_name'),
            'types' => PosOrder::TYPES,
            'from' => $from,
            'to' => $to,
            'orders' => (int) $rows->sum('orders'),
            'amount' => round($rows->sum('amount'), 2),
        ]);
    }

    /** GET point-of-sale/pos/collections */
    public function collections(Request $request): View
    {
        [$branchId, $outlet, $outlets] = $this->context($request);
        [$from, $to] = $this->range($request);

        $rows = $this->payments($branchId, $outlet, $from, $to);

        $byDay = DB::table('pos_payments as p')
            ->join('pos_invoices as i', 'i.id', '=', 'p.pos_invoice_id')
            ->where('i.status', '!=', 'cancelled')
            ->where('p.branch_id', $branchId)
            ->when($outlet, fn ($q) => $q->where('i.outlet_id', $outlet->id))
            ->whereDate('p.paid_at', '>=', $from)
            ->whereDate('p.paid_at', '<=', $to)
            ->groupBy(DB::raw('date(p.paid_at)'))
            ->select([DB::raw('date(p.paid_at) as day'), DB::raw('sum(p.amount) as amount')])
            ->orderBy('day')
            ->get();

        return $this->render('pos.till.collections', $request, $outlet, $outlets, [
            'rows' => $rows,
            'byDay' => $byDay,
            'from' => $from,
            'to' => $to,
            'total' => round($rows->sum('amount'), 2),
            'busiest' => $byDay->max('amount') ?: 0,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Shared plumbing
    |--------------------------------------------------------------------------
    */

    /** Money taken, grouped by how it arrived. */
    private function payments(int $branchId, ?Outlet $outlet, string $from, string $to)
    {
        return DB::table('pos_payments as p')
            ->join('pos_invoices as i', 'i.id', '=', 'p.pos_invoice_id')
            ->leftJoin('pay_mode as m', 'm.id', '=', 'p.pay_mode_id')
            ->where('i.status', '!=', 'cancelled')
            ->where('p.branch_id', $branchId)
            ->when($outlet, fn ($q) => $q->where('i.outlet_id', $outlet->id))
            ->whereDate('p.paid_at', '>=', $from)
            ->whereDate('p.paid_at', '<=', $to)
            ->groupBy('p.pay_mode_id', 'm.name')
            ->select([
                'p.pay_mode_id',
                DB::raw("COALESCE(m.name, 'Not stated') as mode"),
                DB::raw('count(*) as entries'),
                DB::raw('sum(p.amount) as amount'),
            ])
            ->orderByDesc('amount')
            ->get();
    }

    /**
     * Whether a pay mode is cash.
     *
     * By name, because the pay modes are a master list the hotel types itself
     * and there is no column saying which one the drawer holds. Wrong only if
     * somebody names a card machine "Cash Counter", which is a rename away from
     * being right again.
     */
    private function looksLikeCash(?string $mode): bool
    {
        return $mode !== null && str_contains(strtolower($mode), 'cash');
    }

    /** @return array{0: int, 1: ?Outlet, 2: \Illuminate\Support\Collection} */
    private function context(Request $request): array
    {
        $branchId = (int) Helper::getActiveBranchId();

        // The same list the till itself offers, so a cashier restricted to one
        // outlet cannot read another's takings by editing the URL.
        $outlets = PosController::outlets($branchId);

        $outlet = $outlets->firstWhere('id', $request->integer('outlet')) ?: $outlets->first();

        return [$branchId, $outlet, $outlets];
    }

    /** @param  array<string, mixed>  $data */
    private function render(string $view, Request $request, ?Outlet $outlet, $outlets, array $data): View
    {
        return view($view, $data + [
            'outlet' => $outlet,
            'outlets' => $outlets,
            'nav' => PosController::nav($outlet),
        ]);
    }

    private function date(Request $request): string
    {
        return rescue(
            fn () => CarbonImmutable::parse($request->string('date')->toString() ?: 'x')->toDateString(),
            now()->toDateString(),
            false
        );
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
