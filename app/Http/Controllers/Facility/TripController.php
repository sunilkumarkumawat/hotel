<?php

namespace App\Http\Controllers\Facility;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Facility\GuestTrip;
use App\Models\Facility\Vehicle;
use App\Models\Reservation\Reservation;
use App\Support\Facility;
use App\Support\GuestMessage;
use App\Support\Money;
use App\Support\Notify;
use App\Support\Tax;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Fetching guests, and taking them back.
 *
 * A pickup is normally booked against a *reservation* — the guest has not
 * arrived, so there is no stay to hang it on — and a drop against a stay. Both
 * links are offered and both are optional, because the airport run for a
 * walk-in is neither.
 *
 * Like parking, a trip is recorded first and charged only if asked:
 * `is_chargeable` starts off, and a hotel whose tariff includes the airport
 * pickup never turns it on.
 */
class TripController extends Controller
{
    /** GET car/trips */
    public function index(Request $request): View
    {
        $branchId = (int) Helper::getActiveBranchId();

        $filters = [
            'from' => Facility::date($request->string('from')->toString(), today()->toDateString()),
            'to' => Facility::date($request->string('to')->toString(), today()->addWeek()->toDateString()),
            'type' => $request->string('type')->toString(),
            'status' => $request->string('status')->toString(),
            'vehicle' => $request->integer('vehicle'),
            'q' => $request->string('q')->toString(),
        ];

        if ($filters['from'] > $filters['to']) {
            [$filters['from'], $filters['to']] = [$filters['to'], $filters['from']];
        }

        $rows = GuestTrip::query()
            ->forBranch($branchId)
            ->with(['vehicle', 'stay.room'])
            ->where('trip_date', '>=', $filters['from'])
            ->where('trip_date', '<', CarbonImmutable::parse($filters['to'])->addDay()->toDateString())
            ->when($filters['type'], fn ($q, $t) => $q->where('trip_type', $t))
            ->when($filters['status'], fn ($q, $s) => $q->where('status', $s))
            ->when($filters['vehicle'], fn ($q, $id) => $q->where('vehicle_id', $id))
            ->when($filters['q'], fn ($q, $t) => $q->where(function ($w) use ($t) {
                $w->where('guest_name', 'like', "%{$t}%")
                    ->orWhere('trip_no', 'like', "%{$t}%")
                    ->orWhere('flight_no', 'like', "%{$t}%")
                    ->orWhere('mobile', 'like', "%{$t}%");
            }))
            ->orderBy('trip_date')
            ->orderBy('trip_time')
            ->paginate(25)
            ->withQueryString();

        return view('car.trips', [
            'rows' => $rows,
            'filters' => $filters,
            'types' => GuestTrip::TYPES,
            'statuses' => GuestTrip::STATUSES,
            'vehicles' => Vehicle::query()->forBranch($branchId)->active()->orderBy('name')->get(),
            'counts' => [
                'today' => GuestTrip::query()->forBranch($branchId)->live()->whereDate('trip_date', today())->count(),
                'running' => GuestTrip::query()->forBranch($branchId)->where('status', 'started')->count(),
                'pickups' => GuestTrip::query()->forBranch($branchId)->live()->where('trip_type', 'pickup')->count(),
                'drops' => GuestTrip::query()->forBranch($branchId)->live()->where('trip_type', 'drop')->count(),
            ],
        ]);
    }

    /** GET car/trips/new  ·  GET car/trips/{trip}/edit */
    public function form(Request $request, ?int $trip = null): View
    {
        $branchId = (int) Helper::getActiveBranchId();

        $row = $trip
            ? GuestTrip::query()->forBranch($branchId)->findOrFail($trip)
            : new GuestTrip([
                'trip_type' => $request->string('type')->toString() === 'drop' ? 'drop' : 'pickup',
                'trip_date' => Facility::date($request->string('date')->toString()),
                'trip_time' => '12:00',
                'pax' => 1,
                'rate_type' => 'trip',
                'status' => 'scheduled',
                'is_chargeable' => (bool) config('pms.trip_charge_default', false),
                'tax_choice' => Tax::defaultChoice(),
            ]);

        return view('car.trip-form', [
            'row' => $row,
            'types' => GuestTrip::TYPES,
            'statuses' => GuestTrip::STATUSES,
            'rateTypes' => GuestTrip::RATE_TYPES,
            'vehicles' => Vehicle::query()->forBranch($branchId)->active()->orderBy('name')->get(),
            'stays' => Facility::stays($branchId),
            'arrivals' => $this->arrivals($branchId),
            'taxChoices' => Tax::optionsFor($row->tax_choice, $branchId, false, (float) $row->tax_percent),
        ]);
    }

    /** POST car/trips  ·  PUT car/trips/{trip} */
    public function save(Request $request, ?int $trip = null): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();

