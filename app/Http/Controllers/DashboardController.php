<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Models\Branch\Branch;
use App\Models\Common\Module;
use App\Models\Common\SubModule;
use App\Models\Crm\GuestFeedback;
use App\Models\Role\Role;
use App\Models\User;
use App\Support\HotelDashboard;
use App\Support\HotelInsights;
use App\Support\Reports;
use App\Support\RoomBoard;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;


class DashboardController extends Controller
{
    public function dashboard(Request $request): View
    {
        $branchId = Helper::getActiveBranchId();

        if (! $branchId) {
            return view('dashboard', $this->systemOnly($request));
        }

        $date = rescue(
            fn () => CarbonImmutable::parse($request->string('date')->toString() ?: 'today')->toDateString(),
            today()->toDateString(),
            false
        );

        $month = rescue(
            fn () => CarbonImmutable::parse($request->string('month')->toString() ?: $date)->startOfMonth(),
            CarbonImmutable::parse($date)->startOfMonth(),
            false
        );

        $data = new HotelDashboard($branchId, $date);
        $movements = $data->movements();

        $money = Reports::run('occupancy', $branchId, ['from' => $date, 'to' => $date]);

        return view('dashboard', [
            'me' => $request->user()->load(['role', 'branch']),
            'date' => $date,
            'month' => $month,
            'headline' => $data->headline(),
            'overview' => $data->overview(),
            'categories' => $data->categories(),
            'board' => $data->board(),
            'states' => RoomBoard::STATES,
            'legend' => RoomBoard::legend($data->rooms(), $data->board()),
            'availability' => $data->availability(),
            'calendar' => $data->month($month->toDateString()),
            'totalRooms' => $data->rooms()->count(),
            'arrivals' => $movements['arrivals'],
            'departures' => $movements['departures'],
            'today' => $money['rows']->first(),
            'insights' => HotelInsights::build($branchId, $data),
            'feedback' => $this->feedbackSummary($branchId),
            'system' => $this->system(),
        ]);
    }

    /**
     * @return array{count: int, average: ?float, needs_attention: int, areas: Collection}|null
     */
    private function feedbackSummary(int $branchId): ?array
    {
        $answered = GuestFeedback::forBranch($branchId)->answered();
        $count = (clone $answered)->count();

        if ($count === 0) {
            return null;
        }

        $rows = (clone $answered)->get(array_merge(['overall'], array_keys(GuestFeedback::AREAS)));

        $overall = $rows->pluck('overall')->filter(fn ($v) => $v !== null);

        $areas = collect(GuestFeedback::AREAS)->map(function (string $label, string $key) use ($rows) {
            $scores = $rows->pluck($key)->filter(fn ($v) => $v !== null);

            return ['key' => $key, 'label' => $label, 'average' => $scores->isEmpty() ? null : round($scores->avg(), 1)];
        })->values();

        return [
            'count' => $count,
            'average' => $overall->isEmpty() ? null : round($overall->avg(), 1),
            'needs_attention' => GuestFeedback::forBranch($branchId)->needsAttention()->count(),
            'areas' => $areas,
        ];
    }

    /** @return array<string, mixed> */
    private function systemOnly(Request $request): array
    {
        return [
            'me' => $request->user()->load(['role', 'branch']),
            'date' => today()->toDateString(),
            'month' => CarbonImmutable::now()->startOfMonth(),
            'headline' => null,
            'system' => $this->system(),
        ];
    }

    /** @return array<string, int> */
    private function system(): array
    {
        return [
            'users' => User::count(),
            'active_users' => User::where('status', 1)->count(),
            'roles' => Role::count(),
            'branches' => Branch::count(),
            'modules' => Module::count(),
            'submodules' => SubModule::count(),
        ];
    }
}
