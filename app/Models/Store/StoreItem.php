<?php

namespace App\Models\Store;

use App\Models\Master\BaseMaster;
use App\Support\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One thing the store keeps.
 *
 * `current_qty` and `avg_rate` are a CACHE of running `stock_ledger`. Nothing
 * on a screen ever writes them — App\Support\Store does, on every movement,
 * and can rebuild them from the ledger at any time. If the two disagree, the
 * ledger is right.
 */
class StoreItem extends BaseMaster
{
    use RecordsActivity;

    protected $table = 'store_items';

    protected function casts(): array
    {
        return [
            'current_qty' => 'decimal:3',
            'avg_rate' => 'decimal:2',
            'last_rate' => 'decimal:2',
            'reorder_level' => 'decimal:3',
            'opening_qty' => 'decimal:3',
            'opening_rate' => 'decimal:2',
            'tax_percent' => 'decimal:2',
            'is_ingredient' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(StoreCategory::class, 'store_category_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockLedgerEntry::class, 'store_item_id');
    }

    /** What is on the shelf, at what it cost. */
    public function getValueAttribute(): float
    {
        return round((float) $this->current_qty * (float) $this->avg_rate, 2);
    }

    /** At or below the level somebody set — and only if they set one. */
    public function needsReorder(): bool
    {
        return (float) $this->reorder_level > 0
            && (float) $this->current_qty <= (float) $this->reorder_level;
    }

    /**
     * The books say there is less than none.
     *
     * Not an error: it means stock was issued before the delivery note was
     * entered. Worth showing, because it is the fastest way to find the
     * paperwork that is late.
     */
    public function isShort(): bool
    {
        return (float) $this->current_qty < 0;
    }

    public function scopeNeedingReorder(Builder $query): Builder
    {
        return $query->where('reorder_level', '>', 0)
            ->whereColumn('current_qty', '<=', 'reorder_level');
    }

    public function scopeIngredients(Builder $query): Builder
    {
        return $query->where('is_ingredient', true);
    }

    public function getLabelAttribute(): string
    {
        return $this->name . ($this->unit ? ' (' . $this->unit . ')' : '');
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
        return ['current_qty', 'avg_rate', 'last_rate'];
    }
}
