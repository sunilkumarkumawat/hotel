<?php

namespace App\Http\Controllers\Rate;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Master\BusinessMarket;
use App\Models\Master\Company;
use App\Models\Master\RoomType;
use App\Models\Rate\RatePlan;
use App\Models\Rate\RateRule;
use App\Models\Rate\RateSeason;
use App\Support\Rates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule as ValidationRule;
use Illuminate\View\View;

/**
 * Setting up what rooms cost: plans, seasons and the rate grid.
 *
 * Three screens, one controller, because they are one subject and nobody sets
 * up a season without setting up the rates that use it. Each screen is a list
 * you type straight into: a row at the top for a new entry, and Edit turning a
 * row into inputs where it already sits.
 *
 * The editing is done by the server. `?edit=7` re-renders row 7 as inputs,
 * Update posts it, Cancel is a link back. One page load per edit buys a screen
 * that works with scripts blocked and shows a validation error on the exact
 * row it belongs to — which matters more here than anywhere, because a rate
 * typed into the wrong row is money.
 *
 * Nothing on these screens can change what a booking was sold at. The rate is
 * copied onto the booking when it is taken; these tables only answer "what
 * should this cost", and only when somebody asks.
 */
class RateController extends Controller
{
    /* ── Plans ─────────────────────────────────────────────────────────── */

