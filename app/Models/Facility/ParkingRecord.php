<?php

namespace App\Models\Facility;

use App\Models\FrontOffice\CheckIn;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A car in the hotel's park, from when it arrived to when it left.
 *
 * **Parking is free.** `is_chargeable` starts at 0 and a record with it off
 * costs the guest nothing no matter what sits in `rate` — the tick is a
 * deliberate act on the screen and the only thing that turns a ticket into a
 * charge. Everything in this class that touches money reads it first.
 */
class ParkingRecord extends Model
{
    protected $table = 'parking_records';

    protected $guarded = ['id'];

    public const STATUSES = [
        'parked' => 'In the park',
        'out' => 'Taken out',
        'cancelled' => 'Cancelled',
    ];

    protected function casts(): array
    {
        return [
            'in_at' => 'datetime',
            'out_at' => 'datetime',
            'is_chargeable' => 'boolean',
            'rate' => 'decimal:2',
            'hours' => 'decimal:2',
            'amount' => 'decimal:2',
            'tax_percent' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'post_to_room' => 'boolean',
        ];
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(ParkingSlot::class, 'parking_slot_id');
    }

    public function stay(): BelongsTo
    {
        return $this->belongsTo(CheckIn::class, 'check_in_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    /** Cars that are still in the park — what holds a bay. */
    public function scopeParked(Builder $query): Builder
    {
        return $query->where('status', 'parked');
    }

    public function scopeForBranch(Builder $query, ?int $branchId = null): Builder
    {
        return $query->where('branch_id', $branchId ?? (int) \App\Helpers\Helper::getActiveBranchId());
    }

    public function isParked(): bool
    {
        return $this->status === 'parked';
    }

    /** True only when somebody ticked the box AND put a rate against it. */
    public function charges(): bool
    {
        return (bool) $this->is_chargeable && (float) $this->rate > 0;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function getStatusToneAttribute(): string
    {
        return match ($this->status) {
            'parked' => 'info',
            'out' => 'success',
            default => 'danger',
        };
    }

    /** How long it has been there, live, for a car that has not left yet. */
    public function getStandingHoursAttribute(): float
    {
        return \App\Support\Facility::hoursBetween(
            (string) $this->in_at,
            (string) ($this->out_at ?: now())
        );
    }
}
