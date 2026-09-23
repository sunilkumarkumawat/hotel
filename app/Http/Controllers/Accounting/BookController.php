<?php

namespace App\Http\Controllers\Accounting;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Accounting\Ledger;
use App\Models\Accounting\Voucher;
use App\Models\Master\PayMode;
use App\Support\Ledgers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The books you read rather than write.
 *
 * Day Book, Cash Book, Bank Book, Ledger Statement and All Receipt are all
 * views over the same two tables — `vouchers` and `voucher_entries` — which is
 * why none of them can disagree with another. Nothing here writes anything.
 *
 * Every one of them reads through {@see \App\Models\Accounting\Voucher} scopes,
 * so cancelled vouchers are left out in one place and cannot be forgotten on
 * the one screen nobody re-reads.
 */
class BookController extends Controller
{
    /**
     * GET accounting/day-book
     *
     * Everything posted on a day, in order. The screen a manager opens first.
     */
    public function dayBook(Request $request): View
    {
        $branchId = (int) Helper::getActiveBranchId();
        [$from, $to] = $this->range($request, today()->toDateString(), today()->toDateString());

        $vouchers = Voucher::query()
            ->forBranch($branchId)
            ->posted()
            ->ofType($request->string('type')->toString() ?: null)
            ->where('voucher_date', '>=', $from)
            ->where('voucher_date', '<', Ledgers::dayAfter($to))
            ->with(['entries.ledger', 'creator'])
            ->orderBy('voucher_date')
            ->orderBy('id')
            ->get();

        $debit = 0.0;
        $credit = 0.0;

        foreach ($vouchers as $voucher) {
            $debit += (float) $voucher->entries->sum('debit');
            $credit += (float) $voucher->entries->sum('credit');
        }

        return view('accounting.day-book', [
            'vouchers' => $vouchers->groupBy(fn (Voucher $v) => $v->voucher_date->toDateString()),
            'from' => $from,
            'to' => $to,
            'type' => $request->string('type')->toString(),
            'types' => Voucher::TYPES,
            'debit' => round($debit, 2),
            'credit' => round($credit, 2),
            'count' => $vouchers->count(),
        ]);
    }

    /** GET accounting/cash-book */
    public function cashBook(Request $request): View
    {
        return $this->book($request, 'cash', 'Cash Book', 'The cash box, movement by movement.');
    }

    /** GET accounting/bank-book */
    public function bankBook(Request $request): View
    {
        return $this->book($request, 'bank', 'Bank Book', 'One bank account, movement by movement.');
    }

    /**
     * GET accounting/ledger-statement
     *
     * The same statement for any ledger at all — opening, every movement, a
     * running balance, and where it closes.
     */
    public function statement(Request $request): View
    {
        $branchId = (int) Helper::getActiveBranchId();
        [$from, $to] = $this->range($request, today()->startOfMonth()->toDateString(), today()->toDateString());

        $ledgers = Ledger::query()->forBranch($branchId)->orderBy('name')->get();
        $chosen = $this->chosenLedger($request, $ledgers);

        return view('accounting.ledger-statement', [
            'ledgers' => $ledgers,
            'chosen' => $chosen,
            'statement' => $chosen
                ? Ledgers::statement((int) $chosen->id, $from, $to, $branchId)
                : null,
            'from' => $from,
            'to' => $to,
            'title' => 'Ledger Statement',
        ]);
    }

