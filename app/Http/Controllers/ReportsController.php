<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Models\Master\Room;
use App\Models\Master\PayMode;
use App\Models\Master\RoomType;
use App\Models\Pos\Outlet;
use App\Support\Reports;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The Reports section.
 *
 * An index of every report the system can produce, and one screen that renders
 * any of them. They all come back in the same shape from {@see Reports}, so
 * there is one view, one CSV export and one set of filters rather than eighteen
 * of each — which is what makes adding the nineteenth a ten-minute job.
 *
 * One permission for the whole section, on purpose. A hotel that trusts
 * somebody with the occupancy report trusts them with the arrivals list, and
 * eighteen permission rows is a matrix nobody would ever tick correctly.
 */
class ReportsController extends Controller
{
    /** GET reports */
    public function index(): View
    {
        return view('reports.index', [
            'groups' => config('reports.groups'),
            'reports' => collect(config('reports.reports'))
                ->map(fn (array $meta, string $slug) => $meta + ['slug' => $slug])
                ->groupBy('group'),
        ]);
    }

    /** GET reports/{report} */
    public function show(Request $request, string $report): View
    {
        $meta = $this->meta($report);
        $branchId = (int) Helper::getActiveBranchId();
        $filters = $this->filters($request, $meta);

        $result = Reports::run($report, $branchId, $filters);

        return view('reports.show', $result + [
            'slug' => $report,
            'meta' => $meta,
            'filters' => $filters,
            'options' => $this->options($meta, $branchId),
        ]);
    }

    /**
     * GET reports/{report}/export
     *
     * The same rows as the screen, as a spreadsheet. Built by re-running the
     * report rather than by re-querying: a export that could disagree with the
     * screen it was downloaded from is worse than no export at all.
     */
    public function export(Request $request, string $report): StreamedResponse
    {
        $meta = $this->meta($report);
        $branchId = (int) Helper::getActiveBranchId();
        $filters = $this->filters($request, $meta);

        $result = Reports::run($report, $branchId, $filters);

        $name = $report . '-' . $filters['from'] . '-to-' . $filters['to'] . '.csv';

        return response()->streamDownload(function () use ($result) {
            $out = fopen('php://output', 'w');

            fputcsv($out, array_map(fn (array $column) => $column['label'], $result['columns']));

            foreach ($result['rows'] as $row) {
                fputcsv($out, array_map(
                    function (string $key) use ($row) {
                        $value = $row->{$key} ?? '';

                        // Numbers go out unformatted so a spreadsheet can add
                        // them up — a column of "₹ 1,250.00" is text.
                        return is_float($value) ? number_format($value, 2, '.', '') : $value;
                    },
                    array_keys($result['columns'])
                ));
            }

            if (! empty($result['totals'])) {
                fputcsv($out, array_map(
                    fn (string $key) => array_key_exists($key, $result['totals'])
                        ? number_format((float) $result['totals'][$key], 2, '.', '')
                        : ($key === array_key_first($result['columns']) ? 'Total' : ''),
                    array_keys($result['columns'])
                ));
            }

            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /** @return array<string, mixed> */
    private function meta(string $report): array
    {
        $reports = config('reports.reports');

        abort_unless(isset($reports[$report]), 404);

        return $reports[$report] + ['slug' => $report];
    }

    /**
     * Whatever this report's filters are, read safely.
     *
     * A date the browser sent as nonsense falls back rather than throwing, and
     * a range typed backwards is treated as a typo rather than as a reason to
     * show nothing.
     *
     * @return array<string, mixed>
     */
    private function filters(Request $request, array $meta): array
    {
        $wanted = $meta['filters'] ?? [];

        $from = $this->date($request->string('from')->toString(), today()->startOfMonth()->toDateString());
        $to = $this->date($request->string('to')->toString(), today()->toDateString());

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [
            'from' => $from,
            'to' => $to,
            'room_type' => in_array('room_type', $wanted, true) ? $request->integer('room_type') : null,
            'pay_mode' => in_array('pay_mode', $wanted, true) ? $request->integer('pay_mode') : null,
            'outlet' => in_array('outlet', $wanted, true) ? $request->integer('outlet') : null,
            'room' => in_array('room', $wanted, true) ? $request->integer('room') : null,
            'status' => in_array('status', $wanted, true) ? $request->string('status')->toString() : null,
        ];
    }

    /**
     * The lists the filter dropdowns are drawn from.
     *
     * Only the ones this report actually asks for — a screen with no outlet
     * filter should not cost a query against the outlets table.
     *
     * @return array<string, mixed>
     */
    private function options(array $meta, int $branchId): array
    {
        $wanted = $meta['filters'] ?? [];
        $options = [];

        if (in_array('room_type', $wanted, true)) {
            $options['room_type'] = RoomType::query()->forBranch($branchId)->active()
                ->orderBy('name')->pluck('name', 'id');
        }

        if (in_array('pay_mode', $wanted, true)) {
            $options['pay_mode'] = PayMode::query()->forBranch($branchId)->active()
                ->orderBy('name')->pluck('name', 'id');
        }

        if (in_array('outlet', $wanted, true)) {
            $options['outlet'] = Outlet::query()->forBranch($branchId)->sellable()
                ->orderBy('name')->pluck('name', 'id');
        }

        if (in_array('room', $wanted, true)) {
            $options['room'] = Room::query()->forBranch($branchId)->active()
                ->orderBy('room_no')->pluck('room_no', 'id');
        }

        return $options;
    }

    private function date(?string $value, string $fallback): string
    {
        return rescue(
            fn () => CarbonImmutable::parse(trim((string) $value) ?: $fallback)->toDateString(),
            $fallback,
            false
        );
    }
}
