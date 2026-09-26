<?php

namespace App\Models\Pos;

use App\Models\Master\BaseMaster;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A heading on the menu — Starters, Beverages, Indian Breads.
 *
 * A row is either a category or a sub-category, and a sub-category names the
 * category it sits under. That parent is what turns a wall of eighty buttons
 * into Beverages → Hot / Cold on the POS screen.
 */
class PosMenuCategory extends BaseMaster
{
    use SoftDeletes;

    protected $table = 'pos_menu_categories';

    public const TYPES = [
        'category' => 'Category',
        'sub_category' => 'Sub Category',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PosMenuItem::class, 'pos_menu_category_id');
    }

    /** Top-level headings — the only rows a sub-category may point at. */
    public function scopeHeadings(Builder $query): Builder
    {
        return $query->where('type', 'category');
    }

    public function isSubCategory(): bool
    {
        return $this->type === 'sub_category';
    }
}