    /** GET accounting/ledger-statement/export */
    public function exportStatement(Request $request): StreamedResponse
    {
        $branchId = (int) Helper::getActiveBranchId();
        [$from, $to] = $this->range($request, today()->startOfMonth()->toDateString(), today()->toDateString());

        $ledgers = Ledger::query()->forBranch($branchId)->orderBy('name')->get();
        $chosen = $this->chosenLedger($request, $ledgers);

        abort_unless($chosen, 404);

        $statement = Ledgers::statement((int) $chosen->id, $from, $to, $branchId);

        $name = 'ledger-' . \Illuminate\Support\Str::slug($chosen->name) . '-' . $from . '-to-' . $to . '.csv';

        return response()->streamDownload(function () use ($statement) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['Date', 'Voucher', 'Type', 'Particulars', 'Narration', 'Debit', 'Credit', 'Balance']);
            fputcsv($out, ['', '', '', 'Opening balance', '', '', '', number_format($statement['opening'], 2, '.', '')]);

            foreach ($statement['lines'] as $line) {
                fputcsv($out, [
                    substr((string) $line->voucher_date, 0, 10),
                    $line->voucher_no,
                    $line->voucher_type,
                    $line->against,
                    $line->line_narration ?: $line->narration,
                    number_format($line->debit, 2, '.', ''),
                    number_format($line->credit, 2, '.', ''),
                    number_format($line->running, 2, '.', ''),
                ]);
            }

            fputcsv($out, ['', '', '', 'Closing balance', '', '', '', number_format($statement['closing'], 2, '.', '')]);

            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    /**
     * GET accounting/all-receipt
     *
     * Every rupee that came in, wherever it was taken.
     *
     * Three separate sources on one screen, and they are *not* added together
     * into one grand total by accident: a front-office settlement and the
     * receipt voucher an accountant later posts for it are the same money seen
     * twice. Each source is totalled on its own and the screen says so, which
     * is the honest answer to "how much did we take today".
     */
    public function allReceipts(Request $request): View
    {
        $branchId = (int) Helper::getActiveBranchId();
        [$from, $to] = $this->range($request, today()->toDateString(), today()->toDateString());

        $nextDay = Ledgers::dayAfter($to);
        $payMode = $request->integer('pay_mode');

        $vouchers = Voucher::query()
            ->forBranch($branchId)
            ->posted()
            ->ofType('receipt')
            ->where('voucher_date', '>=', $from)
            ->where('voucher_date', '<', $nextDay)
            ->with('entries.ledger')
            ->orderBy('voucher_date')
            ->get();

        $frontOffice = DB::table('settlements as s')
            ->leftJoin('check_ins as ci', 'ci.id', '=', 's.check_in_id')
            ->leftJoin('pay_mode as pm', 'pm.id', '=', 's.pay_mode_id')
            ->where('s.branch_id', $branchId)
            ->where('s.settle_date', '>=', $from)
            ->where('s.settle_date', '<', $nextDay)
            ->when($payMode, fn ($q, $id) => $q->where('s.pay_mode_id', $id))
            ->orderBy('s.settle_date')
            ->get([
                's.id', 's.settle_date', 's.amount', 's.reference_no', 's.remark',
                'ci.folio_no', 'ci.guest_name', 'pm.name as pay_mode',
            ]);

        $pos = DB::table('pos_payments as p')
            ->leftJoin('pos_invoices as i', 'i.id', '=', 'p.pos_invoice_id')
            ->leftJoin('pay_mode as pm', 'pm.id', '=', 'p.pay_mode_id')
            ->where('p.branch_id', $branchId)
            ->where('p.paid_at', '>=', $from . ' 00:00:00')
            ->where('p.paid_at', '<', $nextDay . ' 00:00:00')
            ->when($payMode, fn ($q, $id) => $q->where('p.pay_mode_id', $id))
            ->orderBy('p.paid_at')
            ->get([
                'p.id', 'p.paid_at', 'p.amount', 'p.reference_no',
                'i.invoice_no', 'pm.name as pay_mode',
            ]);

        return view('accounting.all-receipt', [
            'vouchers' => $vouchers,
            'frontOffice' => $frontOffice,
            'pos' => $pos,
            'from' => $from,
            'to' => $to,
            'payMode' => $payMode,
            'payModes' => PayMode::query()->forBranch($branchId)->active()->orderBy('name')->pluck('name', 'id'),
            'totals' => [
                'vouchers' => round((float) $vouchers->sum('amount'), 2),
                'frontOffice' => round((float) $frontOffice->sum('amount'), 2),
                'pos' => round((float) $pos->sum('amount'), 2),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /** Cash Book and Bank Book are one screen asked two questions. */
    private function book(Request $request, string $cashType, string $title, string $subtitle): View
    {
        $branchId = (int) Helper::getActiveBranchId();
        [$from, $to] = $this->range($request, today()->startOfMonth()->toDateString(), today()->toDateString());

        $ledgers = Ledgers::cashBankLedgers($branchId, $cashType);
        $chosen = $this->chosenLedger($request, $ledgers);

        return view('accounting.ledger-statement', [
            'ledgers' => $ledgers,
            'chosen' => $chosen,
            'statement' => $chosen
                ? Ledgers::statement((int) $chosen->id, $from, $to, $branchId)
                : null,
            'from' => $from,
            'to' => $to,
            'title' => $title,
            'subtitle' => $subtitle,
            // The Cash Book has nothing to show until a ledger has been marked
            // as the cash box, and saying that beats an empty table.
            'emptyHint' => $cashType === 'cash'
                ? 'No ledger is marked as the cash box yet. Open Accounting → Ledger, edit the one that is, and set “This is the cash box”.'
                : 'No ledger is marked as a bank account yet. Open Accounting → Ledger, edit the one that is, and set “This is a bank account”.',
        ]);
    }

    /**
     * The ledger the screen is looking at.
     *
     * Whatever was asked for — but only if it is on the list this screen is
     * allowed to show, so `?ledger=` cannot turn the Cash Book into a statement
     * of somebody's salary account.
     */
    private function chosenLedger(Request $request, $ledgers): ?Ledger
    {
        $wanted = $request->integer('ledger');

        return $ledgers->firstWhere('id', $wanted) ?: $ledgers->first();
    }

    /** @return array{0: string, 1: string} */
    private function range(Request $request, string $defaultFrom, string $defaultTo): array
    {
        $from = Ledgers::date($request->string('from')->toString(), $defaultFrom);
        $to = Ledgers::date($request->string('to')->toString(), $defaultTo);

        // A range typed backwards is a typo, not a reason to show nothing.
        return $from <= $to ? [$from, $to] : [$to, $from];
    }
}
