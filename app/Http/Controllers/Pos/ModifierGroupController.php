<?php

namespace App\Http\Controllers\Pos;

use App\Models\Pos\PosModifier;
use App\Models\Pos\PosModifierGroup;
use Illuminate\Database\Eloquent\Model;


class ModifierGroupController extends SetupListController
{
    protected function definition(): array
    {
        return [
            'model' => PosModifierGroup::class,
            'route' => 'point-of-sale.setup.modifier-groups',
            'permission' => 'point-of-sale/setup/modifier-groups',
            'label' => 'Modifier Group',
            'plural' => 'Modifier Groups',
            'icon' => 'grid',
            'intro' => 'A question the till asks when a dish is added — Size, Spice Level, Toppings — and how many answers it takes.',
            'fields' => [
                'name' => [
                    'label' => 'Group',
                    'type' => 'text',
                    'rules' => 'required|string|max:255',
                    'placeholder' => 'Toppings',
                ],
                'selection_type' => [
                    'label' => 'Pick',
                    'type' => 'select',
                    'options' => PosModifierGroup::TYPES,
                    'rules' => 'required|in:single,multiple',
                    'width' => '140px',
                    'display' => fn (PosModifierGroup $row) => PosModifierGroup::TYPES[$row->selection_type] ?? $row->selection_type,
                ],
                'is_required' => [
                    'label' => 'Required',
                    'type' => 'checkbox',
                    'help' => 'Must be answered before the dish can be added.',
                    'width' => '130px',
                    'display' => fn (PosModifierGroup $row) => $row->is_required ? 'Yes' : 'No',
                ],
                'max_select' => [
                    'label' => 'Max choices',
                    'type' => 'text',
                    'rules' => 'nullable|integer|min:1|max:50',
                    'placeholder' => 'Any number',
                    'help' => 'Only used when Pick is "Any number".',
                    'width' => '130px',
                    'display' => fn (PosModifierGroup $row) => $row->selection_type === 'multiple' && $row->max_select
                        ? $row->max_select
                        : '—',
                ],
            ],
        ];
    }

    protected function inUseBy(Model $row): ?string
    {
        if (PosModifier::query()->where('pos_modifier_group_id', $row->id)->exists()) {
            return 'it still has modifiers under it — delete those first';
        }

        return null;
    }
}
