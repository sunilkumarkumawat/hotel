<?php

namespace App\Http\Controllers\Pos;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The shared engine behind the small Setup lists.
 *
 * Item Category, Rate Plan, Department, Stewards and NC Types are the same
 * screen wearing five sets of column headings: a table you type straight into,
 * a row at the top for a new entry, and Edit turning a row into inputs where it
 * already sits.
 *
 * That editing is done by the server, not by JavaScript. `?edit=7` re-renders
 * row 7 as inputs, Update posts it, Cancel is a link back. It costs one page
 * load per edit and buys a screen that works with scripts blocked, keeps the
 * browser's Back button honest, and shows validation errors on the exact row
 * they belong to.
 *
 * A subclass supplies `definition()` and nothing else unless it has to — Rate
 * Plan overrides `afterSave()` to write its outlets, Item Category overrides
 * `extraRules()` to insist a sub-category names a parent.
 */
abstract class SetupListController extends Controller
{
    /**
     * Field types that are not columns on the model.
     *
     * They are validated like anything else, then lifted out of the data before
     * it reaches the row — a subclass writes them in `afterSave()`, once the row
     * has an id to hang a relation off.
     */
    private const PSEUDO_TYPES = ['checkboxes', 'prices'];

    /**
     * Field types somebody has to type something into.
     *
     * Used to tell an untouched blank row from one being filled in. A select
     * cannot be part of that test: it posts its first option whether or not
     * anybody looked at it, so a row of nothing but selects would count as
     * typed in and then fail validation for having no name.
     */
    private const TYPED_TYPES = ['text', 'money', 'number', 'textarea'];

    /**
     * What this list is and what it holds.
     *
     * Keys: model, route, permission, label, plural, icon, intro, fields, and
     * optionally order, with, empty.
     *
     * @return array<string, mixed>
     */
    abstract protected function definition(): array;

