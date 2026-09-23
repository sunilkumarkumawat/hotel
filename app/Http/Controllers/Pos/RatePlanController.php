<?php

namespace App\Http\Controllers\Pos;

use App\Models\Pos\Outlet;
use App\Models\Pos\PosRatePlan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Rate Plan — a price list and the outlets that run it.
 *
 * A plan with no outlets ticked is a plan nobody can sell on, so the form
 * insists on at least one. The prices themselves belong to the menu item
 * screen, which is not built yet; this is the list that screen will hang its
 * columns off.
 */
class RatePlanController extends SetupListController
{
    protected function definition(): array
    {
        return [
            'model' => PosRatePlan::class,
            'route' => 'point-of-sale.setup.rate-plan',
            'permission' => 'point-of-sale/setup/rate-plan',
            'label' => 'Rate Plan',
            'plural' => 'Rate Plans',
            'icon' => 'credit-card',
            'intro' => 'Price lists — à la carte, Happy Hours, banquet — and which outlets each one runs in.',
            'with' => ['outlets'],
            'fields' => [
                'name' => [
                    'label' => 'Name',
                    'type' => 'text',
                    'rules' => 'required|string|max:255',
                    'placeholder' => 'Happy Hours',
                ],
                'outlets' => [
                    'label' => 'Outlets',
                    'type' => 'checkboxes',
                    'options' => $this->outlets(),
                    'rules' => 'required|array|min:1',
                    'item_rules' => 'integer|exists:outlets,id',
                    'help' => 'Where this price list applies.',
                    'current' => fn (Model $row) => $row->exists ? $row->outlets->pluck('id')->all() : [],
                    'display' => fn (Model $row) => $row->outlets->pluck('name')->implode(', ') ?: '—',
                ],
            ],
        ];
    }

    protected function afterSave(Model $row, Request $request): void
    {
        // Only outlets this branch can actually see — a posted id from another
        // property is dropped rather than trusted.
        $allowed = $this->outlets()->keys()->all();

        $row->outlets()->sync(
            array_values(array_intersect(
                array_map('intval', (array) $request->input('outlets', [])),
                $allowed
            ))
        );
    }

    private function outlets()
    {
        return Outlet::query()->forBranch()->orderBy('name')->pluck('name', 'id');
    }
}
