<?php

namespace App\Models\Facility;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An extra on a hall booking — décor, a DJ, the buffet.
 *
 * Its own row rather than a line of free text because each one is priced and
 * taxed on its own: a buffet may carry GST where the hall hire does not, and a
 * single "extras" figure makes that impossible to state on the bill.
 */
class HallBookingItem extends Model
{
    protected $table = 'hall_booking_items';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'price' => 'decimal:2',
            'tax_percent' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(HallBooking::class, 'hall_booking_id');
    }
}
