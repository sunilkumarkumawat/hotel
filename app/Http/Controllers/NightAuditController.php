<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Models\FrontOffice\BusinessDay;
use App\Support\NightAudit;
use App\Support\PostingRefused;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The night audit screen.
 *
 * One page with three parts, in the order the job is done: the walk round
 * (what needs fixing before the day closes), the figures as they stand right
 * now, and the button that closes it. Underneath, every night already audited.
 *
 * The screen holds no arithmetic of its own — App\Support\NightAudit answers
 * every question on it, so the preview a manager reads and the report that
 * gets frozen are the same numbers by construction, not by coincidence.
 */
class NightAuditController extends Controller
{
    /** GET front-office/night-audit */
    public function index(Request $request): View
    {
        $branchId = (int) Helper::getActiveBranchId();

        /*
         * A date in the query string lets a manager look back at a night that
         * is already closed. It never lets them look FORWARD: the audit runs
         * on the business date and nothing else, so an unaudited future night
         * has no figures to show and no button to press.
         */
        $asked = $request->string('date')->toString();
        $businessDate = NightAudit::businessDate($branchId);

        $date = $asked && $asked <= $businessDate
            ? rescue(fn () => \Carbon\CarbonImmutable::parse($asked)->toDateString(), $businessDate, false)
            : $businessDate;

        return view('front-office.night-audit.index', NightAudit::preview($branchId, $date) + [
            'businessDate' => $businessDate,
            'pending' => NightAudit::pendingNights($branchId),
            'history' => NightAudit::history($branchId),
        ]);
    }

    /** POST front-office/night-audit */
    public function run(Request $request): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();

        $data = $request->validate([
            'date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        // The posted date is a confirmation, not an instruction. If the day
        // moved on while the screen sat open, the clerk is agreeing to close
        // a night that is no longer the open one, and the run is refused.
        if ($data['date'] !== NightAudit::businessDate($branchId)) {
            return back()->with('error',
                'The business date has moved on since this screen was opened. Reload and run the audit again.');
        }

        try {
            $day = NightAudit::run($branchId, (int) auth()->id(), $data['note'] ?? null, $data['date']);
        } catch (PostingRefused $e) {
            return back()->with('error', $e->getMessage());
        }

        NightAudit::announce($day);

        return redirect()
            ->route('front-office.night-audit.show', $day)
            ->with('status', sprintf(
                'Night of %s closed. %d room night%s posted, %d no show%s marked. The hotel is now trading on %s.',
                $day->business_date->format('d M Y'),
                $day->nights_posted, $day->nights_posted === 1 ? '' : 's',
                $day->no_shows, $day->no_shows === 1 ? '' : 's',
                NightAudit::businessDate($branchId),
            ));
    }

    /** GET front-office/night-audit/{day} — the frozen report for one night. */
    public function show(BusinessDay $day): View
    {
        $branchId = (int) Helper::getActiveBranchId();

        abort_unless((int) $day->branch_id === $branchId, 404);

        return view('front-office.night-audit.show', [
            'day' => $day->load('closer'),
            'figures' => $day->figures ?? [],
            'businessDate' => NightAudit::businessDate($branchId),
        ]);
    }

    /** GET front-office/night-audit/{day}/print — the manager's report on paper. */
    public function print(BusinessDay $day): View
    {
        $branchId = (int) Helper::getActiveBranchId();

        abort_unless((int) $day->branch_id === $branchId, 404);

        return view('front-office.night-audit.print', [
            'day' => $day->load('closer'),
            'figures' => $day->figures ?? [],
            'branch' => Helper::activeBranch(),
            'back' => route('front-office.night-audit.show', $day),
        ]);
    }

    /** DELETE front-office/night-audit/{day} — open the last closed night again. */
    public function reopen(BusinessDay $day): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();

        abort_unless((int) $day->branch_id === $branchId, 404);

        try {
            NightAudit::reopen($branchId, $day->id, (int) auth()->id());
        } catch (PostingRefused $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('front-office.night-audit')
            ->with('warning', sprintf(
                'The night of %s is open again. The charges it posted are still on the guests\' folios — '
                . 'running the audit again will not charge them twice.',
                $day->business_date->format('d M Y')
            ));
    }
}
