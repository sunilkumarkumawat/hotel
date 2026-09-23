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
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Items — the menu itself.
 *
 * Everything the till can sell is a row here. Without it the POS screen is a
 * grid of empty buttons, which is why this screen exists even though the old
 * system keeps its item list somewhere else entirely.
 *
 * Price is the everyday price. The small boxes under Plan prices are the
 * exceptions — leave one empty and that plan charges the everyday price, so a
 * Happy Hours plan can be switched on having priced only the six drinks it
 * actually discounts.
 */
class ItemController extends SetupListController
{
    /** Where uploaded item photos live on the public disk. */
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
            /*
             * A menu arrives as a list, not one dish at a time, so this screen
             * takes as many rows as somebody wants to type and saves them in
             * one go. The other Setup lists are six rows long and keep the
             * simpler one-at-a-time screen.
             */
            'bulk' => true,
            /*
             * A thumbnail column, read and written entirely outside the
             * bulk-save mechanism above — see photo() below. Twenty file
             * pickers in one big "save all rows" form is not a screen anybody
             * wants, so a picture is only ever added to a row that already
             * exists, one click at a time.
             */
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
                    'label' => 'Code',
                    'type' => 'text',
                    'rules' => 'nullable|string|max:30',
                    'placeholder' => 'MD01',
                    'width' => '110px',
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
                /*
                 * The tax this dish carries — and "No Tax" is what a new row
                 * opens on, because tax is something the hotel adds when it
                 * wants to, never something a screen adds on its own.
                 *
                 * Nothing here taxes anything by itself. The till decides:
                 * an order set to "tax per item" reads this, and an order set
                 * to No Tax ignores it however it is filled in. So a menu can
                 * carry its GST rates for the day somebody starts charging
                 * them without a single bill changing in the meantime.
                 */
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
                    // What the row shows when it is not being edited, and what
                    // the boxes are filled with when it is.
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
            // Blank is a real answer here — it means No Tax — so the rule is
            // nullable and an id that is not a live tax is refused rather than
            // stored and wondered about later.
            'tax_master_id' => ['nullable', 'integer', Rule::exists('tax_master', 'id')],
        ];
    }

    /**
     * Write the plan prices.
     *
     * An empty box is not "free" — it is "this plan has nothing to say about
     * this item", so it is removed rather than stored as zero. That distinction
     * is what makes the fallback to the everyday price work.
     */
    protected function afterSave(Model $row, Request $request): void
    {
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

        // Only groups this branch can actually see — a posted id from another
        // property is dropped rather than trusted.
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

    /**
     * POST point-of-sale/setup/items/{item}/photo
     *
     * On its own, outside store()/update() and the bulk save — a picture is a
     * click on a thumbnail, not another box in a row of text fields. The old
     * file is deleted once the new one is safely written, same as an outlet's
     * logo.
     */
    public function photo(Request $request, int $item): RedirectResponse
    {
        $row = PosMenuItem::query()->forBranch()->findOrFail($item);

        $request->validate([
            'photo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ], [
            'photo.max' => 'The photo must be 2 MB or smaller.',
        ]);

        $old = $row->photo;
        $row->photo = $request->file('photo')->store(self::PHOTO_DIR, 'public');
        $row->save();

        if ($old && $old !== $row->photo) {
            Storage::disk('public')->delete($old);
        }

        return back()->with('status', "Photo saved for \"{$row->name}\".");
    }

    /** What the row reads when nobody is editing it. */
    private function planSummary(PosMenuItem $row): string
    {
        if ($row->ratePlans->isEmpty()) {
            return '—';
        }

        return $row->ratePlans
            ->map(fn ($plan) => $plan->name . ' ₹' . rtrim(rtrim(number_format((float) $plan->pivot->price, 2), '0'), '.'))
            ->implode(', ');
    }

    /** Every heading and sub-heading, sub-headings shown under their parent. */
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
     * The taxes somebody may attach to a dish, by id.
     *
     * "No Tax" is not in this list: it is the select's own empty option, so an
     * item with no tax stores a NULL rather than a word in a column that holds
     * ids. Everything else from Masters -> Tax is here, including the ones
     * switched off — marked, so nobody picks one by accident, but present, so
     * that opening an old item for editing cannot silently re-point it at
     * something it was never set to.
     *
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
