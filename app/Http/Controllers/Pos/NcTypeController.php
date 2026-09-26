<?php

namespace App\Http\Controllers\Pos;

use App\Models\Pos\PosNcType;


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
