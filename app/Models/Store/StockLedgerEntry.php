<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One movement of one item — the only thing in the store that is true.
 *
 * Never updated, never deleted. A correction is another row and a cancelled
 * document posts its reverse, so the history reads as what actually happened
 * rather than as what somebody last decided it should look like.
 */
class StockLedgerEntry extends Model
{
    protected $table = 'stock_ledger';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'qty' => 'decimal:3',
            'rate' => 'decimal:2',
            'value' => 'decimal:2',
            'balance_qty' => 'decimal:3',
            'balance_rate' => 'decimal:2',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(StoreItem::class, 'store_item_id');
    }

    public function doc(): BelongsTo
    {
        return $this->belongsTo(StoreDoc::class, 'store_doc_id');
    }

    public function scopeIn(Builder $query): Builder
    {
        return $query->where('direction', 'in');
    }

    public function scopeOut(Builder $query): Builder
    {
        return $query->where('direction', 'out');
    }

    public function isIn(): bool
    {
        return $this->direction === 'in';
    }
}