        $row = $trip
            ? GuestTrip::query()->forBranch($branchId)->findOrFail($trip)
            : new GuestTrip;

        if ($row->exists && $row->isClosed()) {
            return back()->with('error', 'This trip is ' . strtolower($row->status_label) . ' — it cannot be changed.');
        }

        $data = $request->validate([
            'trip_type' => ['required', Rule::in(array_keys(GuestTrip::TYPES))],
            'reservation_id' => 'nullable|integer',
            'check_in_id' => 'nullable|integer',
            'guest_name' => 'required|string|max:150',
            'mobile' => 'nullable|string|max:20',
            'room_no' => 'nullable|string|max:20',
            'pax' => 'nullable|integer|min:1|max:60',
            'luggage' => 'nullable|integer|min:0|max:60',
            'vehicle_id' => 'nullable|integer',
            'driver_name' => 'nullable|string|max:80',
            'driver_mobile' => 'nullable|string|max:20',
            'from_place' => 'required|string|max:150',
            'to_place' => 'required|string|max:150',
            'flight_no' => 'nullable|string|max:30',
            'trip_date' => 'required|date',
            'trip_time' => 'required',
            'km' => 'nullable|numeric|min:0|max:9999',
            'is_chargeable' => 'nullable|boolean',
            'rate_type' => ['required', Rule::in(array_keys(GuestTrip::RATE_TYPES))],
            'rate' => 'nullable|numeric|min:0|max:999999',
            'tax_choice' => Tax::rule($branchId),
            'status' => ['nullable', Rule::in(array_keys(GuestTrip::STATUSES))],
            'post_to_room' => 'nullable|boolean',
            'remark' => 'nullable|string|max:255',
        ]);

        $vehicle = $data['vehicle_id']
            ? Vehicle::query()->forBranch($branchId)->find($data['vehicle_id'])
            : null;

        if ($data['vehicle_id'] && ! $vehicle) {
            return back()->with('error', 'That vehicle is not one of this branch\'s.')->withInput();
        }

        // Booked against a reservation, checked against the branch — a posted id
        // is not a permission check.
        $reservationId = null;

        if ($data['reservation_id']) {
            $reservationId = Reservation::query()
                ->where('branch_id', $branchId)
                ->whereKey($data['reservation_id'])
                ->value('id');

            if (! $reservationId) {
                return back()->with('error', 'That booking is not one of this branch\'s.')->withInput();
            }
        }

        /*
         * The tick decides, and nothing else. A trip with it off is worth
         * nothing however many kilometres are typed in — which is the whole
         * point of recording free pickups at all.
         */
        $chargeable = (bool) ($data['is_chargeable'] ?? false);
        $km = round((float) ($data['km'] ?? 0), 2);
        $rateType = $data['rate_type'];

        $rate = $chargeable
            ? round((float) ($data['rate'] ?? 0) ?: (float) ($rateType === 'km' ? $vehicle?->km_rate : $vehicle?->trip_rate), 2)
            : 0.0;

        $gross = $chargeable
            ? round($rateType === 'km' ? $km * $rate : $rate, 2)
            : 0.0;

        $choice = $chargeable ? Tax::normalise($data['tax_choice'] ?? Tax::defaultChoice()) : Tax::NONE;

        $figures = Money::serviceRow([
            'qty' => 1,
            'price' => max(0, $gross),
            'tax_choice' => $choice,
            'tax_type' => 'exclusive',
        ], $branchId);

        $guest = Facility::guest($data, $branchId);
        $date = Facility::date($data['trip_date']);
        $warning = null;

        DB::transaction(function () use (
            $row, $branchId, $data, $guest, $reservationId, $vehicle, $date,
            $km, $chargeable, $rateType, $rate, $choice, $figures, $request, &$warning
        ) {
            $row->fill($guest + [
                'branch_id' => $branchId,
                'trip_type' => $data['trip_type'],
                'reservation_id' => $reservationId,
                'pax' => (int) ($data['pax'] ?? 1),
                'luggage' => (int) ($data['luggage'] ?? 0),
                'vehicle_id' => $vehicle?->id,
                // Copied off the vehicle rather than read through it: the
                // regular driver being off sick must not rewrite who drove
                // last Tuesday.
                'driver_name' => $data['driver_name'] ?: $vehicle?->driver_name,
                'driver_mobile' => $data['driver_mobile'] ?: $vehicle?->driver_mobile,
                'from_place' => $data['from_place'],
                'to_place' => $data['to_place'],
                'flight_no' => $data['flight_no'] ?? null,
                'trip_date' => $date,
                'trip_time' => Facility::hms($data['trip_time']),
                'km' => $km,
                'is_chargeable' => $chargeable ? 1 : 0,
                'rate_type' => $rateType,
                'rate' => $rate,
                'amount' => $figures['amount'],
                'tax_choice' => $choice,
                'tax_percent' => $figures['tax_percent'],
                'tax_amount' => $figures['tax_amount'],
                'total_amount' => $figures['total_amount'],
                'status' => $data['status'] ?? ($row->status ?: 'scheduled'),
                'post_to_room' => (bool) ($data['post_to_room'] ?? false),
                'remark' => $data['remark'] ?? null,
            ]);

            if (! $row->exists) {
                $row->trip_no = Facility::nextNumber(
                    'guest_trips', 'trip_no', config('pms.trip_prefix', 'TRIP'), $branchId
                );
                $row->created_by = $request->user()->user_id;
            }

            $row->save();

            $warning = Facility::syncFolio(
                $row,
                ucfirst($row->trip_type) . ' — ' . $row->from_place . ' to ' . $row->to_place,
                $branchId,
                $request->user()->user_id,
                $date
            );
        });

