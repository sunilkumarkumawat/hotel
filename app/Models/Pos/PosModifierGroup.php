<?php

namespace App\Models\Pos;

use App\Models\Master\BaseMaster;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A question the till asks when a dish is added — Size, Toppings, Spice
 * Level. `selection_type` is whether it takes one answer or several;
 * `is_required` is whether it may be left unanswered. The answers themselves
 * are {@see PosModifier} rows; this is only the group and its rules.
 */
class PosModifierGroup extends BaseMaster
{
    use SoftDeletes;

    protected $table = 'pos_modifier_groups';

    public const TYPES = [
        'single' => 'Just one',
        'multiple' => 'Any number',
    ];

    protected function casts(): array
    {
        return ['is_required' => 'boolean'];
    }

    public function modifiers(): HasMany
    {
        return $this->hasMany(PosModifier::class, 'pos_modifier_group_id')->orderBy('sort')->orderBy('name');
    }

    /** The dishes this group is asked on. */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(
            PosMenuItem::class,
            'pos_menu_item_modifier_group',
            'pos_modifier_group_id',
            'pos_menu_item_id'
        );
    }
}
