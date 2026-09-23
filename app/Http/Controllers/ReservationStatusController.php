<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Support\Availability;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reservation Status View — how full the hotel is, category by category,
 * for a run of dates.
 */
class ReservationStatusController extends Controller
{
    /** How many days the grid may show. */
    private const SPANS = [7 => '7 days', 15 => '15 days', 30 => '30 days'];

    public function index(Request $request): View
    {
        [$start, $days] = $this->window($request);

        $availability = Availability::for(Helper::getActiveBranchId(), $start->toDateString(), $days);
        $grid = $availability->grid();

        $today = CarbonImmutable::today()->toDateString();
        $todayCell = $grid['totals'][$today] ?? null;

        return view('reservation.status', [
            'start' => $start,
            'days' => $days,
            'spans' => self::SPANS,
            'dates' => $availability->dates(),
            'grid' => $grid,
            'today' => $today,
            'stats' => [
                'rooms' => $grid['rooms'],
                'booked' => $todayCell['booked'] ?? 0,
                'available' => $todayCell['available'] ?? 0,
                'occupancy' => $grid['rooms'] > 0 && $todayCell
                    ? round((($todayCell['booked'] + $todayCell['tentative']) / $grid['rooms']) * 100, 1)
                    : 0.0,
            ],
            'prev' => $start->subDays($days)->toDateString(),
            'next' => $start->addDays($days)->toDateString(),
        ]);
    }

    /** The same grid as a CSV, for the Excel button. */
    public function export(Request $request): StreamedResponse
    {
        [$start, $days] = $this->window($request);

        $availability = Availability::for(Helper::getActiveBranchId(), $start->toDateString(), $days);
        $grid = $availability->grid();
        $dates = $availability->dates();

        $filename = 'reservation-status-' . $start->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($grid, $dates) {
            $out = fopen('php://output', 'w');

            // Excel needs the BOM to read the ₹ sign and any non-ASCII names.
            fwrite($out, "\xEF\xBB\xBF");

            $header = array_merge(
                ['Room category', 'Rooms', 'Figure'],
                $dates->map(fn ($d) => $d->format('d M Y (D)'))->all()
            );
            fputcsv($out, $header);

            foreach ($grid['categories'] as $category) {
                foreach (['booked' => 'Booked', 'tentative' => 'Tentative', 'blocked' => 'Blocked', 'available' => 'Available'] as $field => $label) {
                    fputcsv($out, array_merge(
                        [$category->name, $category->rooms, $label],
                        $dates->map(fn ($d) => $grid['rows'][$category->key][$d->toDateString()][$field])->all()
                    ));
                }
            }

            foreach (['booked' => 'Booked', 'tentative' => 'Tentative', 'blocked' => 'Blocked', 'available' => 'Available'] as $field => $label) {
                fputcsv($out, array_merge(
                    ['TOTAL', $grid['rooms'], $label],
                    $dates->map(fn ($d) => $grid['totals'][$d->toDateString()][$field])->all()
                ));
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Which bookings sit in one cell — used by the drill-down panel.
     */
    public function cell(Request $request): View
    {
        $data = $request->validate([
            'date' => 'required|date',
            'category' => 'nullable',
        ]);

        $date = CarbonImmutable::parse($data['date'])->toDateString();
        $category = ($data['category'] ?? 'none') === 'none' ? null : (int) $data['category'];

        $bookings = DB::table('reservation_rooms as rr')
            ->join('reservations as r', 'r.id', '=', 'rr.reservation_id')
            ->leftJoin('room_type as rt', 'rt.id', '=', 'rr.room_type_id')
            ->where('r.branch_id', Helper::getActiveBranchId())
            ->whereIn('r.status', array_merge(Availability::HOLDS_ROOM, Availability::TENTATIVE))
            // See the note in Availability: compare against the next day so a
            // stored '2026-09-08 00:00:00' still counts as the 8th.
            ->where('rr.arrival_date', '<', CarbonImmutable::parse($date)->addDay()->toDateString())
            ->where('rr.checkout_date', '>', $date)
            ->when(
                $category === null,
                fn ($q) => $q->whereNull('rr.room_category_id'),
                fn ($q) => $q->where('rr.room_category_id', $category)
            )
            ->orderBy('rr.arrival_date')
            ->get([
                'r.id', 'r.reservation_no', 'r.first_name', 'r.last_name', 'r.title',
                'r.mobile', 'r.status',
                'rr.arrival_date', 'rr.checkout_date', 'rr.no_of_rooms', 'rr.room_no',
                'rt.name as room_type',
            ]);

        return view('reservation.partials.status-cell', compact('bookings', 'date'));
    }

    /**
     * Read the date and span off the query string, with sensible fallbacks.
     *
     * @return array{0: CarbonImmutable, 1: int}
     */
    private function window(Request $request): array
    {
        try {
            $start = CarbonImmutable::parse($request->string('date')->toString() ?: 'today')->startOfDay();
        } catch (\Throwable) {
            $start = CarbonImmutable::today();
        }

        $days = (int) $request->integer('days', 15);

        if (! array_key_exists($days, self::SPANS)) {
            $days = 15;
        }

        return [$start, $days];
    }
}
