<?php

namespace App\Http\Controllers\Audit;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Audit\CashierShift;
use App\Support\ShiftRefused;
use App\Support\Shifts;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The cashier's own screen, and the manager's view of everybody's.
 *
 * A shift close is the one moment in the day when a person is asked to put a
 * number against what they are holding, so the screen shows what the books
 * expect only AFTER they have typed what they counted — the count comes from
 * the drawer, not from the screen. That is the whole reason a blind count
 * exists, and a screen that shows the expected figure first quietly turns
 * every close into a transcription exercise.
 */
class ShiftController extends Controller
{
    /** GET shift/my-shift */
    public function mine(Request $request): View
    {
        $branchId = (int) Helper::getActiveBranchId();
        $userId = (int) $request->user()->user_id;

        $shift = Shifts::current($branchId, $userId);

        return view('shift.mine', [
            'shift' => $shift,
            'figures' => $shift ? Shifts::figures($shift) : null,
            'lines' => $shift
                ? Shifts::linesFor($branchId, $userId, $shift->opened_at->toDateTimeString(), now()->toDateTimeString())
                : [],
            'modes' => Shifts::payModes($branchId),
            'recent' => CashierShift::query()
                ->forBranch($branchId)
                ->where('user_id', $userId)
                ->where('status', 'closed')
                ->orderByDesc('closed_at')
                ->limit(5)
                ->get(),
            'tolerance' => Shifts::TOLERANCE,
        ]);
    }

    /** POST shift/my-shift/open */
    public function open(Request $request): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();

        $data = $request->validate([
            'opening_float' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'name' => ['nullable', 'string', 'max:40'],
            'remark' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $shift = Shifts::open(
                $branchId,
                (int) $request->user()->user_id,
                round((float) ($data['opening_float'] ?? 0), 2),
                $data['name'] ?? null,
                $data['remark'] ?? null
            );
        } catch (ShiftRefused $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('shift.my-shift')
            ->with('status', 'Shift ' . $shift->shift_no . ' is open.');
    }

    /**
     * POST shift/my-shift/close
     *
     * The counted figures arrive as `counted[<pay mode id>]`. A mode left
     * blank is zero — the screen says so beside the boxes, because a close
     * that silently skipped the empty ones would balance for the wrong reason.
     */
    public function close(Request $request): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();
        $userId = (int) $request->user()->user_id;

        $shift = Shifts::current($branchId, $userId);

        if (! $shift) {
            return back()->with('info', 'You do not have a shift open.');
        }

        $data = $request->validate([
            'counted' => ['nullable', 'array'],
            'counted.*' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'close_note' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $closed = Shifts::close(
                $shift,
                $data['counted'] ?? [],
                $data['close_note'] ?? null,
                $userId
            );
        } catch (ShiftRefused $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('shift.reports.show', $closed->id)
            ->with('status', 'Shift ' . $closed->shift_no . ' closed.');
    }

    /** GET shift/reports */
    public function index(Request $request): View
    {
        $branchId = (int) Helper::getActiveBranchId();

        [$from, $to] = $this->range($request);

        $shifts = Shifts::between($branchId, $from, $to);

        $user = $request->integer('user');
        $status = $request->string('status')->toString();

        $filtered = $shifts
            ->when($user > 0, fn ($rows) => $rows->where('user_id', $user))
            ->when(in_array($status, ['open', 'closed'], true), fn ($rows) => $rows->where('status', $status))
            ->values();

        return view('shift.reports', [
            'shifts' => $filtered,
            'summary' => Shifts::summary($filtered),
            'unattached' => Shifts::unattached($branchId, $from, $to),
            'users' => \App\Models\User::query()
                ->where('branch_id', $branchId)
                ->orderBy('name')
                ->pluck('name', 'user_id'),
            'filters' => ['user' => $user, 'status' => $status],
            'from' => $from,
            'to' => $to,
            'tolerance' => Shifts::TOLERANCE,
        ]);
    }

    /** GET shift/reports/{shift} */
    public function show(Request $request, CashierShift $shift): View
    {
        $branchId = (int) Helper::getActiveBranchId();
        abort_unless((int) $shift->branch_id === $branchId, 404);

        return view('shift.show', $this->report($shift) + ['print' => false]);
    }

    /** GET shift/reports/{shift}/print */
    public function print(Request $request, CashierShift $shift): View
    {
        $branchId = (int) Helper::getActiveBranchId();
        abort_unless((int) $shift->branch_id === $branchId, 404);

        return view('shift.print', $this->report($shift));
    }

    /**
     * POST shift/reports/{shift}/close
     *
     * A manager closing somebody else's drawer, because the cashier has gone
     * home with it open. It is an edit rather than an add: the shift already
     * exists, and what changes is whose name is on the close.
     */
    public function closeOther(Request $request, CashierShift $shift): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();
        abort_unless((int) $shift->branch_id === $branchId, 404);

        $data = $request->validate([
            'counted' => ['nullable', 'array'],
            'counted.*' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'close_note' => ['required', 'string', 'max:2000'],
        ]);

        try {
            Shifts::close(
                $shift,
                $data['counted'] ?? [],
                $data['close_note'],
                (int) $request->user()->user_id
            );
        } catch (ShiftRefused $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Shift ' . $shift->shift_no . ' closed on behalf of ' . $shift->cashier . '.');
    }

    /** Everything both the screen and the printout need. */
    private function report(CashierShift $shift): array
    {
        $figures = Shifts::frozenOrLive($shift);

        return [
            'shift' => $shift->load(['user', 'closer']),
            'figures' => $figures,
            'lines' => Shifts::linesFor(
                (int) $shift->branch_id,
                (int) $shift->user_id,
                $shift->opened_at->toDateTimeString(),
                ($shift->closed_at ?: now())->toDateTimeString()
            ),
            'modes' => Shifts::payModes((int) $shift->branch_id),
            'branch' => Helper::activeBranch(),
            'tolerance' => Shifts::TOLERANCE,
        ];
    }

    /**
     * The window the list is looking at — today by default.
     *
     * A shift list is read at the end of a day about that day, so it does not
     * open on the month the way a revenue report does.
     *
     * @return array{0: string, 1: string}
     */
    private function range(Request $request): array
    {
        $today = CarbonImmutable::parse(today()->toDateString());

        $from = rescue(
            fn () => CarbonImmutable::parse($request->string('from')->toString())->toDateString(),
            $today->toDateString(),
            false
        );

        $to = rescue(
            fn () => CarbonImmutable::parse($request->string('to')->toString())->toDateString(),
            $today->toDateString(),
            false
        );

        return $from <= $to ? [$from, $to] : [$to, $from];
    }
}
