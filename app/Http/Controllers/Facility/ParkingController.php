<?php

namespace App\Http\Controllers\Facility;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Facility\ParkingRecord;
use App\Models\Facility\ParkingSlot;
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

class ParkingController extends Controller
{

    public function index(Request $request): View
    {
        $branchId = (int) Helper::getActiveBranchId();

        $filters = [
            'from' => Facility::date($request->string('from')->toString(), today()->subWeek()->toDateString()),
            'to' => Facility::date($request->string('to')->toString(), today()->toDateString()),
            'status' => $request->string('status')->toString() ?: 'parked',
            'q' => $request->string('q')->toString(),
        ];

        if ($filters['from'] > $filters['to']) {
            [$filters['from'], $filters['to']] = [$filters['to'], $filters['from']];
        }

        $rows = ParkingRecord::query()
            ->forBranch($branchId)
            ->with(['slot', 'stay.room'])
            ->when(
                $filters['status'] !== 'parked',
                fn ($q) => $q
                    ->where('in_at', '>=', $filters['from'] . ' 00:00:00')
                    ->where('in_at', '<=', $filters['to'] . ' 23:59:59')
            )
            ->when($filters['status'], fn ($q, $s) => $q->where('status', $s))
            ->when($filters['q'], fn ($q, $t) => $q->where(function ($w) use ($t) {
                $w->where('vehicle_no', 'like', "%{$t}%")
                    ->orWhere('ticket_no', 'like', "%{$t}%")
                    ->orWhere('guest_name', 'like', "%{$t}%")
                    ->orWhere('room_no', 'like', "%{$t}%");
            }))
            ->orderByDesc('in_at')
            ->paginate(25)
            ->withQueryString();

        $slots = ParkingSlot::query()->forBranch($branchId)->active()->orderBy('code')->get();
        $taken = ParkingRecord::query()->forBranch($branchId)->parked()->pluck('parking_slot_id')->filter()->flip();

        return view('car.parking', [
            'rows' => $rows,
            'filters' => $filters,
            'statuses' => ParkingRecord::STATUSES,
            'slots' => $slots,
            'freeSlots' => $slots->reject(fn ($slot) => $taken->has($slot->id))->values(),
            'stays' => Facility::stays($branchId),
            'vehicleTypes' => ParkingSlot::VEHICLE_TYPES,
            'taxChoices' => Tax::options($branchId),
            'chargeByDefault' => (bool) config('pms.parking_charge_default', false),
            'defaultRate' => (float) config('pms.parking_default_rate', 0),
            'counts' => [
                'in' => ParkingRecord::query()->forBranch($branchId)->parked()->count(),
                'free' => max(0, $slots->count() - $taken->count()),
                'today' => ParkingRecord::query()->forBranch($branchId)->whereDate('in_at', today())->count(),
                'charged' => ParkingRecord::query()->forBranch($branchId)
                    ->where('is_chargeable', 1)
                    ->whereDate('in_at', '>=', $filters['from'])
                    ->sum('total_amount'),
            ],
        ]);
    }

    public function checkIn(Request $request): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();

        $data = $request->validate([
            'vehicle_no' => 'required|string|max:20',
            'vehicle_type' => ['required', Rule::in(array_keys(ParkingSlot::VEHICLE_TYPES))],
            'parking_slot_id' => 'nullable|integer',
            'check_in_id' => 'nullable|integer',
            'guest_name' => 'nullable|string|max:150',
            'mobile' => 'nullable|string|max:20',
            'room_no' => 'nullable|string|max:20',
            'make_model' => 'nullable|string|max:60',
            'colour' => 'nullable|string|max:30',
            'driver_name' => 'nullable|string|max:80',
            'driver_mobile' => 'nullable|string|max:20',
            'in_at' => 'nullable|date',
            'remark' => 'nullable|string|max:255',
        ]);

        $plate = strtoupper(preg_replace('/\s+/', '', $data['vehicle_no']) ?: '');

        $already = ParkingRecord::query()->forBranch($branchId)->parked()
            ->whereRaw("UPPER(REPLACE(vehicle_no, ' ', '')) = ?", [$plate])
            ->first();

        if ($already) {
            return back()->with('error', sprintf(
                '%s is already in the park on ticket %s, since %s.',
                $plate,
                $already->ticket_no,
                $already->in_at?->format('d M, h:i A')
            ))->withInput();
        }

        if ($slotId = (int) ($data['parking_slot_id'] ?? 0)) {
            $slot = ParkingSlot::query()->forBranch($branchId)->find($slotId);

            if (! $slot) {
                return back()->with('error', 'That bay is not one of this branch\'s.')->withInput();
            }

            $holder = ParkingRecord::query()->forBranch($branchId)->parked()
                ->where('parking_slot_id', $slotId)
                ->first();

            if ($holder) {
                return back()->with('error', sprintf(
                    'Bay %s already has %s in it (ticket %s).',
                    $slot->code,
                    $holder->vehicle_no,
                    $holder->ticket_no
                ))->withInput();
            }
        }

        $guest = Facility::guest($data, $branchId);

