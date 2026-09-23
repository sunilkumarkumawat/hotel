<?php

namespace App\Http\Controllers\Pos;

use App\Models\Pos\PosMenuCategory;
use App\Models\Pos\PosMenuItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Item Category — the headings on a menu.
 *
 * A row is either a heading or something filed under one. Two levels is
 * deliberately the limit: Beverages → Hot Beverages is how a POS screen is
 * navigated with a thumb, and a third level would be a menu nobody can find
 * anything on.
 */
class ItemCategoryController extends SetupListController
{
    protected function definition(): array
    {
        return [
            'model' => PosMenuCategory::class,
            'route' => 'point-of-sale.setup.item-category',
            'permission' => 'point-of-sale/setup/item-category',
            'label' => 'Item Category',
            'plural' => 'Item Categories',
            'icon' => 'layers',
            'intro' => 'The headings the menu is grouped under, and the sub-headings inside them.',
            'with' => ['parent'],
            'order' => 'name',
            'fields' => [
                'name' => [
                    'label' => 'Name',
                    'type' => 'text',
                    'rules' => 'required|string|max:255',
                    'placeholder' => 'Starters',
                ],
                'type' => [
                    'label' => 'Type',
                    'type' => 'select',
                    'options' => PosMenuCategory::TYPES,
                    'placeholder' => 'Select',
                    'width' => '180px',
                ],
                'parent_id' => [
                    'label' => 'Under',
                    'type' => 'select',
                    'options' => $this->headings(),
                    'placeholder' => '—',
                    'help' => 'Only a sub category needs one.',
                    'width' => '200px',
                ],
            ],
        ];
    }

    protected function extraRules(?int $id): array
    {
        return [
            'type' => [
                'required',
                Rule::in(array_keys(PosMenuCategory::TYPES)),
                // Demoting a heading that already has rows under it would leave
                // them pointing at something that is itself filed elsewhere.
                function (string $attribute, mixed $value, callable $fail) use ($id) {
                    if ($id && $value === 'sub_category'
                        && PosMenuCategory::query()->where('parent_id', $id)->exists()) {
                        $fail('This has sub categories under it, so it cannot become one itself.');
                    }
                },
            ],
            'parent_id' => [
                'nullable',
                'integer',
                'required_if:type,sub_category',
                Rule::notIn(array_filter([$id])),
                Rule::exists('pos_menu_categories', 'id')
                    ->where(fn ($q) => $q->where('type', 'category')->whereNull('deleted_at')),
            ],
        ];
    }

    protected function beforeSave(array $data, Request $request, ?Model $row): array
    {
        // A heading is not under anything. Clearing it here rather than trusting
        // the form means a leftover value from a mis-click cannot survive.
        if (($data['type'] ?? 'category') === 'category') {
            $data['parent_id'] = null;
        }

        return $data;
    }

    protected function inUseBy(Model $row): ?string
    {
        if (PosMenuCategory::query()->where('parent_id', $row->id)->exists()) {
            return 'it still has sub categories under it';
        }

        if (PosMenuItem::query()->where('pos_menu_category_id', $row->id)->exists()) {
            return 'menu items are filed under it';
        }

        return null;
    }

    /** Headings a sub category may be filed under. */
    private function headings()
    {
        return PosMenuCategory::query()
            ->forBranch()
            ->headings()
            ->orderBy('name')
            ->pluck('name', 'id');
    }
}
