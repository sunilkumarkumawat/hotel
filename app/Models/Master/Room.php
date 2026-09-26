<?php

namespace App\Models\Master;

use App\Models\Reservation\ReservationRoom;
use App\Support\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Room extends BaseMaster
{
    use RecordsActivity;

    protected $table = 'rooms';

    public const HOUSEKEEPING = [
        'clean' => 'Cleaned',
        'dirty' => 'Dirty',
        // Somebody is in there doing it right now. The old list had no room
        // for this, which is why a supervisor could never tell a room nobody
        // had started from one being made up — see App\Support\HousekeepingBoard.
        'cleaning' => 'Being cleaned',
        'touch_up' => 'Touch Up',
        'inspected' => 'Inspect',
        'out_of_order' => 'Repair',
    ];

    /**
     * What housekeeping may set by hand.
     *
     * "Repair" is not on the list: taking a room out of service is a block,
     * with a reason and dates, and it is done from the Room Blocked screen so
     * the calendars know about it too.
     */
    public const HOUSEKEEPING_SETTABLE = ['clean', 'dirty', 'cleaning', 'touch_up', 'inspected'];

    public function housekeeper(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'housekeeper_id', 'user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(RoomCategory::class, 'room_category_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(RoomType::class, 'room_type_id');
    }

    /**
     * Rooms with nothing booked into them over the given nights.
     *
     * Two stays overlap unless one ends on or before the other starts, so the
     * clash test is `arrival < $to AND checkout > $from` — a guest leaving on
     * the 9th does not block a guest arriving on the 9th.
     *
     * Three things can hold a room: a booking that named it, a block, and a
     * check-in. The third matters because a booking for "3 Deluxe" names no
     * room at all until the guests arrive — the room number lives on the
     * check-in, and without this clause the same room could be handed to the
     * next arrival.
     *
     * `$exceptRow` is a reservation_rooms id to ignore — the row being checked
     * in or moved. Without it a booking that already named room 201 would find
     * 201 "taken" by its own hold, and the desk would be forced to put the
     * guest somewhere else at arrival.
     */
    public function scopeAvailableBetween(Builder $query, string $from, string $to, ?int $exceptRow = null): Builder
    {
        return $query
            ->active()
            ->whereNotIn('id', function ($sub) use ($from, $to, $exceptRow) {
                $sub->select('room_id')
                    ->from('reservation_rooms')
                    ->join('reservations', 'reservations.id', '=', 'reservation_rooms.reservation_id')
                    ->whereNotNull('room_id')
                    ->whereNotIn('reservations.status', ['cancelled', 'no_show', 'checked_out'])
                    ->when($exceptRow, fn ($q, $id) => $q->where('reservation_rooms.id', '!=', $id))
                    ->where('reservation_rooms.arrival_date', '<', $to)
                    ->where('reservation_rooms.checkout_date', '>', $from);
            })
            ->whereNotIn('id', function ($sub) use ($from, $to) {
                $sub->select('room_id')
                    ->from('room_blocks')
                    ->where('status', 'blocked')
                    ->where('from_date', '<', $to)
                    ->where('to_date', '>', $from);
            })
            ->whereNotIn('id', function ($sub) use ($from, $to) {
                $sub->select('room_id')
                    ->from('check_ins')
                    ->whereNotNull('room_id')
                    ->where('status', 'in_house')
                    ->where('checkin_date', '<', $to)
                    // A stay with no actual checkout yet runs to its expected date.
                    ->whereRaw('COALESCE(actual_checkout_date, expected_checkout_date) > ?', [$from]);
            });
    }

    public function bookings()
    {
        return $this->hasMany(ReservationRoom::class, 'room_id');
    }

    public function getLabelAttribute(): string
    {
        return trim($this->room_no . ' — ' . ($this->type?->name ?? ''), ' —');
    }

    /**
     * Columns that move on their own.
     *
     * These are written by the code that keeps them up to date rather than
     * by a person, so logging them would bury the changes somebody actually
     * made under a drift of housekeeping.
     */
    public function auditHidden(): array
    {
        return ['housekeeping_status', 'housekeeper_id', 'housekeeping_remark'];
    }
}
