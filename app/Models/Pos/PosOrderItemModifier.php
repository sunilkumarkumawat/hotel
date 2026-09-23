<?php

namespace App\Models\Pos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One modifier on one order line, frozen at the moment it was added.
 *
 * `name` and `price` are a snapshot — the same reason pos_order_items keeps
 * its own `item_name` rather than always reading PosMenuItem::name — so a
 * modifier renamed or re-priced next month cannot rewrite what last night's
 * bill said. `pos_modifier_id` is kept only so Setup can tell a modifier is
 * still referenced by history; it is never read back for the price.
 */
class PosOrderItemModifier extends Model
{
    protected $table = 'pos_order_item_modifiers';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2'];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(PosOrderItem::class, 'pos_order_item_id');
    }

    public function modifier(): BelongsTo
    {
        return $this->belongsTo(PosModifier::class, 'pos_modifier_id');
    }
}
