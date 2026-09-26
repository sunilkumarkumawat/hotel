<?php

namespace App\Http\Controllers\Pos;

use App\Helpers\Helper;
use App\Models\Master\TaxMaster;
use App\Models\Pos\PosDepartment;
use App\Models\Pos\PosMenuCategory;
use App\Models\Pos\PosMenuItem;
use App\Models\Pos\PosModifierGroup;
use App\Models\Pos\PosOrderItem;
use App\Models\Pos\PosRatePlan;
use App\Support\Tax;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ItemController extends SetupListController
{
    private const PHOTO_DIR = 'pos-items';

    protected function definition(): array
    {
        return [
            'model' => PosMenuItem::class,
            'route' => 'point-of-sale.setup.items',
            'permission' => 'point-of-sale/setup/items',
            'label' => 'Item',
            'plural' => 'Items',
            'icon' => 'bag',
            'intro' => 'Everything the till can sell: what it is called, what it costs and which kitchen cooks it.',
            'bulk' => true,
            'photos' => true,
            'with' => ['category', 'department', 'ratePlans', 'modifierGroups'],
            'order' => 'name',
            'fields' => [
                'name' => [
                    'label' => 'Item',
                    'type' => 'text',
                    'rules' => 'required|string|max:255',
                    'placeholder' => 'Masala Dosa',
                ],
                'code' => [
                    'label' => 'Code / Barcode',
                    'type' => 'text',
                    'placeholder' => 'MD01',
                    'width' => '110px',
                    'help' => 'Shown on the till and matched when a barcode is scanned there.',
                ],
                'pos_menu_category_id' => [
                    'label' => 'Category',
                    'type' => 'select',
                    'options' => $this->categories(),
                    'placeholder' => 'Select',
                    'width' => '190px',
                    'display' => fn (PosMenuItem $row) => $row->category?->name ?: '—',
                ],
                'pos_department_id' => [
                    'label' => 'Department',
                    'type' => 'select',
                    'options' => $this->departments(),
                    'placeholder' => '—',
                    'width' => '160px',
                    'help' => 'Which ticket it prints on.',
                    'display' => fn (PosMenuItem $row) => $row->department?->name ?: '—',
                ],
                'price' => [
                    'label' => 'Price',
                    'type' => 'money',
                    'rules' => 'required|numeric|min:0|max:999999',
                    'width' => '120px',
                    'display' => fn (PosMenuItem $row) => '₹ ' . number_format((float) $row->price, 2),
                ],
                'is_veg' => [
                    'label' => 'Type',
                    'type' => 'select',
                    'options' => [1 => 'Veg', 0 => 'Non-Veg'],
                    'width' => '120px',
                ],
                'tax_master_id' => [
                    'label' => 'Tax',
                    'type' => 'select',
                    'options' => $this->taxes(),
                    'placeholder' => 'No Tax',
                    'width' => '150px',
                    'help' => 'Used only when the till is set to tax per item.',
                    'display' => fn (PosMenuItem $row) => $this->taxes()[$row->tax_master_id] ?? 'No Tax',
                    'current' => fn (PosMenuItem $row) => $row->tax_master_id,
                ],
                'plan_price' => [
                    'label' => 'Plan prices',
                    'type' => 'prices',
                    'options' => $this->plans(),
                    'rules' => 'nullable|array',
                    'item_rules' => 'nullable|numeric|min:0|max:999999',
                    'width' => '220px',
                    'display' => fn (PosMenuItem $row) => $this->planSummary($row),
                    'current' => fn (PosMenuItem $row) => $row->ratePlans
                        ->mapWithKeys(fn ($plan) => [$plan->id => (float) $plan->pivot->price])
                        ->all(),
                ],
                'modifier_groups' => [
                    'label' => 'Modifiers',
                    'type' => 'checkboxes',
                    'options' => $this->modifierGroups(),
                    'rules' => 'nullable|array',
                    'item_rules' => 'integer|exists:pos_modifier_groups,id',
                    'width' => '200px',
                    'help' => 'Which add-on groups a guest may pick for this dish.',
                    'current' => fn (PosMenuItem $row) => $row->exists ? $row->modifierGroups->pluck('id')->all() : [],
                    'display' => fn (PosMenuItem $row) => $row->modifierGroups->pluck('name')->implode(', ') ?: '—',
                ],
            ],
        ];
    }

    protected function extraRules(?int $id): array
    {
        $branch = Helper::getActiveBranchId();

        return [
            'pos_menu_category_id' => [
                'required',
                'integer',
                Rule::exists('pos_menu_categories', 'id')->whereNull('deleted_at'),
            ],
            'pos_department_id' => [
                'nullable',
                'integer',
                Rule::exists('pos_departments', 'id')->whereNull('deleted_at'),
            ],
            'is_veg' => ['required', Rule::in([0, 1])],
            'tax_master_id' => ['nullable', 'integer', Rule::exists('tax_master', 'id')],
            'code' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('pos_menu_items', 'code')
                    ->where(fn ($q) => $branch === null
                        ? $q->whereNull('branch_id')->whereNull('deleted_at')
                        : $q->where('branch_id', $branch)->whereNull('deleted_at'))
                    ->ignore($id),
            ],
        ];
    }
    protected function afterSave(Model $row, Request $request): void
    {
        $this->storePhoto($row, $request, self::PHOTO_DIR);

        $plans = $this->plans();
        $posted = (array) $request->input('plan_price', []);
        $keep = [];

        foreach ($plans as $planId => $ignored) {
            $value = $posted[$planId] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            $keep[$planId] = ['price' => round((float) $value, 2)];
        }

        $row->ratePlans()->sync($keep);

        $allowedGroups = $this->modifierGroups();

        $row->modifierGroups()->sync(
            array_values(array_intersect(
                array_map('intval', (array) $request->input('modifier_groups', [])),
                array_keys($allowedGroups)
            ))
        );
    }

    protected function inUseBy(Model $row): ?string
    {
        if (PosOrderItem::query()->where('pos_menu_item_id', $row->id)->exists()) {
            return 'it is on orders that have already been taken';
        }

        return null;
    }
    public function photo(Request $request, int $item): RedirectResponse
    {
        $row = PosMenuItem::query()->forBranch()->findOrFail($item);

        $request->validate([
            'photo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ], [
            'photo.max' => 'The photo must be 2 MB or smaller.',
        ]);

        $this->storePhoto($row, $request, self::PHOTO_DIR);

        return back()->with('status', "Photo saved for \"{$row->name}\".");
    }
    private function planSummary(PosMenuItem $row): string
    {
        if ($row->ratePlans->isEmpty()) {
            return '—';
        }

        return $row->ratePlans
            ->map(fn ($plan) => $plan->name . ' ₹' . rtrim(rtrim(number_format((float) $plan->pivot->price, 2), '0'), '.'))
            ->implode(', ');
    }
    private function categories(): array
    {
        return PosMenuCategory::query()
            ->forBranch()
            ->with('parent')
            ->orderBy('name')
            ->get()
            ->sortBy(fn (PosMenuCategory $row) => strtolower(($row->parent?->name ?? $row->name) . ' ' . $row->name))
            ->mapWithKeys(fn (PosMenuCategory $row) => [
                $row->id => $row->parent ? $row->parent->name . ' › ' . $row->name : $row->name,
            ])
            ->all();
    }

    private function departments(): array
    {
        return PosDepartment::query()->forBranch()->active()->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @return array<int, string>
     */
    private function taxes(): array
    {
        return TaxMaster::query()
            ->forBranch(Helper::getActiveBranchId())
            ->orderBy('percent')
            ->get(['id', 'name', 'percent', 'status'])
            ->mapWithKeys(fn (TaxMaster $tax) => [
                (int) $tax->id => Tax::describe($tax) . ((int) $tax->status === 1 ? '' : ' (off)'),
            ])
            ->all();
    }

    private function plans(): array
    {
        return PosRatePlan::query()->forBranch()->active()->orderBy('name')->pluck('name', 'id')->all();
    }

    private function modifierGroups(): array
    {
        return PosModifierGroup::query()->forBranch()->active()->orderBy('name')->pluck('name', 'id')->all();
    }
}
