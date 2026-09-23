<?php

namespace App\Models\Facility;

use App\Models\Master\BaseMaster;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A banquet hall.
 *
 * Two rates rather than one because a hall is sold both ways and the difference
 * is not a discount: a conference takes it by the hour, a wedding takes it for
 * the day, and quoting one from the other is how a banquet manager loses money.
 */
class Hall extends BaseMaster
{
    protected $table = 'halls';

    protected function casts(): array
    {
        return [
            'hour_rate' => 'decimal:2',
            'day_rate' => 'decimal:2',
            'status' => 'integer',
        ];
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(HallBooking::class, 'hall_id');
    }

    /** The rate a rate type asks for — `event` has none of its own, so it quotes the day. */
    public function rateFor(string $rateType): float
    {
        return match ($rateType) {
            'hour' => (float) $this->hour_rate,
            default => (float) $this->day_rate,
        };
    }
}
