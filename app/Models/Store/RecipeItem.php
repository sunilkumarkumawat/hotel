<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One ingredient, and how much of it the recipe uses. */
class RecipeItem extends Model
{
    protected $table = 'recipe_items';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['qty' => 'decimal:4'];
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class, 'recipe_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(StoreItem::class, 'store_item_id');
    }

    /**
     * What this line costs at today's average rate.
     *
     * Priced from the moving average rather than the last purchase, so one
     * expensive delivery does not make the menu look unprofitable for a week.
     */
    public function getCostAttribute(): float
    {
        return round((float) $this->qty * (float) ($this->item?->avg_rate ?? 0), 2);
    }
}
