<?php

namespace App\Models\Pos;

use App\Models\Master\BaseMaster;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Waiting staff.
 *
 * An order carries the steward who took it, so a bill can be traced back to a
 * person — for service charge, for tips, and for the awkward conversation about
 * the table that was billed twice.
 */
class PosSteward extends BaseMaster
{
    use SoftDeletes;

    protected $table = 'pos_stewards';

    public function getLabelAttribute(): string
    {
        return trim($this->name . ($this->phone ? ' · ' . $this->phone : ''));
    }
}
