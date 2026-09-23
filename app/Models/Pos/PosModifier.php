<?php

namespace App\Models\Pos;

use App\Models\Master\BaseMaster;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One answer inside a {@see PosModifierGroup} — Extra Cheese, Small, Medium —
 * and what it adds to the item's price. A modifier with no surcharge is a
 * free choice, like "No Onion".
 */
class PosModifier extends BaseMaster
{
    use SoftDeletes;

    protected $table = 'pos_modifiers';

    protected function casts(): array
    {
        return ['price' => 'decimal:2'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(PosModifierGroup::class, 'pos_modifier_group_id');
    }
}
