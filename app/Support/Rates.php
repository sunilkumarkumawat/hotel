<?php

namespace App\Support;

use App\Models\Master\RoomType;
use App\Models\Rate\RatePlan;
use App\Models\Rate\RateRule;
use App\Models\Rate\RateSeason;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * What a room costs tonight — the only place that answers it.
 *
 * The booking screen, the rate calendar and any channel we ever plug in all
 * ask this class, so none of them can drift into having an opinion of its own.
 *
 * The answer is worked out one night at a time. That is not an implementation
 * detail; it is what a rate IS. A three-night stay over a season boundary is
 * two nights at one price and one at another, and a hotel that quotes an
 * average has already lost the argument at checkout.
 *
 * ── How a night is priced ─────────────────────────────────────────────────
 *
 * 1. Pick the plan: the company's own, else its market's, else the default.
 * 2. Take every live rule on that plan for that room type.
 * 3. Throw away the ones that do not speak for this night — wrong dates,
 *    wrong season, wrong day of the week.
 * 4. Of what is left, keep the most specific (see RateRule::specificity).
 * 5. Nothing left? Fall back to the room type's own base rent, and SAY so.
 *
 * Step 5 matters more than the rest put together. A rate engine that silently
 * returns zero when nobody has loaded rates yet is a hotel giving rooms away,
 * so the fallback is loud: every answer carries where it came from.
 */
class Rates
{
    /** Where a price came from. The screen prints this; it is not decoration. */
    public const FROM_RULE = 'rule';
    public const FROM_BASE = 'base';
    public const FROM_NONE = 'none';

    /*
    |--------------------------------------------------------------------------
    | Plans
    |--------------------------------------------------------------------------
    */

    /**
     * The plans this booking could be sold on, best fit first.
     *
     * @return Collection<int, RatePlan>
     */
    public static function plansFor(
        int $branchId,
        ?int $companyId = null,
        ?int $marketId = null,
        ?string $date = null
    ): Collection {
        $date ??= today()->toDateString();

        return RatePlan::query()
            ->forBranch($branchId)
            ->active()
            ->liveOn($date)
            ->with(['market', 'company'])
            ->get()
            /*
             * The fit is worked out beside the plan rather than stuck onto it.
             * Setting it as an attribute would make it look like a column, and
             * a plan saved later would try to write it.
             */
            ->map(fn (RatePlan $plan) => ['plan' => $plan, 'fit' => $plan->fitFor($companyId, $marketId)])
            ->filter(fn (array $row) => $row['fit'] >= 0)
            /*
             * Fit first, then the hotel's own priority, then is_default as the
             * tie-breaker — so a hotel with two rack rates still gets a stable
             * answer rather than whichever the database felt like.
             */
            ->sortByDesc(fn (array $row) => [
                $row['fit'],
                (int) $row['plan']->priority,
                $row['plan']->is_default ? 1 : 0,
            ])
            ->map(fn (array $row) => $row['plan'])
            ->values();
    }

    /** The one plan a booking gets if nobody picks. */
    public static function planFor(
        int $branchId,
        ?int $companyId = null,
        ?int $marketId = null,
        ?string $date = null
    ): ?RatePlan {
        return self::plansFor($branchId, $companyId, $marketId, $date)->first();
    }

    /*
    |--------------------------------------------------------------------------
    | One night
    |--------------------------------------------------------------------------
    */

