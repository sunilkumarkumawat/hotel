<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Models\HouseKeeping\HousekeepingLog;
use App\Models\Master\Room;
use App\Models\Master\RoomType;
use App\Models\User;
use App\Support\HousekeepingBoard;
use App\Support\Notify;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;


class HousekeepingBoardController extends Controller
{
    public function index(Request $request): View
    {
        $branchId = (int) Helper::getActiveBranchId();

        $filters = [
            'type' => $request->integer('type'),
            'floor' => $request->string('floor')->toString(),
            'housekeeper' => $request->integer('housekeeper'),
            'q' => $request->string('q')->toString(),
        ];

        $date = rescue(
            fn () => \Carbon\CarbonImmutable::parse($request->string('date')->toString() ?: 'today')->toDateString(),
            today()->toDateString(),
            false
        );

        $board = HousekeepingBoard::build($branchId, $date, $filters);

        return view('house-keeping.board', $board + [
            'view' => $request->string('view')->toString() === 'pipeline' ? 'pipeline' : 'rooms',
            'filters' => $filters,
            'types' => RoomType::query()->forBranch($branchId)->active()->orderBy('name')->pluck('name', 'id'),
            'floors' => Room::query()->forBranch($branchId)->active()
                ->whereNotNull('floor')->distinct()->orderBy('floor')->pluck('floor'),
            'housekeepers' => $this->housekeepers($branchId),
            'draggable' => HousekeepingBoard::DRAGGABLE,
            'recent' => HousekeepingLog::query()
                ->where('branch_id', $branchId)
                ->with(['room', 'user'])
                ->latest('id')
                ->limit(12)
                ->get(),
        ]);
    }
    public function move(Request $request): RedirectResponse|JsonResponse
    {
        $branchId = (int) Helper::getActiveBranchId();

        $data = $request->validate([
            'room_id' => 'required|integer',
            'to' => ['required', Rule::in(array_keys(HousekeepingBoard::MOVES))],
            'housekeeper_id' => 'nullable|integer',
            'remark' => 'nullable|string|max:255',
            'from' => 'nullable|string|max:20',
        ]);

        $room = Room::query()->forBranch($branchId)->with(['type', 'housekeeper'])->find($data['room_id']);

        if (! $room) {
            return $this->answer($request, false, 'That room is not one of this branch\'s.');
        }

        if ($room->housekeeping_status === 'out_of_order') {
            return $this->answer($request, false, sprintf(
                'Room %s is out of order — release it from House Keeping → Room Blocked before changing its status.',
                $room->room_no
            ));
        }

        $board = HousekeepingBoard::build($branchId, today()->toDateString());
        $tile = collect($board['tiles'])->firstWhere('id', (int) $room->id);
        $stage = $tile['stage'] ?? 'ready';

        if ($why = $this->refuse($stage, $data)) {
            return $this->answer($request, false, $why);
        }

        $move = HousekeepingBoard::MOVES[$data['to']];
        $was = $room->housekeeping_status;

        DB::transaction(function () use ($room, $move, $data, $branchId, $request, $was) {
            $room->update([
                'housekeeping_status' => $move['status'],
                'housekeeping_remark' => $data['remark'] ?? $room->housekeeping_remark,
                'housekeeper_id' => $data['housekeeper_id'] ?: $room->housekeeper_id,
                'cleaning_started_at' => $move['status'] === 'cleaning' ? now() : null,
            ]);

            HousekeepingLog::create([
                'branch_id' => $branchId,
                'room_id' => $room->id,
                'from_status' => $was,
                'to_status' => $move['status'],
                'housekeeper_id' => $room->housekeeper_id,
                'remark' => $data['remark'] ?? null,
                'created_by' => $request->user()->user_id,
            ]);
        });

        Notify::event($data['to'] === 'ready' ? 'housekeeping.ready' : 'housekeeping.status')
            ->title('Room ' . $room->room_no . ' — ' . strtolower($move['label']))
            ->body(trim(($room->type?->name ?? '') . ' · ' . ($room->housekeeper?->name ?? 'nobody assigned')))
            ->url(route('house-keeping.board', ['view' => 'pipeline']))
            ->send();

        return $this->answer($request, true, sprintf('Room %s — %s.', $room->room_no, strtolower($move['label'])));
    }
    public function assign(Request $request): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();

        $data = $request->validate([
            'rooms' => 'required|array|min:1',
            'rooms.*' => 'integer',
            'housekeeper_id' => 'required|integer|exists:users,user_id',
        ], [
            'rooms.required' => 'There are no rooms in that column to give anybody.',
        ]);

        $rooms = Room::query()->forBranch($branchId)->whereIn('id', $data['rooms'])->get();

        if ($rooms->isEmpty()) {
            return back()->with('error', 'None of those rooms are in this branch.');
        }

        Room::whereIn('id', $rooms->pluck('id'))->update([
            'housekeeper_id' => $data['housekeeper_id'],
            'updated_at' => now(),
        ]);

        $who = User::where('user_id', $data['housekeeper_id'])->value('name');

        Notify::event('housekeeping.assigned')
            ->title($who . ' has ' . $rooms->count() . ' room(s)')
            ->body($rooms->pluck('room_no')->implode(', '))
            ->url(route('house-keeping.board', ['view' => 'pipeline']))
            ->send();

        return back()->with('status', sprintf(
            '%s now has %d room(s) — %s.',
            $who,
            $rooms->count(),
            $rooms->pluck('room_no')->implode(', ')
        ));
    }
    private function refuse(string $stage, array $data): ?string
    {
        if ($stage === 'blocked') {
            return 'That room is out of order and is not on the board.';
        }

        if (blank($data['from'] ?? null)) {
            return null;
        }

        if ($stage === 'cleaning') {
            return 'A room being cleaned cannot be dragged. Use Done when it is finished, '
                . 'or Mark dirty to send it back.';
        }

        $allowed = HousekeepingBoard::DRAGGABLE[$stage] ?? [];

        if (! in_array($data['to'], $allowed, true)) {
            return sprintf(
                'A %s room cannot be dragged to %s. %s',
                strtolower(HousekeepingBoard::STAGES[$stage] ?? $stage),
                strtolower(HousekeepingBoard::STAGES[$data['to']] ?? $data['to']),
                $allowed === []
                    ? 'Nothing can be dragged out of that column.'
                    : 'Only ' . implode(' and ', array_map(
                        fn ($s) => strtolower(HousekeepingBoard::STAGES[$s] ?? $s),
                        $allowed
                    )) . '.'
            );
        }

        return null;
    }

    private function answer(Request $request, bool $ok, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => $ok, 'message' => $message], $ok ? 200 : 422);
        }

        return back()->with($ok ? 'status' : 'error', $message);
    }
    private function housekeepers(int $branchId)
    {
        return User::query()
            ->where('branch_id', $branchId)
            ->where('status', 1)
            ->orderBy('name')
            ->pluck('name', 'user_id');
    }
}
