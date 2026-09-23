<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Models\HouseKeeping\RoomBlock;
use App\Models\HouseKeeping\WorkOrder;
use App\Models\Master\Room;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\Notify;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Work Order — the maintenance job card.
 *
 * A lift, a leaking tap, an air conditioner in 204: somebody raises the job,
 * somebody is put on it, and it has a deadline. The list is what a maintenance
 * supervisor works from in the morning, so it leads with what is overdue.
 *
 * **A job can take its room off sale.** Tick "block this room" on the form and
 * the order writes a maintenance block for its own dates — the room goes Repair
 * in house keeping and disappears from the tape chart, the status view and the
 * booking form, all through the same `room_blocks` row the Room Blocked screen
 * uses. Closing the job offers to release it again. Without that tick the job
 * is only a record and the room goes on selling, which is right for a dripping
 * tap that nobody has to move out for.
 */
class WorkOrderController extends Controller
{
    /**
     * Why the room could not be held, when the job asked for it.
     *
     * Carried on the controller rather than flashed from inside the
     * transaction: the job did save, and the list renders a flashed `error` as
     * "Not saved", which would be a plain lie about what just happened.
     */
    private ?string $blockWarning = null;

    /** GET house-keeping/work-order */
    public function index(Request $request): View
    {
        $branchId = Helper::getActiveBranchId();

        $filters = [
            'q' => $request->string('q')->toString(),
            'from' => $request->string('from')->toString(),
            'to' => $request->string('to')->toString(),
            'status' => $request->string('status')->toString(),
            'priority' => $request->string('priority')->toString(),
            'employee' => $request->integer('employee'),
        ];

        $orders = WorkOrder::query()
            ->where('branch_id', $branchId)
            ->with(['room', 'assignee'])
            ->search($filters['q'])
            ->when($filters['status'], fn ($q, $s) => $q->where('status', $s))
            ->when($filters['priority'], fn ($q, $p) => $q->where('priority', $p))
            ->when($filters['employee'], fn ($q, $id) => $q->where('assigned_to', $id))
            // The dates the job is scheduled for, not when it was typed in —
            // "what is on this week" is the question the filter answers.
            ->when($filters['from'], fn ($q, $d) => $q->whereDate('start_date', '>=', $d))
            ->when($filters['to'], fn ($q, $d) => $q->whereDate('start_date', '<=', $d))
            ->orderByRaw("CASE WHEN status IN ('done', 'cancelled') THEN 1 ELSE 0 END")
            ->orderBy('due_date')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 10) ?: 10)
            ->withQueryString();

        $base = WorkOrder::query()->where('branch_id', $branchId);

        return view('house-keeping.work-order', [
            'orders' => $orders,
            'filters' => $filters,
            'perPage' => $orders->perPage(),
            'statuses' => WorkOrder::STATUSES,
            'priorities' => WorkOrder::PRIORITIES,
            'categories' => WorkOrder::categories(),
            'employees' => $this->employees($branchId),
            'counts' => [
                'open' => (clone $base)->open()->count(),
                'overdue' => (clone $base)->open()
                    ->whereNotNull('due_date')
                    ->whereDate('due_date', '<', today()->toDateString())
                    ->count(),
                'urgent' => (clone $base)->open()->where('priority', 'urgent')->count(),
                'done' => (clone $base)->where('status', 'done')->count(),
            ],
        ]);
    }

    /** GET house-keeping/work-order/new */
    public function create(Request $request): View
    {
        return view('house-keeping.work-order-form', $this->formData($request) + [
            'order' => new WorkOrder([
                'priority' => 'normal',
                'status' => 'open',
                'category' => array_key_first(WorkOrder::categories()),
            ]),
            'orderNo' => WorkOrder::nextNumber(Helper::getActiveBranchId()),
        ]);
    }

    /** GET house-keeping/work-order/{order}/edit */
    public function edit(Request $request, WorkOrder $order): View
    {
        $this->guard($order);

        $order->load(['room', 'assignee', 'block']);

        return view('house-keeping.work-order-form', $this->formData($request) + [
            'order' => $order,
            'orderNo' => $order->order_no,
        ]);
    }

    /** POST house-keeping/work-order */
    public function store(Request $request): RedirectResponse
    {
        $branchId = Helper::getActiveBranchId();
        $data = $this->validated($request, $branchId);

        $order = retry(3, fn () => DB::transaction(function () use ($branchId, $data, $request) {
            $order = WorkOrder::create($this->fields($data, $branchId) + [
                'order_no' => WorkOrder::nextNumber($branchId),
                'created_by' => $request->user()->user_id,
            ]);

            $this->syncBlock($order, $data, $branchId, $request);

            return $order;
        }), 50);

        Notify::event('workorder.created')
            ->title($order->order_no . ' — ' . $order->title)
            ->body(trim(
                ($order->room?->room_no ? 'Room ' . $order->room->room_no . ' · ' : '')
                . ucfirst((string) $order->priority) . ' priority'
            ))
            ->level(in_array($order->priority, ['high', 'urgent'], true) ? 'danger' : 'warning')
            ->url(route('house-keeping.work-order'))
            ->send();

        return redirect()
            ->route('house-keeping.work-order')
            ->with('status', sprintf(
                '%s raised — %s.%s',
                $order->order_no,
                $order->title,
                $order->room_block_id ? ' The room is blocked for those dates.' : ''
            ))
            ->with('warning', $this->blockWarning);
    }

    /** PUT house-keeping/work-order/{order} */
    public function update(Request $request, WorkOrder $order): RedirectResponse
    {
        $this->guard($order);

        $branchId = Helper::getActiveBranchId();
        $data = $this->validated($request, $branchId);

        DB::transaction(function () use ($order, $data, $branchId, $request) {
            $order->update($this->fields($data, $branchId));

            $this->syncBlock($order, $data, $branchId, $request);
            $this->syncCompletion($order);
        });

        return redirect()
            ->route('house-keeping.work-order')
            ->with('status', "{$order->order_no} updated.")
            ->with('warning', $this->blockWarning);
    }

    /**
     * POST house-keeping/work-order/{order}/close — done, in one press.
     *
     * The list's own button, so a supervisor closing six finished jobs does not
     * have to open six forms.
     */
    public function close(Request $request, WorkOrder $order): RedirectResponse
    {
        $this->guard($order);

        if ($order->isClosed()) {
            return back()->with('error', "{$order->order_no} is already {$order->status_label}.");
        }

        $release = $request->boolean('release_room', true);

        DB::transaction(function () use ($order, $release) {
            $order->update([
                'status' => 'done',
                'completed_on' => today()->toDateString(),
            ]);

            if ($release) {
                $this->releaseBlock($order);
            }
        });

        Notify::event('workorder.closed')
            ->title($order->order_no . ' closed — ' . $order->title)
            ->body($release && $order->room_id ? 'The room is back on sale.' : '')
            ->url(route('house-keeping.work-order'))
            ->send();

        return back()->with('status', sprintf(
            '%s marked done.%s',
            $order->order_no,
            $release && $order->room_id ? ' The room is back on sale.' : ''
        ));
    }

    /** DELETE house-keeping/work-order/{order} */
    public function destroy(WorkOrder $order): RedirectResponse
    {
        $this->guard($order);

        $number = $order->order_no;

        DB::transaction(function () use ($order) {
            // The block was made for this job, so it goes with it.
            $this->releaseBlock($order);

            $order->delete();
        });

        return back()->with('status', "{$number} deleted.");
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    private function guard(WorkOrder $order): void
    {
        abort_unless($order->branch_id === Helper::getActiveBranchId(), 404);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, int $branchId): array
    {
        return $request->validate([
            'room_id' => [
                'nullable',
                'integer',
                Rule::exists('rooms', 'id')->where(
                    fn ($q) => $q->where(fn ($w) => $w->whereNull('branch_id')->orWhere('branch_id', $branchId))
                ),
            ],
            'category' => ['required', Rule::in(array_keys(WorkOrder::categories()))],
            'priority' => ['required', Rule::in(array_keys(WorkOrder::PRIORITIES))],
            'status' => ['required', Rule::in(array_keys(WorkOrder::STATUSES))],
            'assigned_to' => [
                'nullable',
                'integer',
                Rule::exists('users', 'user_id')->where(fn ($q) => $q->where('branch_id', $branchId)),
            ],
            'start_date' => 'required|date',
            // Most browsers send HH:MM, a few send HH:MM:SS — take either.
            'start_time' => 'nullable|date_format:H:i,H:i:s',
            'end_date' => 'required|date|after_or_equal:start_date',
            'due_date' => 'required|date|after_or_equal:start_date',
            'description' => 'required|string|max:2000',
            'block_room' => 'nullable|boolean',
        ], [
            'description.required' => 'Write what the job is — Job Notes is what the person doing it reads.',
            'end_date.after_or_equal' => 'A job cannot end before it starts.',
            'due_date.after_or_equal' => 'The deadline cannot be before the job starts.',
            'assigned_to.exists' => 'That person is not on this branch.',
        ]);
    }

    /**
     * The columns, worked out from what the form sent.
     *
     * `title` is not asked for: a job is known by its trade and its room, and
     * making the clerk type "AC — 204" as well as picking both is one field of
     * work for nothing.
     *
     * @return array<string, mixed>
     */
    private function fields(array $data, int $branchId): array
    {
        $room = $data['room_id'] ? Room::query()->forBranch($branchId)->find($data['room_id']) : null;

        return [
            'branch_id' => $branchId,
            'room_id' => $room?->id,
            'category' => $data['category'],
            'title' => trim(
                (WorkOrder::categories()[$data['category']] ?? 'Work')
                . ' · ' . ($room ? 'Room ' . $room->room_no : 'Common area')
            ),
            'description' => $data['description'],
            'priority' => $data['priority'],
            'status' => $data['status'],
            'assigned_to' => $data['assigned_to'] ?? null,
            'start_date' => CarbonImmutable::parse($data['start_date'])->toDateString(),
            'start_time' => $data['start_time'] ?? null,
            'end_date' => CarbonImmutable::parse($data['end_date'])->toDateString(),
            'due_date' => CarbonImmutable::parse($data['due_date'])->toDateString(),
        ];
    }

    /**
     * Make, move or drop the block this job holds its room with.
     *
     * The tick is the whole switch: on, and the room is off sale for the job's
     * dates; off, and any block this job made is released. A room somebody else
     * has booked is not blocked quietly — the job still saves, and the message
     * says why the room could not be held.
     */
    private function syncBlock(WorkOrder $order, array $data, int $branchId, Request $request): void
    {
        $wanted = ! empty($data['block_room']) && $order->room_id;

        if (! $wanted) {
            $this->releaseBlock($order);

            return;
        }

        $from = $order->start_date->toDateString();
        // A block runs to the day the room comes back, and a job that starts
        // and ends on one day still costs that night.
        $to = CarbonImmutable::parse($order->end_date->toDateString())->addDay()->toDateString();

        $existing = $order->room_block_id
            ? RoomBlock::where('id', $order->room_block_id)->where('branch_id', $branchId)->first()
            : null;

        // Already covering exactly these nights on this room — nothing to do.
        if ($existing
            && $existing->status === 'blocked'
            && (int) $existing->room_id === (int) $order->room_id
            && $existing->from_date->toDateString() === $from
            && $existing->to_date->toDateString() === $to) {
            return;
        }

        $this->releaseBlock($order);

        if ($this->roomIsTaken($branchId, (int) $order->room_id, $from, $to)) {
            $order->update(['room_block_id' => null]);

            $this->blockWarning = 'The room could not be blocked — somebody has it over those dates. '
                . 'Move the guest first, or block it from House Keeping → Room Blocked.';

            return;
        }

        $block = RoomBlock::create([
            'branch_id' => $branchId,
            'room_id' => $order->room_id,
            'from_date' => $from,
            'to_date' => $to,
            'reason' => $order->order_no . ' — ' . $order->title,
            'block_type' => 'maintenance',
            'status' => 'blocked',
            'created_by' => $request->user()->user_id,
        ]);

        // Only from the day the job actually starts — a work order scheduled
        // for next week must not take a sellable room off the board today.
        if ($from <= today()->toDateString()) {
            Room::whereKey($order->room_id)->update([
                'housekeeping_status' => 'out_of_order',
                'updated_at' => now(),
            ]);
        }

        $order->update(['room_block_id' => $block->id]);
    }

    /**
     * Put the room back, if this job is the one holding it.
     *
     * Only clears Repair when no other maintenance block is still running on
     * that room — two jobs on one bathroom must not un-block each other.
     */
    private function releaseBlock(WorkOrder $order): void
    {
        if (! $order->room_block_id) {
            return;
        }

        $block = RoomBlock::find($order->room_block_id);

        $order->update(['room_block_id' => null]);

        if (! $block || $block->status !== 'blocked') {
            return;
        }

        $block->update(['status' => 'released']);

        $stillHeld = RoomBlock::query()
            ->where('branch_id', $block->branch_id)
            ->live()
            ->where('room_id', $block->room_id)
            ->whereIn('block_type', RoomBlock::MARKS_ROOM_REPAIR)
            ->exists();

        if (! $stillHeld) {
            Room::whereKey($block->room_id)
                ->where('housekeeping_status', 'out_of_order')
                ->update(['housekeeping_status' => 'dirty', 'updated_at' => now()]);
        }
    }

    /** Stamp or clear the completion date when the status moves. */
    private function syncCompletion(WorkOrder $order): void
    {
        if ($order->status === 'done' && ! $order->completed_on) {
            $order->update(['completed_on' => today()->toDateString()]);
        }

        if (! $order->isClosed() && $order->completed_on) {
            $order->update(['completed_on' => null]);
        }
    }

    /** Is anything else holding this room over those nights? */
    private function roomIsTaken(int $branchId, int $roomId, string $from, string $to): bool
    {
        $booked = DB::table('reservation_rooms as rr')
            ->join('reservations as r', 'r.id', '=', 'rr.reservation_id')
            ->where('r.branch_id', $branchId)
            ->where('rr.room_id', $roomId)
            ->whereNotIn('r.status', ['cancelled', 'no_show', 'checked_out'])
            ->where('rr.arrival_date', '<', $to)
            ->where('rr.checkout_date', '>', $from)
            ->exists();

        if ($booked) {
            return true;
        }

        $inHouse = DB::table('check_ins')
            ->where('branch_id', $branchId)
            ->where('room_id', $roomId)
            ->where('status', 'in_house')
            ->where('checkin_date', '<', $to)
            ->whereRaw('COALESCE(actual_checkout_date, expected_checkout_date) > ?', [$from])
            ->exists();

        if ($inHouse) {
            return true;
        }

        return DB::table('room_blocks')
            ->where('branch_id', $branchId)
            ->where('room_id', $roomId)
            ->where('status', 'blocked')
            ->where('from_date', '<', $to)
            ->where('to_date', '>', $from)
            ->exists();
    }

    /** @return array<string, mixed> */
    private function formData(Request $request): array
    {
        $branchId = Helper::getActiveBranchId();
        $today = today()->toDateString();

        return [
            'today' => $today,
            'now' => now()->format('H:i'),
            'rooms' => Room::query()->forBranch($branchId)->active()->orderBy('room_no')
                ->get(['id', 'room_no', 'floor']),
            'employees' => $this->employees($branchId),
            'categories' => WorkOrder::categories(),
            'priorities' => WorkOrder::PRIORITIES,
            'statuses' => WorkOrder::STATUSES,
            // Handy when the job was raised from a room screen.
            'preselect' => $request->integer('room') ?: null,
        ];
    }

    /**
     * Who a job can be given to.
     *
     * Everybody active in the branch, the same as House Keeping Status: a small
     * hotel's manager changes the tap himself, and a "maintenance" role the
     * customer never set up would leave this list empty with no way to tell why.
     */
    private function employees(int $branchId)
    {
        return User::query()
            ->where('branch_id', $branchId)
            ->where('status', 1)
            ->orderBy('name')
            ->pluck('name', 'user_id');
    }
}
