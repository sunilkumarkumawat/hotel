<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Models\FrontOffice\BusinessDay;
use App\Support\NightAudit;
use App\Support\FolioRefused;
use App\Support\PostingRefused;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;


class NightAuditController extends Controller
{
    public function index(Request $request): View
    {
        $branchId = (int) Helper::getActiveBranchId();

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

    public function run(Request $request): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();

        $data = $request->validate([
            'date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        if ($data['date'] !== NightAudit::businessDate($branchId)) {
            return back()->with('error',
                'The business date has moved on since this screen was opened. Reload and run the audit again.');
        }

        try {
            $day = NightAudit::run($branchId, (int) auth()->id(), $data['note'] ?? null, $data['date']);
        } catch (PostingRefused|FolioRefused $e) {
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
