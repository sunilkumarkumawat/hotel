<?php

namespace App\Support;

use App\Models\Master\Guest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Who the guest is, worked out from what the hotel already knows.
 *
 * A property holds all of this the day somebody checks out and then loses it:
 * the stays are in one table, the money in another, the complaint in
 * somebody's memory. This class puts it back together and keeps it together.
 *
 * ── The one judgement call in here ────────────────────────────────────────
 *
 * Tiers are worked out from stays and spend, and the thresholds are the
 * hotel's to change (config/pms.php). What is NOT negotiable is that a tier is
 * always derived: nothing writes a tier that the figures do not support, so a
 * guest can never be Platinum because somebody clicked it once in 2023. The
 * column exists so a list can sort on it, and it is rebuilt from the numbers.
 */
class GuestCrm
{
    /**
     * Tiers, richest first — the order matters, because the first one a guest
     * qualifies for is the one they get.
     *
     * Stated in both stays and spend, and either qualifies. A guest on their
     * twelfth short visit and a guest on their second wedding are both worth
     * recognising, and a rule that only counted rupees would miss the first.
     */
    public const TIERS = [
        'platinum' => ['label' => 'Platinum', 'stays' => 15, 'spend' => 500000],
        'gold' => ['label' => 'Gold', 'stays' => 8, 'spend' => 200000],
        'silver' => ['label' => 'Silver', 'stays' => 3, 'spend' => 60000],
        'guest' => ['label' => 'Guest', 'stays' => 0, 'spend' => 0],
    ];

    /** Points earned per hundred rupees of room revenue. */
    public const POINTS_PER_HUNDRED = 1;

    /*
    |--------------------------------------------------------------------------
    | The figures behind a profile
    |--------------------------------------------------------------------------
    */

    /**
     * Recount everything about a guest, from the bookings up.
     *
     * Called when a stay closes, and on demand from the profile screen. It is
     * a full recount rather than an increment on purpose: an increment is only
     * correct if it has never been missed, and this one is allowed to be run
     * twice without doing any harm.
     *
     * @return array<string, mixed> what was worked out
     */
    public static function recount(Guest $guest): array
    {
        $stays = DB::table('check_ins')
            ->where('guest_id', $guest->id)
            ->where('status', '!=', 'cancelled')
            ->selectRaw('COUNT(*) as stays, MIN(checkin_date) as first_stay, MAX(checkin_date) as last_stay, '
                . 'COALESCE(SUM(DATEDIFF(COALESCE(actual_checkout_date, expected_checkout_date), checkin_date)), 0) as nights')
            ->first();

        /*
         * Spend is what was BILLED, not what was charged to a folio: an open
         * folio is a stay in progress and its figures are still moving. A
         * guest's lifetime value should not go up and down while they are
         * asleep upstairs.
         */
        $spend = (float) DB::table('bills')
            ->where('guest_id', $guest->id)
            ->where('status', '!=', 'cancelled')
            ->sum('net_amount');

        $points = (int) DB::table('loyalty_entries')->where('guest_id', $guest->id)->sum('points');

        $figures = [
            'stays' => (int) ($stays->stays ?? 0),
            'nights' => max(0, (int) ($stays->nights ?? 0)),
            'total_spend' => round($spend, 2),
            'first_stay_at' => $stays->first_stay ?? null,
            'last_stay_at' => $stays->last_stay ?? null,
            'loyalty_points' => $points,
            'totals_at' => now(),
        ];

        $figures['tier'] = self::tierFor($figures['stays'], $figures['total_spend']);

        $guest->forceFill($figures)->save();

        return $figures;
    }

    /**
     * Which tier these figures earn.
     *
     * Either test qualifies, and the richest tier a guest reaches is the one
     * they keep. Never stored without being worked out first.
     */
    public static function tierFor(int $stays, float $spend): string
    {
        foreach (self::TIERS as $key => $tier) {
            if ($stays >= $tier['stays'] || $spend >= $tier['spend']) {
                return $key;
            }
        }

        return 'guest';
    }

    public static function tierLabel(?string $tier): string
    {
        return self::TIERS[$tier]['label'] ?? 'Guest';
    }

