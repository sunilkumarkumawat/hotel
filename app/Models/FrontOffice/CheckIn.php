<?php

namespace App\Models\FrontOffice;

use App\Models\Master\Guest;
use App\Models\Master\PlanType;
use App\Models\Master\Room;
use App\Models\Reservation\Reservation;
use App\Models\Reservation\ReservationRoom;
use App\Models\User;
use App\Support\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * One physical room, occupied by a guest.
 *
 * A booking is a promise; a check-in is the guest actually in the building.
 * Checking three rooms in together makes three rows sharing one `folio_no`,
 * which is the arrival number the front desk reads out.
 */
class CheckIn extends Model
{
    use RecordsActivity;

    protected $guarded = ['id'];

    /** Rows that are still holding a room. */
    public const HOLDS_ROOM = ['in_house'];

    public const STATUSES = [
        'in_house' => 'In house',
        'checked_out' => 'Checked out',
        'cancelled' => 'Cancelled',
    ];

    protected function casts(): array
    {
        return [
            'checkin_date' => 'date',
            'expected_checkout_date' => 'date',
            'actual_checkout_date' => 'date',
            'room_rent' => 'decimal:2',
            'discount' => 'decimal:2',
            'plan_charge' => 'decimal:2',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function reservationRoom(): BelongsTo
    {
        return $this->belongsTo(ReservationRoom::class, 'reservation_room_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id');
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class, 'guest_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PlanType::class, 'plan_type_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function pax(): HasMany
    {
        return $this->hasMany(CheckInPax::class);
    }

    public function charges(): HasMany
    {
        return $this->hasMany(FolioCharge::class, 'check_in_id');
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(Settlement::class, 'check_in_id');
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class, 'check_in_id');
    }

    public function paxCheckouts(): HasMany
    {
        return $this->hasMany(PaxCheckout::class, 'check_in_id');
    }

    /** The bill this stay was settled with, if it has been. */
    public function bill(): ?Bill
    {
        return $this->bills()->where('status', '!=', 'cancelled')->latest('id')->first();
    }

    /** People still in the room — pax checkouts take some of them away. */
    public function paxRemaining(): int
    {
        $total = (int) $this->male + (int) $this->female + (int) $this->child;

        return max(0, $total - (int) $this->paxCheckouts()->sum('pax'));
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes and helpers
    |--------------------------------------------------------------------------
    */

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, fn (Builder $q) => $q->where(function (Builder $q) use ($term) {
            $q->where('folio_no', 'like', "%{$term}%")
                ->orWhere('guest_name', 'like', "%{$term}%")
                ->orWhere('mobile', 'like', "%{$term}%")
                ->orWhereHas('room', fn (Builder $r) => $r->where('room_no', 'like', "%{$term}%"));
        }));
    }

    /** Still occupying its room. */
    public function scopeInHouse(Builder $query): Builder
    {
        return $query->whereIn('status', self::HOLDS_ROOM);
    }

    public function isInHouse(): bool
    {
        return in_array($this->status, self::HOLDS_ROOM, true);
    }

    /** The night the room actually frees up. */
    public function departsOn(): string
    {
        return ($this->actual_checkout_date ?? $this->expected_checkout_date)->toDateString();
    }

    public function getNightsAttribute(): int
    {
        return max(1, $this->checkin_date->diffInDays($this->actual_checkout_date ?? $this->expected_checkout_date));
    }

    /**
     * The next arrival number for a branch.
     *
     * Format: FO-<branch>-<0001>, same shape as a reservation number. Read
     * inside the check-in transaction so two clerks cannot land on the same
     * number.
     */
    public static function nextFolio(int $branchId): string
    {
        $prefix = config('pms.folio_prefix', 'FO') . '-' . $branchId . '-';

        $last = DB::table('check_ins')
            ->where('branch_id', $branchId)
            ->where('folio_no', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('folio_no');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
