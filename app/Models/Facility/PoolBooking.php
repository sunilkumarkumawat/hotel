<?php

namespace App\Models\Facility;

use App\Models\FrontOffice\CheckIn;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Somebody has the pool, between these two times, on this day.
 *
 * A cancelled booking holds nothing — it stays on the list so the desk can see
 * it happened, and every clash test leaves it out through {@see scopeHolding()}.
 */
class PoolBooking extends Model
{
    protected $table = 'pool_bookings';

    protected $guarded = ['id'];

    public const STATUSES = [
        'booked' => 'Booked',
        'in_use' => 'In the pool',
        'completed' => 'Finished',
        'cancelled' => 'Cancelled',
    ];

    protected function casts(): array
    {
        return [
            'booking_date' => 'date',
            'adult_rate' => 'decimal:2',
            'child_rate' => 'decimal:2',
            'discount' => 'decimal:2',
            'amount' => 'decimal:2',
            'tax_percent' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'post_to_room' => 'boolean',
        ];
    }

    public function pool(): BelongsTo
    {
        return $this->belongsTo(Pool::class, 'pool_id');
    }

    public function stay(): BelongsTo
    {
        return $this->belongsTo(CheckIn::class, 'check_in_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    /** Bookings that actually hold the pool — everything except a cancellation. */
    public function scopeHolding(Builder $query): Builder
    {
        return $query->where('status', '!=', 'cancelled');
    }

    public function scopeForBranch(Builder $query, ?int $branchId = null): Builder
    {
        return $query->where('branch_id', $branchId ?? (int) \App\Helpers\Helper::getActiveBranchId());
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isClosed(): bool
    {
        return in_array($this->status, ['completed', 'cancelled'], true);
    }

    public function getPaxAttribute(): int
    {
        return (int) $this->adults + (int) $this->children;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function getStatusToneAttribute(): string
    {
        return match ($this->status) {
            'in_use' => 'info',
            'completed' => 'success',
            'cancelled' => 'danger',
            default => 'warning',
        };
    }

    /** "10:00 – 12:00" */
    public function getSlotAttribute(): string
    {
        return substr((string) $this->from_time, 0, 5) . ' – ' . substr((string) $this->to_time, 0, 5);
    }
}
