<?php

namespace App\Models\Pos;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One order a guest asked for from their own phone, before anybody on staff
 * has looked at it.
 *
 * This is deliberately not a PosOrder. A guest typing on their own phone,
 * with nobody watching, is not the same thing as a waiter sending a ticket —
 * so what they send in sits here, on its own, until a member of staff reads
 * it and decides. Only `approve()` — see GuestRequestController — turns it
 * into real order lines and a KOT; `reject()` just closes it out.
 *
 * `items` is a snapshot of what was asked for at the moment it was sent
 * (name and price included), not a live reference — the menu could have
 * changed by the time anyone looks at this. What actually gets billed is
 * always re-read from the live menu at approval time; this column is a
 * record of the ask, never charged from directly.
 */
class PosGuestRequest extends Model
{
    protected $table = 'pos_guest_requests';

    protected $guarded = ['id'];

    public const STATUSES = [
        'pending' => 'Waiting',
        'approved' => 'Approved',
        'rejected' => 'Declined',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'decided_at' => 'datetime',
        ];
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(PosTable::class, 'pos_table_id');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class, 'pos_order_id');
    }

    /** Requests nobody on staff has answered yet. */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /** How many items were asked for, covers included — "3 items" on a list row. */
    public function itemCount(): int
    {
        return (int) collect($this->items)->sum('qty');
    }

    /** A token nobody can guess — forty characters, the same shape as the feedback link. */
    public static function newToken(): string
    {
        return Str::random(40);
    }
}
