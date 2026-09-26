<?php

namespace App\Http\Controllers\Rate;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Rate\RatePlan;
use App\Support\Rates;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;


class RateCalendarController extends Controller
{
    private const SPANS = [14 => 'A fortnight', 31 => 'A month', 62 => 'Two months'];

    public function index(Request $request): View
    {
        $branchId = (int) Helper::getActiveBranchId();

        $from = rescue(
            fn () => CarbonImmutable::parse($request->string('from')->toString() ?: 'today')->toDateString(),
            today()->toDateString(),
            false
        );

        $nights = (int) $request->integer('nights');
        $nights = isset(self::SPANS[$nights]) ? $nights : 14;

        $planId = $request->integer('plan') ?: null;

        $calendar = Rates::calendar($branchId, $from, $nights, $planId);

        $start = CarbonImmutable::parse($from);

        return view('rates.calendar', $calendar + [
            'from' => $from,
            'nights' => $nights,
            'spans' => self::SPANS,
            'plans' => RatePlan::forBranch($branchId)->active()
                ->orderByDesc('is_default')->orderBy('name')->get(),
            'prev' => $start->subDays($nights)->toDateString(),
            'next' => $start->addDays($nights)->toDateString(),
            'today' => today()->toDateString(),
        ]);
    }
}
