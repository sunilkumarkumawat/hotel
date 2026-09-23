<?php

namespace App\Models\Reservation;

use App\Models\FrontOffice\CheckIn;
use App\Models\Master\PlanType;
use App\Models\Master\Room;
use App\Models\Master\RoomCategory;
use App\Models\Master\RoomType;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReservationRoom extends Model
{
    protected $guarded = ['id'];

    public const GUEST_TYPES = [
        'adv_booking' => 'Adv. Booking',
        'walk_in' => 'Walk In',
        'complimentary' => 'Complimentary',
        'house_use' => 'House Use',
        'group' => 'Group',
    ];

    protected function casts(): array
    {
        return [
            'arrival_date' => 'date',
            'checkout_date' => 'date',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(RoomCategory::class, 'room_category_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(RoomType::class, 'room_type_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanType::class, 'plan_type_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    /** Guests who have actually arrived against this row. */
    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class, 'reservation_room_id');
    }

    /**
     * Move this row to new dates and re-add its money.
     *
     * Used when checkout changes how long the guest actually stayed — an
     * extension, or an early departure. The nightly rate is untouched: only
     * the number of nights moves, and the row's amount, tax and net follow it
     * from the same figures the booking was priced with.
     */
    public function moveCheckoutTo(string $date): bool
    {
        $from = $this->arrival_date->toDateString();

        // One night is the shortest stay: a row that starts and ends on the
        // same date holds no room at all.
        $to = max($date, CarbonImmutable::parse($from)->addDay()->toDateString());

        if ($to === $this->checkout_date->toDateString()) {
            return false;
        }

        $figures = Money::roomRow([
            'room_rent' => (float) $this->room_rent,
            'discount' => (float) $this->discount,
            'plan_charge' => (float) $this->plan_charge,
            'no_of_days' => Money::nights($from, $to),
            'no_of_rooms' => (int) $this->no_of_rooms,
            'tax_type' => $this->tax_type,
            // The row's own choice, not today's default: extending a stay must
            // never change whether it is taxed.
            'tax_choice' => $this->tax_choice,
            'tax_percent' => (float) $this->tax_percent,
        ], $this->reservation?->branch_id);

        $this->update([
            'checkout_date' => $to,
            'no_of_days' => Money::nights($from, $to),
            'amount' => $figures['amount'],
            'tax_percent' => $figures['tax_percent'],
            'tax_amount' => $figures['tax_amount'],
            'net_amount' => $figures['net_amount'],
        ]);

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Partial check-in
    |--------------------------------------------------------------------------
    | A row can be "3 Deluxe rooms". Two guests arriving today and one
    | tomorrow is normal, so what is left to check in is the row's count minus
    | the arrivals already recorded against it. A cancelled check-in gives its
    | room back.
    */

    public function checkedInCount(): int
    {
        return $this->relationLoaded('checkIns')
            ? $this->checkIns->whereNotIn('status', ['cancelled'])->count()
            : $this->checkIns()->whereNot('status', 'cancelled')->count();
    }

    public function pendingCount(): int
    {
        return max(0, (int) $this->no_of_rooms - $this->checkedInCount());
    }

    public function isFullyCheckedIn(): bool
    {
        return $this->pendingCount() === 0;
    }
}
