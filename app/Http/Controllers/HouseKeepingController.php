<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Models\Master\Room;
use App\Models\Master\RoomCategory;
use App\Models\User;
use App\Support\Notify;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class HouseKeepingController extends Controller
{
    public const ACTIONS = [
        'status' => 'Set Status',
        'assign' => 'Assign Housekeeper',
        'unassign' => 'UnAssign Housekeeper',
    ];

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
            ->with(['category', 'type', 'housekeeper'])
            ->when($request->integer('category'), fn ($q, $id) => $q->where('room_category_id', $id))
            ->when($request->string('floor')->toString(), fn ($q, $f) => $q->where('floor', $f))
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('housekeeping_status', $s))
            ->when($request->string('q')->toString(), fn ($q, $t) => $q->where('room_no', 'like', "%{$t}%"))
            ->orderBy('room_no')
            ->get();

        $occupancy = $this->occupancy($rooms, $date, $branchId);

        if ($request->boolean('to_do')) {
            $rooms = $rooms->filter(
                fn (Room $room) => in_array($room->housekeeping_status, ['dirty', 'touch_up'], true)
            )->values();
        }

        return view('house-keeping.status', [
            'rooms' => $rooms,
            'occupancy' => $occupancy,
            'date' => $date,
            'actions' => self::ACTIONS,
            'statuses' => collect(Room::HOUSEKEEPING)
                ->only(Room::HOUSEKEEPING_SETTABLE)
                ->all(),
            'allStatuses' => Room::HOUSEKEEPING,
            'housekeepers' => $this->housekeepers($branchId),
            'categories' => RoomCategory::query()->forBranch()->active()->orderBy('name')->pluck('name', 'id'),
            'floors' => Room::query()->forBranch()->active()
                ->whereNotNull('floor')->distinct()->orderBy('floor')->pluck('floor'),
            'counts' => $this->counts($rooms, $occupancy),
            'filters' => [
                'category' => $request->integer('category'),
                'floor' => $request->string('floor')->toString(),
                'status' => $request->string('status')->toString(),
                'q' => $request->string('q')->toString(),
                'to_do' => $request->boolean('to_do'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(array_keys(self::ACTIONS))],
            'rooms' => 'required|array|min:1',
            'rooms.*' => 'integer',
            'housekeeping_status' => [
                'required_if:action,status',
                'nullable',
                Rule::in(Room::HOUSEKEEPING_SETTABLE),
            ],
            'housekeeper_id' => [
                'required_if:action,assign',
                'nullable',
                'integer',
                Rule::exists('users', 'user_id'),
            ],
            'remark' => 'nullable|string|max:255',
        ], [
            'rooms.required' => 'Tick the rooms you want to update first.',
            'housekeeping_status.required_if' => 'Pick the status to set.',
            'housekeeper_id.required_if' => 'Pick the housekeeper to assign.',
        ]);

        $rooms = Room::query()
            ->forBranch()
            ->whereIn('id', $data['rooms'])
            ->get();

        if ($rooms->isEmpty()) {
            return back()->with('error', 'None of those rooms are in this branch.');
        }

        $blocked = $rooms->where('housekeeping_status', 'out_of_order');

        if ($data['action'] === 'status' && $blocked->isNotEmpty()) {
            return back()->with('error', sprintf(
                'Room %s is out of order — release it from House Keeping → Room Blocked before changing its status.',
                $blocked->pluck('room_no')->implode(', ')
            ));
        }

        $message = DB::transaction(function () use ($data, $rooms) {
            $numbers = $rooms->pluck('room_no')->implode(', ');
            $ids = $rooms->pluck('id');

            return match ($data['action']) {
                'status' => tap(sprintf(
                    '%d room(s) marked %s — %s.',
                    $rooms->count(),
                    strtolower(Room::HOUSEKEEPING[$data['housekeeping_status']]),
                    $numbers
                ), fn () => Room::whereIn('id', $ids)->update([
                    'housekeeping_status' => $data['housekeeping_status'],
                    'housekeeping_remark' => $data['remark'] ?? null,
                    'updated_at' => now(),
                ])),

                'assign' => tap(sprintf(
                    '%s now has %d room(s) — %s.',
                    User::where('user_id', $data['housekeeper_id'])->value('name'),
                    $rooms->count(),
                    $numbers
                ), fn () => Room::whereIn('id', $ids)->update([
                    'housekeeper_id' => $data['housekeeper_id'],
                    'updated_at' => now(),
                ])),

                'unassign' => tap(
                    sprintf('%d room(s) unassigned — %s.', $rooms->count(), $numbers),
                    fn () => Room::whereIn('id', $ids)->update([
                        'housekeeper_id' => null,
                        'updated_at' => now(),
                    ])
                ),
            };
        });

        Notify::event($data['action'] === 'assign' ? 'housekeeping.assigned' : 'housekeeping.status')
            ->title($message)
            ->url(route('house-keeping.board', ['view' => 'pipeline']))
            ->send();

        return back()->with('status', $message);
    }
    private function housekeepers(int $branchId)
    {
        return User::query()
            ->where('branch_id', $branchId)
            ->where('status', 1)
            ->orderBy('name')
            ->pluck('name', 'user_id');
    }

    /**
     * @return array<int, array{pax: int, guest: ?string, state: string}>
     */
    private function occupancy($rooms, string $date, int $branchId): array
    {
        $next = CarbonImmutable::parse($date)->addDay()->toDateString();

        $stays = DB::table('check_ins')
            ->where('branch_id', $branchId)
            ->where('status', 'in_house')
            ->whereNotNull('room_id')
            ->where('checkin_date', '<', $next)
            ->whereRaw('COALESCE(actual_checkout_date, expected_checkout_date) >= ?', [$next])
            ->get(['room_id', 'guest_name', 'male', 'female', 'child'])
            ->keyBy('room_id');

        $bookings = DB::table('reservation_rooms as rr')
            ->join('reservations as r', 'r.id', '=', 'rr.reservation_id')
            ->where('r.branch_id', $branchId)
            ->whereNotNull('rr.room_id')
            ->whereIn('r.status', ['confirmed', 'tentative'])
            ->where('rr.arrival_date', '<', $next)
            ->where('rr.checkout_date', '>=', $next)
            ->get(['rr.room_id', 'r.first_name', 'r.last_name'])
            ->keyBy('room_id');

        $blocked = DB::table('room_blocks')
            ->where('branch_id', $branchId)
            ->where('status', 'blocked')
            ->where('from_date', '<', $next)
            ->where('to_date', '>=', $next)
            ->pluck('room_id')
            ->flip();

        $out = [];

        foreach ($rooms as $room) {
            $stay = $stays->get($room->id);
            $booking = $bookings->get($room->id);

            $out[$room->id] = [
                'pax' => $stay ? (int) $stay->male + (int) $stay->female + (int) $stay->child : 0,
                'guest' => $stay->guest_name
                    ?? ($booking ? trim($booking->first_name . ' ' . $booking->last_name) : null),
                'state' => match (true) {
                    $blocked->has($room->id) => 'blocked',
                    (bool) $stay => 'occupied',
                    (bool) $booking => 'reserved',
                    $room->housekeeping_status === 'out_of_order' => 'dnr',
                    default => 'available',
                },
            ];
        }

        return $out;
    }

    /** @return array<string, int> */
    private function counts($rooms, array $occupancy): array
    {
        return [
            'rooms' => $rooms->count(),
            'dirty' => $rooms->whereIn('housekeeping_status', ['dirty', 'touch_up'])->count(),
            'occupied' => collect($occupancy)
                ->only($rooms->pluck('id'))
                ->where('state', 'occupied')
                ->count(),
            'unassigned' => $rooms->whereNull('housekeeper_id')->count(),
        ];
    }
}
