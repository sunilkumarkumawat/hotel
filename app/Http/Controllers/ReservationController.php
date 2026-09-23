<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Models\Master\BillingInstruction;
use App\Models\Master\BookedBy;
use App\Models\Master\BusinessMarket;
use App\Models\Master\Company;
use App\Models\Master\Guest;
use App\Models\Master\PayMode;
use App\Models\Master\PickDrop;
use App\Models\Master\PlanType;
use App\Models\Master\Room;
use App\Models\Master\RoomCategory;
use App\Models\Master\RoomType;
use App\Models\Master\Service;
use App\Models\Master\VisitPurpose;
use App\Models\Reservation\AdvanceDeposit;
use App\Models\Reservation\Reservation;
use App\Models\Reservation\ReservationRoom;
use App\Models\Reservation\ReservationService;
use App\Models\User;
use App\Support\Money;
use App\Support\GuestMessage;
use App\Support\Notify;
use App\Support\Tax;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReservationController extends Controller
{
    /**
     * GET reservation/new-reservation
     *
     * The Status View links here with ?date=&category=, so clicking a free
     * night lands on the form with that night and category already chosen.
     */
    public function create(Request $request): View
    {
        $data = $this->formData(new Reservation([
            'reservation_date' => now()->toDateString(),
            'reservation_type' => 'confirm',
            'title' => 'Mr.',
            'country_id' => $this->defaultCountryId(),
        ]));

        $arrival = rescue(
            fn () => Carbon::parse($request->string('date')->toString() ?: 'today')->toDateString(),
            now()->toDateString(),
            false
        );

        $checkout = rescue(
            fn () => $request->filled('checkout')
                ? Carbon::parse($request->string('checkout')->toString())->toDateString()
                : Carbon::parse($arrival)->addDay()->toDateString(),
            Carbon::parse($arrival)->addDay()->toDateString(),
            false
        );

        // A room chosen on the tape chart carries its category across too, so
        // the Room Type list is already filtered when the form opens.
        $room = $request->integer('room')
            ? Room::query()->forBranch()->find($request->integer('room'))
            : null;

        $data['prefill'] = [
            'arrival_date' => $arrival,
            'checkout_date' => max($checkout, Carbon::parse($arrival)->addDay()->toDateString()),
            'room_category_id' => $room?->room_category_id ?: ($request->integer('category') ?: null),
            'room_type_id' => $room?->room_type_id,
        ];

        return view('reservation.form', $data);
    }

    /** POST reservation/new-reservation */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $branchId = Helper::getActiveBranchId();

        $reservation = DB::transaction(function () use ($data, $branchId, $request) {
            $guest = $this->syncGuest($data, $branchId);

            $reservation = Reservation::create($this->header($data, $branchId) + [
                'reservation_no' => Reservation::nextNumber($branchId),
                'guest_id' => $guest?->id,
                'created_by' => $request->user()->user_id,
            ]);

            $this->saveRooms($reservation, $data['rooms'] ?? []);
            $this->saveServices($reservation, $data['services'] ?? []);
            $this->saveDeposit($reservation, $data, $branchId, $request->user()->user_id);
            $this->recalculate($reservation);

            return $reservation;
        });

        /*
         * The guest's own copy, on their phone. Sent after the transaction has
         * committed, never inside it: a gateway that hangs for ten seconds
         * would otherwise hold a database lock open for ten seconds.
         */
        $stay = $reservation->rooms()->with(['type', 'plan', 'room'])->get();
        $firstStay = $stay->first();
        $nights = ($firstStay && $stay->isNotEmpty())
            ? max(1, \Illuminate\Support\Carbon::parse($stay->min('arrival_date'))->diffInDays(\Illuminate\Support\Carbon::parse($stay->max('checkout_date'))))
            : 0;
        $adultGuests = $stay->sum(fn ($row) => (int) $row->male + (int) $row->female);
        $childGuests = $stay->sum(fn ($row) => (int) $row->child);
        $guestCount = trim($adultGuests . ' Adults' . ($childGuests ? ', ' . $childGuests . ' Child' . ($childGuests > 1 ? 'ren' : '') : ''));
        $roomType = (string) ($firstStay?->type?->name ?? 'Room');
        $roomNo = (string) ($firstStay?->room_no ?: $firstStay?->room?->room_no ?: 'Subject to availability');
        $mealPlan = (string) ($firstStay?->plan?->name ?? 'Room Only');
        $paymentMode = (string) ($reservation->payMode?->name ?? 'Not specified');
        $total = (float) $reservation->net_amount;
        $advance = (float) $reservation->advance_paid;
        $paymentStatus = $advance >= $total && $total > 0
            ? 'Paid'
            : ($advance > 0 ? 'Partially Paid' : 'Pending');

        GuestMessage::send('guest.booking', $reservation->mobile, [
            'guest' => $reservation->guest_name,
            'guest_email' => $reservation->email,
            'guest_phone' => $reservation->mobile,
            'guest_address' => trim(implode(', ', array_filter([
                $reservation->address,
                $reservation->city?->name,
                $reservation->state?->name,
                $reservation->zip_code,
            ]))),
            'reservation_no' => $reservation->reservation_no,
            'booking_date' => optional($reservation->reservation_date)->format('d M Y'),
            'arrival' => optional($stay->min('arrival_date'))->format('d M Y'),
            'arrival_time' => $firstStay?->arrival_time ? \Illuminate\Support\Carbon::parse($firstStay->arrival_time)->format('h:i A') : '02:00 PM',
            'departure' => optional($stay->max('checkout_date'))->format('d M Y'),
            'departure_time' => $firstStay?->checkout_time ? \Illuminate\Support\Carbon::parse($firstStay->checkout_time)->format('h:i A') : '11:00 AM',
            'nights' => $nights,
            'rooms' => $stay->sum('no_of_rooms'),
            'guests' => $guestCount ?: '1 Adult',
            'room_type' => $roomType,
            'room_no' => $roomNo,
            'meal_plan' => $mealPlan,
            'payment_mode' => $paymentMode,
            'payment_status' => $paymentStatus,
            'amount' => '₹ ' . number_format($total, 2),
            'advance' => $advance > 0 ? '₹ ' . number_format($advance, 2) : '₹ 0.00',
            'balance' => '₹ ' . number_format(max(0, $total - $advance), 2),
        ], $branchId);

        Notify::event('reservation.created')
            ->title('New booking — ' . $reservation->reservation_no)
            ->body(trim($reservation->guest_name . ' · ' . $reservation->rooms->sum('no_of_rooms')
                . ' room(s) · ₹ ' . number_format((float) $reservation->net_amount, 2)))
            ->url(route('reservation.show', $reservation))
            ->send();

        return redirect()
            ->route('reservation.show', $reservation)
            ->with('status', "Reservation {$reservation->reservation_no} has been saved.");
    }

    /** GET reservation/booking-details */
    public function index(Request $request): View
    {
        $filters = [
            'q' => $request->string('q')->toString(),
            'status' => $request->string('status')->toString(),
            'from' => $request->string('from')->toString(),
            'to' => $request->string('to')->toString(),
        ];

        $reservations = Reservation::query()
            ->where('branch_id', Helper::getActiveBranchId())
            // rooms.checkIns feeds the Check-in column: a booking can only be
            // checked in while it still has rooms nobody has arrived for.
            ->with(['rooms.type', 'rooms.checkIns', 'company', 'bookedBy'])
            ->search($filters['q'])
            ->when($filters['status'], fn ($q, $s) => $q->where('status', $s))
            ->when($filters['from'], fn ($q, $d) => $q->whereDate('reservation_date', '>=', $d))
            ->when($filters['to'], fn ($q, $d) => $q->whereDate('reservation_date', '<=', $d))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $base = Reservation::query()->where('branch_id', Helper::getActiveBranchId());

        return view('reservation.index', [
            'reservations' => $reservations,
            'filters' => $filters,
            'counts' => [
                'all' => (clone $base)->count(),
                'confirmed' => (clone $base)->where('status', 'confirmed')->count(),
                'cancelled' => (clone $base)->where('status', 'cancelled')->count(),
                'revenue' => (clone $base)->live()->sum('net_amount'),
            ],
        ]);
    }

    /** GET reservation/{reservation} */
    public function show(Reservation $reservation): View
    {
        $this->guardBranch($reservation);

        $reservation->load([
            'rooms.type', 'rooms.category', 'rooms.plan', 'rooms.room',
            'services', 'deposits.payMode',
            'company', 'bookedBy', 'businessMarket', 'visitPurpose',
            'pickDrop', 'billingInstruction', 'payMode', 'employee',
        ]);

        return view('reservation.show', compact('reservation'));
    }

    /** GET reservation/{reservation}/edit */
    public function edit(Reservation $reservation): View
    {
        $this->guardBranch($reservation);

        abort_unless($reservation->isEditable(), 403, 'A cancelled or checked-in reservation cannot be edited.');

        $reservation->load(['rooms', 'services']);

        return view('reservation.form', $this->formData($reservation));
    }

    /** PUT reservation/{reservation} */
    public function update(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->guardBranch($reservation);
        abort_unless($reservation->isEditable(), 403);

        $data = $this->validated($request, $reservation);
        $branchId = Helper::getActiveBranchId();

        $dropped = DB::transaction(function () use ($data, $reservation, $branchId) {
            $guest = $this->syncGuest($data, $branchId, $reservation->guest_id);

            $reservation->update($this->header($data, $branchId) + ['guest_id' => $guest?->id]);

            /*
             * A room already allotted from the Reservation Calendar (room_id
             * set, nobody arrived yet — isEditable() only rules out a guest
             * who has actually checked in) must not vanish just because the
             * clerk fixed a phone number. The grid below is a full delete and
             * recreate, so the allotment is snapshotted here and matched back
             * onto the new rows afterwards.
             */
            $previousRooms = $reservation->rooms()->orderBy('id')->get()->values();

            $reservation->rooms()->delete();
            $reservation->services()->delete();

            $this->saveRooms($reservation, $data['rooms'] ?? []);
            $this->saveServices($reservation, $data['services'] ?? []);
            $this->recalculate($reservation);

            return $this->reattachRooms($reservation, $previousRooms);
        });

        return redirect()
            ->route('reservation.show', $reservation)
            ->with('status', "Reservation {$reservation->reservation_no} has been updated."
                . ($dropped ? " {$dropped} room(s) need to be re-allotted from the Reservation Calendar." : ''));
    }

    /** POST reservation/{reservation}/cancel */
    public function cancel(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->guardBranch($reservation);

        $request->validate(['cancel_reason' => 'required|string|max:255']);

        if ($reservation->isCancelled()) {
            return back()->with('error', 'This reservation is already cancelled.');
        }

        // Status is not the whole story: a booking with a second room still to
        // arrive stays "confirmed" while somebody is already in the first one,
        // and cancelling would hand their room back to the sale pool.
        if (in_array($reservation->status, ['checked_in', 'checked_out'], true) || $reservation->hasArrivals()) {
            return back()->with('error', 'Somebody on this booking has already checked in — cancel from Front Office instead.');
        }

        $reservation->update([
            'status' => 'cancelled',
            'cancelled_on' => now()->toDateString(),
            'cancel_reason' => $request->string('cancel_reason'),
        ]);

        // The rooms go back into the pool the moment the booking is cancelled.
        $reservation->rooms()->update(['room_id' => null, 'room_no' => null]);

        GuestMessage::send('guest.booking-cancelled', $reservation->mobile, [
            'guest' => $reservation->guest_name,
            'guest_email' => $reservation->email,
            'reservation_no' => $reservation->reservation_no,
            'reason' => $request->string('cancel_reason')->toString(),
        ], $reservation->branch_id);

        Notify::event('reservation.cancelled')
            ->title('Booking cancelled — ' . $reservation->reservation_no)
            ->body(trim($reservation->guest_name . ' · ' . $request->string('cancel_reason')->toString()))
            ->url(route('reservation.cancelled'))
            ->send();

        return back()->with('status', "Reservation {$reservation->reservation_no} has been cancelled.");
    }

    /** GET reservation/cancel-list */
    public function cancelled(Request $request): View
    {
        $term = $request->string('q')->toString();

        $reservations = Reservation::query()
            ->where('branch_id', Helper::getActiveBranchId())
            ->where('status', 'cancelled')
            ->search($term)
            ->orderByDesc('cancelled_on')
            ->paginate(15)
            ->withQueryString();

        return view('reservation.cancelled', compact('reservations', 'term'));
    }

    /** POST reservation/{reservation}/deposit */
    public function deposit(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->guardBranch($reservation);

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'deposit_date' => 'required|date',
            'pay_mode_id' => 'nullable|integer',
            'reference_no' => 'nullable|string|max:60',
            'type' => 'required|in:deposit,refund',
            'remark' => 'nullable|string|max:255',
        ]);

        if ($data['type'] === 'refund' && $data['amount'] > $reservation->advance_paid) {
            return back()->with('error', 'You cannot refund more than has been deposited.');
        }

        DB::transaction(function () use ($data, $reservation, $request) {
            AdvanceDeposit::create($data + [
                'branch_id' => $reservation->branch_id,
                'reservation_id' => $reservation->id,
                'created_by' => $request->user()->user_id,
            ]);

            $reservation->refreshAdvancePaid();
        });

        // A refund is not good news to announce as one, so only money coming
        // in earns the guest a message.
        if ($data['type'] === 'deposit') {
            GuestMessage::send('guest.advance', $reservation->mobile, [
                'guest' => $reservation->guest_name,
                'guest_email' => $reservation->email,
                'reservation_no' => $reservation->reservation_no,
                'amount' => '₹ ' . number_format((float) $data['amount'], 2),
            ], $reservation->branch_id);

            Notify::event('reservation.deposit')
                ->title('Advance ₹ ' . number_format((float) $data['amount'], 2) . ' — ' . $reservation->reservation_no)
                ->body($reservation->guest_name)
                ->url(route('reservation.show', $reservation))
                ->send();
        }

        return back()->with('status', ucfirst($data['type']) . ' of ₹' . number_format($data['amount'], 2) . ' recorded.');
    }

    /*
    |--------------------------------------------------------------------------
    | AJAX helpers used by the form
    |--------------------------------------------------------------------------
    */

    /** Customer Search — find a returning guest by name, mobile or email. */
    public function searchGuests(Request $request): JsonResponse
    {
        $guests = Guest::query()
            ->forBranch()
            ->search($request->string('q')->toString())
            ->orderBy('first_name')
            ->limit(15)
            ->get();

        return response()->json($guests->map(fn (Guest $g) => [
            'id' => $g->id,
            'name' => $g->name,
            'title' => $g->title,
            'first_name' => $g->first_name,
            'last_name' => $g->last_name,
            'email' => $g->email,
            'email2' => $g->email2,
            'mobile' => $g->mobile,
            'mobile2' => $g->mobile2,
            'address' => $g->address,
            'dob' => $g->dob?->format('Y-m-d'),
            'gender' => $g->gender,
            'country_id' => $g->country_id,
            'state_id' => $g->state_id,
            'city_id' => $g->city_id,
            'zip_code' => $g->zip_code,
            'company_id' => $g->company_id,
        ]));
    }

    /**
     * Rooms free for these dates, in this category / type.
     *
     * Two numbers come back, and they answer two different questions:
     *
     *   `available` — how many rooms the current filters leave free. The
     *                 "Avl : n" chip at the top of the rooms card.
     *   `by_type`   — how many of EACH type are free, ignoring the type that
     *                 happens to be selected. That is what lets the Room Type
     *                 dropdown say "Deluxe Double — 4 free" on every line, so a
     *                 clerk booking a family of five can see at a glance which
     *                 type can actually take them rather than picking one and
     *                 finding out on save.
     */
    public function availability(Request $request): JsonResponse
    {
        $data = $request->validate([
            'arrival_date' => 'required|date',
            'checkout_date' => 'required|date|after:arrival_date',
            'room_category_id' => 'nullable|integer',
            'room_type_id' => 'nullable|integer',
        ]);

        // Every free room in the category, before the type filter narrows it.
        $free = Room::query()
            ->forBranch()
            ->availableBetween($data['arrival_date'], $data['checkout_date'])
            ->when($data['room_category_id'] ?? null, fn ($q, $id) => $q->where('room_category_id', $id))
            ->with('type')
            ->orderBy('room_no')
            ->get();

        $rooms = $data['room_type_id'] ?? null
            ? $free->where('room_type_id', $data['room_type_id'])->values()
            : $free;

        return response()->json([
            'available' => $rooms->count(),
            'nights' => Money::nights($data['arrival_date'], $data['checkout_date']),
            // Keyed by room type id, as a plain object the browser can look up.
            'by_type' => $free->groupBy('room_type_id')
                ->map(fn ($group) => $group->count())
                ->all(),
            'rooms' => $rooms->map(fn (Room $r) => [
                'id' => $r->id,
                'room_no' => $r->room_no,
                'type' => $r->type?->name,
                'rent' => (float) ($r->base_rent ?: $r->type?->base_rent ?: 0),
            ])->values(),
        ]);
    }

    /** Room types inside a category, with their rents. */
    public function roomTypes(Request $request): JsonResponse
    {
        $types = RoomType::query()
            ->forBranch()
            ->active()
            ->when($request->integer('room_category_id'), fn ($q, $id) => $q->where('room_category_id', $id))
            ->orderBy('name')
            ->get(['id', 'name', 'base_rent', 'max_adult', 'max_child']);

        return response()->json($types);
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    private function guardBranch(Reservation $reservation): void
    {
        abort_unless($reservation->branch_id === Helper::getActiveBranchId(), 404);
    }

    /** Everything the form needs to render. */
    private function formData(Reservation $reservation): array
    {
        return [
            'reservation' => $reservation,
            'types' => Reservation::TYPES,
            'guestTypes' => ReservationRoom::GUEST_TYPES,
            'categories' => RoomCategory::query()->forBranch()->active()->orderBy('sort')->orderBy('name')->get(),
            'roomTypes' => RoomType::query()->forBranch()->active()->orderBy('name')->get(),
            'planTypes' => PlanType::query()->forBranch()->active()->orderBy('name')->get(),
            'services' => Service::query()->forBranch()->active()->with('tax')->orderBy('name')->get(),
            'companies' => Company::query()->forBranch()->active()->orderBy('name')->pluck('name', 'id'),
            'markets' => BusinessMarket::query()->forBranch()->active()->orderBy('name')->pluck('name', 'id'),
            'purposes' => VisitPurpose::query()->forBranch()->active()->orderBy('name')->pluck('name', 'id'),
            'pickDrops' => PickDrop::query()->forBranch()->active()->orderBy('name')->pluck('name', 'id'),
            'bookedBy' => BookedBy::query()->forBranch()->active()->orderBy('name')->pluck('name', 'id'),
            'instructions' => BillingInstruction::query()->forBranch()->active()->orderBy('name')->pluck('name', 'id'),
            'payModes' => PayMode::query()->forBranch()->active()->orderBy('name')->pluck('name', 'id'),
            'employees' => User::query()
                ->where('branch_id', Helper::getActiveBranchId())
                ->where('status', 1)
                ->orderBy('name')
                ->pluck('name', 'user_id'),
            'countries' => Helper::getCountries(),
            'states' => $reservation->country_id ? Helper::getStates($reservation->country_id) : collect(),
            'cities' => $reservation->state_id ? Helper::getCities($reservation->state_id) : collect(),
            'taxSlabs' => config('pms.room_tax_slabs', []),
            /*
             * The Tax dropdown that sits on every room row and every service
             * row. It opens on "No Tax" and stays there unless somebody picks
             * something — nothing on this form attracts tax by itself.
             */
            'taxChoices' => Tax::options(Helper::getActiveBranchId(), withSlab: true),
            'serviceTaxChoices' => Tax::options(Helper::getActiveBranchId()),
            'defaultTaxChoice' => Tax::defaultChoice(),
            'prefill' => [
                'arrival_date' => now()->toDateString(),
                'checkout_date' => now()->addDay()->toDateString(),
                'room_category_id' => null,
                'room_type_id' => null,
                'room_id' => null,
                'room_no' => null,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Reservation $reservation = null): array
    {
        $branchId = (int) Helper::getActiveBranchId();

        return $request->validate([
            'reservation_date' => 'required|date',
            'reservation_type' => ['required', Rule::in(array_keys(Reservation::TYPES))],

            'title' => 'nullable|string|max:10',
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'email2' => 'nullable|email|max:255',
            'mobile' => 'required|string|max:20',
            'mobile2' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'dob' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female,other',
            'country_id' => 'nullable|integer',
            'state_id' => 'nullable|integer',
            'city_id' => 'nullable|integer',
            'zip_code' => 'nullable|string|max:12',
            'guest_id' => 'nullable|integer',

            'pick_drop_id' => 'nullable|integer',
            'visit_purpose_id' => 'nullable|integer',
            'arrival_from' => 'nullable|string|max:255',
            'departure_to' => 'nullable|string|max:255',
            'transport_mode' => 'nullable|string|max:255',
            'confirm_voucher_no' => 'nullable|string|max:60',

            'booked_by_id' => 'nullable|integer',
            'business_market_id' => 'nullable|integer',
            'company_id' => 'nullable|integer',
            'company_gst_no' => 'nullable|string|max:20',
            'emp_id' => 'nullable|integer',

            'billing_instruction_id' => 'nullable|integer',
            'pay_mode_id' => 'nullable|integer',
            'remark' => 'nullable|string|max:2000',
            'special_remark' => 'nullable|string|max:2000',

            // At least one room — a reservation with no room is not a booking.
            'rooms' => 'required|array|min:1',
            'rooms.*.arrival_date' => 'required|date',
            'rooms.*.arrival_time' => 'nullable',
            // `after`, not `after_or_equal`: a stay of zero nights holds no room at
            // all, so the same room could be checked in twice over the same day.
            'rooms.*.checkout_date' => 'required|date|after:rooms.*.arrival_date',
            'rooms.*.checkout_time' => 'nullable',
            'rooms.*.guest_type' => ['required', Rule::in(array_keys(ReservationRoom::GUEST_TYPES))],
            'rooms.*.room_category_id' => 'nullable|integer',
            'rooms.*.room_type_id' => 'required|integer',
            'rooms.*.plan_type_id' => 'nullable|integer',
            /*
             * A row books a NUMBER of rooms of a type — five for a family — and
             * never a room number. Which five rooms they get is decided when
             * they arrive, from Front Office; by then the house has moved
             * anyway, and a booking that named 102 three weeks out was only
             * ever a wish.
             */
            'rooms.*.no_of_rooms' => 'required|integer|min:1|max:50',
            'rooms.*.tax_type' => 'required|in:exclusive,inclusive',
            /*
             * Tax is a choice, and "No Tax" is what the dropdown opens on. The
             * rule is built from the live list rather than a hard-coded `in:`,
             * so a tax added in Masters works on the next request.
             */
            'rooms.*.tax_choice' => Tax::rule($branchId, withSlab: true),
            'rooms.*.room_rent' => 'required|numeric|min:0',
            'rooms.*.discount' => 'nullable|numeric|min:0',
            'rooms.*.male' => 'nullable|integer|min:0|max:50',
            'rooms.*.female' => 'nullable|integer|min:0|max:50',
            'rooms.*.child' => 'nullable|integer|min:0|max:50',

            'services' => 'nullable|array',
            'services.*.service_id' => 'nullable|integer',
            'services.*.service_name' => 'required_with:services.*.price|string|max:255',
            'services.*.tax_type' => 'nullable|in:exclusive,inclusive',
            'services.*.qty' => 'nullable|numeric|min:0',
            'services.*.price' => 'nullable|numeric|min:0',
            'services.*.tax_percent' => 'nullable|numeric|min:0|max:100',
            'services.*.tax_choice' => Tax::rule($branchId),
            'services.*.remark' => 'nullable|string|max:255',

            'advance_amount' => 'nullable|numeric|min:0',
            'advance_pay_mode_id' => 'nullable|integer',
            'advance_reference_no' => 'nullable|string|max:60',
        ], [
            'rooms.required' => 'Add at least one room to the allotment grid before saving.',
            'rooms.*.room_type_id.required' => 'Every room row needs a room type.',
            'rooms.*.checkout_date.after' => 'A checkout date has to be at least the day after arrival — one night is the shortest stay.',
        ]);
    }

    /** @return array<string, mixed> */
    private function header(array $data, int $branchId): array
    {
        return [
            'branch_id' => $branchId,
            'reservation_date' => $data['reservation_date'],
            'reservation_type' => $data['reservation_type'],
            'status' => $data['reservation_type'] === 'tentative' ? 'tentative' : 'confirmed',
            'title' => $data['title'] ?? null,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'] ?? null,
            'email' => $data['email'] ?? null,
            'email2' => $data['email2'] ?? null,
            'mobile' => $data['mobile'],
            'mobile2' => $data['mobile2'] ?? null,
            'address' => $data['address'] ?? null,
            'dob' => $data['dob'] ?? null,
            'gender' => $data['gender'] ?? null,
            'country_id' => $data['country_id'] ?? null,
            'state_id' => $data['state_id'] ?? null,
            'city_id' => $data['city_id'] ?? null,
            'zip_code' => $data['zip_code'] ?? null,
            'pick_drop_id' => $data['pick_drop_id'] ?? null,
            'visit_purpose_id' => $data['visit_purpose_id'] ?? null,
            'arrival_from' => $data['arrival_from'] ?? null,
            'departure_to' => $data['departure_to'] ?? null,
            'transport_mode' => $data['transport_mode'] ?? null,
            'confirm_voucher_no' => $data['confirm_voucher_no'] ?? null,
            'booked_by_id' => $data['booked_by_id'] ?? null,
            'business_market_id' => $data['business_market_id'] ?? null,
            'company_id' => $data['company_id'] ?? null,
            'company_gst_no' => $data['company_gst_no'] ?? null,
            'emp_id' => $data['emp_id'] ?? null,
            'billing_instruction_id' => $data['billing_instruction_id'] ?? null,
            'pay_mode_id' => $data['pay_mode_id'] ?? null,
            'remark' => $data['remark'] ?? null,
            'special_remark' => $data['special_remark'] ?? null,
        ];
    }

    /**
     * Keep the guest book up to date.
     *
     * A returning guest picked through Customer Search is updated; a new name
     * with a new mobile is added, so the second visit can be searched for.
     */
    private function syncGuest(array $data, int $branchId, ?int $existingId = null): ?Guest
    {
        $fields = [
            'title', 'first_name', 'last_name', 'email', 'email2', 'mobile', 'mobile2',
            'address', 'dob', 'gender', 'country_id', 'state_id', 'city_id', 'zip_code', 'company_id',
        ];

        $attributes = ['branch_id' => $branchId];

        foreach ($fields as $field) {
            $attributes[$field] = $data[$field] ?? null;
        }

        $id = $data['guest_id'] ?? $existingId;

        if ($id && $guest = Guest::query()->forBranch($branchId)->find($id)) {
            $guest->update($attributes);

            return $guest;
        }

        // Same mobile in the same branch is the same person.
        $match = Guest::query()->forBranch($branchId)->where('mobile', $data['mobile'])->first();

        if ($match) {
            $match->update($attributes);

            return $match;
        }

        return Guest::create($attributes);
    }

    private function saveRooms(Reservation $reservation, array $rows): void
    {
        $plans = PlanType::query()->forBranch($reservation->branch_id)->pluck('charge', 'id');
        foreach ($rows as $row) {
            $nights = Money::nights($row['arrival_date'], $row['checkout_date']);

            // Resolved once, here, and then stored on the row. The plan master
            // can be re-priced next month; a booking already taken must not
            // quietly change what the guest was quoted — and the bill has to be
            // able to charge the same nightly rate this row was priced at.
            $planCharge = (float) ($plans[$row['plan_type_id'] ?? null] ?? 0);

            $figures = Money::roomRow([
                'room_rent' => (float) $row['room_rent'],
                'discount' => (float) ($row['discount'] ?? 0),
                'plan_charge' => $planCharge,
                'no_of_days' => $nights,
                'no_of_rooms' => (int) $row['no_of_rooms'],
                'tax_type' => $row['tax_type'],
                'tax_choice' => $row['tax_choice'] ?? Tax::defaultChoice(),
            ], $reservation->branch_id);

            $reservation->rooms()->create([
                'arrival_date' => $row['arrival_date'],
                // Both times are nullable, so the key can be missing entirely
                // when the row does not come from the browser form.
                'arrival_time' => ($row['arrival_time'] ?? null) ?: config('pms.default_arrival_time'),
                'checkout_date' => $row['checkout_date'],
                'checkout_time' => ($row['checkout_time'] ?? null) ?: config('pms.default_checkout_time'),
                'guest_type' => $row['guest_type'],
                'room_category_id' => $row['room_category_id'] ?? null,
                'room_type_id' => $row['room_type_id'],
                'plan_type_id' => $row['plan_type_id'] ?? null,
                // Allotted at check-in, not at booking.
                'room_id' => null,
                'room_no' => null,
                'no_of_days' => $nights,
                'no_of_rooms' => (int) $row['no_of_rooms'],
                'tax_type' => $row['tax_type'],
                // Stored, not re-derived: what this booking was quoted at is
                // what it stays at, whatever Masters → Tax does next month.
                'tax_choice' => Tax::normalise($row['tax_choice'] ?? Tax::defaultChoice()),
                'room_rent' => (float) $row['room_rent'],
                'discount' => (float) ($row['discount'] ?? 0),
                'plan_charge' => $planCharge,
                'male' => (int) ($row['male'] ?? 0),
                'female' => (int) ($row['female'] ?? 0),
                'child' => (int) ($row['child'] ?? 0),
                'amount' => $figures['amount'],
                'tax_percent' => $figures['tax_percent'],
                'tax_amount' => $figures['tax_amount'],
                'net_amount' => $figures['net_amount'],
            ]);
        }
    }

    private function saveServices(Reservation $reservation, array $rows): void
    {
        foreach ($rows as $row) {
            if (blank($row['service_name'] ?? null)) {
                continue;
            }

            $choice = Tax::normalise($row['tax_choice'] ?? Tax::defaultChoice());

            $figures = Money::serviceRow([
                'qty' => (float) ($row['qty'] ?? 1),
                'price' => (float) ($row['price'] ?? 0),
                'tax_percent' => (float) ($row['tax_percent'] ?? 0),
                'tax_choice' => $choice,
                'tax_type' => $row['tax_type'] ?? 'exclusive',
            ], $reservation->branch_id);

            $reservation->services()->create([
                'service_id' => $row['service_id'] ?? null,
                'service_name' => $row['service_name'],
                'tax_type' => $row['tax_type'] ?? 'exclusive',
                'tax_choice' => $choice,
                'qty' => (float) ($row['qty'] ?? 1),
                'price' => (float) ($row['price'] ?? 0),
                'tax_percent' => $figures['tax_percent'],
                'tax_amount' => $figures['tax_amount'],
                'amount' => $figures['amount'],
                'total_amount' => $figures['total_amount'],
                'remark' => $row['remark'] ?? null,
            ]);
        }
    }

    private function saveDeposit(Reservation $reservation, array $data, int $branchId, int $userId): void
    {
        if (! ($data['advance_amount'] ?? 0)) {
            return;
        }

        AdvanceDeposit::create([
            'branch_id' => $branchId,
            'reservation_id' => $reservation->id,
            'deposit_date' => $reservation->reservation_date,
            'pay_mode_id' => $data['advance_pay_mode_id'] ?? $data['pay_mode_id'] ?? null,
            'amount' => (float) $data['advance_amount'],
            'reference_no' => $data['advance_reference_no'] ?? null,
            'type' => 'deposit',
            'created_by' => $userId,
        ]);
    }

    /** Re-add the header totals from the rows that are actually stored. */
    private function recalculate(Reservation $reservation): void
    {
        $reservation->refreshTotals();
        $reservation->refreshAdvancePaid();
    }

    /**
     * Carry a room allotment across the delete-and-recreate in update().
     *
     * Matched back onto the freshly-created rows by position, and only when
     * the new row in that position is still the same shape — same room type,
     * same dates, same room count — as the old one. That covers the ordinary
     * edit, where the room grid itself is untouched and every row lines up
     * exactly. Anything that does not line up is left unallotted rather than
     * guessed at: the room the old row held might not even be free for the
     * new dates, and re-checking that is exactly what the Reservation
     * Calendar's own clash test is for.
     *
     * @param  Collection<int, ReservationRoom>  $previousRooms  the rows as they stood before the delete, in order
     * @return int how many allotments could not be carried across
     */
    private function reattachRooms(Reservation $reservation, Collection $previousRooms): int
    {
        if ($previousRooms->every(fn (ReservationRoom $row) => ! $row->room_id)) {
            return 0;
        }

        $currentRooms = $reservation->rooms()->orderBy('id')->get()->values();
        $dropped = 0;

        foreach ($previousRooms as $position => $old) {
            if (! $old->room_id) {
                continue;
            }

            $new = $currentRooms->get($position);

            $sameShape = $new
                && (int) $new->room_type_id === (int) $old->room_type_id
                && (int) $new->no_of_rooms === (int) $old->no_of_rooms
                && $new->arrival_date->toDateString() === $old->arrival_date->toDateString()
                && $new->checkout_date->toDateString() === $old->checkout_date->toDateString();

            if ($sameShape) {
                $new->update(['room_id' => $old->room_id, 'room_no' => $old->room_no]);
            } else {
                $dropped++;
            }
        }

        return $dropped;
    }

    private function defaultCountryId(): ?int
    {
        return Helper::getCountries()->firstWhere('name', 'India')?->id
            ?? Helper::getCountries()->first()?->id;
    }
}
