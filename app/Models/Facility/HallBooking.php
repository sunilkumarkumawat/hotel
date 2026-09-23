<?php

namespace App\Models\Facility;

use App\Models\FrontOffice\CheckIn;
use App\Models\Master\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A hall, held from one moment to another.
 *
 * Stored as date + time rather than one datetime so the calendar can filter and
 * group by the day without parsing, and so a reception that runs past midnight
 * still belongs to the day it started on — which is how a banquet manager
 * thinks about it and how the diary on the wall is written.
 */
class HallBooking extends Model
{
    protected $table = 'hall_bookings';

    protected $guarded = ['id'];

    public const STATUSES = [
        'tentative' => 'Tentative',
        'confirmed' => 'Confirmed',
        'in_use' => 'Running',
        'completed' => 'Finished',
        'cancelled' => 'Cancelled',
    ];

    public const RATE_TYPES = [
        'event' => 'For the whole event',
        'day' => 'Per day',
        'hour' => 'Per hour',
    ];

    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
            'rate' => 'decimal:2',
            'qty' => 'decimal:2',
            'discount' => 'decimal:2',
            'amount' => 'decimal:2',
            'tax_percent' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'advance' => 'decimal:2',
            'post_to_room' => 'boolean',
        ];
    }

    public function hall(): BelongsTo
    {
        return $this->belongsTo(Hall::class, 'hall_id');
    }

    public function stay(): BelongsTo
    {
        return $this->belongsTo(CheckIn::class, 'check_in_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(HallBookingItem::class, 'hall_booking_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    /**
     * Bookings that hold the hall.
     *
     * A tentative booking holds it too — that is the entire point of writing
     * one down, and a banquet manager who is told a date is free because the
     * hold was "only tentative" has been told something useless.
     */
    public function scopeHolding(Builder $query): Builder
    {
        return $query->where('status', '!=', 'cancelled');
    }

    public function scopeForBranch(Builder $query, ?int $branchId = null): Builder
    {
        return $query->where('branch_id', $branchId ?? (int) \App\Helpers\Helper::getActiveBranchId());
    }

    public function isClosed(): bool
    {
        return in_array($this->status, ['completed', 'cancelled'], true);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function getStatusToneAttribute(): string
    {
        return match ($this->status) {
            'tentative' => 'warning',
            'in_use' => 'info',
            'completed' => 'success',
            'cancelled' => 'danger',
            default => 'primary',
        };
    }

    /** What is still owed after the advance — what the banquet manager chases. */
    public function getBalanceAttribute(): float
    {
        return round((float) $this->total_amount - (float) $this->advance, 2);
    }

    public function startsAt(): string
    {
        return $this->from_date->toDateString() . ' ' . substr((string) $this->from_time, 0, 8);
    }

    public function endsAt(): string
    {
        return $this->to_date->toDateString() . ' ' . substr((string) $this->to_time, 0, 8);
    }
}
