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

/**
 * One controller for every master list.
 *
 * The screen at /masters/{master} is built from config/masters.php: what the
 * table shows, what the form asks for and how it validates all come from
 * there, so adding a master never means adding a controller.
 */
class MasterController extends Controller
{
    /** GET /masters — the index of masters. */
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

    /**
     * The grid for typing several at once.
     *
     * Every master here is a short list of short rows — twenty rooms, six pay
     * modes — and the one-at-a-time form makes each of them a page load. This
     * is the same fields laid out as a table, so a floor of rooms is one save.
     */
    public function createMany(string $master): View
    {
        $config = $this->config($master);

        abort_unless($config['bulk'] ?? true, 404);

        // After a failed save, exactly the rows that came back, so every error
        // lands on the line it belongs to. Otherwise five empty ones.
        $rows = (array) old('rows', []);

        return view('masters.bulk', [
            'master' => $master,
            'config' => $config,
            'options' => $this->optionsFor($config),
            'rowKeys' => $rows ? array_keys($rows) : range(0, 4),
        ]);
    }

    /**
     * Save the grid — all of it, or none of it.
     *
     * A batch that half-worked would leave somebody comparing the screen
     * against the list to work out which half, so validation runs across every
     * row before a single one is written.
     */
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

                // "rows.3.room_no is required" is not a sentence anybody can
                // act on. "Row 4 room no." is.
                $labels['rows.' . $index . '.' . $column] =
                    'row ' . ((int) $index + 1) . ' ' . strtolower($config['fields'][$column]['label'] ?? $column);
            }
        }

        $validator = Validator::make($request->all(), $rules, [], $labels);

        /*
         * Two new rows calling themselves the same thing pass every rule above
         * — each is unique against the table, because neither is in it yet.
         * They are only in conflict with each other, and nothing else can see
         * that.
         */
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

    /** Flip the active switch straight from the list. */
    public function toggle(string $master, int $id): RedirectResponse
    {
        $config = $this->config($master);
        $row = $config['model']::query()->forBranch()->findOrFail($id);

        $row->update(['status' => $row->isActive() ? 0 : 1]);

        return back()->with('status', $row->isActive() ? 'Marked active.' : 'Marked inactive.');
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /** @return array<string, mixed> */
    private function config(string $master): array
    {
        return config("masters.{$master}") ?? abort(404, 'No such master.');
    }

    /** Validate against the rules in config, adding a per-branch unique check. */
    private function validated(Request $request, array $config, string $master, ?int $ignore = null): array
    {
        $data = $request->validate($this->rules($config, $ignore, $master), [], $this->attributeNames($config));

        // Unticked switches never reach the request at all.
        foreach ($config['fields'] as $column => $field) {
            if (($field['type'] ?? '') === 'switch') {
                $data[$column] = $request->boolean($column) ? 1 : 0;
            }
        }

        $data['status'] = $request->boolean('status') ? 1 : 0;

        return $data;
    }

    /**
     * Every rule this master applies to one row, keyed by column.
     *
     * Kept separate from validating so the bulk grid can take the same rules
     * and re-key them under `rows.3.…`. One definition of what a valid row is,
     * whether it arrives on its own or with nineteen others.
     *
     * Rooms are unique per branch (two properties can both have a "101");
     * every other master is shared, so its name only has to be unique among
     * the other shared rows — see branchIdFor().
     *
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

    /**
     * Which branch a new row belongs to.
     *
     * A room is physical — it stands in exactly one building, so it keeps
     * whichever branch was active when it was added. Every other master
     * (Tax, Pay Mode, Room Category/Type, Plan Type, Services, Companies,
     * Vendors, and the rest of the lookup lists) is shared: NULL is what
     * BaseMaster::scopeForBranch() already reads as "every branch may use
     * this row", so set up once here, it appears under every branch —
     * nothing has to be typed in twice for a second property.
     */
    private function branchIdFor(string $master): ?int
    {
        return $master === 'room' ? Helper::getActiveBranchId() : null;
    }

    /**
     * One row of the bulk grid, turned into columns.
     *
     * Built from the config rather than from whatever was posted, so a
     * hand-crafted form cannot smuggle an extra column into a mass assignment.
     *
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

            // An empty select or an empty money box is "nothing chosen", which
            // is a NULL — storing '' would make a foreign key of 0.
            $data[$column] = ($value === '' ? null : $value);
        }

        $data['status'] = filter_var($row['status'] ?? false, FILTER_VALIDATE_BOOL) ? 1 : 0;

        return $data;
    }

    /**
     * Has anybody typed into this row, or is it one of the blank ones?
     *
     * Only the fields somebody has to type into count. A select posts its
     * first option whether or not it was ever looked at, so counting selects
     * would make every empty row on screen look like something waiting to be
     * saved.
     *
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

    /** Dropdown choices for every `select` field on this master. */
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

    /** Keep one default tax, and keep the room's rent sensible. */
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

    /**
     * Is anything pointing at this row? Returns the reason, or null.
     *
     * Deleting a room type that live bookings reference would leave those
     * bookings describing a room type that no longer exists, so we stop it.
     */
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
