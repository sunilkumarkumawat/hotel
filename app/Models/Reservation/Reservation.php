<?php

namespace App\Models\Reservation;

use App\Models\Branch\Branch;
use App\Models\Master\BillingInstruction;
use App\Models\Master\BookedBy;
use App\Models\Master\BusinessMarket;
use App\Models\Master\Company;
use App\Models\Master\Guest;
use App\Models\Master\PayMode;
use App\Models\Master\PickDrop;
use App\Models\Master\VisitPurpose;
use App\Models\User;
use App\Support\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Reservation extends Model
{
    use RecordsActivity;

    protected $guarded = ['id'];

    public const TYPES = [
        'confirm' => 'Confirm Booking',
        'tentative' => 'Tentative',
        'waiting' => 'Waiting List',
        'group' => 'Group Booking',
    ];

    public const STATUSES = [
        'confirmed' => 'Confirmed',
        'tentative' => 'Tentative',
        'cancelled' => 'Cancelled',
        'checked_in' => 'Checked in',
        'checked_out' => 'Checked out',
        'no_show' => 'No show',
    ];

    protected function casts(): array
    {
        return [
            'reservation_date' => 'date',
            'dob' => 'date',
            'cancelled_on' => 'date',
            'room_total' => 'decimal:2',
            'service_total' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'advance_paid' => 'decimal:2',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function rooms(): HasMany
    {
        return $this->hasMany(ReservationRoom::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(ReservationService::class);
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(AdvanceDeposit::class);
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(\App\Models\FrontOffice\CheckIn::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class, 'guest_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function bookedBy(): BelongsTo
    {
        return $this->belongsTo(BookedBy::class, 'booked_by_id');
    }

    public function businessMarket(): BelongsTo
    {
        return $this->belongsTo(BusinessMarket::class, 'business_market_id');
    }

    public function visitPurpose(): BelongsTo
    {
        return $this->belongsTo(VisitPurpose::class, 'visit_purpose_id');
    }

    public function pickDrop(): BelongsTo
    {
        return $this->belongsTo(PickDrop::class, 'pick_drop_id');
    }

    public function billingInstruction(): BelongsTo
    {
        return $this->belongsTo(BillingInstruction::class, 'billing_instruction_id');
    }

    public function payMode(): BelongsTo
    {
        return $this->belongsTo(PayMode::class, 'pay_mode_id');
    }

    /*
     * Where the guest is from. The registration card prints the country as
     * Nationality and the city and state into the permanent address.
     */

    public function country(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Country\Country::class, 'country_id');
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(\App\Models\State\State::class, 'state_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(\App\Models\City\City::class, 'city_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emp_id', 'user_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes and helpers
    |--------------------------------------------------------------------------
    */

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, fn (Builder $q) => $q->where(function (Builder $q) use ($term) {
            $q->where('reservation_no', 'like', "%{$term}%")
                ->orWhere('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('mobile', 'like', "%{$term}%");
        }));
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['cancelled', 'no_show']);
    }

    public function getGuestNameAttribute(): string
    {
        return trim(($this->title ? $this->title . ' ' : '') . $this->first_name . ' ' . $this->last_name);
    }

    public function getBalanceAttribute(): float
    {
        return round((float) $this->net_amount - (float) $this->advance_paid, 2);
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Can this booking still be rewritten?
     *
     * Status alone is not enough. Editing replaces every room row, and a
     * booking with two rows keeps the status "confirmed" while only one of
     * them has arrived — so a booking that looks editable can have a guest
     * asleep in one of its rooms. Rewriting the rows under them cuts their
     * check-in loose from its booking: the room they are in stops being
     * accounted for, and the same room can be checked in a second time.
     */
    public function isEditable(): bool
    {
        return in_array($this->status, ['confirmed', 'tentative'], true)
            && ! $this->hasArrivals();
    }

    /**
     * Has anybody on this booking actually turned up?
     *
     * Answered from `rooms.checkIns` when the caller has already loaded it —
     * the booking list asks this once per row, and a query each would be
     * fifteen round trips for one page.
     */
    public function hasArrivals(): bool
    {
        if ($this->relationLoaded('rooms') && $this->rooms->every(fn ($r) => $r->relationLoaded('checkIns'))) {
            return $this->rooms->contains(fn ($row) => $row->checkedInCount() > 0);
        }

        return $this->checkIns()->where('status', '!=', 'cancelled')->exists();
    }

    /*
    |--------------------------------------------------------------------------
    | Check-in
    |--------------------------------------------------------------------------
    */

    /** Rooms on this booking that nobody has arrived for yet. */
    public function pendingRooms(): int
    {
        return $this->rooms->sum(fn (ReservationRoom $room) => $room->pendingCount());
    }

    /** Rooms whose guest is in the building. */
    public function arrivedRooms(): int
    {
        return $this->rooms->sum(fn (ReservationRoom $room) => $room->checkedInCount());
    }

    /**
     * Can the front desk check somebody in against this booking right now?
     *
     * A cancelled or no-show booking is closed, and once every room has
     * arrived there is nothing left to check in.
     */
    public function isCheckInable(): bool
    {
        return ! in_array($this->status, ['cancelled', 'no_show', 'checked_out'], true)
            && $this->pendingRooms() > 0;
    }

    /**
     * Move the booking's status to match its arrivals.
     *
     * Partial arrivals leave the booking where it is — it is still a live
     * booking with rooms owing. Only when the last room arrives does the
     * booking itself become "Checked in".
     */
    public function refreshCheckInStatus(): void
    {
        $this->load('rooms.checkIns');

        // A cancelled or no-show booking is closed; arrivals do not reopen it.
        if (in_array($this->status, ['cancelled', 'no_show', 'checked_out'], true)) {
            return;
        }

        if ($this->pendingRooms() === 0 && $this->arrivedRooms() > 0) {
            $this->update(['status' => 'checked_in']);

            return;
        }

        // Rooms are owed again — undoing an arrival, or the booking growing.
        // "Checked in" has to mean every room, or the list lies about a
        // booking the desk still has work to do on.
        if ($this->status === 'checked_in') {
            $this->update(['status' => $this->reservation_type === 'tentative' ? 'tentative' : 'confirmed']);
        }
    }

    /**
     * Close the booking once the last guest on it has left.
     *
     * This is what gives the room back. A booking row goes on holding its room
     * for the nights it was sold, and the hold is only lifted for a booking
     * that is cancelled, a no-show or checked out — so a guest who leaves two
     * days early would otherwise leave those two nights unsellable, with the
     * tape chart still drawing a bar over a room that is standing empty.
     */
    public function refreshCheckOutStatus(): void
    {
        if (in_array($this->status, ['cancelled', 'no_show'], true)) {
            return;
        }

        $this->load('rooms.checkIns');

        $stillHere = $this->checkIns()->where('status', 'in_house')->exists();

        // Every room accounted for: none still in the house, and none still to
        // arrive. Closing a booking that has a room yet to come would lock out
        // its own check-in and put the room it is holding back on sale.
        if (! $stillHere && $this->arrivedRooms() > 0 && $this->pendingRooms() === 0) {
            $this->update(['status' => 'checked_out']);
        }
    }

    /**
     * Re-add the header totals from the rows that are actually stored.
     *
     * Lives on the model rather than in the reservation controller because
     * checkout moves a row's nights too — extending a stay or a guest leaving
     * early — and the booking's own figures have to follow, or the screen
     * would show five nights beside a three-night total.
     */
    public function refreshTotals(): void
    {
        $rooms = $this->rooms()->get();
        $services = $this->services()->get();

        $this->update([
            'room_total' => round($rooms->sum('amount'), 2),
            'service_total' => round($services->sum('amount'), 2),
            'discount_total' => round($rooms->sum(
                fn (ReservationRoom $r) => $r->discount * $r->no_of_days * $r->no_of_rooms
            ), 2),
            'tax_total' => round($rooms->sum('tax_amount') + $services->sum('tax_amount'), 2),
            'net_amount' => round($rooms->sum('net_amount') + $services->sum('total_amount'), 2),
        ]);
    }

    /**
     * Re-add the deposits and store the result on the reservation.
     *
     * Deposits are recorded from two screens — the reservation itself and
     * Advance Deposit Details — so the sum lives here rather than in either
     * controller. A refund counts against the total.
     */
    public function refreshAdvancePaid(): float
    {
        $paid = (float) $this->deposits()
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'refund' THEN -amount ELSE amount END), 0) AS total")
            ->value('total');

        $paid = round($paid, 2);

        $this->update(['advance_paid' => $paid]);

        return $paid;
    }

    /**
     * The next reservation number for a branch.
     *
     * Format: RSV-<branch>-<0001>. Read inside the same transaction as the
     * insert so two clerks saving at once cannot land on the same number.
     */
    public static function nextNumber(int $branchId): string
    {
        $last = DB::table('reservations')
            ->where('branch_id', $branchId)
            ->orderByDesc('id')
            ->value('reservation_no');

        $next = $last ? ((int) substr($last, strrpos($last, '-') + 1)) + 1 : 1;

        return sprintf('RSV-%d-%04d', $branchId, $next);
    }
}
