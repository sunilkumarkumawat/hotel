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

class RateController extends Controller
{
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

        if (! empty($data['company_id']) && ! empty($data['business_market_id'])) {
            return back()->withInput()->with('error',
                'A plan is for one company or for one market, not both. Clear one of them.');
        }

        $data['branch_id'] = $branchId;
        $data['is_default'] = $request->boolean('is_default');
        $data['status'] = $request->boolean('status', true) ? 1 : 0;

        $plan ? $plan->update($data) : $plan = RatePlan::create($data);

        if ($plan->is_default) {
            RatePlan::query()
                ->forBranch($branchId)
                ->whereKeyNot($plan->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        return redirect()->route('rates.plans')->with('status', 'Rate plan "' . $plan->name . '" saved.');
    }

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
            'breakdown' => array_map(fn (array $n) => [
                'label' => $n['label'],
                'amount' => $n['amount'],
                'source' => $n['source'],
                'season' => $n['season'],
            ], $quote['nights']),
        ]);
    }
}
