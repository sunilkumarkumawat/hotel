<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Models\FrontOffice\CheckIn;
use App\Models\Master\Room;
use App\Models\Master\RoomCategory;
use App\Models\Reservation\ReservationRoom;
use App\Support\RoomBoard;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Room Calendar — every room in the house as a tile, coloured by what is
 * happening in it on a given day.
 *
 * This is the screen the front desk keeps open. The tape chart answers "which
 * nights"; this answers "right now, what is room 203 doing, and who is in it".
 *
 * A tile's colour is worked out from three things at once — the guest in it,
 * the booking holding it, and the housekeeping state — because a room can be
 * both reserved and dirty, and the desk needs to see that.
 */
class RoomCalendarController extends Controller
{
    /**
     * Tile states, in the order the legend prints them.
     *
     * The list itself lives in RoomBoard now, along with the machine that
     * decides which one a room is in — the dashboard and the month calendar
     * read the same one, so a colour cannot come to mean two things.
     */
    public const STATES = RoomBoard::STATES;

    public function index(Request $request): View
    {
        $branchId = Helper::getActiveBranchId();

        $date = rescue(
            fn () => CarbonImmutable::parse($request->string('date')->toString() ?: 'today')->toDateString(),
            today()->toDateString(),
            false
        );

        $rooms = Room::query()
            ->forBranch()
            ->active()
            ->with(['category', 'type'])
            ->when($request->integer('category'), fn ($q, $id) => $q->where('room_category_id', $id))
            ->when($request->string('floor')->toString(), fn ($q, $f) => $q->where('floor', $f))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where('room_no', 'like', "%{$term}%"))
            ->orderBy('room_no')
            ->get();

        $state = RoomBoard::states($rooms, $date, $branchId);

        // "Occupy" and "Available" are the two questions the desk actually
        // asks; everything else is a filter on top of them.
        $show = $request->string('show')->toString();

        $tiles = $rooms->filter(function (Room $room) use ($state, $show) {
            $busy = in_array($state[$room->id]['state'], ['checkin', 'checkin_dirty'], true);

            return match ($show) {
                'occupy' => $busy,
                'available' => ! $busy && $state[$room->id]['state'] !== 'blocked',
                default => true,
            };
        })->values();

        return view('front-office.room-calendar', [
            'rooms' => $tiles,
            'state' => $state,
            'date' => $date,
            'states' => self::STATES,
            'legend' => RoomBoard::legend($rooms, $state),
            'categories' => RoomCategory::query()->forBranch()->active()->orderBy('name')->pluck('name', 'id'),
            'floors' => Room::query()->forBranch()->active()
                ->whereNotNull('floor')->distinct()->orderBy('floor')->pluck('floor'),
            'filters' => [
                'category' => $request->integer('category'),
                'floor' => $request->string('floor')->toString(),
                'q' => $request->string('q')->toString(),
                'show' => $show,
            ],
            'selected' => $request->integer('room') ?: null,
            'guest' => $request->integer('room') ? $this->guestInfo($request->integer('room'), $date) : null,
        ]);
    }

    /** The panel on the right, as JSON, so clicking a tile does not reload. */
    public function guest(Request $request): JsonResponse
    {
        $date = rescue(
            fn () => CarbonImmutable::parse($request->string('date')->toString() ?: 'today')->toDateString(),
            today()->toDateString(),
            false
        );

        return response()->json($this->guestInfo($request->integer('room'), $date));
    }

    /** Change a room's housekeeping state from the calendar. */
    public function housekeeping(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'room_id' => 'required|integer',
            'housekeeping_status' => ['required', Rule::in(array_keys(Room::HOUSEKEEPING))],
        ]);

        $room = Room::query()->forBranch()->findOrFail($data['room_id']);
        $room->update(['housekeeping_status' => $data['housekeeping_status']]);

        return back()->with(
            'status',
            "Room {$room->room_no} marked " . strtolower(Room::HOUSEKEEPING[$data['housekeeping_status']]) . '.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * Everything the right-hand panel shows for one room.
     *
     * @return array<string, mixed>
     */
    private function guestInfo(int $roomId, string $date): array
    {
        $room = Room::query()->forBranch()->with(['category', 'type'])->find($roomId);

        if (! $room) {
            return ['found' => false];
        }

        $checkIn = CheckIn::query()
            ->where('branch_id', $room->branch_id ?? Helper::getActiveBranchId())
            ->where('room_id', $room->id)
            ->inHouse()
            ->with(['reservation.company', 'reservation.bookedBy', 'plan'])
            ->whereDate('checkin_date', '<=', $date)
            ->first();

        $booking = null;

        if (! $checkIn) {
            $next = CarbonImmutable::parse($date)->addDay()->toDateString();

            $booking = ReservationRoom::query()
                ->with(['reservation.company', 'reservation.bookedBy', 'plan', 'category'])
                ->where('room_id', $room->id)
                ->where('arrival_date', '<', $next)
                ->where('checkout_date', '>=', $next)
                ->whereHas('reservation', fn ($q) => $q->whereIn('status', ['confirmed', 'tentative']))
                ->first();
        }

        $reservation = $checkIn?->reservation ?? $booking?->reservation;

        return [
            'found' => true,
            'room_id' => $room->id,
            'room_no' => $room->room_no,
            'room_category' => $room->category?->name,
            'room_type' => $room->type?->name,
            'housekeeping' => Room::HOUSEKEEPING[$room->housekeeping_status] ?? $room->housekeeping_status,
            'guest_name' => $checkIn?->guest_name ?? $reservation?->guest_name,
            'mobile' => $checkIn?->mobile ?? $reservation?->mobile,
            'plan' => $checkIn?->plan?->name ?? $booking?->plan?->name,
            'guest_type' => $booking ? (ReservationRoom::GUEST_TYPES[$booking->guest_type] ?? null) : 'In house',
            'gstin' => $reservation?->company_gst_no,
            'company' => $reservation?->company?->name,
            'booked_by' => $reservation?->bookedBy?->name,
            'arrival' => optional($checkIn?->checkin_date ?? $booking?->arrival_date)->format('d/m/Y'),
            'departure' => optional(
                $checkIn ? ($checkIn->actual_checkout_date ?? $checkIn->expected_checkout_date) : $booking?->checkout_date
            )?->format('d/m/Y'),
            'folio_no' => $checkIn?->folio_no,
            'reservation_no' => $reservation?->reservation_no,
            // What the panel's two buttons should do.
            'check_in_id' => $checkIn?->id,
            'reservation_id' => $reservation?->id,
            'can_check_out' => (bool) $checkIn,
            'can_check_in' => ! $checkIn && (bool) $reservation,
        ];
    }
}
