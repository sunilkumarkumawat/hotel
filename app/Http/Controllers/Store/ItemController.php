<?php

namespace App\Http\Controllers\Store;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreCategory;
use App\Models\Store\StoreItem;
use App\Support\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ItemController extends Controller
{
    public function index(Request $request): View
    {
        $branchId = (int) Helper::getActiveBranchId();

        $view = $request->string('show')->toString();
        $view = in_array($view, ['all', 'reorder', 'short'], true) ? $view : 'all';

        $items = StoreItem::query()
            ->forBranch($branchId)
            ->with('category')
            ->when($request->string('q')->toString(), fn ($q, $t) => $q->where(function ($q) use ($t) {
                $q->where('name', 'like', "%{$t}%")->orWhere('code', 'like', "%{$t}%");
            }))
            ->when($request->integer('category'), fn ($q, $c) => $q->where('store_category_id', $c))
            ->when($view === 'reorder', fn ($q) => $q->needingReorder())
            ->when($view === 'short', fn ($q) => $q->where('current_qty', '<', 0))
            ->orderBy('name')
            ->paginate(40)
            ->withQueryString();

        return view('store.items', [
            'items' => $items,
            'categories' => StoreCategory::forBranch($branchId)->active()->orderBy('name')->get(),
            'editing' => $request->integer('edit') ?: null,
            'summary' => Store::summary($branchId),
            'view' => $view,
            'filters' => [
                'q' => $request->string('q')->toString(),
                'category' => $request->integer('category'),
            ],
        ]);
    }

    public function save(Request $request, ?StoreItem $item = null): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();

        if ($item && $item->exists) {
            abort_unless($item->branch_id === null || (int) $item->branch_id === $branchId, 404);
        } else {
            $item = null;
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable', 'string', 'max:40',
                Rule::unique('store_items', 'code')
                    ->where(fn ($q) => $q->where(fn ($inner) => $inner
                        ->whereNull('branch_id')
                        ->orWhere('branch_id', $branchId)))
                    ->ignore($item?->id),
            ],
            'store_category_id' => ['nullable', 'integer', Rule::exists('store_categories', 'id')],
            'unit' => ['required', 'string', 'max:20'],
            'reorder_level' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'opening_qty' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'opening_rate' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'hsn_code' => ['nullable', 'string', 'max:12'],
            'remark' => ['nullable', 'string', 'max:255'],
        ]);

        $data['branch_id'] = $branchId;
        $data['is_ingredient'] = $request->boolean('is_ingredient', true);
        $data['status'] = $request->boolean('status', true) ? 1 : 0;

        if ($item) {
            $openingChanged = (float) $item->opening_qty !== (float) ($data['opening_qty'] ?? 0)
                || (float) $item->opening_rate !== (float) ($data['opening_rate'] ?? 0);

            $item->update($data);
            if ($openingChanged) {
                Store::rebuild($item->id);

                return redirect()
                    ->route('store.items')
                    ->with('warning', 'Opening balance changed — the whole ledger for '
                        . $item->name . ' was recalculated.');
            }
        } else {
            $item = StoreItem::create($data);

            if ((float) $item->opening_qty > 0) {
                $item->update([
                    'current_qty' => $item->opening_qty,
                    'avg_rate' => $item->opening_rate,
                    'last_rate' => $item->opening_rate,
                ]);
            }
        }

        return redirect()->route('store.items')->with('status', $item->name . ' saved.');
    }

    public function destroy(StoreItem $item): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();
        abort_unless($item->branch_id === null || (int) $item->branch_id === $branchId, 404);
        if (DB::table('stock_ledger')->where('store_item_id', $item->id)->exists()) {
            $item->update(['status' => 0]);

            return back()->with('warning', $item->name
                . ' has stock movements, so it was switched off rather than deleted. '
                . 'Its history stays in the ledger.');
        }

        $name = $item->name;
        $item->delete();

        return redirect()->route('store.items')->with('status', $name . ' deleted.');
    }

    public function categories(Request $request): View
    {
        $branchId = (int) Helper::getActiveBranchId();

        return view('store.categories', [
            'rows' => StoreCategory::forBranch($branchId)
                ->withCount('items')
                ->orderBy('sort')
                ->orderBy('name')
                ->get(),
            'editing' => $request->integer('edit') ?: null,
            'departments' => Store::DEPARTMENTS,
        ]);
    }

    public function saveCategory(Request $request, ?StoreCategory $category = null): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();

        if ($category && $category->exists) {
            abort_unless($category->branch_id === null || (int) $category->branch_id === $branchId, 404);
        } else {
            $category = null;
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:20'],
            'department' => ['nullable', Rule::in(array_keys(Store::DEPARTMENTS))],
            'sort' => ['nullable', 'integer', 'min:0', 'max:255'],
        ]);

        $data['branch_id'] = $branchId;
        $data['status'] = $request->boolean('status', true) ? 1 : 0;

        $category ? $category->update($data) : StoreCategory::create($data);

        return redirect()->route('store.categories')->with('status', 'Saved.');
    }

    public function deleteCategory(StoreCategory $category): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();
        abort_unless($category->branch_id === null || (int) $category->branch_id === $branchId, 404);

        if ($category->items()->exists()) {
            return back()->with('error', 'Items are still in this category. Move them first.');
        }

        $category->delete();

        return redirect()->route('store.categories')->with('status', 'Category deleted.');
    }
}
