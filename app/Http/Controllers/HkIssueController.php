<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Models\HouseKeeping\HkIssue;
use App\Models\HouseKeeping\HkItem;
use App\Models\Master\Vendor;
use App\Support\Laundry;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;


class HkIssueController extends Controller
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

        $issues = HkIssue::query()
            ->where('branch_id', $branchId)
            ->with(['vendor', 'lines'])
            ->search($filters['q'])
            ->when($filters['vendor'], fn ($q, $id) => $q->where('vendor_id', $id))
            ->when($filters['from'], fn ($q, $d) => $q->whereDate('issue_date', '>=', $d))
            ->when($filters['to'], fn ($q, $d) => $q->whereDate('issue_date', '<=', $d))
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 15) ?: 15)
            ->withQueryString();

        $outstanding = Laundry::outstandingTotals($branchId);

        return view('house-keeping.issue', [
            'issues' => $issues,
            'filters' => $filters,
            'vendors' => $this->vendors($branchId),
            'counts' => [
                'notes' => HkIssue::where('branch_id', $branchId)->count(),
                'month' => HkIssue::where('branch_id', $branchId)
                    ->whereDate('issue_date', '>=', now()->startOfMonth()->toDateString())
                    ->sum('total_amount'),
                'out' => $outstanding->sum(),
                'items' => HkItem::query()->forBranch($branchId)->active()->count(),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $branchId = Helper::getActiveBranchId();
        $rows = array_values((array) old('lines', [[]]));

        return view('house-keeping.issue-form', [
            'issueNo' => HkIssue::nextNumber($branchId),
            'today' => now()->toDateString(),
            'vendors' => $this->vendors($branchId),
            'items' => $this->items($branchId),
            'rows' => $rows ?: [[]],
            'units' => ['pcs' => 'pcs', 'set' => 'set', 'kg' => 'kg', 'pair' => 'pair'],
        ]);
    }

    public function pending(Request $request): JsonResponse
    {
        $branchId = Helper::getActiveBranchId();

        return response()->json([
            'pending' => Laundry::outstandingFor($branchId, $request->integer('vendor')),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $branchId = Helper::getActiveBranchId();

        $data = $request->validate([
            'vendor_id' => ['required', 'integer', $this->belongsToBranch('vendors', $branchId)],
            'issue_date' => 'required|date',
            'remark' => 'nullable|string|max:255',
            'lines' => 'required|array|min:1',
            'lines.*.hk_item_id' => 'nullable|integer',
            'lines.*.std_qty' => 'nullable|numeric|min:0|max:999999',
            'lines.*.exp_qty' => 'nullable|numeric|min:0|max:999999',
            'lines.*.rewash_qty' => 'nullable|numeric|min:0|max:999999',
            'lines.*.std_rate' => 'nullable|numeric|min:0|max:999999',
            'lines.*.exp_rate' => 'nullable|numeric|min:0|max:999999',
        ], [
            'vendor_id.required' => 'Pick the vendor this linen is going to.',
            'lines.required' => 'Add at least one item to the note.',
        ]);

        $items = $this->items($branchId)->keyBy('id');
        $pending = Laundry::outstandingFor($branchId, (int) $data['vendor_id']);

        [$lines, $twice] = $this->cleanLines($data['lines'], $items, $pending);

        if ($twice !== []) {
            return back()->withInput()->with('error', sprintf(
                '%s %s on the note twice. Put the whole count on one line — two lines would each show the same Prev Qty and read as a double count.',
                implode(', ', $twice),
                count($twice) === 1 ? 'is' : 'are'
            ));
        }

        if ($lines === []) {
            return back()->withInput()->with('error', 'Every line is empty — pick an item and enter a quantity.');
        }
        $issue = retry(3, fn () => DB::transaction(function () use ($branchId, $data, $lines, $request) {
            $issue = HkIssue::create([
                'branch_id' => $branchId,
                'issue_no' => HkIssue::nextNumber($branchId),
                'vendor_id' => $data['vendor_id'],
                'issue_date' => CarbonImmutable::parse($data['issue_date'])->toDateString(),
                'total_qty' => round(array_sum(array_column($lines, 'sent')), 2),
                'total_amount' => round(array_sum(array_column($lines, 'amount')), 2),
                'remark' => $data['remark'] ?? null,
                'created_by' => $request->user()->user_id,
            ]);

            foreach ($lines as $line) {
                unset($line['sent']);
                $issue->lines()->create($line);
            }

            return $issue;
        }), 50);

        return redirect()
            ->route('house-keeping.issue')
            ->with('status', sprintf(
                '%s saved — %s piece(s) out to %s, ₹%s.',
                $issue->issue_no,
                rtrim(rtrim(number_format((float) $issue->total_qty, 2), '0'), '.'),
                $issue->vendor?->name ?? 'the vendor',
                number_format((float) $issue->total_amount, 2)
            ));
    }

    public function show(HkIssue $issue): View
    {
        abort_unless($issue->branch_id === Helper::getActiveBranchId(), 404);

        $issue->load(['vendor', 'lines.item', 'creator']);

        return view('house-keeping.issue-show', ['issue' => $issue]);
    }
    public function destroy(HkIssue $issue): RedirectResponse
    {
        abort_unless($issue->branch_id === Helper::getActiveBranchId(), 404);

        $number = $issue->issue_no;

        DB::transaction(function () use ($issue) {
            $issue->lines()->delete();
            $issue->delete();
        });

        return back()->with('status', "{$number} deleted. Those pieces are off the vendor's list again.");
    }
    public function storeItem(Request $request): RedirectResponse
    {
        $branchId = Helper::getActiveBranchId();

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'unit' => 'nullable|string|max:20',
            'std_rate' => 'nullable|numeric|min:0|max:999999',
            'exp_rate' => 'nullable|numeric|min:0|max:999999',
        ]);

        $exists = HkItem::query()->forBranch($branchId)->where('name', $data['name'])->exists();

        if ($exists) {
            return back()->withInput()->with('error', "There is already an item called {$data['name']}.");
        }

        HkItem::create([
            'branch_id' => $branchId,
            'name' => $data['name'],
            'unit' => $data['unit'] ?: 'pcs',
            'std_rate' => $data['std_rate'] ?? 0,
            'exp_rate' => $data['exp_rate'] ?? 0,
            'status' => 1,
        ]);

        return back()->with('status', "{$data['name']} added. Pick it in the Item column.");
    }

    public function storeVendor(Request $request): RedirectResponse
    {
        $branchId = Helper::getActiveBranchId();

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'mobile' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'gst_no' => 'nullable|string|max:20',
        ]);

        $exists = Vendor::query()->forBranch($branchId)->where('name', $data['name'])->exists();

        if ($exists) {
            return back()->withInput()->with('error', "There is already a vendor called {$data['name']}.");
        }

        Vendor::create($data + ['branch_id' => $branchId, 'status' => 1]);

        return back()->with('status', "{$data['name']} added. Pick them in Vendor.");
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: list<string>}
     */
    private function cleanLines(array $rows, $items, $pending): array
    {
        $out = [];
        $seen = [];
        $twice = [];

        foreach ($rows as $row) {
            $itemId = (int) ($row['hk_item_id'] ?? 0);
            $item = $items->get($itemId);

            if (! $item) {
                continue;
            }

            $std = max(0, (float) ($row['std_qty'] ?? 0));
            $exp = max(0, (float) ($row['exp_qty'] ?? 0));
            $rewash = max(0, (float) ($row['rewash_qty'] ?? 0));

            if ($std + $exp + $rewash <= 0) {
                continue;
            }

            if (isset($seen[$itemId])) {
                $twice[$itemId] = $item->name;

                continue;
            }

            $stdRate = max(0, (float) ($row['std_rate'] ?? $item->std_rate));
            $expRate = max(0, (float) ($row['exp_rate'] ?? $item->exp_rate));

            $seen[$itemId] = true;

            $out[] = [
                'hk_item_id' => $itemId,
                'prev_qty' => (float) ($pending[$itemId] ?? 0),
                'std_qty' => $std,
                'exp_qty' => $exp,
                'rewash_qty' => $rewash,
                'std_rate' => $stdRate,
                'exp_rate' => $expRate,
                'amount' => Laundry::lineAmount($std, $stdRate, $exp, $expRate),
                'sent' => $std + $exp + $rewash,
            ];
        }

        return [$out, array_values($twice)];
    }

    private function items(int $branchId)
    {
        return HkItem::query()->forBranch($branchId)->active()->orderBy('name')->get();
    }

    private function vendors(int $branchId)
    {
        return Vendor::query()->forBranch($branchId)->active()->orderBy('name')->get();
    }

    private function belongsToBranch(string $table, int $branchId): \Closure
    {
        return function (string $attribute, $value, \Closure $fail) use ($table, $branchId) {
            $ok = DB::table($table)
                ->where('id', $value)
                ->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId))
                ->exists();

            if (! $ok) {
                $fail('That is not one of this branch\'s records.');
            }
        };
    }
}
