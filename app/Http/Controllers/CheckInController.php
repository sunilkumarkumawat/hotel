<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Models\FrontOffice\CheckIn;
use App\Models\FrontOffice\FolioCharge;
use App\Models\FrontOffice\PaxCheckout;
use App\Models\FrontOffice\Settlement;
use App\Models\Master\BillingInstruction;
use App\Models\Master\BookedBy;
use App\Models\Master\BusinessMarket;
use App\Models\Master\Company;
use App\Models\Master\PayMode;
use App\Models\Master\PickDrop;
use App\Models\Master\Room;
use App\Models\Master\VisitPurpose;
use App\Models\Reservation\Reservation;
use App\Models\Reservation\ReservationRoom;
use App\Support\Compliance;
use App\Support\Folio;
use App\Support\FolioRefused;
use App\Support\GuestMessage;
use App\Support\Notify;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CheckInController extends Controller
{

    public function create(Request $request): View|RedirectResponse
    {
 
        if (! $request->integer('reservation')) {
            return redirect()->route('reservation.index')
                ->with('info', 'Pick the booking you want to check in — tick it in the list and press Check In.');
        }

        $reservation = $this->reservation($request->integer('reservation'));

        abort_unless(
            $reservation->isCheckInable(),
            403,
            'Every room on ' . $reservation->reservation_no . ' has already been checked in.'
        );

        return view('front-office.check-in', [
            'reservation' => $reservation,
            'rows' => $this->pendingRows($reservation),
            'payModes' => PayMode::query()->forBranch()->active()->orderBy('name')->pluck('name', 'id'),
            'billingInstructions' => BillingInstruction::query()->forBranch()->active()->orderBy('name')->pluck('name', 'id'),
            'pickDrops' => PickDrop::query()->forBranch()->active()->orderBy('name')->pluck('name', 'id'),
            'visitPurposes' => VisitPurpose::query()->forBranch()->active()->orderBy('name')->pluck('name', 'id'),
            'bookedBy' => BookedBy::query()->forBranch()->active()->orderBy('name')->pluck('name', 'id'),
            'businessMarkets' => BusinessMarket::query()->forBranch()->active()->orderBy('name')->pluck('name', 'id'),
            'companies' => Company::query()->forBranch()->active()->orderBy('name')->pluck('name', 'id'),
            'idTypes' => Compliance::ID_TYPES,
        ]);
    }

    public function rooms(Request $request): JsonResponse
    {
        $row = $this->row($request->integer('row'));
        $rooms = Room::query()
            ->forBranch()
            ->availableBetween($row->arrival_date->toDateString(), $row->checkout_date->toDateString(), $row->id)
            ->when(
                $row->room_category_id,
                fn ($q, $id) => $q->where('room_category_id', $id),
                fn ($q) => $q->when($row->room_type_id, fn ($q, $id) => $q->where('room_type_id', $id))
            )
            ->with(['category', 'type'])
            ->orderBy('room_no')
            ->get();

        return response()->json([
            'row' => [
                'id' => $row->id,
                'pending' => $row->pendingCount(),
                'checked_in' => $row->checkedInCount(),
                'total' => (int) $row->no_of_rooms,
                'category' => $row->category?->name,
                'type' => $row->type?->name,
                'arrival' => $row->arrival_date->toDateString(),
                'checkout' => $row->checkout_date->toDateString(),
                'male' => (int) $row->male,
                'female' => (int) $row->female,
                'child' => (int) $row->child,
            ],
            'rooms' => $rooms->map(fn (Room $room) => [
                'id' => $room->id,
                'room_no' => $room->room_no,
                'category' => $room->category?->name,
                'type' => $room->type?->name,
                'housekeeping' => $room->housekeeping_status,
                'as_booked' => ! $row->room_type_id || $room->room_type_id === $row->room_type_id,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $reservation = $this->reservation($request->integer('reservation_id'));
        $data = $this->validated($request);

        $allotments = $this->allotments($request, $reservation);

        if (is_string($allotments)) {
            return back()->withInput()->with('error', $allotments);
        }
        $idPhoto = $request->hasFile('id_photo')
            ? $request->file('id_photo')->store('id-proofs', 'public')
            : null;
        try {
            $folio = DB::transaction(function () use ($reservation, $data, $allotments, $request, $idPhoto) {
            $folio = CheckIn::nextFolio($reservation->branch_id);

            $reservation->update($this->guestFields($data));

            $earliestDeparture = CarbonImmutable::parse($data['checkin_date'])->addDay()->toDateString();

            foreach ($allotments as $allotment) {
                /** @var ReservationRoom $row */
                $row = $allotment['row'];

                $departure = max($row->checkout_date->toDateString(), $earliestDeparture);

                foreach ($allotment['rooms'] as $room) {
                    $checkIn = CheckIn::create([
                        'branch_id' => $reservation->branch_id,
                        'reservation_id' => $reservation->id,
                        'reservation_room_id' => $row->id,
                        'guest_id' => $reservation->guest_id,
                        'room_id' => $room->id,
                        'folio_no' => $folio,
                        'guest_name' => $reservation->guest_name,
                        'mobile' => $reservation->mobile,
                        'id_type' => $data['id_type'] ?? null,
                        'id_number' => $data['id_number'] ?? null,
                        'photo' => $idPhoto,
                        'checkin_date' => $data['checkin_date'],
                        'checkin_time' => $data['checkin_time'] ?: now()->format('H:i'),
                        'expected_checkout_date' => $departure,
                        'expected_checkout_time' => $row->checkout_time ?: config('pms.default_checkout_time'),
                        'plan_type_id' => $row->plan_type_id,
                        'room_rent' => $row->room_rent,
                        'discount' => $row->discount,
                        'plan_charge' => $row->plan_charge,
                        'tax_type' => $row->tax_type,
                        'tax_choice' => $row->tax_choice,
                        'tax_percent' => (float) $row->tax_percent,
                        'male' => $row->male,
                        'female' => $row->female,
                        'child' => $row->child,
                        'status' => 'in_house',
                        'is_direct' => 0,
                        'remark' => $data['remark'] ?? null,
                        'created_by' => $request->user()->user_id,
                    ]);
                    if ((int) $row->no_of_rooms === 1 && (int) $row->room_id !== (int) $room->id) {
                        $row->update(['room_id' => $room->id, 'room_no' => $room->room_no]);
                    }

                    Folio::for($checkIn)->post($request->user()->user_id);
                }
            }

            $reservation->refreshCheckInStatus();

            return $folio;
            });
        } catch (FolioRefused $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $rooms = collect($allotments)->sum(fn (array $a) => count($a['rooms']));
        $left = $reservation->fresh()->load('rooms.checkIns')->pendingRooms();
        $firstRoom = CheckIn::query()
            ->where('branch_id', $reservation->branch_id)
            ->where('folio_no', $folio)
            ->with(['room.type', 'plan'])
            ->first();
        $adults = collect($allotments)->sum(fn (array $a) => (int) $a['row']->male + (int) $a['row']->female);
        $children = collect($allotments)->sum(fn (array $a) => (int) $a['row']->child);

        GuestMessage::send('guest.checkin', $reservation->mobile, [
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
            'room' => $rooms > 1
                ? $rooms . ' rooms'
                : ($firstRoom?->room?->room_no ?: null),
            'room_type' => (string) ($firstRoom?->room?->type?->name ?? 'Room'),
            'meal_plan' => (string) ($firstRoom?->plan?->name ?? 'Room Only'),
            'guests' => trim($adults . ' Adults' . ($children ? ', ' . $children . ' Child' . ($children > 1 ? 'ren' : '') : '')),
            'folio_no' => $folio,
            'arrival' => optional($firstRoom?->checkin_date)->format('d M Y'),
            'departure' => optional($firstRoom?->expected_checkout_date)->format('d M Y'),
        ], $reservation->branch_id);

        Notify::event('checkin.done')
            ->title($reservation->guest_name . ' checked in')
            ->body(sprintf(
                '%d room(s) under folio %s%s',
                $rooms,
                $folio,
                $left ? ' · ' . $left . ' still to arrive' : ''
            ))
            ->url(route('front-office.check-in-details'))
            ->send();

        return redirect()
            ->route('front-office.check-in-details')
            ->with('status', sprintf(
                '%s checked in — %d room%s under %s.%s',
                $reservation->guest_name,
                $rooms,
                $rooms === 1 ? '' : 's',
                $folio,
                $left ? " {$left} room(s) on this booking are still to arrive." : ''
            ));
    }

    public function index(Request $request): View
    {
        $branchId = Helper::getActiveBranchId();

        $filters = [
            'q' => $request->string('q')->toString(),
            'status' => $request->string('status')->toString(),
            'from' => $request->string('from')->toString(),
            'to' => $request->string('to')->toString(),
        ];

        $checkIns = CheckIn::query()
            ->where('branch_id', $branchId)
            ->with(['room', 'plan', 'reservation'])
            ->search($filters['q'])
            ->when($filters['status'], fn ($q, $s) => $q->where('status', $s))
            ->when($filters['from'], fn ($q, $d) => $q->whereDate('checkin_date', '>=', $d))
            ->when($filters['to'], fn ($q, $d) => $q->whereDate('checkin_date', '<=', $d))
            ->orderByDesc('checkin_date')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 10) ?: 10)
            ->withQueryString();

        return view('front-office.check-in-details', [
            'checkIns' => $checkIns,
            'filters' => $filters,
            'perPage' => $checkIns->perPage(),
            'statuses' => CheckIn::STATUSES,
            'counts' => [
                'in_house' => CheckIn::where('branch_id', $branchId)->inHouse()->count(),
                'today' => CheckIn::where('branch_id', $branchId)->whereDate('checkin_date', today())->count(),
                'rooms' => CheckIn::where('branch_id', $branchId)->inHouse()->distinct('room_id')->count('room_id'),
                'total' => $checkIns->total(),
            ],
        ]);
    }

    public function undo(CheckIn $checkIn): RedirectResponse
    {
        abort_unless($checkIn->branch_id === Helper::getActiveBranchId(), 404);

        abort_unless(
            $checkIn->isInHouse(),
            403,
            'Only a guest who is still in house can have their check-in undone.'
        );

        $room = $checkIn->room?->room_no;

        DB::transaction(function () use ($checkIn) {
            Folio::for($checkIn)->releaseReservationServices();

            FolioCharge::where('check_in_id', $checkIn->id)->delete();
            Settlement::where('check_in_id', $checkIn->id)->delete();
            PaxCheckout::where('check_in_id', $checkIn->id)->delete();

            $checkIn->delete();

            $checkIn->reservation?->refreshCheckInStatus();
        });

        Notify::event('checkin.undone')
            ->title('Check-in undone — room ' . $room)
            ->url(route('front-office.check-in-details'))
            ->send();

        return back()->with('status', "Check-in undone — room {$room} is free again.");
    }

    private function reservation(?int $id): Reservation
    {
        $reservation = Reservation::query()
            ->where('branch_id', Helper::getActiveBranchId())
            ->with(['rooms.category', 'rooms.type', 'rooms.plan', 'rooms.checkIns'])
            ->find($id);

        abort_unless($reservation, 404, 'That booking is not in this branch.');

        return $reservation;
    }

    private function row(?int $id): ReservationRoom
    {
        $row = ReservationRoom::query()
            ->with(['category', 'type', 'reservation'])
            ->find($id);

        abort_unless(
            $row && $row->reservation?->branch_id === Helper::getActiveBranchId(),
            404,
            'That booking row is not in this branch.'
        );

        return $row;
    }
    private function pendingRows(Reservation $reservation)
    {
        return $reservation->rooms->filter(fn (ReservationRoom $row) => $row->pendingCount() > 0)->values();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'reservation_id' => 'required|integer',
            'checkin_date' => 'required|date',
            'checkin_time' => 'nullable',

            'title' => 'nullable|string|max:10',
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'mobile' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:1000',
            'gender' => 'nullable|in:male,female,other',

            'id_type' => ['nullable', Rule::in(array_keys(Compliance::ID_TYPES))],
            'id_number' => 'nullable|string|max:60',
            'id_photo' => ['nullable', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:8192'],

            'pick_drop_id' => 'nullable|integer',
            'visit_purpose_id' => 'nullable|integer',
            'arrival_from' => 'nullable|string|max:255',
            'departure_to' => 'nullable|string|max:255',
            'booked_by_id' => 'nullable|integer',
            'business_market_id' => 'nullable|integer',
            'company_id' => 'nullable|integer',
            'company_gst_no' => 'nullable|string|max:20',
            'billing_instruction_id' => 'nullable|integer',
            'pay_mode_id' => 'nullable|integer',
            'remark' => 'nullable|string|max:2000',
            'special_remark' => 'nullable|string|max:2000',

            // rooms[<reservation_room_id>][] = room_id
            'rooms' => 'required|array|min:1',
            'rooms.*' => 'array|min:1',
            'rooms.*.*' => 'integer',
        ], [
            'rooms.required' => 'Allot at least one room before saving — use the Allot Room button.',
            'id_photo.mimes' => 'That does not look like a photo — try again from the camera or choose an image file.',
            'id_photo.max' => 'The ID photo must be 8 MB or smaller.',
        ]);
    }

    /** @return array<string, mixed> */
    private function guestFields(array $data): array
    {
        return [
            'title' => $data['title'] ?? null,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'] ?? null,
            'mobile' => $data['mobile'],
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'gender' => $data['gender'] ?? null,
            'pick_drop_id' => $data['pick_drop_id'] ?? null,
            'visit_purpose_id' => $data['visit_purpose_id'] ?? null,
            'arrival_from' => $data['arrival_from'] ?? null,
            'departure_to' => $data['departure_to'] ?? null,
            'booked_by_id' => $data['booked_by_id'] ?? null,
            'business_market_id' => $data['business_market_id'] ?? null,
            'company_id' => $data['company_id'] ?? null,
            'company_gst_no' => $data['company_gst_no'] ?? null,
            'billing_instruction_id' => $data['billing_instruction_id'] ?? null,
            'pay_mode_id' => $data['pay_mode_id'] ?? null,
            'special_remark' => $data['special_remark'] ?? null,
        ];
    }

    /**
     * @return array<int, array{row: ReservationRoom, rooms: \Illuminate\Support\Collection}>|string
     */
    private function allotments(Request $request, Reservation $reservation): array|string
    {
        $posted = collect($request->input('rooms', []))
            ->map(fn ($ids) => collect((array) $ids)->filter()->map(fn ($id) => (int) $id)->unique()->values())
            ->filter(fn ($ids) => $ids->isNotEmpty());

        if ($posted->isEmpty()) {
            return 'Allot at least one room before saving.';
        }

        $allIds = $posted->flatten();

        if ($allIds->count() !== $allIds->unique()->count()) {
            return 'The same room has been allotted twice. Pick a different room for each guest.';
        }

        $out = [];

        foreach ($posted as $rowId => $roomIds) {
            $row = $reservation->rooms->firstWhere('id', (int) $rowId);

            if (! $row) {
                return 'One of the allotted rows does not belong to this booking. Reopen the screen and try again.';
            }

            if ($roomIds->count() > $row->pendingCount()) {
                return sprintf(
                    'Only %d room(s) are left to check in on the %s row — you picked %d.',
                    $row->pendingCount(),
                    $row->type?->name ?? 'booking',
                    $roomIds->count()
                );
            }

            $free = Room::query()
                ->forBranch()
                ->availableBetween($row->arrival_date->toDateString(), $row->checkout_date->toDateString(), $row->id)
                ->whereIn('id', $roomIds)
                ->get();

            if ($free->count() !== $roomIds->count()) {
                $taken = $roomIds->diff($free->pluck('id'));
                $numbers = Room::whereIn('id', $taken)->pluck('room_no')->implode(', ');

                return "Room {$numbers} was taken while this screen was open. Allot a different room.";
            }

            $out[] = ['row' => $row, 'rooms' => $free];
        }

        return $out;
    }
}
