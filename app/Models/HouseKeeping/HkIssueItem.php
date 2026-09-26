<?php

namespace App\Models\HouseKeeping;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One kind of linen on one laundry note. */
class HkIssueItem extends Model
{
    protected $table = 'hk_issue_items';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'prev_qty' => 'decimal:2',
            'std_qty' => 'decimal:2',
            'exp_qty' => 'decimal:2',
            'rewash_qty' => 'decimal:2',
            'std_rate' => 'decimal:2',
            'exp_rate' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(HkIssue::class, 'hk_issue_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(HkItem::class, 'hk_item_id');
    }

    /** Everything that physically left the hotel on this line. */
    public function sentQty(): float
    {
        return (float) $this->std_qty + (float) $this->exp_qty + (float) $this->rewash_qty;
    }
}
