<?php

namespace App\Models\Rate;

use App\Models\Master\BaseMaster;
use App\Models\Master\RoomType;
use App\Support\RecordsActivity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a price list: this plan, this room type, these nights, this much.
 *
 * A rule is dated in one of three ways, and the engine reads them in that
 * order of specificity:
 *
 *   by season   — "Peak"
 *   by dates    — 20 Dec to 2 Jan
 *   by nothing  — all year, the row that catches everything else
 *
 * `weekdays` narrows any of those to particular nights ('fri,sat'), which is
 * how a weekend rate is written without drawing fifty-two seasons.
 *
 * Min-stay and stop-sell live on the same row as the price because they are
 * said about the same stretch of dates: "over Diwali it is nine thousand, and
 * not below three nights."
 */
class RateRule extends BaseMaster
{
    use RecordsActivity;

    protected $table = 'rate_rules';

    /** Monday first, the way a rate sheet is read. */
    public const WEEKDAYS = [
        'mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed', 'thu' => 'Thu',
        'fri' => 'Fri', 'sat' => 'Sat', 'sun' => 'Sun',
    ];

    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
            'amount' => 'decimal:2',
            'extra_adult' => 'decimal:2',
            'extra_child' => 'decimal:2',
            'stop_sell' => 'boolean',
            'closed_to_arrival' => 'boolean',
            'closed_to_departure' => 'boolean',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(RatePlan::class, 'rate_plan_id');
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(RateSeason::class, 'rate_season_id');
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class, 'room_type_id');
    }

    /**
     * A rule row has no `name`, so the shared master search would look for a
     * column that is not there. Search what somebody would actually type: the
     * plan, the room type, or the note.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, fn (Builder $q) => $q->where(function (Builder $q) use ($term) {
            $q->where('remark', 'like', "%{$term}%")
                ->orWhereHas('plan', fn (Builder $p) => $p->where('name', 'like', "%{$term}%"))
                ->orWhereHas('roomType', fn (Builder $t) => $t->where('name', 'like', "%{$term}%"));
        }));
    }

    /*
    |--------------------------------------------------------------------------
    | Does this rule speak for a given night?
    |--------------------------------------------------------------------------
    */

    /** @param  array<int, RateSeason>  $seasons  seasons covering the night, by id */
    public function appliesOn(string $date, array $seasonIds = []): bool
    {
        if (! $this->matchesWeekday($date)) {
            return false;
        }

        if ($this->rate_season_id) {
            return in_array((int) $this->rate_season_id, $seasonIds, true);
        }

        if ($this->from_date && $this->from_date->toDateString() > $date) {
            return false;
        }

        if ($this->to_date && $this->to_date->toDateString() < $date) {
            return false;
        }

        return true;
    }

    public function matchesWeekday(string $date): bool
    {
        $days = $this->weekdayList();

        if ($days === []) {
            return true;
        }

        return in_array(strtolower(CarbonImmutable::parse($date)->format('D')), $days, true);
    }

    /** @return list<string> */
    public function weekdayList(): array
    {
        return array_values(array_filter(
            array_map('trim', explode(',', strtolower((string) $this->weekdays))),
            fn (string $d) => isset(self::WEEKDAYS[$d])
        ));
    }

    /**
     * How specific this rule is — the engine keeps the highest.
     *
     * Dates beat a season because a hotel that typed exact dates meant those
     * exact dates; a season is the broader statement it sits inside. Weekdays
     * add on top of either, since "Saturdays in the Peak" is narrower than
     * "the Peak". `priority` is the hotel's own thumb on the scale and is
     * added last so it can always win.
     */
    public function specificity(): int
    {
        $score = 0;

        if ($this->from_date || $this->to_date) {
            $score += 40;
        } elseif ($this->rate_season_id) {
            $score += 30;
        }

        if ($this->weekdayList() !== []) {
            $score += 15;
        }

        return $score + (int) $this->priority;
    }

    /** What the row says it covers, in words, for the list screen. */
    public function getWhenAttribute(): string
    {
        $when = match (true) {
            (bool) $this->rate_season_id => $this->season?->name ?: 'A season',
            (bool) ($this->from_date || $this->to_date) => trim(
                ($this->from_date?->format('d M Y') ?? 'any date')
                . ' – ' . ($this->to_date?->format('d M Y') ?? 'onwards')
            ),
            default => 'All year',
        };

        $days = $this->weekdayList();

        if ($days !== []) {
            $when .= ' · ' . implode(', ', array_map(fn ($d) => self::WEEKDAYS[$d], $days));
        }

        return $when;
    }
}