        $record = DB::transaction(function () use ($branchId, $data, $guest, $plate, $request) {
            return ParkingRecord::create($guest + [
                'branch_id' => $branchId,
                'ticket_no' => Facility::nextNumber(
                    'parking_records', 'ticket_no', config('pms.parking_prefix', 'PRK'), $branchId
                ),
                'parking_slot_id' => $data['parking_slot_id'] ?: null,
                'vehicle_no' => $plate,
                'vehicle_type' => $data['vehicle_type'],
                'make_model' => $data['make_model'] ?? null,
                'colour' => $data['colour'] ?? null,
                'driver_name' => $data['driver_name'] ?? null,
                'driver_mobile' => $data['driver_mobile'] ?? null,
                'in_at' => $data['in_at'] ? CarbonImmutable::parse($data['in_at']) : now(),
                'is_chargeable' => 0,
                'rate' => 0,
                'status' => 'parked',
                'remark' => $data['remark'] ?? null,
                'created_by' => $request->user()->user_id,
            ]);
        });

        GuestMessage::send('guest.parking', $record->mobile, [
            'guest' => $record->guest_name,
            'guest_email' => $record->stay?->reservation?->email,
            'vehicle_no' => $record->vehicle_no,
            'ticket_no' => $record->ticket_no,
            'in_at' => $record->in_at?->format('d M Y, h:i A'),
            'bay' => $record->slot?->label,
        ], $branchId);

        Notify::event('parking.in')
            ->title('Parked — ' . $record->vehicle_no)
            ->body(trim(($record->guest_name ?: 'Walk-in') . ' · ticket ' . $record->ticket_no))
            ->url(route('car.parking'))
            ->send();

        return back()->with('status', $record->vehicle_no . ' parked on ticket ' . $record->ticket_no . '. No charge.');
    }

    public function checkOut(Request $request, int $record): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();
        $row = ParkingRecord::query()->forBranch($branchId)->findOrFail($record);

        if (! $row->isParked()) {
            return back()->with('info', 'That car has already left.');
        }

        $data = $request->validate([
            'out_at' => 'nullable|date',
            'is_chargeable' => 'nullable|boolean',
            'rate' => 'nullable|numeric|min:0|max:999999',
            'tax_choice' => Tax::rule($branchId),
            'post_to_room' => 'nullable|boolean',
            'remark' => 'nullable|string|max:255',
        ]);

        $out = $data['out_at'] ? CarbonImmutable::parse($data['out_at']) : now();

        if ($out->lessThan(CarbonImmutable::parse((string) $row->in_at))) {
            return back()->with('error', 'A car cannot leave before it arrived.');
        }

        $hours = Facility::hoursBetween((string) $row->in_at, $out->toDateTimeString());

        $chargeable = (bool) ($data['is_chargeable'] ?? false);
        $rate = $chargeable ? round((float) ($data['rate'] ?? 0), 2) : 0.0;
        $gross = $chargeable ? round($hours * $rate, 2) : 0.0;

        $choice = $chargeable ? Tax::normalise($data['tax_choice'] ?? Tax::defaultChoice()) : Tax::NONE;

        $figures = Money::serviceRow([
            'qty' => 1,
            'price' => $gross,
            'tax_choice' => $choice,
            'tax_type' => 'exclusive',
        ], $branchId);

        $warning = null;

        DB::transaction(function () use ($row, $branchId, $out, $hours, $chargeable, $rate, $choice, $figures, $data, $request, &$warning) {
            $row->update([
                'out_at' => $out,
                'hours' => $hours,
                'is_chargeable' => $chargeable ? 1 : 0,
                'rate' => $rate,
                'amount' => $figures['amount'],
                'tax_choice' => $choice,
                'tax_percent' => $figures['tax_percent'],
                'tax_amount' => $figures['tax_amount'],
                'total_amount' => $figures['total_amount'],
                'post_to_room' => (bool) ($data['post_to_room'] ?? false),
                'status' => 'out',
                'remark' => $data['remark'] ?? $row->remark,
            ]);

            $warning = Facility::syncFolio(
                $row,
                'Car parking — ' . $row->vehicle_no . ' (' . rtrim(rtrim(number_format($hours, 2), '0'), '.') . ' hr)',
                $branchId,
                $request->user()->user_id,
                $out->toDateString()
            );
        });

        Notify::event('parking.out')
            ->title('Left the park — ' . $row->vehicle_no)
            ->body($row->charges()
                ? 'Ticket ' . $row->ticket_no . ' · ₹ ' . number_format((float) $row->total_amount, 2)
                : 'Ticket ' . $row->ticket_no . ' · no charge')
            ->url(route('car.parking'))
            ->send();

        return back()
            ->with('status', $row->vehicle_no . ' out. ' . ($row->charges()
                ? '₹ ' . number_format((float) $row->total_amount, 2) . ' charged.'
                : 'No charge.'))
            ->with($warning ? 'warning' : 'ignored', $warning);
    }

    public function cancel(int $record): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();
        $row = ParkingRecord::query()->forBranch($branchId)->findOrFail($record);

        if ($row->status === 'cancelled') {
            return back()->with('info', 'That ticket was already cancelled.');
        }

        $warning = null;

        DB::transaction(function () use ($row, $branchId, &$warning) {
            $warning = Facility::releaseFolio($row, $branchId);

            $row->update(['status' => 'cancelled']);
        });

        return back()
            ->with('status', 'Ticket ' . $row->ticket_no . ' cancelled.')
            ->with($warning ? 'warning' : 'ignored', $warning);
    }
}
