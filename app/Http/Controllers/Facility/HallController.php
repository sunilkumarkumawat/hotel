<?php

namespace App\Http\Controllers\Facility;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Facility\Hall;
use App\Models\Facility\HallBooking;
use App\Models\Master\Company;
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
 * The banquet diary.
 *
 * A hall is held from one moment to another, and the one thing this screen must
 * never allow is two weddings in the same room. The clash test is the same
 * half-open rule the room calendar uses, applied to date-and-time together: a
 * conference that ends at 18:00 does not block a reception that starts at
 * 18:00, and a booking being edited never finds itself busy.
 *
 * A tentative booking holds the hall exactly as a confirmed one does. That is
 * the entire point of writing a hold down — a banquet manager who is told a
 * Saturday is free because the hold was "only tentative" has been told
 * something useless.
 */
class HallController extends Controller
{
    /** GET hall/bookings */
    public function index(Request $request): View
    {
        $branchId = (int) Helper::getActiveBranchId();

        $filters = [
            'from' => Facility::date($request->string('from')->toString(), today()->toDateString()),
            'to' => Facility::date($request->string('to')->toString(), today()->addMonth()->toDateString()),
            'hall' => $request->integer('hall'),
            'status' => $request->string('status')->toString(),
            'q' => $request->string('q')->toString(),
        ];

        if ($filters['from'] > $filters['to']) {
            [$filters['from'], $filters['to']] = [$filters['to'], $filters['from']];
        }

        $rows = HallBooking::query()
            ->forBranch($branchId)
            ->with(['hall', 'company', 'items'])
            // An event that starts before the window but runs into it still
            // belongs on the list — the test is overlap, not containment.
            ->where('to_date', '>=', $filters['from'])
            ->where('from_date', '<', CarbonImmutable::parse($filters['to'])->addDay()->toDateString())
            ->when($filters['hall'], fn ($q, $id) => $q->where('hall_id', $id))
            ->when($filters['status'], fn ($q, $s) => $q->where('status', $s))
            ->when($filters['q'], fn ($q, $t) => $q->where(function ($w) use ($t) {
                $w->where('guest_name', 'like', "%{$t}%")
                    ->orWhere('booking_no', 'like', "%{$t}%")
                    ->orWhere('event_type', 'like', "%{$t}%")
                    ->orWhere('mobile', 'like', "%{$t}%");
            }))
            ->orderBy('from_date')
            ->orderBy('from_time')
            ->paginate(25)
            ->withQueryString();

        $live = fn () => HallBooking::query()->forBranch($branchId)->holding();

        return view('hall.bookings', [
            'rows' => $rows,
            'filters' => $filters,
            'halls' => Hall::query()->forBranch($branchId)->active()->orderBy('name')->pluck('name', 'id'),
            'statuses' => HallBooking::STATUSES,
            'counts' => [
                'today' => $live()->whereDate('from_date', '<=', today())->whereDate('to_date', '>=', today())->count(),
                'tentative' => $live()->where('status', 'tentative')->count(),
                'upcoming' => $live()->whereDate('from_date', '>', today())->count(),
                'due' => round(
                    (float) $live()->sum('total_amount') - (float) $live()->sum('advance'),
                    2
                ),
            ],
        ]);
    }

    /** GET hall/bookings/new  ·  GET hall/bookings/{booking}/edit */
    public function form(Request $request, ?int $booking = null): View
    {
        $branchId = (int) Helper::getActiveBranchId();

        $row = $booking
            ? HallBooking::query()->forBranch($branchId)->with('items')->findOrFail($booking)
            : new HallBooking([
                'from_date' => Facility::date($request->string('date')->toString()),
                'to_date' => Facility::date($request->string('date')->toString()),
                'from_time' => '10:00',
                'to_time' => '18:00',
                'rate_type' => 'event',
                'qty' => 1,
                'status' => 'confirmed',
                'tax_choice' => Tax::defaultChoice(),
            ]);

        return view('hall.form', [
            'row' => $row,
            'halls' => Hall::query()->forBranch($branchId)->active()->orderBy('name')->get(),
            'stays' => Facility::stays($branchId),
            'companies' => Company::query()->forBranch($branchId)->active()->orderBy('name')->pluck('name', 'id'),
            'statuses' => HallBooking::STATUSES,
            'rateTypes' => HallBooking::RATE_TYPES,
            'taxChoices' => Tax::optionsFor($row->tax_choice, $branchId, false, (float) $row->tax_percent),
            'itemTaxChoices' => Tax::options($branchId),
        ]);
    }

    /** POST hall/bookings  ·  PUT hall/bookings/{booking} */
    public function save(Request $request, ?int $booking = null): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();

