<?php

namespace App\Http\Controllers\Store;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Pos\PosMenuItem;
use App\Models\Store\Recipe;
use App\Models\Store\RecipeItem;
use App\Models\Store\StoreItem;
use App\Support\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * What a dish is made of, and therefore what it costs.
 *
 * The list is really a costing report: every recipe with its cost per portion
 * next to the menu price it is sold at, and the margin between them. That last
 * column is the reason anybody opens this screen.
 *
 * Costs come from the store's moving average, not from the last purchase, so
 * one expensive delivery does not make the menu look unprofitable for a week.
 */
class RecipeController extends Controller
{
    /** GET store/recipes */
    public function index(Request $request): View
    {
        $branchId = (int) Helper::getActiveBranchId();

        $recipes = Recipe::query()
            ->forBranch($branchId)
            ->with(['menuItem', 'lines.item'])
            ->when($request->string('q')->toString(), fn ($q, $t) => $q->where('name', 'like', "%{$t}%"))
            ->orderBy('name')
            ->get()
            ->map(function (Recipe $recipe) {
                $costing = Store::recipeCost($recipe->id);

                $recipe->cost = $costing['per_portion'];
                $recipe->missing = $costing['missing'];
                $recipe->price = (float) ($recipe->menuItem?->price ?? 0);
                /*
                 * Food cost as a percentage of the selling price — the number
                 * a chef is judged on. Meaningless without a price, so it is
                 * null rather than zero when the recipe is not tied to one.
                 */
                $recipe->food_cost = $recipe->price > 0
                    ? round($recipe->cost / $recipe->price * 100, 1)
                    : null;

                return $recipe;
            });

        return view('store.recipes', [
            'recipes' => $recipes,
            'term' => $request->string('q')->toString(),
        ]);
    }

    /** GET store/recipes/new  ·  GET store/recipes/{recipe} */
    public function edit(?Recipe $recipe = null): View
    {
        $branchId = (int) Helper::getActiveBranchId();

        if ($recipe && $recipe->exists) {
            abort_unless((int) $recipe->branch_id === $branchId, 404);
            $recipe->load('lines.item');
        } else {
            $recipe = null;
        }

        return view('store.recipe', [
            'recipe' => $recipe,
            'costing' => $recipe ? Store::recipeCost($recipe->id) : null,
            'items' => StoreItem::forBranch($branchId)->active()->ingredients()->orderBy('name')->get(),
            'menuItems' => PosMenuItem::query()
                ->forBranch($branchId)
                ->active()
                ->orderBy('name')
                ->get(['id', 'name', 'price']),
        ]);
    }

    /** POST store/recipes  ·  PUT store/recipes/{recipe} */
    public function save(Request $request, ?Recipe $recipe = null): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();

        if ($recipe && $recipe->exists) {
            abort_unless((int) $recipe->branch_id === $branchId, 404);
        } else {
            $recipe = null;
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'pos_item_id' => ['nullable', 'integer'],
            'yield_qty' => ['required', 'numeric', 'min:0.001', 'max:99999'],
            'yield_unit' => ['required', 'string', 'max:20'],
            'method' => ['nullable', 'string', 'max:5000'],

            'lines' => ['required', 'array', 'min:1'],
            'lines.*.store_item_id' => ['required', 'integer', Rule::exists('store_items', 'id')],
            'lines.*.qty' => ['required', 'numeric', 'min:0.0001', 'max:99999'],
            'lines.*.remark' => ['nullable', 'string', 'max:255'],
        ]);

        // The same ingredient twice is a typo that quietly doubles a cost.
        $ids = array_column($data['lines'], 'store_item_id');

        if (count($ids) !== count(array_unique($ids))) {
            return back()->withInput()->with('error',
                'The same ingredient is on this recipe twice. Put the whole quantity on one line.');
        }

        $recipe = DB::transaction(function () use ($data, $recipe, $branchId, $request) {
            $header = [
                'branch_id' => $branchId,
                'name' => $data['name'],
                'pos_item_id' => $data['pos_item_id'] ?? null,
                'yield_qty' => $data['yield_qty'],
                'yield_unit' => $data['yield_unit'],
                'method' => $data['method'] ?? null,
                'status' => $request->boolean('status', true) ? 1 : 0,
            ];

            if ($recipe) {
                $recipe->update($header);
                $recipe->lines()->delete();
            } else {
                $recipe = Recipe::create($header + ['created_by' => $request->user()?->user_id]);
            }

            foreach ($data['lines'] as $line) {
                RecipeItem::create([
                    'recipe_id' => $recipe->id,
                    'store_item_id' => (int) $line['store_item_id'],
                    'qty' => round((float) $line['qty'], 4),
                    'remark' => $line['remark'] ?? null,
                ]);
            }

            return $recipe;
        });

        $costing = Store::recipeCost($recipe->id);

        return redirect()
            ->route('store.recipes.edit', $recipe)
            ->with($costing['missing'] > 0 ? 'warning' : 'status',
                $costing['missing'] > 0
                    ? 'Saved — but ' . $costing['missing'] . ' ' .
                        \Illuminate\Support\Str::plural('ingredient', $costing['missing'])
                        . ' have never been bought, so they cost nothing and the dish looks cheaper than it is.'
                    : $recipe->name . ' costs ₹' . number_format($costing['per_portion'], 2)
                        . ' a ' . $recipe->yield_unit . '.');
    }

    /** DELETE store/recipes/{recipe} */
    public function destroy(Recipe $recipe): RedirectResponse
    {
        abort_unless((int) $recipe->branch_id === (int) Helper::getActiveBranchId(), 404);

        $name = $recipe->name;
        $recipe->lines()->delete();
        $recipe->delete();

        return redirect()->route('store.recipes')->with('status', $name . ' deleted.');
    }
}
