<?php

namespace App\Http\Controllers\Facility;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Facility\Hall;
use App\Models\Facility\ParkingSlot;
use App\Models\Facility\Pool;
use App\Models\Facility\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FacilitySetupController extends Controller
{

    public static function screens(): array
    {
        return [
            'pool/setup' => [
                'model' => Pool::class,
                'title' => 'Pools',
                'subtitle' => 'The water the hotel sells time in.',
                'crumb' => 'Pool',
                'singular' => 'pool',
                'columns' => ['name' => 'Name', 'code' => 'Code', 'capacity' => 'Holds', 'hours' => 'Open'],
                'fields' => [
                    'name' => ['label' => 'Name', 'type' => 'text', 'rules' => 'required|string|max:100', 'wide' => true],
                    'code' => ['label' => 'Code', 'type' => 'text', 'rules' => 'nullable|string|max:20'],
                    'capacity' => [
                        'label' => 'Capacity',
                        'type' => 'number',
                        'rules' => 'nullable|integer|min:0|max:5000',
                        'help' => 'Leave it at 0 and the pool is booked exclusively — one session at a time. '
                            . 'Put a number in and sessions may share the water up to that many people.',
                    ],
                    'open_time' => ['label' => 'Opens', 'type' => 'time', 'rules' => 'nullable'],
                    'close_time' => ['label' => 'Closes', 'type' => 'time', 'rules' => 'nullable'],
                    'adult_rate' => ['label' => 'Adult rate', 'type' => 'money', 'rules' => 'nullable|numeric|min:0|max:999999'],
                    'child_rate' => ['label' => 'Child rate', 'type' => 'money', 'rules' => 'nullable|numeric|min:0|max:999999'],
                    'remark' => ['label' => 'Remark', 'type' => 'text', 'rules' => 'nullable|string|max:255', 'wide' => true],
                ],
            ],

            'hall/setup' => [
                'model' => Hall::class,
                'title' => 'Halls',
                'subtitle' => 'Banquet halls, and what each one is quoted at.',
                'crumb' => 'Banquet Hall',
                'singular' => 'hall',
                'columns' => ['name' => 'Name', 'code' => 'Code', 'floor' => 'Floor', 'capacity' => 'Seats'],
                'fields' => [
                    'name' => ['label' => 'Name', 'type' => 'text', 'rules' => 'required|string|max:100', 'wide' => true],
                    'code' => ['label' => 'Code', 'type' => 'text', 'rules' => 'nullable|string|max:20'],
                    'floor' => ['label' => 'Floor', 'type' => 'text', 'rules' => 'nullable|string|max:20'],
                    'capacity' => ['label' => 'Seats', 'type' => 'number', 'rules' => 'nullable|integer|min:0|max:5000'],
                    'hour_rate' => [
                        'label' => 'Per hour',
                        'type' => 'money',
                        'rules' => 'nullable|numeric|min:0|max:99999999',
                        'help' => 'What a conference pays.',
                    ],
                    'day_rate' => [
                        'label' => 'Per day',
                        'type' => 'money',
                        'rules' => 'nullable|numeric|min:0|max:99999999',
                        'help' => 'What a wedding pays. Also quoted for a whole-event booking.',
                    ],
                    'amenities' => ['label' => 'Amenities', 'type' => 'textarea', 'rules' => 'nullable|string|max:1000', 'wide' => true],
                ],
            ],

            'car/parking-slots' => [
                'model' => ParkingSlot::class,
                'title' => 'Parking Slots',
                'subtitle' => 'The marked bays, if the hotel has any.',
                'crumb' => 'Car & Parking',
                'singular' => 'bay',
                'columns' => ['code' => 'Bay', 'zone' => 'Zone', 'vehicle_type' => 'For'],
                'fields' => [
                    'code' => ['label' => 'Bay', 'type' => 'text', 'rules' => 'required|string|max:20'],
                    'zone' => ['label' => 'Zone', 'type' => 'text', 'rules' => 'nullable|string|max:40', 'help' => 'Basement, Front, Annexe…'],
                    'vehicle_type' => [
                        'label' => 'For',
                        'type' => 'select',
                        'options' => ParkingSlot::VEHICLE_TYPES,
                        'rules' => ['required', 'string'],
                    ],
                ],
            ],

            'car/vehicles' => [
                'model' => Vehicle::class,
                'title' => 'Vehicles',
                'subtitle' => 'The cars the hotel sends out, and who usually drives them.',
                'crumb' => 'Car & Parking',
                'singular' => 'vehicle',
                'columns' => ['name' => 'Name', 'vehicle_no' => 'Number', 'type' => 'Type', 'driver_name' => 'Driver'],
                'fields' => [
                    'name' => ['label' => 'Name', 'type' => 'text', 'rules' => 'required|string|max:100'],
                    'vehicle_no' => ['label' => 'Number', 'type' => 'text', 'rules' => 'nullable|string|max:20'],
                    'type' => ['label' => 'Type', 'type' => 'select', 'options' => Vehicle::TYPES, 'rules' => ['required', 'string']],
                    'seats' => ['label' => 'Seats', 'type' => 'number', 'rules' => 'nullable|integer|min:1|max:60'],
                    'driver_name' => ['label' => 'Driver', 'type' => 'text', 'rules' => 'nullable|string|max:80'],
                    'driver_mobile' => ['label' => 'Driver mobile', 'type' => 'text', 'rules' => 'nullable|string|max:20'],
                    'km_rate' => ['label' => 'Per km', 'type' => 'money', 'rules' => 'nullable|numeric|min:0|max:999999'],
                    'trip_rate' => ['label' => 'Per trip', 'type' => 'money', 'rules' => 'nullable|numeric|min:0|max:999999'],
                ],
            ],
        ];
    }

    public function index(Request $request): View
    {
        $screen = $this->screen($request);
        $config = $this->config($screen);
        $branchId = (int) Helper::getActiveBranchId();

        /** @var class-string<\App\Models\Master\BaseMaster> $model */
        $model = $config['model'];

        $rows = $model::query()
            ->forBranch($branchId)
            ->search($request->string('q')->toString())
            ->orderBy(array_key_first($config['columns']))
            ->get();

        return view('facility.setup', [
            'screen' => $screen,
            'config' => $config,
            'routes' => self::routeNames($screen),
            'rows' => $rows,
            'editing' => $request->integer('edit')
                ? $model::query()->forBranch($branchId)->find($request->integer('edit'))
                : null,
            'q' => $request->string('q')->toString(),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $screen = $this->screen($request);
        $config = $this->config($screen);
        $branchId = (int) Helper::getActiveBranchId();

        /** @var class-string<\App\Models\Master\BaseMaster> $model */
        $model = $config['model'];

        $rules = ['id' => 'nullable|integer'];

        foreach ($config['fields'] as $name => $field) {
            $rules[$name] = $field['rules'];

            if (($field['type'] ?? '') === 'select') {
                $rules[$name] = array_merge((array) $field['rules'], [Rule::in(array_keys($field['options']))]);
            }
        }

        $data = $request->validate($rules);

        $row = ! empty($data['id'])
            ? $model::query()->forBranch($branchId)->find($data['id'])
            : new $model;

        if (! empty($data['id']) && ! $row) {
            return back()->with('error', 'That row is not one of this branch\'s.');
        }

        foreach (array_keys($config['fields']) as $name) {
            $row->{$name} = $data[$name] ?? null;
        }

        if (! $row->exists) {
            $row->branch_id = $branchId;
            $row->status = 1;
        }

        $row->save();

        return redirect()->route(self::routeNames($screen)['index'])
            ->with('status', ucfirst($config['singular']) . ' saved.');
    }

    public function toggle(Request $request, int $id): RedirectResponse
    {
        $screen = $this->screen($request);
        $config = $this->config($screen);
        $branchId = (int) Helper::getActiveBranchId();

        /** @var class-string<\App\Models\Master\BaseMaster> $model */
        $model = $config['model'];

        $row = $model::query()->forBranch($branchId)->findOrFail($id);

        $row->update(['status' => $row->isActive() ? 0 : 1]);

        return back()->with('status', sprintf(
            '%s %s.',
            ucfirst($config['singular']),
            $row->isActive() ? 'switched back on' : 'switched off — it cannot be picked for anything new'
        ));
    }

    /**
     * @return array{index: string, save: string, toggle: string}
     */
    public static function routeNames(string $screen): array
    {
        $base = str_replace('/', '.', $screen);

        return ['index' => $base, 'save' => $base . '.save', 'toggle' => $base . '.toggle'];
    }
    private function screen(Request $request): string
    {
        return (string) ($request->route()?->defaults['screen'] ?? '');
    }

    /** @return array<string, mixed> */
    private function config(string $screen): array
    {
        $screens = self::screens();

        abort_unless(isset($screens[$screen]), 404);

        return $screens[$screen];
    }
}
