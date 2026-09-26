<?php

namespace App\Models\Facility;

use App\Models\Master\BaseMaster;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A car the hotel sends out — with whoever usually drives it.
 *
 * The driver is stored here as a default and copied onto the trip, not read
 * through a relationship: the regular driver being off sick must not rewrite
 * who drove last Tuesday.
 */
class Vehicle extends BaseMaster
{
    protected $table = 'vehicles';

    public const TYPES = [
        'car' => 'Car',
        'suv' => 'SUV',
        'tempo' => 'Tempo Traveller',
        'bus' => 'Bus',
        'other' => 'Other',
    ];

    protected function casts(): array
    {
        return [
            'km_rate' => 'decimal:2',
            'trip_rate' => 'decimal:2',
            'status' => 'integer',
        ];
    }

    public function trips(): HasMany
    {
        return $this->hasMany(GuestTrip::class, 'vehicle_id');
    }

    public function getLabelAttribute(): string
    {
        return trim($this->name . ($this->vehicle_no ? ' — ' . $this->vehicle_no : ''));
    }
}
