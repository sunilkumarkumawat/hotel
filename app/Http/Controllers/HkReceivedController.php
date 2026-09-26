<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Models\HouseKeeping\HkItem;
use App\Models\HouseKeeping\HkReceipt;
use App\Models\Master\Vendor;
use App\Support\Laundry;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;


class HkReceivedController extends Controller
{
    public function index(Request $request): View
    {
        $branchId = Helper::getActiveBranchId();

        $filters = [
            'q' => $request->string('q')->toString(),
            'vendor' => $request->integer('vendor'),
            'from' => $request->string('from')->toString(),
            'to' => $request->string('to')->toString(),
        ];

        $receipts = HkReceipt::query()
            ->where('branch_id', $branchId)
            ->with(['vendor', 'lines'])
            ->search($filters['q'])
            ->when($filters['vendor'], fn ($q, $id) => $q->where('vendor_id', $id))
            ->when($filters['from'], fn ($q, $d) => $q->whereDate('receive_date', '>=', $d))
            ->when($filters['to'], fn ($q, $d) => $q->whereDate('receive_date', '<=', $d))
            ->orderByDesc('receive_date')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15) ?: 15)
            ->withQueryString();

        $outstanding = Laundry::outstandingTotals($branchId);
        $items = HkItem::query()->forBranch($branchId)->orderBy('name')->get()->keyBy('id');

        return view('house-keeping.received', [
            'receipts' => $receipts,
            'filters' => $filters,
            'vendors' => $this->vendors($branchId),
            'outstanding' => $outstanding
                ->mapWithKeys(fn ($qty, $id) => [$id => [
                    'name' => $items[$id]->name ?? 'Item #' . $id,
                    'unit' => $items[$id]->unit ?? 'pcs',
                    'qty' => $qty,
                ]])
                ->sortBy('name'),
            'counts' => [
                'notes' => HkReceipt::where('branch_id', $branchId)->count(),
                'out' => $outstanding->sum(),
                'kinds' => $outstanding->count(),
                'month' => HkReceipt::where('branch_id', $branchId)
                    ->whereDate('receive_date', '>=', now()->startOfMonth()->toDateString())
                    ->sum('total_qty'),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $branchId = Helper::getActiveBranchId();

        $vendorId = (int) old('vendor_id', $request->integer('vendor')) ?: null;

        return view('house-keeping.received-form', [
            'receiptNo' => HkReceipt::nextNumber($branchId),
            'today' => now()->toDateString(),
            'vendors' => $this->vendors($branchId),
            'vendorId' => $vendorId,
            'lines' => $this->pendingLines($branchId, $vendorId),
        ]);
    }

    public function pending(Request $request): JsonResponse
    {
        $branchId = Helper::getActiveBranchId();

        return response()->json([
            'lines' => array_values($this->pendingLines($branchId, $request->integer('vendor') ?: null)),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $branchId = Helper::getActiveBranchId();

        $data = $request->validate([
            'vendor_id' => ['required', 'integer'],
            'receive_date' => 'required|date',
            'remark' => 'nullable|string|max:255',
            'lines' => 'required|array|min:1',
            'lines.*.hk_item_id' => 'nullable|integer',
            'lines.*.received_qty' => 'nullable|numeric|min:0|max:999999',
            'lines.*.damaged_qty' => 'nullable|numeric|min:0|max:999999',
            'lines.*.missing_qty' => 'nullable|numeric|min:0|max:999999',
            'lines.*.remark' => 'nullable|string|max:120',
        ], [
            'vendor_id.required' => 'Pick the vendor this linen came back from.',
            'lines.required' => 'Nothing to receive — this vendor is not holding anything.',
        ]);

        $pending = Laundry::outstandingFor($branchId, (int) $data['vendor_id']);

        if ($pending->isEmpty()) {
            return back()->withInput()->with('error', 'That vendor is not holding any linen right now.');
        }

        [$lines, $over] = $this->cleanLines($data['lines'], $pending, $branchId);

        if ($over !== []) {
            return back()->withInput()->with('error', sprintf(
                'More came back than went out for %s. Check the count, or write an issue note for the extra first.',
                implode(', ', $over)
            ));
        }

        if ($lines === []) {
            return back()->withInput()->with('error', 'Every line is zero — enter what actually came back.');
        }
        $receipt = retry(3, fn () => DB::transaction(function () use ($branchId, $data, $lines, $request) {
            $receipt = HkReceipt::create([
                'branch_id' => $branchId,
                'receipt_no' => HkReceipt::nextNumber($branchId),
                'vendor_id' => $data['vendor_id'],
                'receive_date' => CarbonImmutable::parse($data['receive_date'])->toDateString(),
                'total_qty' => round(array_sum(array_column($lines, 'received_qty')), 2),
                'remark' => $data['remark'] ?? null,
                'created_by' => $request->user()->user_id,
            ]);

            foreach ($lines as $line) {
                $receipt->lines()->create($line);
            }

            return $receipt;
        }), 50);

        $lost = round(
            array_sum(array_column($lines, 'damaged_qty')) + array_sum(array_column($lines, 'missing_qty')),
            2
        );

        return redirect()
            ->route('house-keeping.received')
            ->with('status', sprintf(
                '%s saved — %s piece(s) back from %s.%s',
                $receipt->receipt_no,
                rtrim(rtrim(number_format((float) $receipt->total_qty, 2), '0'), '.'),
                $receipt->vendor?->name ?? 'the vendor',
                $lost > 0
                    ? ' ' . rtrim(rtrim(number_format($lost, 2), '0'), '.') . ' written off as damaged or missing.'
                    : ''
            ));
    }

    public function show(HkReceipt $receipt): View
    {
        abort_unless($receipt->branch_id === Helper::getActiveBranchId(), 404);

        $receipt->load(['vendor', 'lines.item', 'creator']);

        return view('house-keeping.received-show', ['receipt' => $receipt]);
    }

    public function destroy(HkReceipt $receipt): RedirectResponse
    {
        abort_unless($receipt->branch_id === Helper::getActiveBranchId(), 404);

        $number = $receipt->receipt_no;

        DB::transaction(function () use ($receipt) {
            $receipt->lines()->delete();
            $receipt->delete();
        });

        return back()->with('status', "{$number} deleted. Those pieces are back on the vendor's list.");
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pendingLines(int $branchId, ?int $vendorId): array
    {
        $pending = Laundry::outstandingFor($branchId, $vendorId);

        if ($pending->isEmpty()) {
            return [];
        }

        $items = HkItem::query()->forBranch($branchId)->whereIn('id', $pending->keys())->orderBy('name')->get();

        return $items
            ->map(fn (HkItem $item) => [
                'hk_item_id' => $item->id,
                'name' => $item->name,
                'unit' => $item->unit,
                'pending' => (float) $pending[$item->id],
            ])
            ->all();
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: list<string>}
     */
    private function cleanLines(array $rows, $pending, int $branchId): array
    {
        $names = HkItem::query()->forBranch($branchId)->pluck('name', 'id');

        $out = [];
        $over = [];
        $seen = [];

        foreach ($rows as $row) {
            $itemId = (int) ($row['hk_item_id'] ?? 0);

            if (! $pending->has($itemId)) {
                continue;
            }

            $received = max(0, (float) ($row['received_qty'] ?? 0));
            $damaged = max(0, (float) ($row['damaged_qty'] ?? 0));
            $missing = max(0, (float) ($row['missing_qty'] ?? 0));
            $total = $received + $damaged + $missing;

            if ($total <= 0) {
                continue;
            }

            if (isset($seen[$itemId])) {
                $at = $seen[$itemId];
                $out[$at]['received_qty'] += $received;
                $out[$at]['damaged_qty'] += $damaged;
                $out[$at]['missing_qty'] += $missing;
                $total = $out[$at]['received_qty'] + $out[$at]['damaged_qty'] + $out[$at]['missing_qty'];
            }

            if (round($total, 2) > round((float) $pending[$itemId], 2)) {
                $over[$itemId] = $names[$itemId] ?? ('item #' . $itemId);

                continue;
            }

            if (isset($seen[$itemId])) {
                continue;
            }

            $seen[$itemId] = count($out);

            $out[] = [
                'hk_item_id' => $itemId,
                'pending_qty' => (float) $pending[$itemId],
                'received_qty' => $received,
                'damaged_qty' => $damaged,
                'missing_qty' => $missing,
                'remark' => $row['remark'] ?? null,
            ];
        }

        if ($over !== []) {
            return [[], array_values($over)];
        }

        return [$out, []];
    }

    private function vendors(int $branchId)
    {
        return Vendor::query()->forBranch($branchId)->active()->orderBy('name')->get();
    }
}
