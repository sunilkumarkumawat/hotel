<?php

namespace App\Models\Pos;

use App\Models\Master\BaseMaster;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A sitting an outlet takes table bookings for.
 *
 * `max_booking` is how many parties it will hold at that time — the number the
 * booking screen counts down from before it says "fully booked". Zero means no
 * ceiling has been set, so nothing is refused.
 *
 * Times are stored as a plain `HH:MM:SS` string rather than a date: a slot is
 * 7:30 PM on every day the restaurant opens, not 7:30 PM on one Tuesday.
 */
class PosReservationSlot extends BaseMaster
{
    use SoftDeletes;

    protected $table = 'pos_reservation_slots';

    /** The list is read as a timetable, so it always comes back in clock order. */
    protected static function booted(): void
    {
        static::addGlobalScope('by_time', fn ($query) => $query->orderBy('slot_time'));
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }

    /** "07:30 PM" — how the old screen writes it, and how a host reads it. */
    public function getTimeLabelAttribute(): string
    {
        return date('h:i A', strtotime((string) $this->slot_time));
    }

    /** "19:30" — what an <input type="time"> wants back. */
    public function getTimeValueAttribute(): string
    {
        return date('H:i', strtotime((string) $this->slot_time));
    }
}
