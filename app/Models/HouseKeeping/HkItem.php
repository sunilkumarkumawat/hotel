<?php

namespace App\Models\HouseKeeping;

use App\Models\Master\BaseMaster;

/**
 * A piece of linen the hotel washes — bedsheet, towel, pillow cover.
 *
 * The two rates are the laundry contract: what the vendor charges per piece
 * for a standard wash and for an express one. They are only defaults; the
 * issue note stores the rate it actually went out at, so re-negotiating the
 * contract next month cannot change a note already written.
 */
class HkItem extends BaseMaster
{
    protected $table = 'hk_items';

    protected function casts(): array
    {
        return [
            'std_rate' => 'decimal:2',
            'exp_rate' => 'decimal:2',
        ];
    }
}
