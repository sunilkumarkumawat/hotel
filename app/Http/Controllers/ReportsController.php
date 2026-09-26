<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Models\FrontOffice\Settlement;
use App\Models\Master\BookedBy;
use App\Models\Master\Room;
use App\Models\Master\PayMode;
use App\Models\Master\RoomType;
use App\Models\Pos\Outlet;
use App\Models\User;
use App\Support\Reports;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;


class ReportsController extends Controller
{
    public function index(): View
    {
        return view('reports.index', [
            'groups' => config('reports.groups'),
            'reports' => collect(config('reports.reports'))
                ->map(fn (array $meta, string $slug) => $meta + ['slug' => $slug])
                ->groupBy('group'),
        ]);
    }

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
        ] + (in_array($report, ['outstanding', 'check-in-out'], true) ? [
            'payModes' => PayMode::query()->forBranch($branchId)->active()->orderBy('name')->pluck('name', 'id'),
            'payTypes' => Settlement::PAY_TYPES,
            'cardTypes' => Settlement::CARD_TYPES,
        ] : []));
    }

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

    /** @return array<string, mixed> */
    private function meta(string $report): array
    {
        $reports = config('reports.reports');

        abort_unless(isset($reports[$report]), 404);

        return $reports[$report] + ['slug' => $report];
    }

    /**
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

        $status = $request->string('status')->toString();
        $status = in_array($status, ['checkin', 'checkout'], true) ? $status : 'both';

        $paymentStatus = $request->string('payment_status')->toString();
        $paymentStatus = in_array($paymentStatus, ['due', 'paid'], true) ? $paymentStatus : null;

        return [
            'from' => $from,
            'to' => $to,
            'room_type' => in_array('room_type', $wanted, true) ? $request->integer('room_type') : null,
            'pay_mode' => in_array('pay_mode', $wanted, true) ? $request->integer('pay_mode') : null,
            'outlet' => in_array('outlet', $wanted, true) ? $request->integer('outlet') : null,
            'room' => in_array('room', $wanted, true) ? $request->integer('room') : null,
            'status' => in_array('status', $wanted, true) ? $status : null,
            'payment_status' => in_array('payment_status', $wanted, true) ? $paymentStatus : null,
            'booking_source' => in_array('booking_source', $wanted, true) ? $request->integer('booking_source') : null,
            'staff' => in_array('staff', $wanted, true) ? $request->integer('staff') : null,
        ];
    }

    /**
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

        if (in_array('booking_source', $wanted, true)) {
            $options['booking_source'] = BookedBy::query()->forBranch($branchId)->active()
                ->orderBy('name')->pluck('name', 'id');
        }

        if (in_array('staff', $wanted, true)) {
            $options['staff'] = User::query()
                ->whereIn('user_id', DB::table('check_ins')
                    ->where('branch_id', $branchId)
                    ->whereNotNull('created_by')
                    ->distinct()
                    ->pluck('created_by'))
                ->orderBy('name')
                ->pluck('name', 'user_id');
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