    public function index(Request $request): View
    {
        $def = $this->definition();
        $model = $def['model'];

        $term = trim($request->string('q')->toString());
        $showDeleted = $request->boolean('deleted');
        $editing = $request->integer('edit') ?: null;

        $rows = $model::query()
            ->when($showDeleted, fn ($q) => $q->withTrashed())
            ->forBranch()
            ->with($def['with'] ?? [])
            ->when($term, fn ($q) => $q->where('name', 'like', "%{$term}%"))
            ->orderBy($def['order'] ?? 'name')
            ->paginate(50)
            ->withQueryString();

        return view('pos.setup.list', [
            'def' => $def,
            'rows' => $rows,
            'term' => $term,
            'showDeleted' => $showDeleted,
            'editing' => $editing,
            'counts' => [
                'all' => $model::query()->forBranch()->count(),
                'active' => $model::query()->forBranch()->where('status', 1)->count(),
                'deleted' => $model::query()->onlyTrashed()->forBranch()->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $def = $this->definition();
        $data = $this->validated($request, null);

        /** @var Model $row */
        $row = new $def['model']($this->beforeSave($data, $request, null));
        $row->branch_id = Helper::getActiveBranchId();
        $row->status = $request->boolean('status', true) ? 1 : 0;
        $row->save();

        $this->afterSave($row, $request);

        return redirect()
            ->route($def['route'], $this->keep($request))
            ->with('status', "{$def['label']} \"{$row->name}\" has been added.");
    }

    /**
     * Several rows in one save.
     *
     * Typing a menu in one item at a time means a page load between every
     * dosa, and a hundred-line menu is a hundred page loads. This takes as many
     * rows as are on screen, ignores the ones nobody touched, and either saves
     * them all or saves none — a batch that half-worked would leave somebody
     * guessing which half.
     *
     * Only lists whose definition says `bulk` have this; the others keep the
     * one-row-at-a-time screen, which is right for a list of six departments.
     */
    public function storeMany(Request $request): RedirectResponse
    {
        $def = $this->definition();

        abort_unless($def['bulk'] ?? false, 404);

        $filled = [];

        foreach ((array) $request->input('rows', []) as $index => $row) {
            if (is_array($row) && $this->typedIn($row)) {
                $filled[$index] = $row;
            }
        }

        if ($filled === []) {
            return back()
                ->withInput()
                ->with('error', 'None of those rows had anything typed into them, so nothing was added.');
        }

        $rules = [];
        $labels = [];

        foreach (array_keys($filled) as $index) {
            foreach ($this->rules(null) as $field => $rule) {
                $rules['rows.' . $index . '.' . $field] = $rule;

                // "rows.0.pos_menu_category_id is required" is not a sentence
                // anybody can act on. "Row 1 category" is.
                $labels['rows.' . $index . '.' . $field] =
                    'row ' . ((int) $index + 1) . ' ' . strtolower($def['fields'][$field]['label'] ?? $field);
            }
        }

        $validator = Validator::make($request->all(), $rules, [], $labels);

        /*
         * Two new rows calling themselves the same thing pass every rule above
         * — each one is unique against the table, because neither is in it yet.
         * They are only in conflict with each other, which nothing but this
         * can see.
         */
        $validator->after(function ($validator) use ($filled) {
            $seen = [];

            foreach ($filled as $index => $row) {
                $name = strtolower(trim((string) ($row['name'] ?? '')));

                if ($name === '') {
                    continue;
                }

                if (isset($seen[$name])) {
                    $validator->errors()->add(
                        'rows.' . $index . '.name',
                        'Row ' . ((int) $index + 1) . ' repeats a name already on row ' . ($seen[$name] + 1) . '.'
                    );

                    continue;
                }

                $seen[$name] = (int) $index;
            }
        });

        $validator->validate();

        $saved = 0;

        foreach ($filled as $row) {
            // Each row is handled as if it had been posted on its own, so
            // beforeSave() and afterSave() — which read the request — work
            // unchanged whether one row was saved or twenty.
            $only = Request::create($request->fullUrl(), 'POST', $row);

            /** @var Model $record */
            $record = new $def['model']($this->beforeSave($this->fieldValues($only), $only, null));
            $record->branch_id = Helper::getActiveBranchId();
            $record->status = $only->boolean('status', true) ? 1 : 0;
            $record->save();

            $this->afterSave($record, $only);

            $saved++;
        }

        return redirect()
            ->route($def['route'], $this->keep($request))
            ->with('status', $saved === 1
                ? "1 {$def['label']} has been added."
                : "{$saved} " . strtolower($def['plural']) . ' have been added.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $def = $this->definition();
        $row = $def['model']::query()->forBranch()->findOrFail($id);

        $data = $this->validated($request, $id);

        $row->fill($this->beforeSave($data, $request, $row));
        $row->status = $request->boolean('status', true) ? 1 : 0;
        $row->save();

        $this->afterSave($row, $request);

        return redirect()
            ->route($def['route'], $this->keep($request))
            ->with('status', "{$def['label']} \"{$row->name}\" has been saved.");
    }

    public function destroy(int $id): RedirectResponse
    {
        $def = $this->definition();
        $row = $def['model']::query()->forBranch()->findOrFail($id);

        if ($blocker = $this->inUseBy($row)) {
            return back()->with('error', "Cannot delete \"{$row->name}\" — {$blocker}.");
        }

        $name = $row->name;
        $row->delete();

        return back()->with(
            'status',
            "{$def['label']} \"{$name}\" has been deleted. Show deleted brings it back."
        );
    }

    public function restore(int $id): RedirectResponse
    {
        $def = $this->definition();
        $row = $def['model']::query()->onlyTrashed()->forBranch()->findOrFail($id);
        $row->restore();

        return back()->with('status', "{$def['label']} \"{$row->name}\" is back.");
    }

    /*
    |--------------------------------------------------------------------------
    | Hooks — a subclass overrides only what it needs
    |--------------------------------------------------------------------------
    */

    /** Rules a subclass wants on top of the ones its fields declare. */
    protected function extraRules(?int $id): array
    {
        return [];
    }

    /** Last look at the data before it reaches the model. */
    protected function beforeSave(array $data, Request $request, ?Model $row): array
    {
        return $data;
    }

    /** Relations to write once the row has an id. */
    protected function afterSave(Model $row, Request $request): void
    {
        //
    }

    /** A sentence naming what still points at this row, or null. */
    protected function inUseBy(Model $row): ?string
    {
        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /** @return array<string, mixed> */
    private function validated(Request $request, ?int $id): array
    {
        $request->validate($this->rules($id));

        return $this->fieldValues($request);
    }

    /**
     * Every rule this list applies to one row, keyed by field name.
     *
     * Kept separate from validating so the bulk save can take the same rules
     * and re-key them under `rows.3.…` — one definition of what a valid row is,
     * whether it arrives on its own or with nineteen others.
     *
     * @return array<string, mixed>
     */
    private function rules(?int $id): array
    {
        $def = $this->definition();
        $branch = Helper::getActiveBranchId();
        $rules = ['status' => 'nullable|boolean'];

        foreach ($def['fields'] as $name => $field) {
            // A pseudo-field like Rate Plan's outlets or an item's plan prices
            // is stored through a relation, so it never reaches the model's own
            // columns.
            if (in_array($field['type'] ?? 'text', self::PSEUDO_TYPES, true)) {
                $rules[$name] = $field['rules'] ?? 'nullable|array';
                $rules[$name . '.*'] = $field['item_rules'] ?? 'integer';

                continue;
            }

            $rules[$name] = $field['rules'] ?? 'nullable|string|max:255';
        }

        /*
         * No two live rows in a branch may share a name — these lists are
         * picked from by name and nothing else. A deleted row is allowed to
         * hold a name that has since been reused, so restoring is never blocked
         * by something the user cannot see.
         */
        $name = $rules['name'] ?? 'required|string|max:255';
        $name = is_array($name) ? $name : explode('|', $name);

        $name[] = Rule::unique((new $def['model'])->getTable(), 'name')
            ->where(fn ($q) => $branch === null
                ? $q->whereNull('branch_id')->whereNull('deleted_at')
                : $q->where('branch_id', $branch)->whereNull('deleted_at'))
            ->ignore($id);

        $rules['name'] = $name;

        return array_merge($rules, $this->extraRules($id));
    }

    /**
     * The row's own columns, read off a request that has already passed.
     *
     * Built from the definition rather than from whatever was posted, so a
     * hand-crafted form cannot smuggle an extra column into a mass assignment.
     * A field the form did not send at all is left out entirely — that is the
     * difference between "set this to nothing" and "this was not on screen",
     * and an update must not wipe a column it never showed.
     *
     * @return array<string, mixed>
     */
    private function fieldValues(Request $request): array
    {
        $data = [];

        foreach ($this->definition()['fields'] as $name => $field) {
            $type = $field['type'] ?? 'text';

            if (in_array($type, self::PSEUDO_TYPES, true)) {
                continue;
            }

            // An unticked box posts nothing at all, so it has to be read as a
            // decision rather than as an absence.
            if ($type === 'checkbox') {
                $data[$name] = $request->boolean($name) ? 1 : 0;

                continue;
            }

            if (! $request->has($name)) {
                continue;
            }

            $value = $request->input($name);

            // An empty select or an empty money box is "nothing chosen", which
            // is a NULL — storing '' would make a foreign key of 0.
            $data[$name] = $value === '' ? null : $value;
        }

        return $data;
    }

    /**
     * Has anybody actually typed into this row, or is it a blank one they left?
     *
     * Only the fields somebody has to type into count. A select posts its first
     * option whether or not it was ever looked at, so counting selects would
     * make every empty row on screen look like an item waiting to be saved.
     *
     * @param  array<string, mixed>  $row
     */
    private function typedIn(array $row): bool
    {
        foreach ($this->definition()['fields'] as $name => $field) {
            $value = $row[$name] ?? null;

            if (is_array($value)) {
                // A plan price box with a number in it is somebody typing.
                if (array_filter($value, fn ($each) => $each !== null && $each !== '') !== []) {
                    return true;
                }

                continue;
            }

            if (! in_array($field['type'] ?? 'text', self::TYPED_TYPES, true)) {
                continue;
            }

            if (trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }

    /** The filters the user was looking at, carried through a save. */
    private function keep(Request $request): array
    {
        return array_filter([
            'q' => trim($request->string('q')->toString()),
            'deleted' => $request->boolean('deleted') ? 1 : null,
            'outlet' => $request->integer('outlet') ?: null,
        ]);
    }
}
