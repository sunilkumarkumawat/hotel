<?php

namespace App\Models\Rate;

use App\Models\Master\BaseMaster;
use App\Support\RecordsActivity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named stretch of the calendar.
 *
 * "Peak", "Monsoon Offer", "Diwali". A season carries no price of its own —
 * it is a shape on the calendar that rate rules point at, so that extending
 * the peak by three days is one edit rather than one per room type.
 *
 * Seasons may overlap. A short Diwali laid over a long Peak is the normal
 * case, and `priority` decides which one a night belongs to.
 */
class RateSeason extends BaseMaster
{
    use RecordsActivity;

    protected $table = 'rate_seasons';

    /** The bands the calendar draws, in the theme's own colours. */
    public const COLOURS = [
        'plum' => 'Plum',
        'sea' => 'Sea green',
        'amber' => 'Amber',
        'rose' => 'Rose',
        'blue' => 'Blue',
        'slate' => 'Slate',
    ];

    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
        ];
    }

    public function rules(): HasMany
    {
        return $this->hasMany(RateRule::class, 'rate_season_id');
    }

    /** Seasons that cover the given night. */
    public function scopeCovering(Builder $query, string $date): Builder
    {
        return $query->whereDate('from_date', '<=', $date)->whereDate('to_date', '>=', $date);
    }

    /** Seasons with any night inside the range — what the calendar draws. */
    public function scopeOverlapping(Builder $query, string $from, string $to): Builder
    {
        return $query->whereDate('from_date', '<=', $to)->whereDate('to_date', '>=', $from);
    }

    public function covers(string $date): bool
    {
        return $this->from_date->toDateString() <= $date && $this->to_date->toDateString() >= $date;
    }

    public function getNightsAttribute(): int
    {
        return max(1, (int) CarbonImmutable::parse($this->from_date->toDateString())
            ->diffInDays(CarbonImmutable::parse($this->to_date->toDateString())) + 1);
    }

    public function getLabelAttribute(): string
    {
        return $this->name . ' (' . $this->from_date->format('d M') . ' – ' . $this->to_date->format('d M Y') . ')';
    }
}
