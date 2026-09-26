<?php

namespace App\Models\Master;

use App\Models\FrontOffice\CheckIn;
use App\Support\GuestCrm;
use App\Support\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Guest extends BaseMaster
{
    use RecordsActivity;

    protected $table = 'guests';

    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'anniversary' => 'date',
            'first_stay_at' => 'date',
            'last_stay_at' => 'date',
            'blacklisted_on' => 'date',
            'totals_at' => 'datetime',
            'is_blacklisted' => 'boolean',
            'total_spend' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class, 'guest_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(\App\Models\Crm\GuestNote::class, 'guest_id');
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(\App\Models\Crm\GuestFeedback::class, 'guest_id');
    }

    public function getNameAttribute(): string
    {
        return trim(($this->title ? $this->title . ' ' : '') . $this->first_name . ' ' . $this->last_name);
    }

    public function getInitialsAttribute(): string
    {
        return Str::upper(Str::substr($this->first_name, 0, 1) . Str::substr($this->last_name ?: '', 0, 1));
    }

    /*
    |--------------------------------------------------------------------------
    | Who they are to the hotel
    |--------------------------------------------------------------------------
    */

    public function getTierLabelAttribute(): string
    {
        return GuestCrm::tierLabel($this->tier);
    }

    /** Somebody who has been here before — the reason CRM exists. */
    public function isReturning(): bool
    {
        return (int) $this->stays > 1;
    }

    /**
     * Have the cached totals been worked out since the last stay ended?
     *
     * Shown on the profile rather than hidden, so a figure that is behind is
     * visibly behind instead of quietly wrong.
     */
    public function totalsAreStale(): bool
    {
        if (! $this->totals_at) {
            return (int) $this->stays > 0;
        }

        return $this->last_stay_at !== null
            && $this->totals_at->lt($this->last_stay_at->endOfDay());
    }

    public function scopeReturning(Builder $query): Builder
    {
        return $query->where('stays', '>', 1);
    }

    public function scopeBlacklisted(Builder $query): Builder
    {
        return $query->where('is_blacklisted', true);
    }

    public function scopeOfTier(Builder $query, ?string $tier): Builder
    {
        return $query->when($tier, fn (Builder $q) => $q->where('tier', $tier));
    }

    /** Name, mobile or email — what the Customer Search box looks through. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, fn (Builder $q) => $q->where(function (Builder $q) use ($term) {
            $q->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('mobile', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%");
        }));
    }

    /**
     * Columns that move on their own.
     *
     * These are written by the code that keeps them up to date rather than
     * by a person, so logging them would bury the changes somebody actually
     * made under a drift of housekeeping.
     */
    public function auditHidden(): array
    {
        return ['stays', 'nights', 'total_spend', 'first_stay_at', 'last_stay_at', 'totals_at', 'tier', 'loyalty_points'];
    }
}
