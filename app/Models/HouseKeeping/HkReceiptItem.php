<?php

namespace App\Models\HouseKeeping;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One kind of linen on one receipt note. */
class HkReceiptItem extends Model
{
    protected $table = 'hk_receipt_items';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'pending_qty' => 'decimal:2',
            'received_qty' => 'decimal:2',
            'damaged_qty' => 'decimal:2',
            'missing_qty' => 'decimal:2',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(HkReceipt::class, 'hk_receipt_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(HkItem::class, 'hk_item_id');
    }

    /**
     * How much this line takes off what the vendor owes.
     *
     * Damaged and missing count: the hotel is never getting those pieces back,
     * so leaving them on the outstanding list would keep a closed job open for
     * ever. They are written off here and shown separately on the note.
     */
    public function settledQty(): float
    {
        return (float) $this->received_qty + (float) $this->damaged_qty + (float) $this->missing_qty;
    }
}