        $row = $booking
            ? HallBooking::query()->forBranch($branchId)->findOrFail($booking)
            : new HallBooking;

        if ($row->exists && $row->isClosed()) {
            return back()->with('error', 'This booking is ' . strtolower($row->status_label) . ' — it cannot be changed.');
        }

        $data = $request->validate([
            'hall_id' => ['required', 'integer'],
            'check_in_id' => 'nullable|integer',
            'company_id' => 'nullable|integer',
            'guest_name' => 'required|string|max:150',
            'mobile' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:150',
            'event_type' => 'nullable|string|max:60',
            'from_date' => 'required|date',
            'from_time' => 'required',
            'to_date' => 'required|date',
            'to_time' => 'required',
            'pax' => 'nullable|integer|min:0|max:5000',
            'rate_type' => ['required', Rule::in(array_keys(HallBooking::RATE_TYPES))],
            'rate' => 'nullable|numeric|min:0|max:99999999',
            'qty' => 'nullable|numeric|min:0|max:9999',
            'discount' => 'nullable|numeric|min:0|max:99999999',
            'tax_choice' => Tax::rule($branchId),
            'advance' => 'nullable|numeric|min:0|max:99999999',
            'status' => ['nullable', Rule::in(array_keys(HallBooking::STATUSES))],
            'post_to_room' => 'nullable|boolean',
            'remark' => 'nullable|string|max:1000',

            'items' => 'nullable|array|max:30',
            'items.*.particulars' => 'nullable|string|max:150',
            'items.*.qty' => 'nullable|numeric|min:0|max:9999',
            'items.*.price' => 'nullable|numeric|min:0|max:9999999',
            'items.*.tax_choice' => Tax::rule($branchId),
        ]);

        $hall = Hall::query()->forBranch($branchId)->find($data['hall_id']);

        if (! $hall) {
            return back()->with('error', 'That hall is not one of this branch\'s.')->withInput();
        }

        $start = Facility::stamp(Facility::date($data['from_date']), $data['from_time']);
        $end = Facility::stamp(Facility::date($data['to_date']), $data['to_time']);

        if ($end <= $start) {
            return back()->with('error', 'The event has to finish after it starts.')->withInput();
        }

        if ($clash = $this->clash($hall, $branchId, $start, $end, $row->id)) {
            return back()->with('error', sprintf(
                '%s is already held by %s (%s) from %s to %s. Nothing was saved.',
                $hall->name,
                $clash->guest_name,
                $clash->booking_no,
                CarbonImmutable::parse($clash->startsAt())->format('d M, h:i A'),
                CarbonImmutable::parse($clash->endsAt())->format('d M, h:i A')
            ))->withInput();
        }

        /*
         * The quantity is worked out from the dates and only then overridden by
         * what the form sent. The form is where somebody types 1 by mistake and
         * bills a three-day wedding as one hour; the screen shows this figure
         * and lets it be changed, which is a different thing from believing
         * whatever arrives.
         */
        $rateType = $data['rate_type'];
        $suggested = Facility::hallQty($rateType, $start, $end);
        $qty = round((float) ($data['qty'] ?? 0) ?: $suggested, 2);

        $rate = round((float) ($data['rate'] ?? 0) ?: $hall->rateFor($rateType), 2);
        $discount = round((float) ($data['discount'] ?? 0), 2);

        $gross = max(0, round(($rate * $qty) - $discount, 2));
        $choice = Tax::normalise($data['tax_choice'] ?? Tax::defaultChoice());

        $figures = Money::serviceRow([
            'qty' => 1,
            'price' => $gross,
            'tax_choice' => $choice,
            'tax_type' => 'exclusive',
        ], $branchId);

        $items = $this->shapeItems($data['items'] ?? [], $branchId);

        /*
         * The extras are kept as two figures, not one.
         *
         * `amount` on this booking is its TAXABLE VALUE and `total_amount` is
         * what the guest pays, and the two have to stay `amount + tax = total`
         * — a folio line is posted straight from them, and a bill whose value
         * and tax do not add up to its total is a bill somebody has to explain.
         * Adding the extras' tax-inclusive total into `amount` would have
         * counted their tax twice.
         */
        $extrasNet = round(array_sum(array_column($items, 'amount')), 2);
        $extrasTax = round(array_sum(array_column($items, 'tax_amount')), 2);

        // Facility::guest prefers the typed name over the stay's, which is what
        // a banquet needs: the wife booking the hall on her husband's room is
        // still the person the manager rings.
        $guest = Facility::guest($data, $branchId);

        $warning = null;

