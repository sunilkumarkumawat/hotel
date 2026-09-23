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

/**
 * Every screen that writes a voucher.
 *
 * Six screens, one controller, because they differ in exactly three ways —
 * which kind of voucher they write, which side the cash sits on, and which
 * ledgers the party dropdown offers — and everything else about them is
 * identical. Copying this six times is how five of them end up with the balance
 * check and the sixth does not.
 *
 * None of them writes to the database directly: they shape lines and hand them
 * to {@see \App\Support\Vouchers::post()}, which is the only place a voucher is
 * checked, numbered and stored. A screen cannot post an unbalanced voucher
 * because a screen cannot post at all.
 */
class VoucherController extends Controller
{
    /**
     * slug => what that screen is.
     *
     * `party` narrows the ledger dropdown to a group's subtree by name, which
     * is what makes Vendor Payment a vendor screen rather than a second
     * Payment Voucher. A hotel that renames "Sundry Creditors" gets an empty
     * list and a message saying so, rather than a silently wrong one.
     */
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

    /** GET accounting/<slug> */
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
            // Same builder the rows above came from — a search box narrows
            // the rows but not this, so the total still reads "everything
            // in range", but it can no longer disagree with the list on
            // *which screen's* vouchers that range means.
            'total' => round((float) (clone $base)->sum('amount'), 2),
        ]);
    }

    /**
     * Every voucher that belongs on one of the six screens, before search or
     * paging — the row list and the total below it are both built from a
     * clone of this, so they cannot drift the way they used to: the total
     * on Payment Voucher used to count Vendor Payment's amounts too, even
     * though not one of those vouchers appeared in the list above it,
     * because only the row query excluded `source_type`. One query, cloned
     * for each of the two different endings, closes that gap.
     */
    private function scopedVouchers(int $branchId, array $kind, string $from, string $to): Builder
    {
        return Voucher::query()
            ->forBranch($branchId)
            ->posted()
            ->ofType($kind['type'])
            // Vendor Payment and Payment Voucher write the same kind of
            // voucher, so the list is narrowed by what wrote it too.
            ->when($kind['source'], fn ($q, $source) => $q->where('source_type', $source))
            ->when(
                ! $kind['source'] && $kind['type'] !== 'journal' && $kind['type'] !== 'contra',
                fn ($q) => $q->whereNull('source_type')
            )
            ->where('voucher_date', '>=', $from)
            ->where('voucher_date', '<', Ledgers::dayAfter($to));
    }

    /** POST accounting/<slug> */
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
            // The message is written for the clerk at the screen, so it goes
            // straight back to them rather than into a log.
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

    /** POST accounting/<slug>/{voucher}/cancel */
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

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * The voucher's lines, built from whichever shape of form was posted.
     *
     * This is the only part of the six screens that genuinely differs. Note
     * what it does *not* do: it never decides whether the voucher balances, and
     * it never picks a number. Both of those belong to Vouchers::post(), which
     * is the single door into the books.
     *
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
                // Out of one, into the other. Both sides are checked for being
                // cash or bank inside Vouchers::post().
                ['ledger_id' => (int) ($data['cash_ledger_id'] ?? 0), 'debit' => 0, 'credit' => $amount, 'narration' => null],
                ['ledger_id' => (int) ($data['to_ledger_id'] ?? 0), 'debit' => $amount, 'credit' => 0, 'narration' => null],
            ];
        }

        /*
         * Payment and Receipt are mirror images: the cash side takes the total
         * of the party lines, on the opposite side to them. Working the total
         * out here rather than trusting a posted figure is what stops a form
         * that says "₹5,000 out" while its lines add to ₹4,000.
         */
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
     * The party dropdown.
     *
     * Narrowed to a group's subtree for the two party screens and left wide
     * open for the others, so a Payment Voucher can pay an expense head
     * directly while Vendor Payment cannot.
     *
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
     * What each party stands at — shown beside the dropdown.
     *
     * The whole reason Vendor Payment exists as its own screen: paying a
     * supplier without seeing what they are owed is data entry, not accounting.
     * Signs are flipped so a creditor the hotel owes ₹5,000 reads as 5,000
     * rather than as −5,000.
     *
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

        // A creditor is Cr-positive and a debtor Dr-positive, so one is flipped
        // and the other is not — both then read as "owes / is owed this much".
        $flip = $kind['party'] === AccountGroup::CREDITORS ? -1 : 1;

        return Ledgers::balancesFor($branchId, $ledgerIds, today()->toDateString())
            ->map(fn (float $balance) => round($balance * $flip, 2))
            ->all();
    }

    /**
     * Which of the six screens this is.
     *
     * Read off the route's defaults for the same reason the facility setup
     * screens do it: Laravel appends route defaults after URI parameters, so a
     * method signature that took it as an argument would get them in the wrong
     * order the moment a route grew an id.
     */
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
