<?php

namespace App\Http\Controllers\Accounting;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Accounting\AccountGroup;
use App\Models\Accounting\Voucher;
use App\Support\Ledgers;
use App\Support\PostingRefused;
use App\Support\Vouchers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;


class VoucherController extends Controller
{

    public static function kinds(): array
    {
        return [
            'payment-voucher' => [
                'type' => 'payment',
                'title' => 'Payment Voucher',
                'subtitle' => 'Money going out of the cash box or a bank account.',
                'shape' => 'party',
                'party' => null,
                'party_label' => 'Paid to',
                'cash_label' => 'Paid from',
                'source' => null,
            ],
            'receipt-voucher' => [
                'type' => 'receipt',
                'title' => 'Receipt Voucher',
                'subtitle' => 'Money coming into the cash box or a bank account.',
                'shape' => 'party',
                'party' => null,
                'party_label' => 'Received from',
                'cash_label' => 'Received into',
                'source' => null,
            ],
            'contra-voucher' => [
                'type' => 'contra',
                'title' => 'Contra Voucher',
                'subtitle' => 'Moving money between the cash box and a bank account. Nothing is earned or spent.',
                'shape' => 'contra',
                'party' => null,
                'party_label' => 'Into',
                'cash_label' => 'Out of',
                'source' => null,
            ],
            'journal' => [
                'type' => 'journal',
                'title' => 'Journal',
                'subtitle' => 'Anything that is not money moving — an adjustment, a write-off, an accrual.',
                'shape' => 'journal',
                'party' => null,
                'party_label' => 'Ledger',
                'cash_label' => null,
                'source' => null,
            ],
            'vendor-payment' => [
                'type' => 'payment',
                'title' => 'Vendor Payment',
                'subtitle' => 'Paying a supplier, with what each one is owed in front of you.',
                'shape' => 'party',
                'party' => AccountGroup::CREDITORS,
                'party_label' => 'Vendor',
                'cash_label' => 'Paid from',
                'source' => 'vendor_payment',
            ],
            'customer-receipt' => [
                'type' => 'receipt',
                'title' => 'Customer Receipt',
                'subtitle' => 'Taking money from a customer, with what each one owes in front of you.',
                'shape' => 'party',
                'party' => AccountGroup::DEBTORS,
                'party_label' => 'Customer',
                'cash_label' => 'Received into',
                'source' => 'customer_receipt',
            ],
        ];
    }

