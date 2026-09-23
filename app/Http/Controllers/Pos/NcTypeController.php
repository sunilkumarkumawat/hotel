<?php

namespace App\Http\Controllers\Pos;

use App\Models\Pos\PosNcType;

/**
 * NC Types — the named reasons food goes out without being paid for.
 *
 * Complimentary, In-House, Day Vacation, Promotional. None of it is theft, but
 * all of it has to be named, or the difference between hospitality and leakage
 * stops being visible on any report.
 *
 * `requires_department` is the second question: an in-house meal belongs to
 * somebody's cost centre, a promotional plate to marketing's. Ticking it here
 * makes the POS screen insist on an answer at the moment the comp is given,
 * which is the only moment anybody actually knows it.
 */
class NcTypeController extends SetupListController
{
    protected function definition(): array
    {
        return [
            'model' => PosNcType::class,
            'route' => 'point-of-sale.setup.nc-types',
            'permission' => 'point-of-sale/setup/nc-types',
            'label' => 'NC Type',
            'plural' => 'NC Types',
            'icon' => 'alert',
            'intro' => 'Why a bill was not charged — and whether the cost has to be pinned to a department.',
            'fields' => [
                'name' => [
                    'label' => 'NC Type Name',
                    'type' => 'text',
                    'rules' => 'required|string|max:255',
                    'placeholder' => 'Complimentary',
                ],
                'requires_department' => [
                    'label' => 'Requires Department',
                    'type' => 'checkbox',
                    'rules' => 'nullable|boolean',
                    'help' => 'Ask which department carries the cost.',
                    'width' => '220px',
                ],
            ],
        ];
    }
}
