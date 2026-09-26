<?php

namespace App\Http\Controllers\Pos;

use App\Models\Pos\Outlet;
use App\Models\Pos\PosRatePlan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;


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