        GuestMessage::send('guest.trip', $row->mobile, [
            'guest' => $row->guest_name,
            'guest_email' => $row->stay?->reservation?->email,
            'kind' => strtolower($row->type_label),
            'trip_no' => $row->trip_no,
            'from_place' => $row->from_place,
            'to_place' => $row->to_place,
            'when' => $row->when,
            'vehicle' => $vehicle?->label,
            'driver' => $row->driver_name,
            'driver_mobile' => $row->driver_mobile,
        ], $branchId);

        Notify::event('trip.scheduled')
            ->title($row->type_label . ' booked — ' . $row->trip_no)
            ->body(trim($row->guest_name . ' · ' . $row->when . ' · ' . $row->from_place . ' → ' . $row->to_place))
            ->url(route('car.trips'))
            ->send();

        return redirect()->route('car.trips')
            ->with('status', $row->type_label . ' ' . $row->trip_no . ' saved'
                . ($row->charges() ? ' — ₹ ' . number_format((float) $row->total_amount, 2) . '.' : ' — no charge.'))
            ->with($warning ? 'warning' : 'ignored', $warning);
    }

    /** POST car/trips/{trip}/status */
    public function status(Request $request, int $trip): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();
        $row = GuestTrip::query()->forBranch($branchId)->findOrFail($trip);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(GuestTrip::STATUSES))],
        ]);

        if ($row->isClosed()) {
            return back()->with('error', 'A ' . strtolower($row->status_label) . ' trip cannot be moved on.');
        }

        $warning = null;

        DB::transaction(function () use ($row, $branchId, $data, &$warning) {
            $row->update(['status' => $data['status']]);

            // A cancelled trip takes its charge back off the guest's bill.
            if ($data['status'] === 'cancelled') {
                $warning = Facility::releaseFolio($row, $branchId);
            }
        });

        $event = match ($data['status']) {
            'started' => 'trip.started',
            'completed' => 'trip.completed',
            default => null,
        };

        /*
         * "The car has left" is the one message a guest waiting outside an
         * airport actually wants, and it is the only status change worth
         * putting on their phone — a trip marked finished is news to the desk,
         * not to the person who just got out of the car.
         */
        if ($data['status'] === 'started') {
            GuestMessage::send('guest.trip-started', $row->mobile, [
                'guest' => $row->guest_name,
                'from_place' => $row->from_place,
                'driver' => $row->driver_name,
                'driver_mobile' => $row->driver_mobile,
                'vehicle' => $row->vehicle?->label,
            ], $branchId);
        }

        if ($event) {
            Notify::event($event)
                ->title($row->type_label . ' ' . $row->trip_no . ' — ' . strtolower($row->status_label))
                ->body(trim($row->guest_name . ' · ' . ($row->driver_name ?: 'no driver named')))
                ->url(route('car.trips'))
                ->send();
        }

        return back()
            ->with('status', $row->trip_no . ' is now ' . strtolower($row->status_label) . '.')
            ->with($warning ? 'warning' : 'ignored', $warning);
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * Bookings a pickup could be for — arriving in the next fortnight.
     *
     * Not every booking ever taken: a pickup is arranged days ahead, and a
     * dropdown of three years of history is a dropdown nobody uses.
     */
    private function arrivals(int $branchId)
    {
        return Reservation::query()
            ->where('branch_id', $branchId)
            ->whereIn('status', ['confirmed', 'tentative'])
            ->whereHas('rooms', fn ($q) => $q
                ->where('arrival_date', '>=', today()->subDay()->toDateString())
                ->where('arrival_date', '<=', today()->addDays(14)->toDateString()))
            ->orderBy('id', 'desc')
            ->limit(200)
            ->get(['id', 'reservation_no', 'first_name', 'last_name', 'mobile'])
            ->mapWithKeys(fn (Reservation $r) => [
                $r->id => trim($r->reservation_no . ' — ' . $r->first_name . ' ' . $r->last_name),
            ]);
    }
}
