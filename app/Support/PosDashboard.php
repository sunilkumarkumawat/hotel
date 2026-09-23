<?php

namespace App\Support;

use App\Models\Pos\Outlet;
use App\Models\Pos\PosAuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Every figure on the POS Dashboard, worked out in one place.
 *
 * The screen is a reading of five tables — orders, order items, invoices,
 * payments and the audit log — over a date range. Keeping the arithmetic here
 * rather than in the controller means the same numbers can later feed a report
 * or an export without a second, subtly different version of them.
 *
 * **Sales are counted on the invoice, not the order.** An order can be opened,
 * changed and cancelled without a rupee moving; what the hotel sold is what it
 * billed. Cancelled invoices are excluded everywhere.
 *
 * Dates are inclusive of both ends — a manager asking for 1st to 10th means the
 * 10th as well — so every range test is written `>= from 00:00` and
 * `< the day after to`, which also keeps a datetime column from dropping the
 * last day's afternoon.
 */
class PosDashboard
{
    private string $from;

    private string $to;

    /** Exclusive upper bound: the morning after `$to`. */
    private string $until;

    public function __construct(private int $branchId, string $from, string $to)
    {
        // Given back to front, take them the right way round rather than
        // showing an empty screen and letting the manager wonder why.
        [$from, $to] = $from <= $to ? [$from, $to] : [$to, $from];

        $this->from = CarbonImmutable::parse($from)->toDateString();
        $this->to = CarbonImmutable::parse($to)->toDateString();
        $this->until = CarbonImmutable::parse($this->to)->addDay()->toDateString();
    }

    public function from(): string
    {
        return $this->from;
    }

    public function to(): string
    {
        return $this->to;
    }

    /*
    |--------------------------------------------------------------------------
    | The six tiles
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, float|int>
     */
    public function headline(): array
    {
        $invoices = $this->invoiceQuery()
            ->selectRaw('COUNT(*) as invoices, COALESCE(SUM(net_amount), 0) as sales, COALESCE(SUM(discount_total), 0) as discounts')
            ->first();

        $discounted = (clone $this->invoiceQuery())->where('discount_total', '>', 0)->count();

        $collections = DB::table('pos_payments as p')
            ->join('pos_invoices as i', 'i.id', '=', 'p.pos_invoice_id')
            ->where('p.branch_id', $this->branchId)
            ->where('i.status', '!=', 'cancelled')
            ->where('p.paid_at', '>=', $this->from)
            ->where('p.paid_at', '<', $this->until)
            ->sum('p.amount');

        $orders = $this->orderQuery(false)->count();
        $cancelled = (clone $this->orderQuery(false))->where('status', 'cancelled')->count();
        $complimentary = (clone $this->orderQuery())->where('is_complimentary', 1)->count();

        return [
            'sales' => round((float) ($invoices->sales ?? 0), 2),
            'collections' => round((float) $collections, 2),
            'orders' => (int) $orders,
            'cancelled' => (int) $cancelled,
            'complimentary' => (int) $complimentary,
            'turnaround' => $this->turnAroundMinutes(),
            'discounts' => round((float) ($invoices->discounts ?? 0), 2),
            'discount_invoices' => (int) $discounted,
            'invoices' => (int) ($invoices->invoices ?? 0),
        ];
    }

    /**
     * Average minutes from opening an order to closing it.
     *
     * Only orders that actually closed — one still open has no turn-around yet,
     * and counting it as zero would make a busy lunch look fast.
     */
    public function turnAroundMinutes(): float
    {
        $rows = $this->orderQuery()
            ->whereNotNull('closed_at')
            ->get(['opened_at', 'closed_at']);

        if ($rows->isEmpty()) {
            return 0.0;
        }

        $total = $rows->sum(function ($row) {
            $open = CarbonImmutable::parse($row->opened_at);
            $close = CarbonImmutable::parse($row->closed_at);

            return max(0, $close->getTimestamp() - $open->getTimestamp()) / 60;
        });

        return round($total / $rows->count(), 2);
    }

