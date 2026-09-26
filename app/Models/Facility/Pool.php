<?php

namespace App\Models\Facility;

use App\Models\Master\BaseMaster;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A pool the hotel can sell time in.
 *
 * `capacity` is the only field that does any work beyond printing: a booking
 * that would take the pool past it is refused, because a pool is the one
 * facility where overselling is a safety matter rather than an inconvenience.
 * Zero means no limit, which is what a small hotel with one pool and no
 * lifeguard rota actually wants.
 */
class Pool extends BaseMaster
{
    protected $table = 'pools';

    protected function casts(): array
    {
        return [
            'adult_rate' => 'decimal:2',
            'child_rate' => 'decimal:2',
            'status' => 'integer',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(PoolBooking::class, 'pool_id');
    }

    /** "06:00 – 21:00", or nothing when the hotel has not said. */
    public function getHoursAttribute(): string
    {
        if (! $this->open_time || ! $this->close_time) {
            return '';
        }

        return substr((string) $this->open_time, 0, 5) . ' – ' . substr((string) $this->close_time, 0, 5);
    }
}
