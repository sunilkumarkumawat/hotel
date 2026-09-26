<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Support\MonthlyPosition;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MonthlyCalendarController extends Controller
{
    private const SPANS = [30 => '30 days', 45 => '45 days', 60 => '60 days'];

    public function index(Request $request): View
    {
        [$start, $days] = $this->window($request);

        $position = MonthlyPosition::for(Helper::getActiveBranchId(), $start->toDateString(), $days);

        return view('reservation.calendar-monthly', [
            'start' => $start,
            'days' => $days,
            'spans' => self::SPANS,
            'dates' => $position->dates(),
            'data' => $position->build(),
            'rowLabels' => MonthlyPosition::ROWS,
            'today' => today()->toDateString(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$start, $days] = $this->window($request);

        $position = MonthlyPosition::for(Helper::getActiveBranchId(), $start->toDateString(), $days);
        $data = $position->build();
        $dates = $position->dates();

        $name = 'position-' . $start->toDateString() . '-' . $days . 'd.csv';

        return Response::streamDownload(function () use ($data, $dates) {
            $out = fopen('php://output', 'w');

            fputcsv($out, array_merge([''], $dates->map(fn ($d) => $d->format('d M Y'))->all(), ['TOTAL']));

            foreach (MonthlyPosition::ROWS as $key => $label) {
                fputcsv($out, array_merge(
                    [$label],
                    $data['keys']->map(fn (string $k) => $data['rows'][$key][$k])->all(),
                    [$data['totals'][$key]]
                ));
            }

            fputcsv($out, array_merge(
                ['Occupancy %'],
                $data['keys']->map(fn (string $k) => $data['occupancy_percent'][$k] . '%')->all(),
                [$data['occupancy_percent']['total'] . '%']
            ));

            fputcsv($out, []);
            fputcsv($out, ['Room Typewise Position']);

            foreach ($data['types'] as $type) {
                fputcsv($out, array_merge(
                    [$type->name . ' (' . $type->rooms . ')'],
                    $data['keys']->map(fn (string $k) => $data['typewise'][$type->key][$k])->all()
                ));
            }

            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array{0: CarbonImmutable, 1: int}
     */
    private function window(Request $request): array
    {
        $start = rescue(
            fn () => CarbonImmutable::parse($request->string('date')->toString() ?: 'today')->startOfDay(),
            CarbonImmutable::today(),
            false
        );

        $days = (int) $request->integer('days', 30);
        $days = array_key_exists($days, self::SPANS) ? $days : 30;

        return [$start, $days];
    }
}