    /*
    |--------------------------------------------------------------------------
    | Revenue Control
    |--------------------------------------------------------------------------
    */

    /** @return array<string, int> keyed by PosAuditLog::ACTIONS */
    public function revenueControl(): array
    {
        $counts = DB::table('pos_audit_logs')
            ->where('branch_id', $this->branchId)
            ->where('happened_at', '>=', $this->from)
            ->where('happened_at', '<', $this->until)
            ->groupBy('action')
            ->selectRaw('action, COUNT(*) as total')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->action => (int) $row->total]);

        return collect(PosAuditLog::ACTIONS)
            ->map(fn ($label, $key) => (int) ($counts[$key] ?? 0))
            ->all();
    }

    /*
    |--------------------------------------------------------------------------
    | The three charts
    |--------------------------------------------------------------------------
    */

    /**
     * Outlet sales, one column per day.
     *
     * Returns the axis (every day in the range, whether it sold anything or
     * not — a blank Tuesday is information), the outlets that actually sold,
     * and a value per outlet per day.
     *
     * @return array{days: list<array{date: string, label: string}>, outlets: list<array{id: int, name: string}>, values: array<int, array<string, float>>, max: float, totals: array<int, float>}
     */
    public function outletTrend(): array
    {
        $days = [];

        for ($day = CarbonImmutable::parse($this->from); $day->toDateString() <= $this->to; $day = $day->addDay()) {
            $days[] = [
                'date' => $day->toDateString(),
                'label' => $day->format('d M'),
            ];
        }

        $rows = $this->invoiceQuery()
            ->selectRaw('outlet_id, ' . $this->dateOf('invoice_at') . ' as day, COALESCE(SUM(net_amount), 0) as amount')
            ->groupBy('outlet_id', 'day')
            ->get();

        $outlets = Outlet::query()
            ->forBranch($this->branchId)
            ->orderBy('name')
            ->get(['id', 'name']);

        $values = [];
        $totals = [];

        foreach ($outlets as $outlet) {
            $totals[$outlet->id] = 0.0;

            foreach ($days as $day) {
                $values[$outlet->id][$day['date']] = 0.0;
            }
        }

        foreach ($rows as $row) {
            $id = (int) $row->outlet_id;
            $day = substr((string) $row->day, 0, 10);

            if (! isset($values[$id][$day])) {
                continue;
            }

            $values[$id][$day] = round((float) $row->amount, 2);
            $totals[$id] += (float) $row->amount;
        }

        // A column's height is the whole stack, not its tallest piece.
        $max = 0.0;

        foreach ($days as $day) {
            $stack = 0.0;

            foreach ($values as $byDay) {
                $stack += $byDay[$day['date']] ?? 0;
            }

            $max = max($max, $stack);
        }

        return [
            'days' => $days,
            'outlets' => $outlets->map(fn ($o) => ['id' => (int) $o->id, 'name' => $o->name])->all(),
            'values' => $values,
            'totals' => $totals,
            'max' => $max,
        ];
    }

    /**
     * Sales split by how the order was sold.
     *
     * @return Collection<int, array{key: string, label: string, amount: float, share: float}>
     */
    public function byOrderType(): Collection
    {
        $rows = DB::table('pos_invoices as i')
            ->join('pos_orders as o', 'o.id', '=', 'i.pos_order_id')
            ->where('i.branch_id', $this->branchId)
            ->where('i.status', '!=', 'cancelled')
            ->where('i.invoice_at', '>=', $this->from)
            ->where('i.invoice_at', '<', $this->until)
            ->groupBy('o.order_type')
            ->selectRaw('o.order_type as kind, COALESCE(SUM(i.net_amount), 0) as amount')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->kind => round((float) $row->amount, 2)]);

        $total = $rows->sum();

        return collect(\App\Models\Pos\PosOrder::TYPES)
            ->map(fn ($label, $key) => [
                'key' => $key,
                'label' => $label,
                'amount' => (float) ($rows[$key] ?? 0),
                'share' => $total > 0 ? round(((float) ($rows[$key] ?? 0)) / $total * 100, 1) : 0.0,
            ])
            ->values();
    }

    /**
     * What was collected, by pay mode.
     *
     * @return Collection<int, array{name: string, amount: float, share: float}>
     */
    public function byPayMode(): Collection
    {
        $rows = DB::table('pos_payments as p')
            ->join('pos_invoices as i', 'i.id', '=', 'p.pos_invoice_id')
            ->leftJoin('pay_mode as m', 'm.id', '=', 'p.pay_mode_id')
            ->where('p.branch_id', $this->branchId)
            ->where('i.status', '!=', 'cancelled')
            ->where('p.paid_at', '>=', $this->from)
            ->where('p.paid_at', '<', $this->until)
            ->groupBy('m.name')
            ->selectRaw('COALESCE(m.name, ?) as name, COALESCE(SUM(p.amount), 0) as amount', ['Unassigned'])
            ->orderByDesc('amount')
            ->get();

        $total = $rows->sum('amount');

        return $rows->map(fn ($row) => [
            'name' => $row->name,
            'amount' => round((float) $row->amount, 2),
            'share' => $total > 0 ? round((float) $row->amount / $total * 100, 1) : 0.0,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | The two item lists
    |--------------------------------------------------------------------------
    */

    /** @return Collection<int, array{name: string, qty: float, amount: float}> */
    public function topItems(int $limit = 8): Collection
    {
        return $this->itemQuery()->orderByDesc('amount')->limit($limit)->get()
            ->map(fn ($row) => [
                'name' => $row->item_name,
                'qty' => round((float) $row->qty, 2),
                'amount' => round((float) $row->amount, 2),
            ]);
    }

    /** @return Collection<int, array{name: string, qty: float, amount: float}> */
    public function lowItems(int $limit = 8): Collection
    {
        return $this->itemQuery()->orderBy('amount')->limit($limit)->get()
            ->map(fn ($row) => [
                'name' => $row->item_name,
                'qty' => round((float) $row->qty, 2),
                'amount' => round((float) $row->amount, 2),
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /** Invoices in range, cancelled ones left out. */
    private function invoiceQuery()
    {
        return DB::table('pos_invoices')
            ->where('branch_id', $this->branchId)
            ->where('status', '!=', 'cancelled')
            ->where('invoice_at', '>=', $this->from)
            ->where('invoice_at', '<', $this->until);
    }

    /** Orders in range. `$live` drops the cancelled ones. */
    private function orderQuery(bool $live = true)
    {
        $query = DB::table('pos_orders')
            ->where('branch_id', $this->branchId)
            ->where('opened_at', '>=', $this->from)
            ->where('opened_at', '<', $this->until);

        return $live ? $query->where('status', '!=', 'cancelled') : $query;
    }

    private function itemQuery()
    {
        return DB::table('pos_order_items as it')
            ->join('pos_orders as o', 'o.id', '=', 'it.pos_order_id')
            ->where('o.branch_id', $this->branchId)
            ->where('o.status', '!=', 'cancelled')
            ->where('o.opened_at', '>=', $this->from)
            ->where('o.opened_at', '<', $this->until)
            ->groupBy('it.item_name')
            ->selectRaw('it.item_name, COALESCE(SUM(it.qty), 0) as qty, COALESCE(SUM(it.total_amount), 0) as amount');
    }

    /**
     * The date part of a datetime column, in the driver's own dialect.
     *
     * MySQL and SQLite disagree about how to get it, and this screen has to
     * work on both — SQLite while the app is being built, MySQL in the hotel.
     */
    private function dateOf(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "date({$column})"
            : "DATE({$column})";
    }
}
