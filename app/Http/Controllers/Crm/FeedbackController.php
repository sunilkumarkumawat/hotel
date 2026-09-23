<?php

namespace App\Http\Controllers\Crm;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Crm\GuestFeedback;
use App\Support\GuestCrm;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * What guests said after they left, and what the hotel did about it.
 *
 * The screen is an inbox rather than a report. Averages are at the top because
 * somebody asks for them, but the thing it is built around is the list of low
 * scores nobody has dealt with — the only part of this that is a to-do list.
 */
class FeedbackController extends Controller
{
    /** GET crm/feedback */
    public function index(Request $request): View
    {
        $branchId = (int) Helper::getActiveBranchId();

        [$from, $to] = $this->range($request);

        $view = $request->string('view')->toString();
        $view = in_array($view, ['attention', 'all', 'unanswered'], true) ? $view : 'attention';

        $rows = GuestFeedback::query()
            ->forBranch($branchId)
            ->with(['guest', 'checkIn.room', 'handler'])
            ->when($view === 'attention', fn ($q) => $q->needsAttention())
            ->when($view === 'all', fn ($q) => $q->answered()
                ->whereBetween('answered_at', [$from . ' 00:00:00', $to . ' 23:59:59']))
            ->when($view === 'unanswered', fn ($q) => $q->whereNull('answered_at')
                ->whereBetween('sent_at', [$from . ' 00:00:00', $to . ' 23:59:59']))
            ->orderByDesc('answered_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('crm.feedback', [
            'rows' => $rows,
            'summary' => GuestCrm::feedbackSummary($branchId, $from, $to),
            'from' => $from,
            'to' => $to,
            'view' => $view,
            'areas' => GuestFeedback::AREAS,
        ]);
    }

    /**
     * POST crm/feedback/{feedback}/handled
     *
     * Marking it handled takes a note, because "handled" with nothing written
     * down is a tick box somebody presses to clear a list.
     */
    public function handled(Request $request, GuestFeedback $feedback): RedirectResponse
    {
        abort_unless((int) $feedback->branch_id === (int) Helper::getActiveBranchId(), 404);

        $data = $request->validate([
            'handled_note' => ['required', 'string', 'max:2000'],
        ]);

        $feedback->update([
            'handled_at' => now(),
            'handled_by' => $request->user()?->user_id,
            'handled_note' => $data['handled_note'],
        ]);

        return back()->with('status', 'Marked as dealt with.');
    }

    /**
     * The window the screen is looking at — this month by default.
     *
     * Feedback is read while it is still worth acting on, so this one does NOT
     * default to last month the way the GST screen does.
     *
     * @return array{0: string, 1: string}
     */
    private function range(Request $request): array
    {
        $today = CarbonImmutable::parse(today()->toDateString());

        $from = rescue(
            fn () => CarbonImmutable::parse($request->string('from')->toString())->toDateString(),
            $today->startOfMonth()->toDateString(),
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
