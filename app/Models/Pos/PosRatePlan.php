<?php

namespace App\Models\Pos;

use App\Models\Master\BaseMaster;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One price list.
 *
 * The same dosa is ₹180 à la carte and ₹120 during Happy Hours. A plan is
 * switched on per outlet, because the bar runs Happy Hours and the coffee shop
 * does not — so a plan with no outlets ticked is a plan nobody can sell on,
 * which is why the form insists on at least one.
 */
class PosRatePlan extends BaseMaster
{
    use SoftDeletes;

    protected $table = 'pos_rate_plans';

    public function outlets(): BelongsToMany
    {
        return $this->belongsToMany(Outlet::class, 'pos_rate_plan_outlet', 'pos_rate_plan_id', 'outlet_id');
    }
}