    public function index(Request $request): View
    {
        $slug = $this->slug($request);
        $kind = $this->kind($slug);
        $branchId = (int) Helper::getActiveBranchId();

        $from = Ledgers::date($request->string('from')->toString(), today()->startOfMonth()->toDateString());
        $to = Ledgers::date($request->string('to')->toString(), today()->toDateString());

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $base = $this->scopedVouchers($branchId, $kind, $from, $to);

        $rows = (clone $base)
            ->when($request->string('q')->toString(), fn ($q, $t) => $q->where(function ($w) use ($t) {
                $w->where('voucher_no', 'like', "%{$t}%")
                    ->orWhere('reference_no', 'like', "%{$t}%")
                    ->orWhere('narration', 'like', "%{$t}%");
            }))
            ->with(['entries.ledger', 'creator'])
            ->orderByDesc('voucher_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('accounting.voucher', [
            'slug' => $slug,
            'kind' => $kind,
            'rows' => $rows,
            'from' => $from,
            'to' => $to,
            'q' => $request->string('q')->toString(),
            'nextNo' => Vouchers::nextNumber($branchId, $kind['type']),
            'cashLedgers' => Ledgers::cashBankLedgers($branchId),
            'partyLedgers' => $this->partyOptions($branchId, $kind),
            'partyBalances' => $this->partyBalances($branchId, $kind),
            'allLedgers' => Ledgers::options($branchId),
            'total' => round((float) (clone $base)->sum('amount'), 2),
        ]);
    }

    private function scopedVouchers(int $branchId, array $kind, string $from, string $to): Builder
    {
        return Voucher::query()
            ->forBranch($branchId)
            ->posted()
            ->ofType($kind['type'])
            ->when($kind['source'], fn ($q, $source) => $q->where('source_type', $source))
            ->when(
                ! $kind['source'] && $kind['type'] !== 'journal' && $kind['type'] !== 'contra',
                fn ($q) => $q->whereNull('source_type')
            )
            ->where('voucher_date', '>=', $from)
            ->where('voucher_date', '<', Ledgers::dayAfter($to));
    }

    public function store(Request $request): RedirectResponse
    {
        $slug = $this->slug($request);
        $kind = $this->kind($slug);
        $branchId = (int) Helper::getActiveBranchId();

        $data = $request->validate([
            'voucher_date' => 'required|date',
            'reference_no' => 'nullable|string|max:60',
            'narration' => 'nullable|string|max:1000',

            'cash_ledger_id' => 'nullable|integer',
            'to_ledger_id' => 'nullable|integer',
            'amount' => 'nullable|numeric|min:0|max:999999999',

            'lines' => 'nullable|array|max:40',
            'lines.*.ledger_id' => 'nullable|integer',
            'lines.*.amount' => 'nullable|numeric|min:0|max:999999999',
            'lines.*.debit' => 'nullable|numeric|min:0|max:999999999',
            'lines.*.credit' => 'nullable|numeric|min:0|max:999999999',
            'lines.*.narration' => 'nullable|string|max:255',
        ]);

        try {
            $voucher = Vouchers::post([
                'branch_id' => $branchId,
                'voucher_type' => $kind['type'],
                'voucher_date' => $data['voucher_date'],
                'reference_no' => $data['reference_no'] ?? null,
                'narration' => $data['narration'] ?? null,
                'source_type' => $kind['source'],
                'created_by' => $request->user()->user_id,
            ], $this->lines($kind, $data));
        } catch (PostingRefused $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        Vouchers::announce($voucher, $request->user()->name);

        return back()->with('status', sprintf(
            '%s %s posted — ₹ %s.',
            $kind['title'],
            $voucher->voucher_no,
            number_format((float) $voucher->amount, 2)
        ));
    }

    public function cancel(Request $request, int $voucher): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();

        $row = Voucher::query()->forBranch($branchId)->findOrFail($voucher);

        if ($row->isCancelled()) {
            return back()->with('info', 'That voucher was already cancelled.');
        }

        Vouchers::cancel($row);

        return back()->with('status', sprintf(
            '%s cancelled. It keeps its number — a hole in the series is the first thing an auditor asks about.',
            $row->voucher_no
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function lines(array $kind, array $data): array
    {
        if ($kind['shape'] === 'journal') {
            return array_map(fn ($line) => [
                'ledger_id' => $line['ledger_id'] ?? 0,
                'debit' => $line['debit'] ?? 0,
                'credit' => $line['credit'] ?? 0,
                'narration' => $line['narration'] ?? null,
            ], $data['lines'] ?? []);
        }

        if ($kind['shape'] === 'contra') {
            $amount = round((float) ($data['amount'] ?? 0), 2);

            return [
                ['ledger_id' => (int) ($data['cash_ledger_id'] ?? 0), 'debit' => 0, 'credit' => $amount, 'narration' => null],
                ['ledger_id' => (int) ($data['to_ledger_id'] ?? 0), 'debit' => $amount, 'credit' => 0, 'narration' => null],
            ];
        }

        $isPayment = $kind['type'] === 'payment';
        $lines = [];
        $total = 0.0;

        foreach ($data['lines'] ?? [] as $line) {
            $amount = round((float) ($line['amount'] ?? 0), 2);
            $ledgerId = (int) ($line['ledger_id'] ?? 0);

            if ($ledgerId <= 0 || $amount <= 0) {
                continue;
            }

            $lines[] = [
                'ledger_id' => $ledgerId,
                'debit' => $isPayment ? $amount : 0,
                'credit' => $isPayment ? 0 : $amount,
                'narration' => $line['narration'] ?? null,
            ];

            $total = round($total + $amount, 2);
        }

        array_unshift($lines, [
            'ledger_id' => (int) ($data['cash_ledger_id'] ?? 0),
            'debit' => $isPayment ? 0 : $total,
            'credit' => $isPayment ? $total : 0,
            'narration' => null,
        ]);

        return $lines;
    }

    /**
     * @return array<int, string>
     */
    private function partyOptions(int $branchId, array $kind): array
    {
        if (! $kind['party']) {
            return Ledgers::options($branchId);
        }

        $groupIds = AccountGroup::namedSubtreeIds($branchId, $kind['party']);

        return Ledgers::optionsInGroups($branchId, $groupIds);
    }

    /**
     * @return array<int, float>
     */
    private function partyBalances(int $branchId, array $kind): array
    {
        if (! $kind['party']) {
            return [];
        }

        $groupIds = AccountGroup::namedSubtreeIds($branchId, $kind['party']);
        $ledgerIds = array_keys(Ledgers::optionsInGroups($branchId, $groupIds));

        if ($ledgerIds === []) {
            return [];
        }

        $flip = $kind['party'] === AccountGroup::CREDITORS ? -1 : 1;

        return Ledgers::balancesFor($branchId, $ledgerIds, today()->toDateString())
            ->map(fn (float $balance) => round($balance * $flip, 2))
            ->all();
    }

    private function slug(Request $request): string
    {
        return (string) ($request->route()?->defaults['kind'] ?? '');
    }

    /** @return array<string, mixed> */
    private function kind(string $slug): array
    {
        $kinds = self::kinds();

        abort_unless(isset($kinds[$slug]), 404);

        return $kinds[$slug] + ['slug' => $slug, 'url' => 'accounting/' . $slug];
    }
}