    /**
     * How far through the current tier a guest is, as a percentage.
     *
     * Shown on the profile because "four more stays to Gold" is a thing a
     * receptionist can say out loud, and a bare tier badge is not.
     *
     * @return array{next: ?string, label: ?string, stays_to_go: int, spend_to_go: float, percent: float}
     */
    public static function progress(int $stays, float $spend): array
    {
        $keys = array_keys(self::TIERS);           // platinum … guest
        $current = self::tierFor($stays, $spend);
        $position = array_search($current, $keys, true);

        // Already at the top: nothing to be next.
        if ($position === 0) {
            return ['next' => null, 'label' => null, 'stays_to_go' => 0, 'spend_to_go' => 0.0, 'percent' => 100.0];
        }

        $next = $keys[$position - 1];
        $target = self::TIERS[$next];

        $byStays = $target['stays'] > 0 ? min(1, $stays / $target['stays']) : 0;
        $bySpend = $target['spend'] > 0 ? min(1, $spend / $target['spend']) : 0;

        return [
            'next' => $next,
            'label' => $target['label'],
            'stays_to_go' => max(0, $target['stays'] - $stays),
            'spend_to_go' => round(max(0, $target['spend'] - $spend), 2),
            // Whichever route they are further along — the one they will
            // arrive by, and the only honest number to show them.
            'percent' => round(max($byStays, $bySpend) * 100, 1),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Loyalty
    |--------------------------------------------------------------------------
    */

    /**
     * Award the points a stay earned.
     *
     * Idempotent per stay: the ledger is keyed on the check-in, so a checkout
     * screen opened twice does not pay twice.
     */
    public static function awardStay(int $guestId, int $checkInId, float $roomRevenue, ?int $branchId = null): ?int
    {
        $points = (int) floor($roomRevenue / 100 * self::POINTS_PER_HUNDRED);

        if ($points <= 0 || ! $guestId) {
            return null;
        }

        $already = DB::table('loyalty_entries')
            ->where('guest_id', $guestId)
            ->where('check_in_id', $checkInId)
            ->where('kind', 'earned')
            ->exists();

        if ($already) {
            return null;
        }

        DB::table('loyalty_entries')->insert([
            'branch_id' => $branchId,
            'guest_id' => $guestId,
            'check_in_id' => $checkInId,
            'entry_date' => today()->toDateString(),
            'kind' => 'earned',
            'points' => $points,
            'reason' => 'Earned on stay — ' . number_format($roomRevenue, 2) . ' of room revenue',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $points;
    }

    /** The ledger behind a balance, newest first. */
    public static function ledger(int $guestId, int $limit = 50): Collection
    {
        return collect(DB::table('loyalty_entries')
            ->where('guest_id', $guestId)
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get());
    }

    /*
    |--------------------------------------------------------------------------
    | A guest's history
    |--------------------------------------------------------------------------
    */

    /**
     * Every stay this guest has had, with what it came to.
     *
     * @return Collection<int, object>
     */
    public static function stays(int $guestId, int $limit = 50): Collection
    {
        return collect(DB::table('check_ins as ci')
            ->leftJoin('rooms as r', 'r.id', '=', 'ci.room_id')
            ->leftJoin('bills as b', function ($join) {
                $join->on('b.check_in_id', '=', 'ci.id')->where('b.status', '!=', 'cancelled');
            })
            ->where('ci.guest_id', $guestId)
            ->where('ci.status', '!=', 'cancelled')
            ->orderByDesc('ci.checkin_date')
            ->limit($limit)
            ->get([
                'ci.id', 'ci.folio_no', 'ci.checkin_date', 'ci.expected_checkout_date',
                'ci.actual_checkout_date', 'ci.status', 'ci.room_rent',
                'r.room_no', 'b.bill_no', 'b.net_amount', 'b.bill_date',
            ]));
    }

    /**
     * Guests with a birthday or anniversary in the next few days.
     *
     * Matched on the day and month, never the year, which is the whole trick:
     * a birthday is an anniversary of a date, and a plain BETWEEN on the
     * stored date would match nobody after the first year.
     *
     * @return Collection<int, object>
     */
    public static function occasions(?int $branchId, int $days = 14): Collection
    {
        $today = CarbonImmutable::parse(today()->toDateString());

        $wanted = [];

        for ($i = 0; $i < max(1, $days); $i++) {
            $day = $today->addDays($i);
            $wanted[$day->format('m-d')] = ['date' => $day->toDateString(), 'in' => $i];
        }

        $rows = collect(DB::table('guests')
            ->where('status', 1)
            ->when($branchId, fn ($q) => $q->where(fn ($w) => $w->whereNull('branch_id')->orWhere('branch_id', $branchId)))
            ->where(fn ($q) => $q->whereNotNull('dob')->orWhereNotNull('anniversary'))
            ->get(['id', 'title', 'first_name', 'last_name', 'mobile', 'email', 'dob', 'anniversary', 'tier', 'stays']));

        $out = collect();

        foreach ($rows as $guest) {
            foreach (['dob' => 'Birthday', 'anniversary' => 'Anniversary'] as $field => $label) {
                if (! $guest->{$field}) {
                    continue;
                }

                $key = CarbonImmutable::parse($guest->{$field})->format('m-d');

                if (! isset($wanted[$key])) {
                    continue;
                }

                $out->push((object) [
                    'guest' => $guest,
                    'occasion' => $label,
                    'on' => $wanted[$key]['date'],
                    'in_days' => $wanted[$key]['in'],
                    'name' => trim(($guest->title ? $guest->title . ' ' : '') . $guest->first_name . ' ' . $guest->last_name),
                ]);
            }
        }

        return $out->sortBy('in_days')->values();
    }

    /*
    |--------------------------------------------------------------------------
    | Feedback
    |--------------------------------------------------------------------------
    */

    /**
     * Make (or find) the feedback row for a stay and hand back its link.
     *
     * The row is created when the link is sent rather than when the guest
     * answers, so "we asked and they did not reply" is a fact the hotel holds
     * rather than a silence it has to interpret.
     */
    public static function feedbackFor(int $branchId, int $checkInId, ?int $guestId = null): object
    {
        $row = DB::table('guest_feedback')->where('check_in_id', $checkInId)->first();

        if ($row) {
            return $row;
        }

        $token = Str::random(40);

        DB::table('guest_feedback')->insert([
            'branch_id' => $branchId,
            'guest_id' => $guestId,
            'check_in_id' => $checkInId,
            'token' => $token,
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('guest_feedback')->where('token', $token)->first();
    }

    /**
     * The scores, and the thing a manager actually wants from them.
     *
     * Averages are shown to one decimal because a hotel's overall score moves
     * in tenths and rounding it to whole numbers hides every improvement.
     *
     * `detractors` is the count at or below 3 — the rows somebody has to ring
     * up. It is the only figure on the screen that is a to-do list.
     *
     * @return array<string, mixed>
     */
    public static function feedbackSummary(int $branchId, string $from, string $to): array
    {
        $rows = collect(DB::table('guest_feedback')
            ->where('branch_id', $branchId)
            ->whereNotNull('answered_at')
            ->whereBetween('answered_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->get());

        $avg = function (string $field) use ($rows) {
            $scored = $rows->filter(fn ($r) => $r->{$field} !== null);

            return $scored->isEmpty() ? null : round($scored->sum($field) / $scored->count(), 1);
        };

        $sent = (int) DB::table('guest_feedback')
            ->where('branch_id', $branchId)
            ->whereBetween('sent_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->count();

        return [
            'answered' => $rows->count(),
            'sent' => $sent,
            'rate' => $sent > 0 ? round($rows->count() / $sent * 100, 1) : 0.0,
            'overall' => $avg('overall'),
            'scores' => [
                'Room' => $avg('room'),
                'Cleanliness' => $avg('cleanliness'),
                'Staff' => $avg('staff'),
                'Food' => $avg('food'),
                'Value' => $avg('value'),
            ],
            // A 4 is not a complaint, but it is not a five either — "positive"
            // here means the guest was happy, which starts at 4.
            'positive' => $rows->filter(fn ($r) => $r->overall !== null && $r->overall >= 4)->count(),
            'detractors' => $rows->filter(fn ($r) => $r->overall !== null && $r->overall <= 3)->count(),
            'unhandled' => $rows->filter(fn ($r) => $r->overall !== null && $r->overall <= 3 && ! $r->handled_at)->count(),
            'would_return' => $rows->filter(fn ($r) => $r->would_return === 1 || $r->would_return === true)->count(),
        ];
    }
}
