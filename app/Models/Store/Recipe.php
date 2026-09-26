<?php

namespace App\Models\Store;

use App\Models\Pos\PosMenuItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What a dish is made of, and therefore what it costs.
 *
 * `yield_qty` is how many portions the recipe makes: a biryani written for
 * four is costed per one. Without it, every recipe written the way a chef
 * writes one would price a dish at four times what it costs.
 */
class Recipe extends Model
{
    protected $table = 'recipes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['yield_qty' => 'decimal:3'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(RecipeItem::class, 'recipe_id');
    }

    /** The menu item this is the recipe for, where it is tied to one. */
    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(PosMenuItem::class, 'pos_item_id');
    }

    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 1);
    }

    public function isActive(): bool
    {
        return (int) $this->status === 1;
    }
}
