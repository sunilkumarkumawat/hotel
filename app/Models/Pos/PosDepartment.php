<?php

namespace App\Models\Pos;

use App\Models\Master\BaseMaster;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Who cooks it — Kitchen, Bar, Room Service, Bakery.
 *
 * A KOT for a drink prints at the bar and a KOT for a curry prints in the
 * kitchen, so a department is what routes a ticket. It is also the answer an
 * NC type asks for: "which department is eating the cost of this free meal?"
 */
class PosDepartment extends BaseMaster
{
    use SoftDeletes;

    protected $table = 'pos_departments';
}