        DB::transaction(function () use (
            $row, $branchId, $hall, $start, $end, $data, $rateType, $rate, $qty,
            $discount, $choice, $figures, $extrasNet, $extrasTax, $items, $guest, $request, &$warning
        ) {
            $row->fill($guest + [
                'branch_id' => $branchId,
                'hall_id' => $hall->id,
                'company_id' => $data['company_id'] ?: null,
                'email' => $data['email'] ?? null,
                'event_type' => $data['event_type'] ?? null,
                'from_date' => substr($start, 0, 10),
                'from_time' => substr($start, 11),
                'to_date' => substr($end, 0, 10),
                'to_time' => substr($end, 11),
                'pax' => (int) ($data['pax'] ?? 0),
                'rate_type' => $rateType,
                'rate' => $rate,
                'qty' => $qty,
                'discount' => $discount,
                'amount' => round($figures['amount'] + $extrasNet, 2),
                'tax_choice' => $choice,
                'tax_percent' => $figures['tax_percent'],
                // Hall hire and its extras are taxed separately — an extra can
                // carry GST where the hire does not — so the two taxes are
                // added rather than one rate being worked out over the lot.
                'tax_amount' => round($figures['tax_amount'] + $extrasTax, 2),
                'total_amount' => round($figures['total_amount'] + $extrasNet + $extrasTax, 2),
                'advance' => round((float) ($data['advance'] ?? 0), 2),
                'status' => $data['status'] ?? ($row->status ?: 'confirmed'),
                'post_to_room' => (bool) ($data['post_to_room'] ?? false),
                'remark' => $data['remark'] ?? null,
            ]);

            if (! $row->exists) {
                $row->booking_no = Facility::nextNumber(
                    'hall_bookings', 'booking_no', config('pms.hall_booking_prefix', 'HALL'), $branchId
                );
                $row->created_by = $request->user()->user_id;
            }

            $row->save();

            // Replaced wholesale rather than merged: the grid on the form is
            // the complete list of extras, and a line the clerk deleted has to
            // actually go.
            $row->items()->delete();

            foreach ($items as $item) {
                $row->items()->create($item);
            }

            $warning = Facility::syncFolio(
                $row,
                'Hall — ' . $hall->name . ' (' . CarbonImmutable::parse($start)->format('d M Y') . ')',
                $branchId,
                $request->user()->user_id,
                substr($start, 0, 10)
            );
        });

        GuestMessage::send('guest.hall', $row->mobile, [
            'guest' => $row->guest_name,
            'guest_email' => $row->stay?->reservation?->email,
            'booking_no' => $row->booking_no,
            'hall' => $hall->name,
            'event' => $row->event_type,
            'from' => CarbonImmutable::parse($start)->format('d M Y, h:i A'),
            'to' => CarbonImmutable::parse($end)->format('d M Y, h:i A'),
            'pax' => (int) $row->pax ?: null,
            'amount' => '₹ ' . number_format((float) $row->total_amount, 2),
            'advance' => (float) $row->advance > 0
                ? '₹ ' . number_format((float) $row->advance, 2)
                : null,
            'balance' => $row->balance > 0
                ? '₹ ' . number_format($row->balance, 2)
                : null,
        ], $branchId);

        Notify::event('hall.booked')
            ->title('Hall booked — ' . $row->booking_no)
            ->body(trim($row->guest_name . ' · ' . $hall->name . ' · '
                . CarbonImmutable::parse($start)->format('d M Y, h:i A')))
            ->url(route('hall.bookings'))
            ->send();

