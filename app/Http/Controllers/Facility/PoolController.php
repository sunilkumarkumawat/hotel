<?php

namespace App\Http\Controllers\Facility;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Facility\Pool;
use App\Models\Facility\PoolBooking;
use App\Support\Facility;
use App\Support\GuestMessage;
use App\Support\Money;
use App\Support\Notify;
use App\Support\Tax;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PoolController extends Controller
{
    public function index(Request $request): View
    {
        $branchId = (int) Helper::getActiveBranchId();

        $filters = [
            'from' => Facility::date($request->string('from')->toString(), today()->toDateString()),
            'to' => Facility::date($request->string('to')->toString(), today()->addWeek()->toDateString()),
            'pool' => $request->integer('pool'),
            'status' => $request->string('status')->toString(),
            'q' => $request->string('q')->toString(),
        ];

        if ($filters['from'] > $filters['to']) {
            [$filters['from'], $filters['to']] = [$filters['to'], $filters['from']];
        }

        $rows = PoolBooking::query()
            ->forBranch($branchId)
            ->with(['pool', 'stay.room'])
            ->where('booking_date', '>=', $filters['from'])
            ->where('booking_date', '<', \Carbon\CarbonImmutable::parse($filters['to'])->addDay()->toDateString())
            ->when($filters['pool'], fn ($q, $id) => $q->where('pool_id', $id))
            ->when($filters['status'], fn ($q, $s) => $q->where('status', $s))
            ->when($filters['q'], fn ($q, $t) => $q->where(function ($w) use ($t) {
                $w->where('guest_name', 'like', "%{$t}%")
                    ->orWhere('booking_no', 'like', "%{$t}%")
                    ->orWhere('mobile', 'like', "%{$t}%");
            }))
            ->orderBy('booking_date')
            ->orderBy('from_time')
            ->paginate(25)
            ->withQueryString();

        $live = PoolBooking::query()->forBranch($branchId)->holding();

        return view('pool.bookings', [
            'rows' => $rows,
            'filters' => $filters,
            'pools' => Pool::query()->forBranch($branchId)->active()->orderBy('name')->pluck('name', 'id'),
            'statuses' => PoolBooking::STATUSES,
            'counts' => [
                'today' => (clone $live)->whereDate('booking_date', today())->count(),
                'swimming' => (clone $live)->where('status', 'in_use')->count(),
                'upcoming' => (clone $live)->where('booking_date', '>', today())->count(),
                'value' => (clone $live)
                    ->where('booking_date', '>=', $filters['from'])
                    ->sum('total_amount'),
            ],
        ]);
    }
    public function form(Request $request, ?int $booking = null): View
    {
        $branchId = (int) Helper::getActiveBranchId();

        $row = $booking
            ? PoolBooking::query()->forBranch($branchId)->findOrFail($booking)
            : new PoolBooking([
                'guest_name' => $request->string('guest_name')->toString() ?: null,
                'mobile' => $request->string('mobile')->toString() ?: null,
                'booking_date' => Facility::date($request->string('date')->toString()),
                'from_time' => '10:00',
                'to_time' => '12:00',
                'adults' => 1,
                'children' => 0,
                'status' => 'booked',
                'tax_choice' => Tax::defaultChoice(),
            ]);

        return view('pool.form', [
            'row' => $row,
            'pools' => Pool::query()->forBranch($branchId)->active()->orderBy('name')->get(),
            'stays' => Facility::stays($branchId),
            'statuses' => PoolBooking::STATUSES,
            'taxChoices' => Tax::optionsFor($row->tax_choice, $branchId, false, (float) $row->tax_percent),
        ]);
    }
    public function save(Request $request, ?int $booking = null): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();

        $row = $booking
            ? PoolBooking::query()->forBranch($branchId)->findOrFail($booking)
            : new PoolBooking;

        if ($row->exists && $row->isClosed()) {
            return back()->with('error', 'This booking is ' . strtolower($row->status_label) . ' — it cannot be changed.');
        }

        $data = $request->validate([
            'pool_id' => ['required', 'integer', Rule::exists('pools', 'id')],
            'check_in_id' => 'nullable|integer',
            'guest_name' => 'required_without:check_in_id|nullable|string|max:150',
            'mobile' => 'nullable|string|max:20',
            'room_no' => 'nullable|string|max:20',
            'booking_date' => 'required|date',
            'from_time' => 'required',
            'to_time' => 'required',
            'adults' => 'required|integer|min:0|max:500',
            'children' => 'nullable|integer|min:0|max:500',
            'adult_rate' => 'nullable|numeric|min:0|max:999999',
            'child_rate' => 'nullable|numeric|min:0|max:999999',
            'discount' => 'nullable|numeric|min:0|max:999999',
            'tax_choice' => Tax::rule($branchId),
            'status' => ['nullable', Rule::in(array_keys(PoolBooking::STATUSES))],
            'post_to_room' => 'nullable|boolean',
            'remark' => 'nullable|string|max:255',
        ], [
            'guest_name.required_without' => 'Pick an in-house guest, or type a name for a walk-in.',
        ]);

        $pool = Pool::query()->forBranch($branchId)->find($data['pool_id']);

        if (! $pool) {
            return back()->with('error', 'That pool is not one of this branch\'s.')->withInput();
        }

        $date = Facility::date($data['booking_date']);
        $from = Facility::hms($data['from_time']);
        $to = Facility::hms($data['to_time']);

        if ($to <= $from) {
            return back()->with('error', 'The session has to end after it starts.')->withInput();
        }

        if ($why = $this->poolIsBusy($pool, $branchId, $date, $from, $to, $data, $row->id)) {
            return back()->with('error', $why)->withInput();
        }

        $adults = (int) $data['adults'];
        $children = (int) ($data['children'] ?? 0);

        if ($adults + $children < 1) {
            return back()->with('error', 'A booking with nobody in it is not a booking.')->withInput();
        }

        $adultRate = round((float) ($data['adult_rate'] ?? $pool->adult_rate), 2);
        $childRate = round((float) ($data['child_rate'] ?? $pool->child_rate), 2);
        $discount = round((float) ($data['discount'] ?? 0), 2);

        $gross = max(0, round(($adults * $adultRate) + ($children * $childRate) - $discount, 2));

        $choice = Tax::normalise($data['tax_choice'] ?? Tax::defaultChoice());

        $figures = Money::serviceRow([
            'qty' => 1,
            'price' => $gross,
            'tax_choice' => $choice,
            'tax_type' => 'exclusive',
        ], $branchId);

        $guest = Facility::guest($data, $branchId);

        $warning = null;

        DB::transaction(function () use (
            $row, $branchId, $pool, $date, $from, $to, $adults, $children,
            $adultRate, $childRate, $discount, $choice, $figures, $guest, $data, $request, &$warning
        ) {
            $row->fill($guest + [
                'branch_id' => $branchId,
                'pool_id' => $pool->id,
                'booking_date' => $date,
                'from_time' => $from,
                'to_time' => $to,
                'adults' => $adults,
                'children' => $children,
                'adult_rate' => $adultRate,
                'child_rate' => $childRate,
                'discount' => $discount,
                'amount' => $figures['amount'],
                'tax_choice' => $choice,
                'tax_percent' => $figures['tax_percent'],
                'tax_amount' => $figures['tax_amount'],
                'total_amount' => $figures['total_amount'],
                'status' => $data['status'] ?? ($row->status ?: 'booked'),
                'post_to_room' => (bool) ($data['post_to_room'] ?? false),
                'remark' => $data['remark'] ?? null,
            ]);

            if (! $row->exists) {
                $row->booking_no = Facility::nextNumber(
                    'pool_bookings', 'booking_no', config('pms.pool_booking_prefix', 'POOL'), $branchId
                );
                $row->created_by = $request->user()->user_id;
            }

            $row->save();

            $warning = Facility::syncFolio(
                $row,
                'Pool — ' . $pool->name . ' (' . $row->slot . ')',
                $branchId,
                $request->user()->user_id,
                $date
            );
        });

        GuestMessage::send('guest.pool', $row->mobile, [
            'guest' => $row->guest_name,
            'guest_email' => $row->stay?->reservation?->email,
            'booking_no' => $row->booking_no,
            'pool' => $pool->name,
            'date' => \Carbon\CarbonImmutable::parse($date)->format('d M Y'),
            'slot' => $row->slot,
            'pax' => $row->pax,
            'amount' => (float) $row->total_amount > 0
                ? '₹ ' . number_format((float) $row->total_amount, 2)
                : null,
        ], $branchId);

        Notify::event('pool.booked')
            ->title('Pool booked — ' . $row->booking_no)
            ->body(trim($row->guest_name . ' · ' . $pool->name . ' · ' . $date . ' ' . $row->slot))
            ->url(route('pool.bookings'))
            ->send();

        return redirect()->route('pool.bookings', ['from' => $date, 'to' => $date])
            ->with('status', 'Pool booking ' . $row->booking_no . ' saved.')
            ->with($warning ? 'warning' : 'ignored', $warning);
    }
    public function status(Request $request, int $booking): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();
        $row = PoolBooking::query()->forBranch($branchId)->findOrFail($booking);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(PoolBooking::STATUSES))],
        ]);

        if ($row->isClosed()) {
            return back()->with('error', 'A ' . strtolower($row->status_label) . ' booking cannot be moved on.');
        }

        $row->update(['status' => $data['status']]);

        return back()->with('status', $row->booking_no . ' is now ' . strtolower($row->status_label) . '.');
    }
    public function cancel(Request $request, int $booking): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();
        $row = PoolBooking::query()->forBranch($branchId)->with('pool')->findOrFail($booking);

        if ($row->isCancelled()) {
            return back()->with('info', 'That booking was already cancelled.');
        }

        $warning = null;

        DB::transaction(function () use ($row, $branchId, &$warning) {
            $warning = Facility::releaseFolio($row, $branchId);

            $row->update(['status' => 'cancelled']);
        });

        Notify::event('pool.cancelled')
            ->title('Pool booking cancelled — ' . $row->booking_no)
            ->body(trim($row->guest_name . ' · ' . ($row->pool?->name ?? '')))
            ->url(route('pool.bookings'))
            ->send();

        return back()
            ->with('status', $row->booking_no . ' cancelled.')
            ->with($warning ? 'warning' : 'ignored', $warning);
    }
    public function calendar(Request $request): View
    {
        $branchId = (int) Helper::getActiveBranchId();
        $date = Facility::date($request->string('date')->toString());

        $pools = Pool::query()->forBranch($branchId)->active()->orderBy('name')->get();

        $bookings = PoolBooking::query()
            ->forBranch($branchId)
            ->holding()
            ->whereDate('booking_date', $date)
            ->orderBy('from_time')
            ->get()
            ->groupBy('pool_id');

        $starts = $pools->pluck('open_time')->filter()->map(fn ($t) => Facility::minutes($t));
        $ends = $pools->pluck('close_time')->filter()->map(fn ($t) => Facility::minutes($t));

        foreach ($bookings->flatten() as $booking) {
            $starts->push(Facility::minutes($booking->from_time));
            $ends->push(Facility::minutes($booking->to_time));
        }

        $openHour = (int) floor(($starts->min() ?? 360) / 60);
        $closeHour = (int) ceil(($ends->max() ?? 1320) / 60);

        if ($closeHour <= $openHour) {
            $closeHour = min(24, $openHour + 1);
        }

        return view('pool.calendar', [
            'date' => $date,
            'pools' => $pools,
            'bookings' => $bookings,
            'openHour' => $openHour,
            'closeHour' => $closeHour,
            'hours' => range($openHour, max($openHour, $closeHour - 1)),
        ]);
    }
    private function poolIsBusy(
        Pool $pool,
        int $branchId,
        string $date,
        string $from,
        string $to,
        array $data,
        ?int $exceptId
    ): ?string {
        $overlapping = PoolBooking::query()
            ->forBranch($branchId)
            ->holding()
            ->where('pool_id', $pool->id)
            ->whereDate('booking_date', $date)
            ->where('from_time', '<', $to)
            ->where('to_time', '>', $from)
            ->when($exceptId, fn ($q, $id) => $q->where('id', '!=', $id))
            ->get();

        if ((int) $pool->capacity > 0) {
            $already = $overlapping->sum(fn (PoolBooking $b) => (int) $b->adults + (int) $b->children);
            $wanted = (int) $data['adults'] + (int) ($data['children'] ?? 0);

            if ($already + $wanted > (int) $pool->capacity) {
                return sprintf(
                    '%s holds %d people and %d are already booked between %s and %s — this booking of %d would take it to %d.',
                    $pool->name,
                    (int) $pool->capacity,
                    $already,
                    substr($from, 0, 5),
                    substr($to, 0, 5),
                    $wanted,
                    $already + $wanted
                );
            }

            return null;
        }

        if ($overlapping->isNotEmpty()) {
            $clash = $overlapping->first();

            return sprintf(
                '%s is already booked %s on %s (%s). Pick another time, or set a capacity on the pool so sessions can share it.',
                $pool->name,
                $clash->slot,
                $date,
                $clash->booking_no
            );
        }

        return null;
    }
}
