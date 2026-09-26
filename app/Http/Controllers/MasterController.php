<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Models\Master\BaseMaster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MasterController extends Controller
{
    public function home(): View
    {
        $masters = collect(config('masters'))->map(function (array $config, string $key) {
            /** @var class-string<BaseMaster> $model */
            $model = $config['model'];

            return [
                'key' => $key,
                'label' => $config['plural'] ?? $config['label'],
                'icon' => $config['icon'] ?? 'grid',
                'intro' => $config['intro'] ?? '',
                'count' => $model::query()->forBranch()->count(),
            ];
        })->values();

        return view('masters.home', compact('masters'));
    }

    public function index(Request $request, string $master): View
    {
        $config = $this->config($master);
        $model = $config['model'];

        $term = $request->string('q')->toString();

        $rows = $model::query()
            ->forBranch()
            ->with($config['with'] ?? [])
            ->when($term, function ($query) use ($config, $term) {
                $column = $config['search'] ?? 'name';
                $query->where($column, 'like', "%{$term}%");
            })
            ->orderBy($config['order'] ?? 'name')
            ->paginate(20)
            ->withQueryString();

        return view('masters.index', [
            'master' => $master,
            'config' => $config,
            'rows' => $rows,
            'term' => $term,
            'options' => $this->optionsFor($config),
            'counts' => [
                'all' => $model::query()->forBranch()->count(),
                'active' => $model::query()->forBranch()->where('status', 1)->count(),
            ],
        ]);
    }

    public function create(string $master): View
    {
        $config = $this->config($master);

        return view('masters.form', [
            'master' => $master,
            'config' => $config,
            'row' => new $config['model'],
            'options' => $this->optionsFor($config),
        ]);
    }

    public function store(Request $request, string $master): RedirectResponse
    {
        $config = $this->config($master);
        $data = $this->validated($request, $config, $master);

        $data['branch_id'] = $this->branchIdFor($master);

        $row = $config['model']::create($data);
        $this->afterSave($master, $row);

        return redirect()
            ->route('masters.index', $master)
            ->with('status', "{$config['label']} \"{$this->title($row, $config)}\" has been added.");
    }
    public function createMany(string $master): View
    {
        $config = $this->config($master);

        abort_unless($config['bulk'] ?? true, 404);

        $rows = (array) old('rows', []);

        return view('masters.bulk', [
            'master' => $master,
            'config' => $config,
            'options' => $this->optionsFor($config),
            'rowKeys' => $rows ? array_keys($rows) : range(0, 4),
        ]);
    }

    public function storeMany(Request $request, string $master): RedirectResponse
    {
        $config = $this->config($master);

        abort_unless($config['bulk'] ?? true, 404);

        $filled = [];

        foreach ((array) $request->input('rows', []) as $index => $row) {
            if (is_array($row) && $this->typedIn($row, $config)) {
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
            foreach ($this->rules($config, null, $master) as $column => $rule) {
                $rules['rows.' . $index . '.' . $column] = $rule;

                $labels['rows.' . $index . '.' . $column] =
                    'row ' . ((int) $index + 1) . ' ' . strtolower($config['fields'][$column]['label'] ?? $column);
            }
        }

        $validator = Validator::make($request->all(), $rules, [], $labels);

        $unique = $config['search'] ?? 'name';

        $validator->after(function ($validator) use ($filled, $unique) {
            $seen = [];

            foreach ($filled as $index => $row) {
                $value = strtolower(trim((string) ($row[$unique] ?? '')));

                if ($value === '') {
                    continue;
                }

                if (isset($seen[$value])) {
                    $validator->errors()->add(
                        'rows.' . $index . '.' . $unique,
                        'Row ' . ((int) $index + 1) . ' repeats what is already on row ' . ($seen[$value] + 1) . '.'
                    );

                    continue;
                }

                $seen[$value] = (int) $index;
            }
        });

        $validator->validate();

        $saved = 0;

        foreach ($filled as $row) {
            $data = $this->rowValues($row, $config);
            $data['branch_id'] = $this->branchIdFor($master);

            $record = $config['model']::create($data);
            $this->afterSave($master, $record);

            $saved++;
        }

        return redirect()
            ->route('masters.index', $master)
            ->with('status', $saved === 1
                ? "1 {$config['label']} has been added."
                : $saved . ' ' . strtolower($config['plural'] ?? $config['label']) . ' have been added.');
    }

    public function edit(string $master, int $id): View
    {
        $config = $this->config($master);

        return view('masters.form', [
            'master' => $master,
            'config' => $config,
            'row' => $config['model']::query()->forBranch()->findOrFail($id),
            'options' => $this->optionsFor($config),
        ]);
    }

    public function update(Request $request, string $master, int $id): RedirectResponse
    {
        $config = $this->config($master);
        $row = $config['model']::query()->forBranch()->findOrFail($id);

        $row->update($this->validated($request, $config, $master, $id));
        $this->afterSave($master, $row);

        return redirect()
            ->route('masters.index', $master)
            ->with('status', "{$config['label']} \"{$this->title($row, $config)}\" has been saved.");
    }

    public function destroy(string $master, int $id): RedirectResponse
    {
        $config = $this->config($master);
        $row = $config['model']::query()->forBranch()->findOrFail($id);

        if ($blocker = $this->inUseBy($master, $row)) {
            return back()->with('error', "Cannot delete this — {$blocker}.");
        }

        $name = $this->title($row, $config);
        $row->delete();

        return back()->with('status', "{$config['label']} \"{$name}\" has been deleted.");
    }

    public function toggle(string $master, int $id): RedirectResponse
    {
        $config = $this->config($master);
        $row = $config['model']::query()->forBranch()->findOrFail($id);

        $row->update(['status' => $row->isActive() ? 0 : 1]);

        return back()->with('status', $row->isActive() ? 'Marked active.' : 'Marked inactive.');
    }

    /** @return array<string, mixed> */
    private function config(string $master): array
    {
        return config("masters.{$master}") ?? abort(404, 'No such master.');
    }

    private function validated(Request $request, array $config, string $master, ?int $ignore = null): array
    {
        $data = $request->validate($this->rules($config, $ignore, $master), [], $this->attributeNames($config));

        foreach ($config['fields'] as $column => $field) {
            if (($field['type'] ?? '') === 'switch') {
                $data[$column] = $request->boolean($column) ? 1 : 0;
            }
        }

        $data['status'] = $request->boolean('status') ? 1 : 0;

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(array $config, ?int $ignore, string $master): array
    {
        $rules = [];

        foreach ($config['fields'] as $column => $field) {
            $rules[$column] = $field['rules'] ?? 'nullable';
        }

        $unique = $config['search'] ?? 'name';

        if (isset($rules[$unique])) {
            $rules[$unique] = array_merge(
                explode('|', is_array($rules[$unique]) ? implode('|', $rules[$unique]) : $rules[$unique]),
                [
                    Rule::unique((new $config['model'])->getTable(), $unique)
                        ->where(fn ($q) => $master === 'room'
                            ? $q->where('branch_id', Helper::getActiveBranchId())
                            : $q->whereNull('branch_id'))
                        ->ignore($ignore),
                ]
            );
        }

        $rules['status'] = 'nullable|boolean';

        return $rules;
    }
    private function branchIdFor(string $master): ?int
    {
        return $master === 'room' ? Helper::getActiveBranchId() : null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function rowValues(array $row, array $config): array
    {
        $data = [];

        foreach ($config['fields'] as $column => $field) {
            if (($field['type'] ?? '') === 'switch') {
                $data[$column] = filter_var($row[$column] ?? false, FILTER_VALIDATE_BOOL) ? 1 : 0;

                continue;
            }

            $value = $row[$column] ?? null;

            $data[$column] = ($value === '' ? null : $value);
        }

        $data['status'] = filter_var($row['status'] ?? false, FILTER_VALIDATE_BOOL) ? 1 : 0;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function typedIn(array $row, array $config): bool
    {
        foreach ($config['fields'] as $column => $field) {
            if (in_array($field['type'] ?? 'text', ['select', 'switch'], true)) {
                continue;
            }

            if (trim((string) ($row[$column] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, string> */
    private function attributeNames(array $config): array
    {
        $names = [];

        foreach ($config['fields'] as $column => $field) {
            $names[$column] = strtolower($field['label']);
        }

        return $names;
    }

    private function optionsFor(array $config): array
    {
        $options = [];

        foreach ($config['fields'] as $column => $field) {
            if (($field['type'] ?? '') !== 'select') {
                continue;
            }

            $options[$column] = isset($field['source'])
                ? $field['source']::query()->forBranch()->active()->orderBy('name')->pluck('name', 'id')->all()
                : ($field['options'] ?? []);
        }

        return $options;
    }

    private function title($row, array $config): string
    {
        $column = $config['search'] ?? 'name';

        return (string) $row->{$column};
    }

    private function afterSave(string $master, $row): void
    {
        if ($master === 'tax' && $row->is_default) {
            $row->newQuery()
                ->where('id', '!=', $row->id)
                ->where('branch_id', $row->branch_id)
                ->update(['is_default' => 0]);
        }

        if ($master === 'room' && ! $row->base_rent && $row->room_type_id) {
            $row->update(['base_rent' => $row->type?->base_rent ?? 0]);
        }
    }
    private function inUseBy(string $master, $row): ?string
    {
        $guards = [
            'room-category' => [
                ['room_type', 'room_category_id', 'room types use it'],
                ['rooms', 'room_category_id', 'rooms use it'],
            ],
            'room-type' => [
                ['rooms', 'room_type_id', 'rooms use it'],
                ['reservation_rooms', 'room_type_id', 'reservations use it'],
            ],
            'plan-type' => [
                ['reservation_rooms', 'plan_type_id', 'reservations use it'],
            ],
            'room' => [
                ['reservation_rooms', 'room_id', 'it is allotted to a reservation'],
            ],
            'tax' => [
                ['services', 'tax_master_id', 'services use it'],
            ],
            'service' => [
                ['reservation_services', 'service_id', 'reservations use it'],
            ],
            'company' => [
                ['reservations', 'company_id', 'reservations use it'],
                ['guests', 'company_id', 'guests are linked to it'],
            ],
            'booked-by' => [
                ['reservations', 'booked_by_id', 'reservations use it'],
            ],
            'business-market' => [
                ['reservations', 'business_market_id', 'reservations use it'],
            ],
            'visit-purpose' => [
                ['reservations', 'visit_purpose_id', 'reservations use it'],
            ],
            'pick-drop' => [
                ['reservations', 'pick_drop_id', 'reservations use it'],
            ],
            'billing-instruction' => [
                ['reservations', 'billing_instruction_id', 'reservations use it'],
            ],
            'pay-mode' => [
                ['reservations', 'pay_mode_id', 'reservations use it'],
                ['advance_deposits', 'pay_mode_id', 'deposits use it'],
            ],
            'expense-head' => [
                ['petty_cash_payments', 'expense_head_id', 'payments use it'],
            ],
            'receive-head' => [
                ['petty_cash_receipts', 'receive_head_id', 'receipts use it'],
            ],
            'vendor' => [
                ['store_docs', 'vendor_id', 'purchase orders or goods receipts use it'],
                ['hk_issues', 'vendor_id', 'laundry issue notes use it'],
                ['hk_receipts', 'vendor_id', 'laundry receipt notes use it'],
            ],
        ];

        foreach ($guards[$master] ?? [] as [$table, $column, $reason]) {
            if (DB::table($table)->where($column, $row->id)->exists()) {
                return $reason;
            }
        }

        return null;
    }
}
