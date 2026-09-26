<?php

namespace App\Http\Controllers\Pos;

use App\Models\Pos\PosSteward;

/**
 * Stewards — the waiting staff a bill can be traced back to.
 *
 * Kept apart from Users on purpose: a steward takes orders but very often has
 * no login, and giving every waiter a user account just to put a name on a KOT
 * would be a security problem dressed up as a feature.
 */
class StewardController extends SetupListController
{
    protected function definition(): array
    {
        return [
            'model' => PosSteward::class,
            'route' => 'point-of-sale.setup.stewards',
            'permission' => 'point-of-sale/setup/stewards',
            'label' => 'Steward',
            'plural' => 'Stewards',
            'icon' => 'user',
            'intro' => 'Whoever takes an order is named on it — for service charge, for tips, and for tracing a mistake.',
            'fields' => [
                'name' => [
                    'label' => 'Name',
                    'type' => 'text',
                    'rules' => 'required|string|max:255',
                    'placeholder' => 'Ramesh',
                ],
                'phone' => [
                    'label' => 'Phone Number',
                    'type' => 'text',
                    'rules' => 'nullable|string|max:30',
                    'placeholder' => '98765 43210',
                    'width' => '220px',
                ],
            ],
        ];
    }
}
