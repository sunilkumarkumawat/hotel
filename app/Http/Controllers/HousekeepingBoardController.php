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

/**
 * The housekeeping board — the house as work, not as a list.
 *
 * Two views of the same data, and they answer two different questions:
 *
 *   **Room View** groups every room under its type, which is how a supervisor
 *   allots: "do the four suites first, they check in at two."
 *
 *   **Pipeline** is the four columns work actually moves through — Occupied,
 *   Dirty, Cleaning, Ready — and it is where a room gets moved along.
 *
 * Every move is written to `housekeeping_logs` as well as to the room. The log
 * is what makes "who marked 312 clean at four in the afternoon when the guest
 * says it never was" a question with an answer.
 */
class HousekeepingBoardController extends Controller
{
    /** GET house-keeping/board */
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

    /**
     * POST house-keeping/board/move
     *
     * One room, one new stage. Answers JSON to the board's drag handler and a
     * redirect to the buttons, so the screen works with the script switched off
     * — a housekeeping terminal is exactly the machine whose browser is eight
     * years old.
     */
    public function move(Request $request): RedirectResponse|JsonResponse
    {
        $branchId = (int) Helper::getActiveBranchId();

        $data = $request->validate([
            'room_id' => 'required|integer',
            'to' => ['required', Rule::in(array_keys(HousekeepingBoard::MOVES))],
            'housekeeper_id' => 'nullable|integer',
            'remark' => 'nullable|string|max:255',
            // Present only when the move came from a drag, and only so the
            // server can check the same rule the browser just checked.
            'from' => 'nullable|string|max:20',
        ]);

        $room = Room::query()->forBranch($branchId)->with(['type', 'housekeeper'])->find($data['room_id']);

        if (! $room) {
            return $this->answer($request, false, 'That room is not one of this branch\'s.');
        }

        /*
         * A room out of service is held by a block with a reason and dates.
         * Housekeeping marking it clean would put it back on sale behind the
         * back of every calendar in the app, so the move is refused and the
         * clerk is told where to go instead.
         */
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
                // The clock the board's "since" label counts from. It starts
                // when cleaning starts and is cleared the moment it stops, so
                // a room that has been "being cleaned for 6 hours" is visible
                // rather than merely true.
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

        // A room becoming sellable is news the front desk wants; the other
        // three moves are housekeeping talking to itself.
        Notify::event($data['to'] === 'ready' ? 'housekeeping.ready' : 'housekeeping.status')
            ->title('Room ' . $room->room_no . ' — ' . strtolower($move['label']))
            ->body(trim(($room->type?->name ?? '') . ' · ' . ($room->housekeeper?->name ?? 'nobody assigned')))
            ->url(route('house-keeping.board', ['view' => 'pipeline']))
            ->send();

        return $this->answer($request, true, sprintf('Room %s — %s.', $room->room_no, strtolower($move['label'])));
    }

    /**
     * POST house-keeping/board/assign
     *
     * Give a housekeeper a column's worth of rooms in one go — the per-column
     * action on the Pipeline board.
     */
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

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * Why this move is not allowed — or null when it is.
     *
     * The drag rules are checked here and not only in the browser. A rule that
     * lives only in JavaScript is a rule that a stale tab, a double-tap or a
     * curl command does not have to obey, and the one that matters here is
     * "cleaning cannot be dragged": without it a room in the middle of being
     * made up could be flicked to Ready by a sleeve on a touchscreen.
     */
    private function refuse(string $stage, array $data): ?string
    {
        if ($stage === 'blocked') {
            return 'That room is out of order and is not on the board.';
        }

        // Only a drag carries `from`; the buttons do not, and the buttons are
        // allowed the moves a drag is not.
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

    /** Same answer, in whichever shape the caller can read. */
    private function answer(Request $request, bool $ok, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => $ok, 'message' => $message], $ok ? 200 : 422);
        }

        return back()->with($ok ? 'status' : 'error', $message);
    }

    /**
     * Who can be given rooms.
     *
     * Everybody active in the branch: a small hotel's manager cleans rooms too,
     * and a "housekeeper" role the customer never set up would leave this list
     * empty with no way to tell why.
     */
    private function housekeepers(int $branchId)
    {
        return User::query()
            ->where('branch_id', $branchId)
            ->where('status', 1)
            ->orderBy('name')
            ->pluck('name', 'user_id');
    }
}
