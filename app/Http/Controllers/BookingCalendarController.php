<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Support\Availability;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reservation Calendar — room category down the side, one column per night,
 * and two numbers in every cell:
 *
 *   green   Current booking   the guest is already in the room
 *   yellow  Advance booking   booked, not arrived yet
 *
 * Clicking either number opens Reservation Booking Details for that cell: who
 * is in house, who is due, and the two added together.
 */
class BookingCalendarController extends Controller
{
    private const SPANS = [7 => '7 days', 15 => '15 days', 30 => '30 days'];

    public function index(Request $request): View
    {
        [$start, $days] = $this->window($request);

        $availability = Availability::for(Helper::getActiveBranchId(), $start->toDateString(), $days);
        $grid = $availability->grid();

        $today = CarbonImmutable::today()->toDateString();
        $todayCell = $grid['totals'][$today] ?? null;

        return view('reservation.booking-calendar', [
            'start' => $start,
            'days' => $days,
            'spans' => self::SPANS,
            'dates' => $availability->dates(),
            'grid' => $grid,
            'today' => $today,
            'stats' => [
                'rooms' => $grid['rooms'],
                'current' => $todayCell['current'] ?? 0,
                'advance' => $todayCell['advance'] ?? 0,
                'available' => $todayCell['available'] ?? 0,
            ],
            'prev' => $start->subDays($days)->toDateString(),
            'next' => $start->addDays($days)->toDateString(),
        ]);
    }

    /**
     * Reservation Booking Details for one cell.
     *
     * Current booking, advance booking, and the tally underneath — the same
     * three blocks the old system showed.
     */
    public function details(Request $request): View
    {
        $data = $request->validate([
            'date' => 'required|date',
            'category' => 'nullable',
        ]);

        $date = CarbonImmutable::parse($data['date'])->toDateString();
        $category = ($data['category'] ?? 'none') === 'none' ? null : (int) $data['category'];

        $current = $this->linesOn($date, $category, Availability::CURRENT);
        $advance = $this->linesOn($date, $category, Availability::ADVANCE);

        $categoryName = $category
            ? DB::table('room_category')->where('id', $category)->value('name')
            : 'Uncategorised';

        return view('reservation.partials.booking-details', [
            'date' => $date,
            'categoryName' => $categoryName ?: 'Uncategorised',
            'current' => $current,
            'advance' => $advance,
            'tally' => [
                'current' => (int) $current->sum('no_of_rooms'),
                'advance' => (int) $advance->sum('no_of_rooms'),
                'total' => (int) $current->sum('no_of_rooms') + (int) $advance->sum('no_of_rooms'),
            ],
        ]);
    }

    /** The grid as a CSV. */
    public function export(Request $request): StreamedResponse
    {
        [$start, $days] = $this->window($request);

        $availability = Availability::for(Helper::getActiveBranchId(), $start->toDateString(), $days);
        $grid = $availability->grid();
        $dates = $availability->dates();

        return response()->streamDownload(function () use ($grid, $dates) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, array_merge(
                ['Room category', 'Rooms', 'Figure'],
                $dates->map(fn ($d) => $d->format('d M Y (D)'))->all()
            ));

            $lines = ['current' => 'Current booking', 'advance' => 'Advance booking', 'available' => 'Available'];

            foreach ($grid['categories'] as $category) {
                foreach ($lines as $field => $label) {
                    fputcsv($out, array_merge(
                        [$category->name, $category->rooms, $label],
                        $dates->map(fn ($d) => $grid['rows'][$category->key][$d->toDateString()][$field])->all()
                    ));
                }
            }

            foreach ($lines + ['blocked' => 'Blocked'] as $field => $label) {
                fputcsv($out, array_merge(
                    ['TOTAL', $grid['rooms'], $label],
                    $dates->map(fn ($d) => $grid['totals'][$d->toDateString()][$field])->all()
                ));
            }

            fclose($out);
        }, 'reservation-calendar-' . $start->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * Booking lines covering one night, in one category, with one set of
     * statuses. Pax is the head count stored on the line.
     *
     * @param  list<string>  $statuses
     */
    private function linesOn(string $date, ?int $category, array $statuses): Collection
    {
        return DB::table('reservation_rooms as rr')
            ->join('reservations as r', 'r.id', '=', 'rr.reservation_id')
            ->leftJoin('room_type as rt', 'rt.id', '=', 'rr.room_type_id')
            ->where('r.branch_id', Helper::getActiveBranchId())
            ->whereIn('r.status', $statuses)
            // Compare against the next day: a stored date can carry a
            // 00:00:00 time, and '2026-09-08 00:00:00' is not <= '2026-09-08'.
            ->where('rr.arrival_date', '<', CarbonImmutable::parse($date)->addDay()->toDateString())
            ->where('rr.checkout_date', '>', $date)
            ->when(
                $category === null,
                fn ($q) => $q->whereNull('rr.room_category_id'),
                fn ($q) => $q->where('rr.room_category_id', $category)
            )
            ->orderBy('rr.arrival_date')
            ->orderBy('rr.room_no')
            ->get([
                'rr.id as line_id', 'rr.arrival_date', 'rr.checkout_date',
                'rr.room_no', 'rr.no_of_rooms', 'rr.male', 'rr.female', 'rr.child',
                'rt.name as room_type',
                'r.id as reservation_id', 'r.reservation_no', 'r.status',
                'r.title', 'r.first_name', 'r.last_name', 'r.mobile', 'r.arrival_from',
            ])
            ->map(function ($row) {
                $row->party = trim(($row->title ? $row->title . ' ' : '') . $row->first_name . ' ' . $row->last_name);
                $row->pax = (int) $row->male + (int) $row->female + (int) $row->child;
                $row->from = substr($row->arrival_date, 0, 10);
                $row->to = substr($row->checkout_date, 0, 10);

                return $row;
            });
    }

    /** @return array{0: CarbonImmutable, 1: int} */
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
