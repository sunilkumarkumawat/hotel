<?php

namespace App\Http\Controllers\Pos;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

abstract class SetupListController extends Controller
{

    private const PSEUDO_TYPES = ['checkboxes', 'prices'];
    private const TYPED_TYPES = ['text', 'money', 'number', 'textarea'];

    /**
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

    public function storeMany(Request $request): RedirectResponse
    {
        $def = $this->definition();

        abort_unless($def['bulk'] ?? false, 404);

        $filled = [];

        foreach ((array) $request->input('rows', []) as $index => $row) {
            $hasPhoto = $request->hasFile('rows.' . $index . '.photo');

            if (is_array($row) && ($this->typedIn($row) || $hasPhoto)) {
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
                $labels['rows.' . $index . '.' . $field] =
                    'row ' . ((int) $index + 1) . ' ' . strtolower($def['fields'][$field]['label'] ?? $field);
            }
        }

        $validator = Validator::make($request->all(), $rules, [], $labels);
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

        foreach ($filled as $index => $row) {
            $only = Request::create($request->fullUrl(), 'POST', $row);

            if ($photo = $request->file('rows.' . $index . '.photo')) {
                $only->files->set('photo', $photo);
            }

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
    protected function extraRules(?int $id): array
    {
        return [];
    }
    protected function beforeSave(array $data, Request $request, ?Model $row): array
    {
        return $data;
    }
    protected function afterSave(Model $row, Request $request): void
    {
        //
    }
    protected function inUseBy(Model $row): ?string
    {
        return null;
    }

    protected function storePhoto(Model $row, Request $request, string $dir): void
    {
        if (! $request->hasFile('photo')) {
            return;
        }

        $old = $row->photo;
        $row->photo = $request->file('photo')->store($dir, 'public');
        $row->save();

        if ($old && $old !== $row->photo) {
            Storage::disk('public')->delete($old);
        }
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?int $id): array
    {
        $request->validate($this->rules($id));

        return $this->fieldValues($request);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(?int $id): array
    {
        $def = $this->definition();
        $branch = Helper::getActiveBranchId();
        $rules = ['status' => 'nullable|boolean'];

        if ($def['photos'] ?? false) {
            $rules['photo'] = ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'];
        }

        foreach ($def['fields'] as $name => $field) {
            if (in_array($field['type'] ?? 'text', self::PSEUDO_TYPES, true)) {
                $rules[$name] = $field['rules'] ?? 'nullable|array';
                $rules[$name . '.*'] = $field['item_rules'] ?? 'integer';

                continue;
            }

            $rules[$name] = $field['rules'] ?? 'nullable|string|max:255';
        }

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

            if ($type === 'checkbox') {
                $data[$name] = $request->boolean($name) ? 1 : 0;

                continue;
            }

            if (! $request->has($name)) {
                continue;
            }

            $value = $request->input($name);

            $data[$name] = $value === '' ? null : $value;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function typedIn(array $row): bool
    {
        foreach ($this->definition()['fields'] as $name => $field) {
            $value = $row[$name] ?? null;

            if (is_array($value)) {
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

    private function keep(Request $request): array
    {
        return array_filter([
            'q' => trim($request->string('q')->toString()),
            'deleted' => $request->boolean('deleted') ? 1 : null,
            'outlet' => $request->integer('outlet') ?: null,
        ]);
    }
}
