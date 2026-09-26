<?php

namespace App\Models\Store;

use App\Models\Master\BaseMaster;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What kind of thing it is: Vegetables, Beverages, Linen, Cleaning.
 *
 * Deliberately not the POS menu categories. "Vegetables" is a store category
 * and "Starters" is a menu one, and the day somebody merges them is the day a
 * tomato appears on a menu.
 */
class StoreCategory extends BaseMaster
{
    protected $table = 'store_categories';

    public function items(): HasMany
    {
        return $this->hasMany(StoreItem::class, 'store_category_id');
    }
}
