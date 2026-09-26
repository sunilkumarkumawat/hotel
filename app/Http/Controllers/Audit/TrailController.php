<?php

namespace App\Http\Controllers\Audit;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Audit\ActivityLog;
use App\Models\User;
use App\Support\Audit;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrailController extends Controller
{
    public function index(Request $request): View
    {
        $branchId = (int) Helper::getActiveBranchId();

        [$from, $to] = $this->range($request);
        $filters = $this->filters($request);

        $rows = $this->query($branchId, $from, $to, $filters)
            ->paginate(40)
            ->withQueryString();

        return view('audit.trail', [
            'rows' => $rows,
            'from' => $from,
            'to' => $to,
            'filters' => $filters,
            'users' => $this->users($branchId),
            'areas' => ActivityLog::AREAS,
            'actions' => ActivityLog::ACTIONS,
            'counts' => $this->counts($branchId, $from, $to),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $branchId = (int) Helper::getActiveBranchId();

        [$from, $to] = $this->range($request);
        $filters = $this->filters($request);

        $rows = $this->query($branchId, $from, $to, $filters)->limit(20000)->get();

        Audit::note('exported', 'Audit trail exported — ' . $from . ' to ' . $to, [
            'area' => 'access',
            'branch_id' => $branchId,
        ]);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['When', 'User', 'Action', 'Area', 'What', 'Details', 'Changed', 'IP']);

            foreach ($rows as $row) {
                $changed = collect($row->changeList())
                    ->map(fn ($c) => $c['column'] . ': ' . $c['from'] . ' → ' . $c['to'])
                    ->join('; ');

                fputcsv($out, [
                    $row->happened_at?->format('Y-m-d H:i:s'),
                    $row->user_name,
                    $row->action_label,
                    $row->area_label,
                    $row->subject_label,
                    $row->summary,
                    $changed,
                    $row->ip,
                ]);
            }

            fclose($out);
        }, 'audit-trail-' . $from . '.csv', ['Content-Type' => 'text/csv']);
    }

    public function logins(Request $request): View
    {
        $branchId = (int) Helper::getActiveBranchId();

        [$from, $to] = $this->range($request);

        $window = [$from . ' 00:00:00', $to . ' 23:59:59'];

        $rows = ActivityLog::query()
            ->forBranch($branchId)
            ->whereIn('action', ['login', 'logout', 'login_failed'])
            ->whereBetween('happened_at', $window)
            ->when($request->integer('user') > 0, fn ($q) => $q->where('user_id', $request->integer('user')))
            ->when($request->string('action')->toString() !== '',
                fn ($q) => $q->where('action', $request->string('action')->toString()))
            ->orderByDesc('happened_at')
            ->paginate(40)
            ->withQueryString();

        $failed = ActivityLog::query()
            ->forBranch($branchId)
            ->where('action', 'login_failed')
            ->whereBetween('happened_at', $window)
            ->get();

        return view('audit.logins', [
            'rows' => $rows,
            'from' => $from,
            'to' => $to,
            'users' => $this->users($branchId),
            'filters' => [
                'user' => $request->integer('user'),
                'action' => $request->string('action')->toString(),
            ],
            'failed' => $failed,
            'suspects' => $failed
                ->groupBy(fn ($row) => ($row->subject_label ?: 'unknown') . ' · ' . ($row->ip ?: 'no address'))
                ->map->count()
                ->sortDesc()
                ->take(6),
        ]);
    }

    private function query(int $branchId, string $from, string $to, array $filters): Builder
    {
        $q = $filters['q'];

        return ActivityLog::query()
            ->forBranch($branchId)
            ->whereBetween('happened_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->when($filters['user'] > 0, fn ($b) => $b->where('user_id', $filters['user']))
            ->when($filters['area'] !== '', fn ($b) => $b->where('area', $filters['area']))
            ->when($filters['action'] !== '', fn ($b) => $b->where('action', $filters['action']))
            ->when($q !== '', fn ($b) => $b->where(function ($w) use ($q) {
                $w->where('subject_label', 'like', '%' . $q . '%')
                    ->orWhere('summary', 'like', '%' . $q . '%');
            }))
            ->orderByDesc('happened_at')
            ->orderByDesc('id');
    }

    /** @return array{user: int, area: string, action: string, q: string} */
    private function filters(Request $request): array
    {
        $area = $request->string('area')->toString();
        $action = $request->string('action')->toString();

        return [
            'user' => $request->integer('user'),
            'area' => isset(ActivityLog::AREAS[$area]) ? $area : '',
            'action' => isset(ActivityLog::ACTIONS[$action]) ? $action : '',
            'q' => trim($request->string('q')->toString()),
        ];
    }

    private function counts(int $branchId, string $from, string $to): array
    {
        return ActivityLog::query()
            ->forBranch($branchId)
            ->whereBetween('happened_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->selectRaw('area, COUNT(*) as total')
            ->groupBy('area')
            ->pluck('total', 'area')
            ->all();
    }

    private function users(int $branchId)
    {
        return User::query()
            ->where('branch_id', $branchId)
            ->orderBy('name')
            ->pluck('name', 'user_id');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function range(Request $request): array
    {
        $today = CarbonImmutable::parse(today()->toDateString());

        $from = rescue(
            fn () => CarbonImmutable::parse($request->string('from')->toString())->toDateString(),
            $today->subDays(6)->toDateString(),
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
