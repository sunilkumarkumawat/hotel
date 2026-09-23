<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Models\Pos\Outlet;
use App\Models\Pos\PosAuditLog;
use App\Models\Pos\PosOrder;
use App\Support\PosDashboard;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * POS Dashboard — one screen for what the outlets sold.
 *
 * Every figure comes off `App\Support\PosDashboard`, so a report built on the
 * same numbers later cannot drift from what the manager saw here.
 *
 * The screen is deliberately honest about an empty house: with no POS entries
 * yet every tile reads zero and every chart says so in words, rather than
 * drawing an axis over nothing.
 */
class PosDashboardController extends Controller
{
    /** GET point-of-sale/dashboard */
    public function index(Request $request): View
    {
        $branchId = Helper::getActiveBranchId();

        // Default to the month so far, which is the question a manager opens
        // this screen to ask.
        $from = $this->date($request->string('from')->toString(), now()->startOfMonth());
        $to = $this->date($request->string('to')->toString(), now());

        $data = new PosDashboard($branchId, $from, $to);

        return view('pos.dashboard', [
            'range' => ['from' => $data->from(), 'to' => $data->to()],
            'headline' => $data->headline(),
            'control' => $data->revenueControl(),
            'controlLabels' => PosAuditLog::ACTIONS,
            'trend' => $data->outletTrend(),
            'orderTypes' => $data->byOrderType(),
            'payModes' => $data->byPayMode(),
            'topItems' => $data->topItems(),
            'lowItems' => $data->lowItems(),
            'outletCount' => Outlet::query()->forBranch($branchId)->active()->count(),
            'typeLabels' => PosOrder::TYPES,
            // Quick ranges, so the common questions are one click.
            'presets' => [
                'Today' => [now()->toDateString(), now()->toDateString()],
                'Last 7 days' => [now()->subDays(6)->toDateString(), now()->toDateString()],
                'This month' => [now()->startOfMonth()->toDateString(), now()->toDateString()],
                'Last month' => [
                    now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                    now()->subMonthNoOverflow()->endOfMonth()->toDateString(),
                ],
            ],
        ]);
    }

    /** A date from the query string, or a sensible default. */
    private function date(string $value, $fallback): string
    {
        return rescue(
            fn () => CarbonImmutable::parse($value ?: 'x')->toDateString(),
            $fallback->toDateString(),
            false
        );
    }
}