    /** GET rates/plans */
    public function plans(Request $request): View
    {
        $branchId = (int) Helper::getActiveBranchId();

        return view('rates.plans', [
            'rows' => RatePlan::query()
                ->forBranch($branchId)
                ->with(['market', 'company'])
                ->withCount('rules')
                ->orderByDesc('is_default')
                ->orderByDesc('priority')
                ->orderBy('name')
                ->get(),
            'editing' => $request->integer('edit') ?: null,
            'markets' => BusinessMarket::forBranch($branchId)->active()->orderBy('name')->pluck('name', 'id'),
            'companies' => Company::forBranch($branchId)->active()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    /** POST rates/plans  ·  PUT rates/plans/{plan} */
    public function savePlan(Request $request, ?RatePlan $plan = null): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();
        $plan = $this->mine($plan, $branchId);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:20'],
            'business_market_id' => ['nullable', 'integer', ValidationRule::exists('business_market', 'id')],
            'company_id' => ['nullable', 'integer', ValidationRule::exists('companies', 'id')],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:255'],
            'remark' => ['nullable', 'string', 'max:255'],
            'is_default' => ['nullable', 'boolean'],
            'status' => ['nullable', 'boolean'],
        ]);

        /*
         * A plan for one company is that company's rate. Letting it also name a
         * market would make "which of the two decides?" a question, and the
         * engine would have to invent an answer. It does not have to: the form
         * refuses the combination.
         */
        if (! empty($data['company_id']) && ! empty($data['business_market_id'])) {
            return back()->withInput()->with('error',
                'A plan is for one company or for one market, not both. Clear one of them.');
        }

        $data['branch_id'] = $branchId;
        $data['is_default'] = $request->boolean('is_default');
        $data['status'] = $request->boolean('status', true) ? 1 : 0;

        $plan ? $plan->update($data) : $plan = RatePlan::create($data);

        // Exactly one default, enforced here rather than hoped for.
        if ($plan->is_default) {
            RatePlan::query()
                ->forBranch($branchId)
                ->whereKeyNot($plan->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        return redirect()->route('rates.plans')->with('status', 'Rate plan "' . $plan->name . '" saved.');
    }

    /** DELETE rates/plans/{plan} */
    public function deletePlan(RatePlan $plan): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();
        $plan = $this->mine($plan, $branchId);

        if ($plan->rules()->exists()) {
            return back()->with('error',
                'This plan still has rates on it. Delete those first, or switch the plan off instead — '
                . 'an inactive plan stops being offered but keeps its history.');
        }

        $name = $plan->name;
        $plan->delete();

        return redirect()->route('rates.plans')->with('status', 'Rate plan "' . $name . '" deleted.');
    }

    /* ── Seasons ───────────────────────────────────────────────────────── */

    /** GET rates/seasons */
    public function seasons(Request $request): View
    {
        $branchId = (int) Helper::getActiveBranchId();

        return view('rates.seasons', [
            'rows' => RateSeason::query()
                ->forBranch($branchId)
                ->withCount('rules')
                ->orderBy('from_date')
                ->get(),
            'editing' => $request->integer('edit') ?: null,
        ]);
    }

    /** POST rates/seasons  ·  PUT rates/seasons/{season} */
    public function saveSeason(Request $request, ?RateSeason $season = null): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();
        $season = $this->mine($season, $branchId);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:20'],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:255'],
            'colour' => ['nullable', ValidationRule::in(array_keys(RateSeason::COLOURS))],
            'remark' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'boolean'],
        ]);

        $data['branch_id'] = $branchId;
        $data['status'] = $request->boolean('status', true) ? 1 : 0;

        $season ? $season->update($data) : $season = RateSeason::create($data);

        return redirect()->route('rates.seasons')->with('status', 'Season "' . $season->name . '" saved.');
    }

    /** DELETE rates/seasons/{season} */
    public function deleteSeason(RateSeason $season): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();
        $season = $this->mine($season, $branchId);

        if ($season->rules()->exists()) {
            return back()->with('error',
                'Rates are pointing at this season. Those rows would silently become all-year rates, '
                . 'so delete or re-date them first.');
        }

        $name = $season->name;
        $season->delete();

        return redirect()->route('rates.seasons')->with('status', 'Season "' . $name . '" deleted.');
    }

    /* ── The rate grid ─────────────────────────────────────────────────── */

    /** GET rates/rules */
    public function rules(Request $request): View
    {
        $branchId = (int) Helper::getActiveBranchId();

        $plans = RatePlan::forBranch($branchId)->orderByDesc('is_default')->orderBy('name')->get();
        $planId = $request->integer('plan') ?: (int) ($plans->firstWhere('is_default', true)?->id ?: $plans->first()?->id);

        $rows = RateRule::query()
            ->forBranch($branchId)
            ->when($planId, fn ($q) => $q->where('rate_plan_id', $planId))
            ->with(['plan', 'season', 'roomType'])
            ->orderBy('room_type_id')
            ->orderByDesc('priority')
            ->get();

        return view('rates.rules', [
            'rows' => $rows,
            'plans' => $plans,
            'planId' => $planId,
            'plan' => $plans->firstWhere('id', $planId),
            'editing' => $request->integer('edit') ?: null,
            'types' => RoomType::forBranch($branchId)->active()->orderBy('name')->get(),
            'seasons' => RateSeason::forBranch($branchId)->active()->orderBy('from_date')->get(),
        ]);
    }

    /** POST rates/rules  ·  PUT rates/rules/{rule} */
    public function saveRule(Request $request, ?RateRule $rule = null): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();
        $rule = $this->mine($rule, $branchId);

        $data = $request->validate([
            'rate_plan_id' => ['required', 'integer', ValidationRule::exists('rate_plans', 'id')],
            'room_type_id' => ['required', 'integer', ValidationRule::exists('room_type', 'id')],
            'rate_season_id' => ['nullable', 'integer', ValidationRule::exists('rate_seasons', 'id')],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'weekdays' => ['nullable', 'array'],
            'weekdays.*' => [ValidationRule::in(array_keys(RateRule::WEEKDAYS))],
            'amount' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'extra_adult' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'extra_child' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'min_stay' => ['nullable', 'integer', 'min:0', 'max:365'],
            'max_stay' => ['nullable', 'integer', 'min:0', 'max:365'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:255'],
            'remark' => ['nullable', 'string', 'max:255'],
        ]);

        /*
         * A season and a pair of dates on the same row are two answers to one
         * question. The engine would take the season and quietly ignore the
         * dates, which is exactly the kind of silence that has somebody
         * swearing at a rate sheet six months later.
         */
        if (! empty($data['rate_season_id']) && (! empty($data['from_date']) || ! empty($data['to_date']))) {
            return back()->withInput()->with('error',
                'A rate is dated either by naming a season or by its own dates — not both. Clear one.');
        }

        if (! empty($data['min_stay']) && ! empty($data['max_stay']) && $data['min_stay'] > $data['max_stay']) {
            return back()->withInput()->with('error',
                'The minimum stay is longer than the maximum, so nothing could ever be sold on this rate.');
        }

        $data['branch_id'] = $branchId;
        $data['weekdays'] = $request->filled('weekdays')
            ? implode(',', (array) $request->input('weekdays'))
            : null;

        foreach (['stop_sell', 'closed_to_arrival', 'closed_to_departure'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        $data['status'] = $request->boolean('status', true) ? 1 : 0;

        $rule ? $rule->update($data) : $rule = RateRule::create($data);

        return redirect()
            ->route('rates.rules', ['plan' => $rule->rate_plan_id])
            ->with('status', 'Rate saved.');
    }

    /** POST rates/rules/add-many — one price for several room types at once. */
    public function storeRules(Request $request): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();

        $data = $request->validate([
            'rate_plan_id' => ['required', 'integer', ValidationRule::exists('rate_plans', 'id')],
            'rate_season_id' => ['nullable', 'integer', ValidationRule::exists('rate_seasons', 'id')],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'weekdays' => ['nullable', 'array'],
            'weekdays.*' => [ValidationRule::in(array_keys(RateRule::WEEKDAYS))],
            'min_stay' => ['nullable', 'integer', 'min:0', 'max:365'],
            'amounts' => ['required', 'array'],
            'amounts.*' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ]);

        if (! empty($data['rate_season_id']) && (! empty($data['from_date']) || ! empty($data['to_date']))) {
            return back()->withInput()->with('error',
                'A rate is dated either by naming a season or by its own dates — not both. Clear one.');
        }

        /*
         * A blank price is a room type the hotel is not pricing on this plan,
         * not a free room. Rows with nothing typed in are skipped rather than
         * written as zero.
         */
        $priced = array_filter(
            $data['amounts'],
            fn ($amount) => $amount !== null && $amount !== '' && (float) $amount >= 0
        );

        if ($priced === []) {
            return back()->withInput()->with('error', 'Nothing was typed in — put a price against at least one room type.');
        }

        $typeIds = RoomType::forBranch($branchId)->active()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $made = 0;

        foreach ($priced as $typeId => $amount) {
            if (! in_array((int) $typeId, $typeIds, true)) {
                continue;
            }

            RateRule::create([
                'branch_id' => $branchId,
                'rate_plan_id' => $data['rate_plan_id'],
                'room_type_id' => (int) $typeId,
                'rate_season_id' => $data['rate_season_id'] ?? null,
                'from_date' => $data['from_date'] ?? null,
                'to_date' => $data['to_date'] ?? null,
                'weekdays' => $request->filled('weekdays') ? implode(',', (array) $request->input('weekdays')) : null,
                'amount' => round((float) $amount, 2),
                'min_stay' => (int) ($data['min_stay'] ?? 0),
                'stop_sell' => $request->boolean('stop_sell'),
                'status' => 1,
            ]);

            $made++;
        }

        return redirect()
            ->route('rates.rules', ['plan' => $data['rate_plan_id']])
            ->with('status', $made . ' ' . \Illuminate\Support\Str::plural('rate', $made) . ' added.');
    }

    /** DELETE rates/rules/{rule} */
    public function deleteRule(RateRule $rule): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();
        $rule = $this->mine($rule, $branchId);

        $planId = $rule->rate_plan_id;
        $rule->delete();

        return redirect()->route('rates.rules', ['plan' => $planId])->with('status', 'Rate deleted.');
    }

    /* ── Shared ────────────────────────────────────────────────────────── */

    /**
     * Refuse a row that belongs to another branch.
     *
     * Route model binding will happily hand over any id in the table, so this
     * is what keeps one property out of another's price list.
     *
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  T|null  $row
     * @return T|null
     */
    private function mine($row, int $branchId)
    {
        if ($row && $row->exists) {
            abort_unless($row->branch_id === null || (int) $row->branch_id === $branchId, 404);

            return $row;
        }

        return null;
    }

    /**
     * GET rates/quote — what a stay should cost, as JSON.
     *
     * The booking screen asks this the moment it knows a room type and two
     * dates. It is the same engine the rate calendar draws, so the price a
     * clerk is offered and the price on the calendar cannot disagree.
     */
    public function quote(Request $request)
    {
        $branchId = (int) Helper::getActiveBranchId();

        $data = $request->validate([
            'room_type_id' => ['required', 'integer'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date'],
            'company_id' => ['nullable', 'integer'],
            'market_id' => ['nullable', 'integer'],
            'plan_id' => ['nullable', 'integer'],
        ]);

        $quote = Rates::quote($branchId, (int) $data['room_type_id'], $data['from'], $data['to'], [
            'company_id' => $data['company_id'] ?? null,
            'market_id' => $data['market_id'] ?? null,
            'plan_id' => $data['plan_id'] ?? null,
        ]);

        return response()->json([
            'amount' => $quote['average'],
            'total' => $quote['total'],
            'nights' => $quote['count'],
            'source' => $quote['source'],
            'plan' => $quote['plan']?->name,
            'plan_id' => $quote['plan_id'],
            'sellable' => $quote['sellable'],
            'warnings' => $quote['warnings'],
            // The per-night breakdown, so the screen can show why the average
            // is not a round number.
            'breakdown' => array_map(fn (array $n) => [
                'label' => $n['label'],
                'amount' => $n['amount'],
                'source' => $n['source'],
                'season' => $n['season'],
            ], $quote['nights']),
        ]);
    }
}
