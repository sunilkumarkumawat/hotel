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

/**
 * What the store keeps, and what it is worth.
 *
 * A list you type straight into: the top row adds, and Edit turns a row into
 * inputs where it already sits. The quantity column is NOT editable — stock
 * moves through documents, never by somebody typing a new number over the old
 * one, or the ledger would stop being able to explain the balance.
 *
 * The one exception is the opening quantity, which is what a store starts with
 * on the day it is set up. It is a property of the item rather than a
 * movement, and it is the first number in every rebuild.
 */
class ItemController extends Controller
{
    /** GET store/items */
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

    /** POST store/items  ·  PUT store/items/{item} */
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
            'code' => ['nullable', 'string', 'max:40'],
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

            /*
             * Changing the opening balance changes every balance after it, so
             * the ledger is replayed rather than patched. This is the only
             * screen that can move a quantity without posting a document, and
             * it is why rebuild() exists.
             */
            if ($openingChanged) {
                Store::rebuild($item->id);

                return redirect()
                    ->route('store.items')
                    ->with('warning', 'Opening balance changed — the whole ledger for '
                        . $item->name . ' was recalculated.');
            }
        } else {
            $item = StoreItem::create($data);

            // A new item's opening balance is its first balance, full stop.
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

    /** DELETE store/items/{item} */
    public function destroy(StoreItem $item): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();
        abort_unless($item->branch_id === null || (int) $item->branch_id === $branchId, 404);

        /*
         * An item with movements is history. Deleting it would leave ledger
         * rows pointing at nothing and a valuation nobody could explain, so
         * the answer is to switch it off instead — which takes it out of every
         * dropdown and leaves the books intact.
         */
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

    /* ── Categories ────────────────────────────────────────────────────── */

    /** GET store/categories */
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

    /** POST store/categories  ·  PUT store/categories/{category} */
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

    /** DELETE store/categories/{category} */
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
