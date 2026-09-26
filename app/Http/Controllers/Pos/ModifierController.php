<?php

namespace App\Http\Controllers\Pos;

use App\Models\Pos\PosModifier;
use App\Models\Pos\PosModifierGroup;
use App\Models\Pos\PosOrderItemModifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;


class ModifierController extends SetupListController
{
    protected function definition(): array
    {
        return [
            'model' => PosModifier::class,
            'route' => 'point-of-sale.setup.modifiers',
            'permission' => 'point-of-sale/setup/modifiers',
            'label' => 'Modifier',
            'plural' => 'Modifiers',
            'icon' => 'package',
            'intro' => 'One choice inside a group — Extra Cheese, Small, Medium — and what it adds to the price.',
            'bulk' => true,
            'with' => ['group'],
            'order' => 'name',
            'fields' => [
                'name' => [
                    'label' => 'Modifier',
                    'type' => 'text',
                    'rules' => 'required|string|max:255',
                    'placeholder' => 'Extra Cheese',
                ],
                'pos_modifier_group_id' => [
                    'label' => 'Group',
                    'type' => 'select',
                    'options' => $this->groups(),
                    'placeholder' => 'Select',
                    'width' => '190px',
                    'display' => fn (PosModifier $row) => $row->group?->name ?: '—',
                ],
                'price' => [
                    'label' => 'Adds',
                    'type' => 'money',
                    'rules' => 'nullable|numeric|min:0|max:999999',
                    'placeholder' => '0.00',
                    'width' => '120px',
                    'help' => 'Leave blank for a free choice, like "No Onion".',
                    'display' => fn (PosModifier $row) => (float) $row->price > 0
                        ? '+₹ ' . number_format((float) $row->price, 2)
                        : 'Free',
                ],
            ],
        ];
    }

    protected function extraRules(?int $id): array
    {
        return [
            'pos_modifier_group_id' => [
                'required',
                'integer',
                Rule::exists('pos_modifier_groups', 'id')->whereNull('deleted_at'),
            ],
        ];
    }

    protected function beforeSave(array $data, Request $request, ?Model $row): array
    {
        if (array_key_exists('price', $data) && ($data['price'] === null || $data['price'] === '')) {
            $data['price'] = 0;
        }

        return $data;
    }

    protected function inUseBy(Model $row): ?string
    {
        if (PosOrderItemModifier::query()->where('pos_modifier_id', $row->id)->exists()) {
            return 'it has already been added to orders';
        }

        return null;
    }

    private function groups(): array
    {
        return PosModifierGroup::query()->forBranch()->active()->orderBy('name')->pluck('name', 'id')->all();
    }
}
