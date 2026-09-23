<?php

namespace App\Models\Rate;

use App\Models\Master\BaseMaster;
use App\Models\Master\BusinessMarket;
use App\Models\Master\Company;
use App\Support\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A price list with a name: Rack, Corporate, OTA, Weekend Special.
 *
 * A plan says WHO a set of rates is for. Left open it is the rack rate —
 * anybody can be sold it. Tied to a market it is that channel's rate; tied to
 * a company it is that company's negotiated rate and nobody else's, which is
 * the whole point of having negotiated it.
 *
 * Exactly one plan should be the default. That is the one a booking gets when
 * it names no company and no market, and it is what keeps the front desk from
 * having to know any of this.
 */
class RatePlan extends BaseMaster
{
    use RecordsActivity;

    protected $table = 'rate_plans';

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'valid_from' => 'date',
            'valid_to' => 'date',
        ];
    }

    public function rules(): HasMany
    {
        return $this->hasMany(RateRule::class, 'rate_plan_id');
    }

    public function market(): BelongsTo
    {
        return $this->belongsTo(BusinessMarket::class, 'business_market_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /** Plans in force on the given date — a contract that has expired is not. */
    public function scopeLiveOn(Builder $query, string $date): Builder
    {
        return $query
            ->where(fn (Builder $q) => $q->whereNull('valid_from')->orWhereDate('valid_from', '<=', $date))
            ->where(fn (Builder $q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $date));
    }

    /** Anybody may be sold this one. */
    public function isPublic(): bool
    {
        return ! $this->business_market_id && ! $this->company_id;
    }

    /**
     * How well this plan fits a booking — bigger is more specific.
     *
     * A company's own rate beats its market's rate, which beats the rack rate.
     * Returned as a number rather than decided with ifs because it is also how
     * the list is sorted on screen, and the two must agree.
     */
    public function fitFor(?int $companyId, ?int $marketId): int
    {
        if ($this->company_id) {
            return $companyId && (int) $this->company_id === $companyId ? 300 : -1;
        }

        if ($this->business_market_id) {
            return $marketId && (int) $this->business_market_id === $marketId ? 200 : -1;
        }

        return 100;
    }

    public function getAudienceAttribute(): string
    {
        if ($this->company_id) {
            return $this->company?->name ? 'Only ' . $this->company->name : 'One company';
        }

        if ($this->business_market_id) {
            return $this->market?->name ? $this->market->name . ' bookings' : 'One market';
        }

        return 'Anybody';
    }
}
