<?php

namespace App\Http\Controllers\Store;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreCategory;
use App\Models\Store\StoreItem;
use App\Support\Store;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * What is on the shelf, what it is worth, and how it got there.
 *
 * Two screens. The stock sheet is what a storekeeper counts against; the
 * ledger is what answers "why does the book say forty kilos?" — and it can
 * answer it, because every movement since the day the item was created is
 * still there.
 */
class StockController extends Controller
{
    /** GET store/stock */
    public function index(Request $request): View
    {
        $branchId = (int) Helper::getActiveBranchId();

        $items = StoreItem::query()
            ->forBranch($branchId)
            ->active()
            ->with('category')
            ->when($request->integer('category'), fn ($q, $c) => $q->where('store_category_id', $c))
            ->when($request->string('q')->toString(), fn ($q, $t) => $q->where('name', 'like', "%{$t}%"))
            ->orderBy('name')
            ->get();

        [$from, $to] = $this->range($request);

        return view('store.stock', [
            'items' => $items,
            'categories' => StoreCategory::forBranch($branchId)->active()->orderBy('name')->get(),
            'summary' => Store::summary($branchId),
            'reorder' => Store::reorder($branchId, 12),
            'short' => Store::shortIssues($branchId),
            'consumption' => Store::consumption($branchId, $from, $to),
            'variance' => Store::consumptionVariance($branchId, $from, $to),
            'from' => $from,
            'to' => $to,
            'filters' => [
                'category' => $request->integer('category'),
                'q' => $request->string('q')->toString(),
            ],
            'departments' => Store::DEPARTMENTS,
        ]);
    }

    /** GET store/stock/export */
    public function export(Request $request): StreamedResponse
    {
        $branchId = (int) Helper::getActiveBranchId();

        $items = StoreItem::query()
            ->forBranch($branchId)->active()->with('category')->orderBy('name')->get();

        return response()->streamDownload(function () use ($items) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['Code', 'Item', 'Category', 'Unit', 'In stock', 'Average rate', 'Value', 'Reorder level']);

            foreach ($items as $item) {
                fputcsv($out, [
                    $item->code,
                    $item->name,
                    $item->category?->name,
                    $item->unit,
                    number_format((float) $item->current_qty, 3, '.', ''),
                    number_format((float) $item->avg_rate, 2, '.', ''),
                    number_format($item->value, 2, '.', ''),
                    number_format((float) $item->reorder_level, 3, '.', ''),
                ]);
            }

            fclose($out);
        }, 'stock-' . today()->toDateString() . '.csv', ['Content-Type' => 'text/csv']);
    }

    /** GET store/stock/{item} — one item's whole history. */
    public function ledger(Request $request, StoreItem $item): View
    {
        $branchId = (int) Helper::getActiveBranchId();
        abort_unless($item->branch_id === null || (int) $item->branch_id === $branchId, 404);

        [$from, $to] = $this->range($request);

        return view('store.ledger', [
            'item' => $item->load('category'),
            'rows' => Store::ledger($item->id, $from, $to),
            'from' => $from,
            'to' => $to,
        ]);
    }

    /**
     * POST store/stock/{item}/rebuild
     *
     * The button behind "are these numbers right?". It replays the item's
     * whole ledger and writes the balances again, so the answer can be
     * produced rather than argued about.
     */
    public function rebuild(StoreItem $item): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();
        abort_unless($item->branch_id === null || (int) $item->branch_id === $branchId, 404);

        $before = (float) $item->current_qty;
        $result = Store::rebuild($item->id);

        $moved = abs($before - $result['qty']) > 0.0005;

        return back()->with($moved ? 'warning' : 'status', $moved
            ? 'Recalculated from ' . $result['rows'] . ' movements — the balance changed from '
                . number_format($before, 3) . ' to ' . number_format($result['qty'], 3)
                . '. The ledger was right; the cached figure was not.'
            : 'Recalculated from ' . $result['rows'] . ' movements. The balance was already correct.');
    }

    /**
     * The window a report covers — this month so far, by default.
     *
     * @return array{0: string, 1: string}
     */
    private function range(Request $request): array
    {
        $today = CarbonImmutable::parse(today()->toDateString());

        $from = rescue(
            fn () => CarbonImmutable::parse($request->string('from')->toString())->toDateString(),
            $today->startOfMonth()->toDateString(),
            false
        );

        $to = rescue(
            fn () => CarbonImmutable::parse($request->string('to')->toString())->toDateString(),
            $today->toDateString(),
            false
        );

        return $from <= $to ? [$from, $to] : [$to, $from];
    }
}
