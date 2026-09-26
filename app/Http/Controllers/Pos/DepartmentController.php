<?php

namespace App\Http\Controllers\Pos;

use App\Models\Pos\PosDepartment;

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
