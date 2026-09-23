<?php

namespace App\Http\Controllers\Rate;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Rate\RatePlan;
use App\Support\Rates;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The rate calendar — every room type's price for every night, on one screen.
 *
 * This is the screen that makes a rate sheet arguable. A hotel can load
 * seasons, weekend rules and a corporate plan and still have no idea what it
 * is actually charging on the 24th; the grid answers that by running the same
 * engine the booking screen runs, one cell at a time, and printing what comes
 * back.
 *
 * Cells that fell back to the room type's base rent are marked. That is the
 * point of the screen as much as the prices are: an unmarked wall of numbers
 * would hide the fortnight nobody has priced.
 */
class RateCalendarController extends Controller
{
    /** How many nights the grid shows. A fortnight fits; a month needs scrolling. */
    private const SPANS = [14 => 'A fortnight', 31 => 'A month', 62 => 'Two months'];

    /** GET rates/calendar */
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
