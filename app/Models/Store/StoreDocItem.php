<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line of one document: this item, this many, at this rate. */
class StoreDocItem extends Model
{
    protected $table = 'store_doc_items';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:3',
            'received_qty' => 'decimal:3',
            'rate' => 'decimal:2',
            'amount' => 'decimal:2',
            'tax_percent' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function doc(): BelongsTo
    {
        return $this->belongsTo(StoreDoc::class, 'store_doc_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(StoreItem::class, 'store_item_id');
    }

    /** On a purchase order or an outgoing transfer: how much has still not turned up. */
    public function getPendingAttribute(): float
    {
        return round(max(0, (float) $this->qty - (float) $this->received_qty), 3);
    }
}
