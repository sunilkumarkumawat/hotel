<?php

namespace App\Models\Pos;

use App\Models\Master\BaseMaster;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One thing on the menu.
 *
 * `price` is what it costs on an ordinary day. A rate plan that charges
 * something else — Happy Hours, a banquet sheet — stores only the exception in
 * `pos_menu_item_prices`, so switching plans never has to rewrite the menu.
 *
 * `pos_department_id` is what routes the KOT. A beer goes to the bar and a
 * curry to the kitchen because the item says so, not because somebody
 * remembered to tick something at billing time.
 */
class PosMenuItem extends BaseMaster
{
    use SoftDeletes;

    protected $table = 'pos_menu_items';

    protected function casts(): array
    {
        return ['price' => 'decimal:2'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PosMenuCategory::class, 'pos_menu_category_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(PosDepartment::class, 'pos_department_id');
    }

    /** The plans that price this item differently, with the price on the pivot. */
    public function ratePlans(): BelongsToMany
    {
        return $this->belongsToMany(
            PosRatePlan::class,
            'pos_menu_item_prices',
            'pos_menu_item_id',
            'pos_rate_plan_id'
        )->withPivot('price');
    }

    /** The modifier groups a guest may be asked when this dish is added. */
    public function modifierGroups(): BelongsToMany
    {
        return $this->belongsToMany(
            PosModifierGroup::class,
            'pos_menu_item_modifier_group',
            'pos_menu_item_id',
            'pos_modifier_group_id'
        );
    }

    /**
     * What this item sells for on a given plan.
     *
     * No plan, or a plan with nothing to say about this item, means the
     * everyday price — which is why a new plan can be switched on mid-service
     * without anybody having to price the whole menu first.
     */
    public function priceOn(?int $ratePlanId): float
    {
        if (! $ratePlanId) {
            return (float) $this->price;
        }

        $override = $this->relationLoaded('ratePlans')
            ? $this->ratePlans->firstWhere('id', $ratePlanId)
            : $this->ratePlans()->where('pos_rate_plans.id', $ratePlanId)->first();

        return $override ? (float) $override->pivot->price : (float) $this->price;
    }

    /** Items a till may actually sell: live, in stock, and in a live category. */
    public function scopeSellable(Builder $query): Builder
    {
        return $query->where('status', 1);
    }

    /**
     * The picture a guest sees on the self-order menu, or null for none yet.
     *
     * `photo` holds a path on the public disk — same convention as
     * Outlet::logo_path — so this is the one place that turns it into
     * something a browser can actually load.
     */
    public function photoUrl(): ?string
    {
        return $this->photo ? asset('storage/' . $this->photo) : null;
    }
}
