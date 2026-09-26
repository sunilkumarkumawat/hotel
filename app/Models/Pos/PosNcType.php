<?php

namespace App\Models\Pos;

use App\Models\Master\BaseMaster;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A reason food left the kitchen without being paid for.
 *
 * NC is "No Charge": a comp for a complaining guest, a staff meal, a tasting
 * for a travel agent, a promotional plate. None of it is theft — but all of it
 * has to be named, or the difference between hospitality and leakage stops
 * being visible.
 *
 * `requires_department` forces the second question for the types that need it:
 * an in-house meal belongs to somebody's cost centre, a promotional plate to
 * marketing's.
 */
class PosNcType extends BaseMaster
{
    use SoftDeletes;

    protected $table = 'pos_nc_types';

    protected $casts = [
        'requires_department' => 'boolean',
    ];
}
