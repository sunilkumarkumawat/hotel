<?php

namespace App\Http\Controllers\Pos;

use App\Models\Pos\PosDepartment;

/**
 * Department — who cooks it.
 *
 * A KOT for a drink prints at the bar and a KOT for a curry prints in the
 * kitchen, so this list is what routes a ticket. It is also the answer an NC
 * type asks for when a free meal has to land on somebody's cost centre.
 */
class DepartmentController extends SetupListController
{
    protected function definition(): array
    {
        return [
            'model' => PosDepartment::class,
            'route' => 'point-of-sale.setup.department',
            'permission' => 'point-of-sale/setup/department',
            'label' => 'Department',
            'plural' => 'Departments',
            'icon' => 'shield',
            'intro' => 'Kitchen, Bar, Room Service, Bakery — where a ticket goes and whose cost a comp is.',
            'fields' => [
                'name' => [
                    'label' => 'Department Name',
                    'type' => 'text',
                    'rules' => 'required|string|max:255',
                    'placeholder' => 'Kitchen',
                ],
            ],
        ];
    }
}