    /**
     * Price one night of one room type.
     *
     * @param  array{rules?: Collection, seasons?: Collection, base?: float}  $loaded
     *         pre-fetched rows, so a fourteen-night quote is not fourteen round trips
     * @return array{
     *     date: string, amount: float, source: string, rule: ?RateRule, plan_id: ?int,
     *     season: ?string, stop_sell: bool, min_stay: int, max_stay: int,
     *     closed_to_arrival: bool, closed_to_departure: bool,
     *     extra_adult: float, extra_child: float
     * }
     */
    public static function forNight(
        int $branchId,
        int $roomTypeId,
        string $date,
        ?int $planId = null,
        array $loaded = []
    ): array {
        $rules = $loaded['rules'] ?? self::rulesFor($branchId, $planId, $roomTypeId);
        $seasons = $loaded['seasons'] ?? self::seasonsFor($branchId, $date, $date);

        $seasonIds = $seasons
            ->filter(fn (RateSeason $s) => $s->covers($date))
            ->sortByDesc('priority')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        /*
         * Seasons overlap — a short "Diwali" laid over a long "Peak" is the
         * normal case — and two rules that each name a season are equally
         * specific, so specificity alone cannot separate them. The season's
         * own priority does, and it has to be part of the sort rather than
         * only of the list above: without this the winner is whichever row
         * the database happened to return first, which is how a hotel charges
         * peak rates over Diwali.
         *
         * Position 0 is the highest-priority season, so the rank is counted
         * back from the end to make bigger mean better.
         */
        $rank = array_flip($seasonIds);
        $seasonRank = fn (?int $id) => $id === null ? 0 : count($seasonIds) - ($rank[$id] ?? count($seasonIds));

        $winner = $rules
            ->filter(fn (RateRule $rule) => (int) $rule->room_type_id === $roomTypeId)
            ->filter(fn (RateRule $rule) => $rule->appliesOn($date, $seasonIds))
            ->sortByDesc(fn (RateRule $rule) => [
                $rule->specificity(),
                $seasonRank($rule->rate_season_id ? (int) $rule->rate_season_id : null),
            ])
            ->first();

        $empty = [
            'date' => $date,
            'plan_id' => $planId,
            'season' => null,
            'stop_sell' => false,
            'min_stay' => 0,
            'max_stay' => 0,
            'closed_to_arrival' => false,
            'closed_to_departure' => false,
            'extra_adult' => 0.0,
            'extra_child' => 0.0,
            'rule' => null,
        ];

        if ($winner) {
            return array_merge($empty, [
                'amount' => round((float) $winner->amount, 2),
                'source' => self::FROM_RULE,
                'rule' => $winner,
                'plan_id' => (int) $winner->rate_plan_id,
                'season' => $winner->season?->name,
                'stop_sell' => (bool) $winner->stop_sell,
                'min_stay' => (int) $winner->min_stay,
                'max_stay' => (int) $winner->max_stay,
                'closed_to_arrival' => (bool) $winner->closed_to_arrival,
                'closed_to_departure' => (bool) $winner->closed_to_departure,
                'extra_adult' => round((float) $winner->extra_adult, 2),
                'extra_child' => round((float) $winner->extra_child, 2),
            ]);
        }

        // Nobody has priced this night. Fall back, and be honest about it.
        $base = $loaded['base'] ?? (float) (RoomType::find($roomTypeId)?->base_rent ?? 0);

        return array_merge($empty, [
            'amount' => round($base, 2),
            'source' => $base > 0 ? self::FROM_BASE : self::FROM_NONE,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | A whole stay
    |--------------------------------------------------------------------------
    */

    /**
     * Price a stay, night by night.
     *
     * `$from` is the arrival and `$to` the departure, so the nights charged are
     * from the arrival up to but NOT including the departure — a guest leaving
     * on the 9th does not pay for the night of the 9th.
     *
     * @param  array{company_id?: ?int, market_id?: ?int, plan_id?: ?int}  $context
     * @return array{
     *     plan: ?RatePlan, plan_id: ?int, nights: list<array<string, mixed>>,
     *     total: float, average: float, count: int, source: string,
     *     warnings: list<string>, sellable: bool
     * }
     */
    public static function quote(
        int $branchId,
        int $roomTypeId,
        string $from,
        string $to,
        array $context = []
    ): array {
        $start = CarbonImmutable::parse($from);
        $end = CarbonImmutable::parse($to);

        // A stay that has not reached its second day is still one night.
        if ($end <= $start) {
            $end = $start->addDay();
        }

        $plan = isset($context['plan_id']) && $context['plan_id']
            ? RatePlan::forBranch($branchId)->find($context['plan_id'])
            : self::planFor($branchId, $context['company_id'] ?? null, $context['market_id'] ?? null, $from);

        $planId = $plan?->id ? (int) $plan->id : null;

        $loaded = [
            'rules' => self::rulesFor($branchId, $planId, $roomTypeId),
            'seasons' => self::seasonsFor($branchId, $start->toDateString(), $end->toDateString()),
            'base' => (float) (RoomType::find($roomTypeId)?->base_rent ?? 0),
        ];

        $nights = [];
        $total = 0.0;

        for ($night = $start; $night < $end; $night = $night->addDay()) {
            $row = self::forNight($branchId, $roomTypeId, $night->toDateString(), $planId, $loaded);

            $row['label'] = $night->format('D, d M');
            $row['weekend'] = in_array($night->format('D'), ['Fri', 'Sat'], true);

            $nights[] = $row;
            $total += $row['amount'];
        }

        $count = max(1, count($nights));

        return [
            'plan' => $plan,
            'plan_id' => $planId,
            'nights' => $nights,
            'total' => round($total, 2),
            'average' => round($total / $count, 2),
            'count' => count($nights),
            'source' => self::sourceOf($nights),
            'warnings' => self::warningsFor($nights),
            'sellable' => ! collect($nights)->contains(fn (array $n) => $n['stop_sell']),
        ];
    }

    /**
     * Where a whole quote's prices came from.
     *
     * Mixed is its own answer rather than being rounded to one of the others:
     * "three nights priced, one falling back to base rent" is exactly the thing
     * a manager wants to see before the rate goes out.
     */
    private static function sourceOf(array $nights): string
    {
        $sources = collect($nights)->pluck('source')->unique();

        return match (true) {
            $sources->count() > 1 => 'mixed',
            default => (string) ($sources->first() ?? self::FROM_NONE),
        };
    }

    /**
     * The things somebody should be told before they take the booking.
     *
     * Deliberately sentences rather than codes — they are shown to a clerk,
     * not parsed.
     *
     * @return list<string>
     */
    private static function warningsFor(array $nights): array
    {
        if ($nights === []) {
            return [];
        }

        $out = [];
        $count = count($nights);

        $stopped = collect($nights)->filter(fn (array $n) => $n['stop_sell']);

        if ($stopped->isNotEmpty()) {
            $out[] = 'Not for sale on ' . $stopped->pluck('label')->join(', ', ' and ')
                . '. The rate plan has these nights closed.';
        }

        $longest = collect($nights)->max('min_stay');

        if ($longest > $count) {
            $out[] = 'This rate needs a minimum of ' . $longest . ' nights; the stay is '
                . $count . '. Either extend it or price it by hand.';
        }

        $cap = collect($nights)->filter(fn (array $n) => $n['max_stay'] > 0)->min('max_stay');

        if ($cap && $cap < $count) {
            $out[] = 'This rate allows at most ' . $cap . ' nights; the stay is ' . $count . '.';
        }

        if ($nights[0]['closed_to_arrival']) {
            $out[] = 'No arrivals on ' . $nights[0]['label'] . ' on this rate.';
        }

        if ($nights[$count - 1]['closed_to_departure']) {
            $out[] = 'No departures the morning after ' . $nights[$count - 1]['label'] . ' on this rate.';
        }

        $fallback = collect($nights)->filter(fn (array $n) => $n['source'] !== self::FROM_RULE);

        if ($fallback->count() === $count) {
            $out[] = 'No rate is loaded for these nights — the room type\'s base rent is being used.';
        } elseif ($fallback->isNotEmpty()) {
            $out[] = $fallback->count() . ' of ' . $count . ' nights have no rate loaded and fall back to '
                . 'the base rent: ' . $fallback->pluck('label')->join(', ', ' and ') . '.';
        }

        return $out;
    }

    /*
    |--------------------------------------------------------------------------
    | The rate calendar
    |--------------------------------------------------------------------------
    */

    /**
     * Every room type's price for every night in a range — the grid.
     *
     * One pass over the rules for all room types at once, because the calendar
     * is the screen most likely to be opened on a fortnight of a twelve-type
     * hotel and a query per cell would be 168 of them.
     *
     * @return array{
     *     plan: ?RatePlan, days: list<array<string, mixed>>,
     *     types: list<array{id: int, name: string, base: float, cells: list<array<string, mixed>>}>,
     *     seasons: Collection
     * }
     */
    public static function calendar(int $branchId, string $from, int $nights = 14, ?int $planId = null): array
    {
        $start = CarbonImmutable::parse($from);
        $nights = max(1, min(62, $nights));
        $end = $start->addDays($nights - 1);

        $plan = $planId
            ? RatePlan::forBranch($branchId)->find($planId)
            : self::planFor($branchId, null, null, $start->toDateString());

        $planId = $plan?->id ? (int) $plan->id : null;

        $types = RoomType::query()->forBranch($branchId)->active()->orderBy('name')->get();
        $rules = self::rulesFor($branchId, $planId);
        $seasons = self::seasonsFor($branchId, $start->toDateString(), $end->toDateString());

        $days = [];

        for ($i = 0; $i < $nights; $i++) {
            $day = $start->addDays($i);
            $date = $day->toDateString();

            $covering = $seasons->filter(fn (RateSeason $s) => $s->covers($date))->sortByDesc('priority');

            $days[] = [
                'date' => $date,
                'day' => $day->format('D'),
                'label' => $day->format('d M'),
                'weekend' => in_array($day->format('D'), ['Fri', 'Sat'], true),
                'season' => $covering->first()?->name,
                'colour' => $covering->first()?->colour,
            ];
        }

        $rows = [];

        foreach ($types as $type) {
            $loaded = ['rules' => $rules, 'seasons' => $seasons, 'base' => (float) $type->base_rent];
            $cells = [];

            foreach ($days as $day) {
                $cell = self::forNight($branchId, (int) $type->id, $day['date'], $planId, $loaded);

                $cells[] = [
                    'date' => $day['date'],
                    'amount' => $cell['amount'],
                    'source' => $cell['source'],
                    'stop_sell' => $cell['stop_sell'],
                    'min_stay' => $cell['min_stay'],
                    'weekend' => $day['weekend'],
                ];
            }

            $rows[] = [
                'id' => (int) $type->id,
                'name' => $type->name,
                'base' => (float) $type->base_rent,
                'cells' => $cells,
            ];
        }

        return ['plan' => $plan, 'days' => $days, 'types' => $rows, 'seasons' => $seasons];
    }

    /*
    |--------------------------------------------------------------------------
    | Loading
    |--------------------------------------------------------------------------
    */

    /** @return Collection<int, RateRule> */
    public static function rulesFor(int $branchId, ?int $planId, ?int $roomTypeId = null): Collection
    {
        return RateRule::query()
            ->forBranch($branchId)
            ->active()
            ->when($planId, fn ($q) => $q->where('rate_plan_id', $planId))
            // No plan means no rates, not every hotel's rates.
            ->when(! $planId, fn ($q) => $q->whereRaw('1 = 0'))
            ->when($roomTypeId, fn ($q) => $q->where('room_type_id', $roomTypeId))
            ->with('season')
            ->get();
    }

    /** @return Collection<int, RateSeason> */
    public static function seasonsFor(int $branchId, string $from, string $to): Collection
    {
        return RateSeason::query()
            ->forBranch($branchId)
            ->active()
            ->overlapping($from, $to)
            ->orderByDesc('priority')
            ->get();
    }

    /**
     * A one-line answer for the booking screen's rate box.
     *
     * @return array{amount: float, total: float, nights: int, source: string, plan: ?string, note: ?string}
     */
    public static function suggest(
        int $branchId,
        int $roomTypeId,
        string $from,
        string $to,
        array $context = []
    ): array {
        $quote = self::quote($branchId, $roomTypeId, $from, $to, $context);

        return [
            'amount' => $quote['average'],
            'total' => $quote['total'],
            'nights' => $quote['count'],
            'source' => $quote['source'],
            'plan' => $quote['plan']?->name,
            'note' => $quote['warnings'][0] ?? null,
        ];
    }
}
