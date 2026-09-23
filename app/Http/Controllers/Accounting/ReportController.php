<?php

namespace App\Http\Controllers\Accounting;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Support\Ledgers;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The two statements everything else adds up to.
 *
 * Trial Balance says the books balance. Profit & Loss says whether the hotel
 * made money. Both are read straight off the ledgers with no stored totals
 * anywhere, which is why neither can drift out of step with the vouchers.
 */
class ReportController extends Controller
{
    /** GET accounting/trial-balance */
    public function trialBalance(Request $request): View
    {
        $branchId = (int) Helper::getActiveBranchId();
        $upto = Ledgers::date($request->string('upto')->toString(), today()->toDateString());

        return view('accounting.trial-balance', Ledgers::trialBalance($branchId, $upto) + [
            'uptoDate' => $upto,
        ]);
    }

    /** GET accounting/trial-balance/export */
    public function exportTrialBalance(Request $request): StreamedResponse
    {
        $branchId = (int) Helper::getActiveBranchId();
        $upto = Ledgers::date($request->string('upto')->toString(), today()->toDateString());

        $data = Ledgers::trialBalance($branchId, $upto);

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['Group', 'Ledger', 'Debit', 'Credit']);

            foreach ($data['groups'] as $group) {
                foreach ($group['rows'] as $row) {
                    fputcsv($out, [
                        $group['name'],
                        $row['ledger']->name,
                        number_format($row['debit'], 2, '.', ''),
                        number_format($row['credit'], 2, '.', ''),
                    ]);
                }
            }

            fputcsv($out, ['', 'Total',
                number_format($data['debit'], 2, '.', ''),
                number_format($data['credit'], 2, '.', ''),
            ]);

            fclose($out);
        }, 'trial-balance-' . $upto . '.csv', ['Content-Type' => 'text/csv']);
    }

    /** GET accounting/profit-loss */
    public function profitAndLoss(Request $request): View
    {
        $branchId = (int) Helper::getActiveBranchId();

        $from = Ledgers::date($request->string('from')->toString(), today()->startOfMonth()->toDateString());
        $to = Ledgers::date($request->string('to')->toString(), today()->toDateString());

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return view('accounting.profit-loss', Ledgers::profitAndLoss($branchId, $from, $to));
    }
}