        return redirect()->route('hall.bookings')
            ->with('status', 'Hall booking ' . $row->booking_no . ' saved.')
            ->with($warning ? 'warning' : 'ignored', $warning);
    }

    /** POST hall/bookings/{booking}/status */
    public function status(Request $request, int $booking): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();
        $row = HallBooking::query()->forBranch($branchId)->findOrFail($booking);

        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(HallBooking::STATUSES))],
        ]);

        if ($row->isClosed()) {
            return back()->with('error', 'A ' . strtolower($row->status_label) . ' booking cannot be moved on.');
        }

        $row->update(['status' => $data['status']]);

        return back()->with('status', $row->booking_no . ' is now ' . strtolower($row->status_label) . '.');
    }

    /** POST hall/bookings/{booking}/cancel */
    public function cancel(int $booking): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();
        $row = HallBooking::query()->forBranch($branchId)->with('hall')->findOrFail($booking);

        if ($row->status === 'cancelled') {
            return back()->with('info', 'That booking was already cancelled.');
        }

        $warning = null;

        DB::transaction(function () use ($row, $branchId, &$warning) {
            $warning = Facility::releaseFolio($row, $branchId);

            $row->update(['status' => 'cancelled']);
        });

        Notify::event('hall.cancelled')
            ->title('Hall booking cancelled — ' . $row->booking_no)
            ->body(trim($row->guest_name . ' · ' . ($row->hall?->name ?? '')))
            ->url(route('hall.bookings'))
            ->send();

        return back()
            ->with('status', $row->booking_no . ' cancelled.')
            ->with($warning ? 'warning' : 'ignored', $warning);
    }

    /** GET hall/calendar — a week of the diary, hall by hall. */
    public function calendar(Request $request): View
    {
        $branchId = (int) Helper::getActiveBranchId();

        $anchor = Facility::date($request->string('date')->toString());
        $start = Facility::weekStart($anchor);
        $end = CarbonImmutable::parse($start)->addDays(6)->toDateString();

        $halls = Hall::query()->forBranch($branchId)->active()->orderBy('name')->get();

        $bookings = HallBooking::query()
            ->forBranch($branchId)
            ->holding()
            ->where('to_date', '>=', $start)
            ->where('from_date', '<', CarbonImmutable::parse($end)->addDay()->toDateString())
            ->orderBy('from_date')
            ->orderBy('from_time')
            ->get();

        /*
         * A booking is laid out per day rather than as one bar across the week:
         * a wedding that runs Friday to Sunday shows in all three columns, and
         * the desk can see at a glance that Saturday is gone. A single spanning
         * bar reads better and answers the question worse.
         */
        $grid = [];

        foreach ($bookings as $booking) {
            $from = CarbonImmutable::parse($booking->from_date->toDateString());
            $to = CarbonImmutable::parse($booking->to_date->toDateString());

            for ($day = $from; $day <= $to; $day = $day->addDay()) {
                $key = $day->toDateString();

                if ($key < $start || $key > $end) {
                    continue;
                }

                $grid[(int) $booking->hall_id][$key][] = $booking;
            }
        }

        return view('hall.calendar', [
            'anchor' => $anchor,
            'start' => $start,
            'end' => $end,
            'halls' => $halls,
            'grid' => $grid,
            'days' => collect(range(0, 6))->map(fn ($i) => CarbonImmutable::parse($start)->addDays($i)),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * The booking already holding this hall over this window, if any.
     *
     * Date and time are compared as one string — '2026-09-18 18:00:00' — which
     * works because both halves are zero-padded and stored that way. Comparing
     * the date and the time as two columns would have to special-case a booking
     * that starts one evening and ends the next morning, and that special case
     * is exactly where double-bookings get in.
     */
    private function clash(Hall $hall, int $branchId, string $start, string $end, ?int $exceptId): ?HallBooking
    {
        return HallBooking::query()
            ->forBranch($branchId)
            ->holding()
            ->where('hall_id', $hall->id)
            // Cheap date window first so the raw comparison below runs on a few
            // rows rather than on the whole diary.
            ->where('to_date', '>=', substr($start, 0, 10))
            ->where('from_date', '<=', substr($end, 0, 10))
            ->when($exceptId, fn ($q, $id) => $q->where('id', '!=', $id))
            ->get()
            ->first(fn (HallBooking $b) => $b->startsAt() < $end && $b->endsAt() > $start);
    }

    /**
     * The extras grid, cleaned and priced.
     *
     * Each line is taxed on its own choice — a buffet can carry GST where the
     * hall hire does not — and a line with no name or no money on it is a blank
     * row on a grid of blank rows, not an error.
     *
     * @return array<int, array<string, mixed>>
     */
    private function shapeItems(array $rows, int $branchId): array
    {
        $items = [];

        foreach ($rows as $row) {
            $name = trim((string) ($row['particulars'] ?? ''));
            $qty = round((float) ($row['qty'] ?? 0), 2);
            $price = round((float) ($row['price'] ?? 0), 2);

            if ($name === '' || ($qty <= 0 && $price <= 0)) {
                continue;
            }

            $choice = Tax::normalise($row['tax_choice'] ?? Tax::defaultChoice());

            $figures = Money::serviceRow([
                'qty' => max($qty, 1),
                'price' => $price,
                'tax_choice' => $choice,
                'tax_type' => 'exclusive',
            ], $branchId);

            $items[] = [
                'particulars' => $name,
                'qty' => max($qty, 1),
                'price' => $price,
                'tax_choice' => $choice,
                'tax_percent' => $figures['tax_percent'],
                'tax_amount' => $figures['tax_amount'],
                'amount' => $figures['amount'],
                'total_amount' => $figures['total_amount'],
            ];
        }

        return $items;
    }
}
