<?php

namespace App\Models\Pos;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One line on an order.
 *
 * `kot_no` is the round it went to the kitchen in. **Zero means it has not been
 * ordered yet** — it is still being typed on the till — and that is the only
 * kind of line the cashier may freely change or take off. Once a line has been
 * sent, changing it is an event somebody has to answer for, so it is written to
 * the audit log.
 */
class PosOrderItem extends Model
{
    protected $table = 'pos_order_items';

    protected $guarded = ['id'];

    /** The kitchen's own column, in the order a ticket moves through it. */
    public const KITCHEN_STATUSES = [
        'pending' => 'New',
        'preparing' => 'Preparing',
        'ready' => 'Ready',
        'served' => 'Served',
    ];

    protected function casts(): array
    {
        return [
            'fired_at' => 'datetime',
            'is_nc' => 'boolean',
            'has_modifiers' => 'boolean',
            'qty' => 'decimal:2',
            'price' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax_percent' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    /**
     * A line's modifiers are its own history, not a relation somebody else
     * has to remember to clean up — removing a line takes its "+ Extra
     * Cheese" with it rather than leaving an orphan row no screen will ever
     * read again.
     */
    protected static function booted(): void
    {
        static::deleting(function (PosOrderItem $item) {
            $item->modifiers()->delete();
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class, 'pos_order_id');
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(PosMenuItem::class, 'pos_menu_item_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(PosDepartment::class, 'pos_department_id');
    }

    /** What was picked for this line — "+ Extra Cheese", frozen at add time. */
    public function modifiers(): HasMany
    {
        return $this->hasMany(PosOrderItemModifier::class, 'pos_order_item_id');
    }

    /** Lines still being typed — never sent to the kitchen. */
    public function scopeUnsent(Builder $query): Builder
    {
        return $query->where('kot_no', 0);
    }

    /** Lines the kitchen has been told about. */
    public function scopeSent(Builder $query): Builder
    {
        return $query->where('kot_no', '>', 0);
    }

    public function isSent(): bool
    {
        return (int) $this->kot_no > 0;
    }

    /** Seconds since the kitchen was told, or 0 for a line it has not seen. */
    public function waitingSeconds(): int
    {
        return $this->fired_at ? max(0, $this->fired_at->diffInSeconds(now())) : 0;
    }
}
