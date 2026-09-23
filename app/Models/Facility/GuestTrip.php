<?php

namespace App\Models\Facility;

use App\Models\FrontOffice\CheckIn;
use App\Models\Reservation\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fetching a guest, or taking them back.
 *
 * A pickup is usually booked against a *reservation* — the guest has not
 * arrived yet, so there is no stay to hang it on — and a drop against a stay.
 * Both links are nullable and both are offered, because the airport run for a
 * walk-in is neither.
 *
 * Like parking, the trip is recorded first and charged only if asked:
 * `is_chargeable` starts off, and a hotel that includes the airport pickup in
 * the tariff never turns it on.
 */
class GuestTrip extends Model
{
    protected $table = 'guest_trips';

    protected $guarded = ['id'];

    public const TYPES = [
        'pickup' => 'Pick up',
        'drop' => 'Drop',
    ];

    public const STATUSES = [
        'scheduled' => 'Scheduled',
        'started' => 'On the way',
        'completed' => 'Done',
        'cancelled' => 'Cancelled',
    ];

    public const RATE_TYPES = [
        'trip' => 'Flat, per trip',
        'km' => 'Per kilometre',
    ];

    protected function casts(): array
    {
        return [
            'trip_date' => 'date',
            'km' => 'decimal:2',
            'is_chargeable' => 'boolean',
            'rate' => 'decimal:2',
            'amount' => 'decimal:2',
            'tax_percent' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'post_to_room' => 'boolean',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function stay(): BelongsTo
    {
        return $this->belongsTo(CheckIn::class, 'check_in_id');
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function scopeForBranch(Builder $query, ?int $branchId = null): Builder
    {
        return $query->where('branch_id', $branchId ?? (int) \App\Helpers\Helper::getActiveBranchId());
    }

    /** Trips still to happen or happening — what a driver's day is made of. */
    public function scopeLive(Builder $query): Builder
    {
        return $query->whereIn('status', ['scheduled', 'started']);
    }

    public function isClosed(): bool
    {
        return in_array($this->status, ['completed', 'cancelled'], true);
    }

    public function charges(): bool
    {
        return (bool) $this->is_chargeable && (float) $this->rate > 0;
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->trip_type] ?? ucfirst((string) $this->trip_type);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function getStatusToneAttribute(): string
    {
        return match ($this->status) {
            'started' => 'info',
            'completed' => 'success',
            'cancelled' => 'danger',
            default => 'warning',
        };
    }

    /** "16 Sep 2026, 02:30 PM" — one place, so every screen says it the same way. */
    public function getWhenAttribute(): string
    {
        return $this->trip_date->format('d M Y') . ', '
            . \Carbon\CarbonImmutable::parse((string) $this->trip_time)->format('h:i A');
    }
}
