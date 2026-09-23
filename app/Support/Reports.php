<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Every report in the Reports section, in one file.
 *
 * Each one returns the same shape — columns, rows, totals and a few figures for
 * the tiles across the top — so there is one view, one export and one set of
 * filters rather than eighteen of each. A new report is a method here and an
 * entry in config/reports.php; nothing else changes and no migration is needed.
 *
 * ──────────────────────────────────────────────────────────────────────────
 * WHAT A REPORT MUST NOT DO
 * ──────────────────────────────────────────────────────────────────────────
 * Nothing here writes, and nothing here keeps a stored total. Every figure is
 * read from the rows that produced it, which is why a report can never drift
 * out of step with the screen it came from — the classic hotel-software bug
 * where the night audit says one thing and the folio says another.
 *
 * Date columns can come back as `2026-09-09 00:00:00`, so every "on or before"
 * test is written as "before the day after". There is no `<= $to` in this file
 * and there must not be one.
 */
class Reports
{
    /**
     * @return array{
     *     columns: array<string, array<string, mixed>>,
     *     rows: Collection,
     *     totals: array<string, float|int>,
     *     summary: array<int, array<string, mixed>>,
     *     note: string|null
     * }
     */
    public static function run(string $slug, int $branchId, array $filters): array
    {
        $method = 'report' . str_replace(' ', '', ucwords(str_replace('-', ' ', $slug)));

        abort_unless(method_exists(self::class, $method), 404);

        $result = self::{$method}($branchId, $filters);

        return $result + ['totals' => [], 'summary' => [], 'note' => null];
    }

    /*
    |--------------------------------------------------------------------------
    | Front desk
    |--------------------------------------------------------------------------
    */

    private static function reportArrivals(int $branchId, array $filters): array
    {
        $rows = DB::table('reservation_rooms as rr')
            ->join('reservations as r', 'r.id', '=', 'rr.reservation_id')
            ->leftJoin('room_type as rt', 'rt.id', '=', 'rr.room_type_id')
            ->leftJoin('rooms as ro', 'ro.id', '=', 'rr.room_id')
            ->where('r.branch_id', $branchId)
            ->whereNotIn('r.status', ['cancelled', 'no_show'])
            ->where('rr.arrival_date', '>=', $filters['from'])
            ->where('rr.arrival_date', '<', self::next($filters['to']))
            ->when($filters['room_type'] ?? null, fn ($q, $id) => $q->where('rr.room_type_id', $id))
            ->orderBy('rr.arrival_date')
            ->orderBy('r.reservation_no')
            ->get([
                'rr.arrival_date', 'rr.arrival_time', 'rr.checkout_date', 'rr.no_of_rooms', 'rr.no_of_days',
                'rr.male', 'rr.female', 'rr.child', 'rr.net_amount',
                'r.reservation_no', 'r.first_name', 'r.last_name', 'r.mobile', 'r.status',
                'rt.name as room_type', 'ro.room_no',
            ])
            ->map(fn ($row) => (object) [
                'date' => self::day($row->arrival_date),
                'reservation_no' => $row->reservation_no,
                'guest' => trim($row->first_name . ' ' . $row->last_name),
                'mobile' => $row->mobile,
                'room_type' => $row->room_type ?: '—',
                'room_no' => $row->room_no ?: 'not allotted',
                'rooms' => (int) $row->no_of_rooms,
                'nights' => (int) $row->no_of_days,
                'pax' => (int) $row->male + (int) $row->female + (int) $row->child,
                'status' => ucfirst(str_replace('_', ' ', (string) $row->status)),
                'amount' => (float) $row->net_amount,
            ]);

        return [
            'columns' => [
                'date' => ['label' => 'Arriving'],
                'reservation_no' => ['label' => 'Booking'],
                'guest' => ['label' => 'Guest'],
                'mobile' => ['label' => 'Mobile'],
                'room_type' => ['label' => 'Room type'],
                'room_no' => ['label' => 'Room'],
                'rooms' => ['label' => 'Rooms', 'num' => true],
                'nights' => ['label' => 'Nights', 'num' => true],
                'pax' => ['label' => 'Pax', 'num' => true],
                'status' => ['label' => 'Status'],
                'amount' => ['label' => 'Value', 'num' => true, 'money' => true],
            ],
            'rows' => $rows,
            'totals' => ['rooms' => $rows->sum('rooms'), 'pax' => $rows->sum('pax'), 'amount' => $rows->sum('amount')],
            'summary' => [
                ['label' => 'Bookings', 'value' => $rows->count(), 'icon' => 'calendar'],
                ['label' => 'Rooms', 'value' => $rows->sum('rooms'), 'icon' => 'home'],
                ['label' => 'Guests', 'value' => $rows->sum('pax'), 'icon' => 'users'],
                ['label' => 'Value', 'value' => self::money($rows->sum('amount')), 'icon' => 'wallet', 'tone' => 'success'],
            ],
        ];
    }

    private static function reportDepartures(int $branchId, array $filters): array
    {
        $rows = DB::table('check_ins as ci')
            ->leftJoin('rooms as ro', 'ro.id', '=', 'ci.room_id')
            ->leftJoin('bills as b', 'b.check_in_id', '=', 'ci.id')
            ->where('ci.branch_id', $branchId)
            ->whereRaw('COALESCE(ci.actual_checkout_date, ci.expected_checkout_date) >= ?', [$filters['from']])
            ->whereRaw('COALESCE(ci.actual_checkout_date, ci.expected_checkout_date) < ?', [self::next($filters['to'])])
            ->orderByRaw('COALESCE(ci.actual_checkout_date, ci.expected_checkout_date)')
            ->get([
                'ci.folio_no', 'ci.guest_name', 'ci.mobile', 'ci.status',
                'ci.checkin_date', 'ci.expected_checkout_date', 'ci.actual_checkout_date',
                'ro.room_no',
                'b.bill_no', 'b.net_amount', 'b.paid_amount', 'b.balance_amount',
            ])
            ->map(fn ($row) => (object) [
                'date' => self::day($row->actual_checkout_date ?: $row->expected_checkout_date),
                'folio_no' => $row->folio_no,
                'guest' => $row->guest_name,
                'room_no' => $row->room_no ?: '—',
                'mobile' => $row->mobile,
                'stayed' => self::day($row->checkin_date),
                'status' => $row->status === 'checked_out' ? 'Checked out' : 'Still in',
                'bill_no' => $row->bill_no ?: '—',
                'amount' => (float) $row->net_amount,
                'paid' => (float) $row->paid_amount,
                'due' => (float) $row->balance_amount,
            ]);

        return [
            'columns' => [
                'date' => ['label' => 'Leaving'],
                'folio_no' => ['label' => 'Folio'],
                'guest' => ['label' => 'Guest'],
                'room_no' => ['label' => 'Room'],
                'mobile' => ['label' => 'Mobile'],
                'stayed' => ['label' => 'Arrived'],
                'status' => ['label' => 'Status'],
                'bill_no' => ['label' => 'Bill'],
                'amount' => ['label' => 'Bill', 'num' => true, 'money' => true],
                'paid' => ['label' => 'Paid', 'num' => true, 'money' => true],
                'due' => ['label' => 'Due', 'num' => true, 'money' => true],
            ],
            'rows' => $rows,
            'totals' => ['amount' => $rows->sum('amount'), 'paid' => $rows->sum('paid'), 'due' => $rows->sum('due')],
            'summary' => [
                ['label' => 'Departures', 'value' => $rows->count(), 'icon' => 'logout'],
                ['label' => 'Billed', 'value' => self::money($rows->sum('amount')), 'icon' => 'file'],
                ['label' => 'Paid', 'value' => self::money($rows->sum('paid')), 'icon' => 'wallet', 'tone' => 'success'],
                ['label' => 'Still due', 'value' => self::money($rows->sum('due')), 'icon' => 'alert', 'tone' => 'danger'],
            ],
        ];
    }

    private static function reportInHouse(int $branchId, array $filters): array
    {
        $rows = DB::table('check_ins as ci')
            ->leftJoin('rooms as ro', 'ro.id', '=', 'ci.room_id')
            ->leftJoin('room_type as rt', 'rt.id', '=', 'ro.room_type_id')
            ->where('ci.branch_id', $branchId)
            ->where('ci.status', 'in_house')
            ->when($filters['room_type'] ?? null, fn ($q, $id) => $q->where('ro.room_type_id', $id))
            ->orderBy('ro.room_no')
            ->get([
                'ci.id', 'ci.folio_no', 'ci.guest_name', 'ci.mobile', 'ci.checkin_date',
                'ci.expected_checkout_date', 'ci.room_rent', 'ci.male', 'ci.female', 'ci.child',
                'ro.room_no', 'rt.name as room_type',
            ]);

        // One query for every folio rather than one per guest: a full house is
        // sixty rooms and sixty extra queries is a screen that takes a second.
        $charged = DB::table('folio_charges')
            ->whereIn('check_in_id', $rows->pluck('id'))
            ->groupBy('check_in_id')
            ->selectRaw('check_in_id, SUM(total_amount) as total')
            ->pluck('total', 'check_in_id');

        $paid = DB::table('settlements')
            ->whereIn('check_in_id', $rows->pluck('id'))
            ->groupBy('check_in_id')
            ->selectRaw('check_in_id, SUM(amount) as total')
            ->pluck('total', 'check_in_id');

        $rows = $rows->map(fn ($row) => (object) [
            'room_no' => $row->room_no ?: '—',
            'room_type' => $row->room_type ?: '—',
            'folio_no' => $row->folio_no,
            'guest' => $row->guest_name,
            'mobile' => $row->mobile,
            'arrived' => self::day($row->checkin_date),
            'leaving' => self::day($row->expected_checkout_date),
            'pax' => (int) $row->male + (int) $row->female + (int) $row->child,
            'rate' => (float) $row->room_rent,
            'charged' => round((float) ($charged[$row->id] ?? 0), 2),
            'paid' => round((float) ($paid[$row->id] ?? 0), 2),
            'balance' => round((float) ($charged[$row->id] ?? 0) - (float) ($paid[$row->id] ?? 0), 2),
        ]);

        return [
            'columns' => [
                'room_no' => ['label' => 'Room'],
                'room_type' => ['label' => 'Type'],
                'folio_no' => ['label' => 'Folio'],
                'guest' => ['label' => 'Guest'],
                'mobile' => ['label' => 'Mobile'],
                'arrived' => ['label' => 'Arrived'],
                'leaving' => ['label' => 'Leaving'],
                'pax' => ['label' => 'Pax', 'num' => true],
                'rate' => ['label' => 'Rate', 'num' => true, 'money' => true],
                'charged' => ['label' => 'Charged', 'num' => true, 'money' => true],
                'paid' => ['label' => 'Paid', 'num' => true, 'money' => true],
                'balance' => ['label' => 'Balance', 'num' => true, 'money' => true],
            ],
            'rows' => $rows,
            'totals' => [
                'pax' => $rows->sum('pax'),
                'charged' => $rows->sum('charged'),
                'paid' => $rows->sum('paid'),
                'balance' => $rows->sum('balance'),
            ],
            'summary' => [
                ['label' => 'Rooms occupied', 'value' => $rows->count(), 'icon' => 'home'],
                ['label' => 'Guests', 'value' => $rows->sum('pax'), 'icon' => 'users'],
                ['label' => 'Charged so far', 'value' => self::money($rows->sum('charged')), 'icon' => 'file'],
                ['label' => 'Outstanding', 'value' => self::money($rows->sum('balance')), 'icon' => 'alert', 'tone' => 'warning'],
            ],
            'note' => 'Live — whoever is in the hotel right now. The date filter does not apply.',
        ];
    }

    private static function reportGuestList(int $branchId, array $filters): array
    {
        $rows = DB::table('check_ins as ci')
            ->leftJoin('rooms as ro', 'ro.id', '=', 'ci.room_id')
            ->where('ci.branch_id', $branchId)
            ->where('ci.checkin_date', '>=', $filters['from'])
            ->where('ci.checkin_date', '<', self::next($filters['to']))
            ->orderByDesc('ci.checkin_date')
            ->get([
                'ci.folio_no', 'ci.guest_name', 'ci.mobile', 'ci.checkin_date',
                'ci.actual_checkout_date', 'ci.expected_checkout_date', 'ci.status', 'ci.is_direct',
                'ro.room_no',
            ])
            ->map(fn ($row) => (object) [
                'guest' => $row->guest_name,
                'mobile' => $row->mobile ?: '—',
                'folio_no' => $row->folio_no,
                'room_no' => $row->room_no ?: '—',
                'arrived' => self::day($row->checkin_date),
                'left' => $row->actual_checkout_date ? self::day($row->actual_checkout_date) : 'still in',
                'nights' => Money::nights(
                    substr((string) $row->checkin_date, 0, 10),
                    substr((string) ($row->actual_checkout_date ?: $row->expected_checkout_date), 0, 10)
                ),
                'source' => (int) $row->is_direct === 1 ? 'Walk-in' : 'Booking',
            ]);

        return [
            'columns' => [
                'guest' => ['label' => 'Guest'],
                'mobile' => ['label' => 'Mobile'],
                'folio_no' => ['label' => 'Folio'],
                'room_no' => ['label' => 'Room'],
                'arrived' => ['label' => 'Arrived'],
                'left' => ['label' => 'Left'],
                'nights' => ['label' => 'Nights', 'num' => true],
                'source' => ['label' => 'Came from'],
            ],
            'rows' => $rows,
            'totals' => ['nights' => $rows->sum('nights')],
            'summary' => [
                ['label' => 'Guests', 'value' => $rows->count(), 'icon' => 'users'],
                ['label' => 'Room nights', 'value' => $rows->sum('nights'), 'icon' => 'calendar'],
                ['label' => 'Walk-ins', 'value' => $rows->where('source', 'Walk-in')->count(), 'icon' => 'desktop'],
            ],
        ];
    }

    private static function reportCancellations(int $branchId, array $filters): array
    {
        $rows = DB::table('reservations as r')
            ->where('r.branch_id', $branchId)
            ->where('r.status', 'cancelled')
            ->where('r.cancelled_on', '>=', $filters['from'])
            ->where('r.cancelled_on', '<', self::next($filters['to']))
            ->orderByDesc('r.cancelled_on')
            ->get([
                'r.reservation_no', 'r.reservation_date', 'r.cancelled_on', 'r.cancel_reason',
                'r.first_name', 'r.last_name', 'r.mobile', 'r.net_amount', 'r.advance_paid',
            ])
            ->map(fn ($row) => (object) [
                'cancelled_on' => self::day($row->cancelled_on),
                'reservation_no' => $row->reservation_no,
                'booked_on' => self::day($row->reservation_date),
                'guest' => trim($row->first_name . ' ' . $row->last_name),
                'mobile' => $row->mobile ?: '—',
                'reason' => $row->cancel_reason ?: '—',
                'value' => (float) $row->net_amount,
                'advance' => (float) $row->advance_paid,
            ]);

        return [
            'columns' => [
                'cancelled_on' => ['label' => 'Cancelled'],
                'reservation_no' => ['label' => 'Booking'],
                'booked_on' => ['label' => 'Booked'],
                'guest' => ['label' => 'Guest'],
                'mobile' => ['label' => 'Mobile'],
                'reason' => ['label' => 'Reason'],
                'value' => ['label' => 'Value lost', 'num' => true, 'money' => true],
                'advance' => ['label' => 'Advance held', 'num' => true, 'money' => true],
            ],
            'rows' => $rows,
            'totals' => ['value' => $rows->sum('value'), 'advance' => $rows->sum('advance')],
            'summary' => [
                ['label' => 'Cancellations', 'value' => $rows->count(), 'icon' => 'x-circle', 'tone' => 'danger'],
                ['label' => 'Value lost', 'value' => self::money($rows->sum('value')), 'icon' => 'trending-up', 'tone' => 'warning'],
                ['label' => 'Advance still held', 'value' => self::money($rows->sum('advance')), 'icon' => 'wallet'],
            ],
        ];
    }

    private static function reportNoShow(int $branchId, array $filters): array
    {
        $rows = DB::table('reservation_rooms as rr')
            ->join('reservations as r', 'r.id', '=', 'rr.reservation_id')
            ->where('r.branch_id', $branchId)
            ->where('r.status', 'no_show')
            ->where('rr.arrival_date', '>=', $filters['from'])
            ->where('rr.arrival_date', '<', self::next($filters['to']))
            ->orderBy('rr.arrival_date')
            ->get([
                'rr.arrival_date', 'rr.no_of_rooms', 'rr.net_amount',
                'r.reservation_no', 'r.first_name', 'r.last_name', 'r.mobile', 'r.advance_paid',
            ])
            ->map(fn ($row) => (object) [
                'date' => self::day($row->arrival_date),
                'reservation_no' => $row->reservation_no,
                'guest' => trim($row->first_name . ' ' . $row->last_name),
                'mobile' => $row->mobile ?: '—',
                'rooms' => (int) $row->no_of_rooms,
                'value' => (float) $row->net_amount,
                'advance' => (float) $row->advance_paid,
            ]);

        return [
            'columns' => [
                'date' => ['label' => 'Was due'],
                'reservation_no' => ['label' => 'Booking'],
                'guest' => ['label' => 'Guest'],
                'mobile' => ['label' => 'Mobile'],
                'rooms' => ['label' => 'Rooms', 'num' => true],
                'value' => ['label' => 'Value', 'num' => true, 'money' => true],
                'advance' => ['label' => 'Advance held', 'num' => true, 'money' => true],
            ],
            'rows' => $rows,
            'totals' => ['rooms' => $rows->sum('rooms'), 'value' => $rows->sum('value'), 'advance' => $rows->sum('advance')],
            'summary' => [
                ['label' => 'No shows', 'value' => $rows->count(), 'icon' => 'alert', 'tone' => 'danger'],
                ['label' => 'Rooms lost', 'value' => $rows->sum('rooms'), 'icon' => 'home'],
                ['label' => 'Advance held', 'value' => self::money($rows->sum('advance')), 'icon' => 'wallet'],
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Money
    |--------------------------------------------------------------------------
    */

    private static function reportOccupancy(int $branchId, array $filters): array
    {
        $sellable = (int) DB::table('rooms')
            ->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId))
            ->where('status', 1)
            ->count();

        $rows = collect();
        $day = CarbonImmutable::parse($filters['from']);
        $last = CarbonImmutable::parse($filters['to']);

        // A range of a year is 365 queries otherwise, so the nights are read
        // once and counted in PHP.
        $nights = DB::table('folio_charges')
            ->where('branch_id', $branchId)
            ->where('charge_type', 'room')
            ->where('charge_date', '>=', $filters['from'])
            ->where('charge_date', '<', self::next($filters['to']))
            ->groupBy('charge_date')
            ->selectRaw('charge_date, COUNT(*) as rooms, SUM(total_amount) as revenue')
            ->get()
            ->keyBy(fn ($row) => substr((string) $row->charge_date, 0, 10));

        while ($day <= $last) {
            $key = $day->toDateString();
            $night = $nights->get($key);

            $sold = (int) ($night->rooms ?? 0);
            $revenue = round((float) ($night->revenue ?? 0), 2);

            $rows->push((object) [
                'date' => $day->format('d M Y'),
                'weekday' => $day->format('D'),
                'available' => $sellable,
                'sold' => $sold,
                'free' => max(0, $sellable - $sold),
                'occupancy' => $sellable > 0 ? round($sold / $sellable * 100, 1) : 0.0,
                'revenue' => $revenue,
                // ADR is per room sold; RevPAR is per room available. Confusing
                // the two is the most common mistake in hotel reporting, and it
                // flatters a quiet night badly.
                'adr' => $sold > 0 ? round($revenue / $sold, 2) : 0.0,
                'revpar' => $sellable > 0 ? round($revenue / $sellable, 2) : 0.0,
            ]);

            $day = $day->addDay();
        }

        $soldTotal = (int) $rows->sum('sold');
        $revenueTotal = round((float) $rows->sum('revenue'), 2);
        $availableTotal = (int) $rows->sum('available');

        return [
            'columns' => [
                'date' => ['label' => 'Night'],
                'weekday' => ['label' => 'Day'],
                'available' => ['label' => 'Rooms', 'num' => true],
                'sold' => ['label' => 'Sold', 'num' => true],
                'free' => ['label' => 'Free', 'num' => true],
                'occupancy' => ['label' => 'Occupancy %', 'num' => true],
                'revenue' => ['label' => 'Room revenue', 'num' => true, 'money' => true],
                'adr' => ['label' => 'ADR', 'num' => true, 'money' => true],
                'revpar' => ['label' => 'RevPAR', 'num' => true, 'money' => true],
            ],
            'rows' => $rows,
            'totals' => ['sold' => $soldTotal, 'revenue' => $revenueTotal],
            'summary' => [
                ['label' => 'Room nights sold', 'value' => $soldTotal, 'icon' => 'home'],
                [
                    'label' => 'Occupancy',
                    'value' => ($availableTotal > 0 ? round($soldTotal / $availableTotal * 100, 1) : 0) . '%',
                    'icon' => 'trending-up',
                    'tone' => 'info',
                ],
                [
                    'label' => 'ADR',
                    'value' => self::money($soldTotal > 0 ? $revenueTotal / $soldTotal : 0),
                    'icon' => 'chart',
                ],
                ['label' => 'Room revenue', 'value' => self::money($revenueTotal), 'icon' => 'wallet', 'tone' => 'success'],
            ],
            'note' => 'Rooms sold is counted from the room nights actually posted to folios, '
                . 'so it agrees with the bills rather than with the calendar.',
        ];
    }

    private static function reportRevenue(int $branchId, array $filters): array
    {
        $next = self::next($filters['to']);

        $folio = DB::table('folio_charges')
            ->where('branch_id', $branchId)
            ->where('charge_date', '>=', $filters['from'])
            ->where('charge_date', '<', $next)
            ->groupBy('charge_date', 'charge_type')
            ->selectRaw('charge_date, charge_type, SUM(total_amount) as total, SUM(tax_amount) as tax')
            ->get();

        $pos = DB::table('pos_orders')
            ->where('branch_id', $branchId)
            ->where('status', '!=', 'cancelled')
            ->where('opened_at', '>=', $filters['from'] . ' 00:00:00')
            ->where('opened_at', '<', $next . ' 00:00:00')
            ->selectRaw('DATE(opened_at) as day, SUM(net_amount) as total, SUM(tax_total) as tax')
            ->groupBy('day')
            ->get()
            ->keyBy(fn ($row) => substr((string) $row->day, 0, 10));

        $days = [];

        foreach ($folio as $row) {
            $key = substr((string) $row->charge_date, 0, 10);

            $days[$key] ??= ['room' => 0.0, 'service' => 0.0, 'misc' => 0.0, 'discount' => 0.0, 'tax' => 0.0, 'pos' => 0.0];

            $bucket = in_array($row->charge_type, ['room', 'service', 'misc', 'discount'], true)
                ? $row->charge_type
                : 'misc';

            $days[$key][$bucket] = round($days[$key][$bucket] + (float) $row->total, 2);
            $days[$key]['tax'] = round($days[$key]['tax'] + (float) $row->tax, 2);
        }

        foreach ($pos as $key => $row) {
            $days[$key] ??= ['room' => 0.0, 'service' => 0.0, 'misc' => 0.0, 'discount' => 0.0, 'tax' => 0.0, 'pos' => 0.0];
            $days[$key]['pos'] = round((float) $row->total, 2);
            $days[$key]['tax'] = round($days[$key]['tax'] + (float) $row->tax, 2);
        }

        ksort($days);

        $rows = collect($days)->map(fn (array $day, string $key) => (object) [
            'date' => CarbonImmutable::parse($key)->format('d M Y'),
            'room' => $day['room'],
            'service' => $day['service'],
            'misc' => $day['misc'],
            'pos' => $day['pos'],
            'discount' => $day['discount'],
            'tax' => $day['tax'],
            'total' => round($day['room'] + $day['service'] + $day['misc'] + $day['pos'] - $day['discount'], 2),
        ])->values();

        return [
            'columns' => [
                'date' => ['label' => 'Day'],
                'room' => ['label' => 'Rooms', 'num' => true, 'money' => true],
                'service' => ['label' => 'Services', 'num' => true, 'money' => true],
                'misc' => ['label' => 'Sundry', 'num' => true, 'money' => true],
                'pos' => ['label' => 'Food & bar', 'num' => true, 'money' => true],
                'discount' => ['label' => 'Discount', 'num' => true, 'money' => true],
                'tax' => ['label' => 'Tax in it', 'num' => true, 'money' => true],
                'total' => ['label' => 'Total', 'num' => true, 'money' => true],
            ],
            'rows' => $rows,
            'totals' => [
                'room' => $rows->sum('room'),
                'service' => $rows->sum('service'),
                'misc' => $rows->sum('misc'),
                'pos' => $rows->sum('pos'),
                'discount' => $rows->sum('discount'),
                'tax' => $rows->sum('tax'),
                'total' => $rows->sum('total'),
            ],
            'summary' => [
                ['label' => 'Rooms', 'value' => self::money($rows->sum('room')), 'icon' => 'home'],
                ['label' => 'Food & bar', 'value' => self::money($rows->sum('pos')), 'icon' => 'bag'],
                ['label' => 'Services & sundry', 'value' => self::money($rows->sum('service') + $rows->sum('misc')), 'icon' => 'package'],
                ['label' => 'Total', 'value' => self::money($rows->sum('total')), 'icon' => 'wallet', 'tone' => 'success'],
            ],
            'note' => 'Tax is shown as part of each figure, not added to it — the totals are what the guest paid.',
        ];
    }

    private static function reportTaxSummary(int $branchId, array $filters): array
    {
        $next = self::next($filters['to']);

        $folio = DB::table('folio_charges')
            ->where('branch_id', $branchId)
            ->where('charge_date', '>=', $filters['from'])
            ->where('charge_date', '<', $next)
            ->groupBy('tax_percent')
            ->selectRaw('tax_percent, COUNT(*) as lines, SUM(amount) as taxable, SUM(tax_amount) as tax')
            ->get();

        $pos = DB::table('pos_order_items as oi')
            ->join('pos_orders as o', 'o.id', '=', 'oi.pos_order_id')
            ->where('o.branch_id', $branchId)
            ->where('o.status', '!=', 'cancelled')
            ->where('o.opened_at', '>=', $filters['from'] . ' 00:00:00')
            ->where('o.opened_at', '<', $next . ' 00:00:00')
            ->groupBy('oi.tax_percent')
            ->selectRaw('oi.tax_percent, COUNT(*) as lines, SUM(oi.amount) as taxable, SUM(oi.tax_amount) as tax')
            ->get();

        $slabs = [];

        foreach ([$folio, $pos] as $source) {
            foreach ($source as $row) {
                $key = (string) round((float) $row->tax_percent, 2);

                $slabs[$key] ??= ['lines' => 0, 'taxable' => 0.0, 'tax' => 0.0];
                $slabs[$key]['lines'] += (int) $row->lines;
                $slabs[$key]['taxable'] = round($slabs[$key]['taxable'] + (float) $row->taxable, 2);
                $slabs[$key]['tax'] = round($slabs[$key]['tax'] + (float) $row->tax, 2);
            }
        }

        ksort($slabs, SORT_NUMERIC);

        $rows = collect($slabs)->map(fn (array $slab, string $percent) => (object) [
            'percent' => (float) $percent > 0
                ? rtrim(rtrim(number_format((float) $percent, 2), '0'), '.') . '%'
                : 'No tax',
            'lines' => $slab['lines'],
            'taxable' => $slab['taxable'],
            'tax' => $slab['tax'],
            // CGST and SGST are half each for a sale inside the state, which is
            // every hotel sale to a guest standing at the desk. An inter-state
            // sale is IGST and is not split — a hotel that needs that column
            // is a hotel that needs a tax consultant, not a report.
            'cgst' => round($slab['tax'] / 2, 2),
            'sgst' => round($slab['tax'] - round($slab['tax'] / 2, 2), 2),
            'total' => round($slab['taxable'] + $slab['tax'], 2),
        ])->values();

        return [
            'columns' => [
                'percent' => ['label' => 'Rate'],
                'lines' => ['label' => 'Lines', 'num' => true],
                'taxable' => ['label' => 'Taxable value', 'num' => true, 'money' => true],
                'cgst' => ['label' => 'CGST', 'num' => true, 'money' => true],
                'sgst' => ['label' => 'SGST', 'num' => true, 'money' => true],
                'tax' => ['label' => 'Total tax', 'num' => true, 'money' => true],
                'total' => ['label' => 'With tax', 'num' => true, 'money' => true],
            ],
            'rows' => $rows,
            'totals' => [
                'lines' => $rows->sum('lines'),
                'taxable' => $rows->sum('taxable'),
                'cgst' => $rows->sum('cgst'),
                'sgst' => $rows->sum('sgst'),
                'tax' => $rows->sum('tax'),
                'total' => $rows->sum('total'),
            ],
            'summary' => [
                ['label' => 'Taxable value', 'value' => self::money($rows->sum('taxable')), 'icon' => 'file'],
                ['label' => 'Tax collected', 'value' => self::money($rows->sum('tax')), 'icon' => 'shield', 'tone' => 'warning'],
                ['label' => 'Untaxed value', 'value' => self::money($rows->firstWhere('percent', 'No tax')?->taxable ?? 0), 'icon' => 'info'],
            ],
            'note' => 'Folio charges and POS lines together. Lines at "No tax" are exactly that — this system '
                . 'never adds tax to anything unless somebody picked a tax on the screen.',
        ];
    }

    private static function reportCollections(int $branchId, array $filters): array
    {
        $next = self::next($filters['to']);

        $desk = DB::table('settlements as s')
            ->leftJoin('pay_mode as pm', 'pm.id', '=', 's.pay_mode_id')
            ->where('s.branch_id', $branchId)
            ->where('s.settle_date', '>=', $filters['from'])
            ->where('s.settle_date', '<', $next)
            ->when($filters['pay_mode'] ?? null, fn ($q, $id) => $q->where('s.pay_mode_id', $id))
            ->groupBy('pm.name')
            ->selectRaw("COALESCE(pm.name, 'Not recorded') as mode, COUNT(*) as count, SUM(s.amount) as total")
            ->get();

        $till = DB::table('pos_payments as p')
            ->leftJoin('pay_mode as pm', 'pm.id', '=', 'p.pay_mode_id')
            ->where('p.branch_id', $branchId)
            ->where('p.paid_at', '>=', $filters['from'] . ' 00:00:00')
            ->where('p.paid_at', '<', $next . ' 00:00:00')
            ->when($filters['pay_mode'] ?? null, fn ($q, $id) => $q->where('p.pay_mode_id', $id))
            ->groupBy('pm.name')
            ->selectRaw("COALESCE(pm.name, 'Not recorded') as mode, COUNT(*) as count, SUM(p.amount) as total")
            ->get();

        $modes = [];

        foreach ($desk as $row) {
            $modes[$row->mode] ??= ['desk' => 0.0, 'till' => 0.0, 'count' => 0];
            $modes[$row->mode]['desk'] = round((float) $row->total, 2);
            $modes[$row->mode]['count'] += (int) $row->count;
        }

        foreach ($till as $row) {
            $modes[$row->mode] ??= ['desk' => 0.0, 'till' => 0.0, 'count' => 0];
            $modes[$row->mode]['till'] = round((float) $row->total, 2);
            $modes[$row->mode]['count'] += (int) $row->count;
        }

        ksort($modes);

        $rows = collect($modes)->map(fn (array $mode, string $name) => (object) [
            'mode' => $name,
            'count' => $mode['count'],
            'desk' => $mode['desk'],
            'till' => $mode['till'],
            'total' => round($mode['desk'] + $mode['till'], 2),
        ])->values();

        return [
            'columns' => [
                'mode' => ['label' => 'Pay mode'],
                'count' => ['label' => 'Payments', 'num' => true],
                'desk' => ['label' => 'Front desk', 'num' => true, 'money' => true],
                'till' => ['label' => 'Point of sale', 'num' => true, 'money' => true],
                'total' => ['label' => 'Total', 'num' => true, 'money' => true],
            ],
            'rows' => $rows,
            'totals' => [
                'count' => $rows->sum('count'),
                'desk' => $rows->sum('desk'),
                'till' => $rows->sum('till'),
                'total' => $rows->sum('total'),
            ],
            'summary' => [
                ['label' => 'Front desk', 'value' => self::money($rows->sum('desk')), 'icon' => 'desktop'],
                ['label' => 'Point of sale', 'value' => self::money($rows->sum('till')), 'icon' => 'bag'],
                ['label' => 'Taken in all', 'value' => self::money($rows->sum('total')), 'icon' => 'wallet', 'tone' => 'success'],
            ],
        ];
    }

    private static function reportOutstanding(int $branchId, array $filters): array
    {
        $rows = DB::table('bills as b')
            ->leftJoin('check_ins as ci', 'ci.id', '=', 'b.check_in_id')
            ->leftJoin('rooms as ro', 'ro.id', '=', 'ci.room_id')
            ->where('b.branch_id', $branchId)
            ->where('b.status', '!=', 'cancelled')
            ->where('b.balance_amount', '>', 0)
            ->where('b.bill_date', '>=', $filters['from'])
            ->where('b.bill_date', '<', self::next($filters['to']))
            ->orderBy('b.bill_date')
            ->get([
                'b.bill_no', 'b.group_no', 'b.bill_date', 'b.net_amount', 'b.paid_amount', 'b.balance_amount',
                'ci.folio_no', 'ci.guest_name', 'ci.mobile', 'ro.room_no',
            ])
            ->map(fn ($row) => (object) [
                'bill_date' => self::day($row->bill_date),
                'bill_no' => $row->bill_no,
                'group_no' => $row->group_no ?: '—',
                'guest' => $row->guest_name ?: '—',
                'room_no' => $row->room_no ?: '—',
                'mobile' => $row->mobile ?: '—',
                'age' => CarbonImmutable::parse(substr((string) $row->bill_date, 0, 10))->diffInDays(now()) . ' days',
                'amount' => (float) $row->net_amount,
                'paid' => (float) $row->paid_amount,
                'due' => (float) $row->balance_amount,
            ]);

        return [
            'columns' => [
                'bill_date' => ['label' => 'Billed'],
                'bill_no' => ['label' => 'Bill'],
                'group_no' => ['label' => 'Group'],
                'guest' => ['label' => 'Guest'],
                'room_no' => ['label' => 'Room'],
                'mobile' => ['label' => 'Mobile'],
                'age' => ['label' => 'Outstanding for'],
                'amount' => ['label' => 'Bill', 'num' => true, 'money' => true],
                'paid' => ['label' => 'Paid', 'num' => true, 'money' => true],
                'due' => ['label' => 'Due', 'num' => true, 'money' => true],
            ],
            'rows' => $rows,
            'totals' => ['amount' => $rows->sum('amount'), 'paid' => $rows->sum('paid'), 'due' => $rows->sum('due')],
            'summary' => [
                ['label' => 'Bills unpaid', 'value' => $rows->count(), 'icon' => 'file', 'tone' => 'warning'],
                ['label' => 'Billed', 'value' => self::money($rows->sum('amount')), 'icon' => 'chart'],
                ['label' => 'Still owed', 'value' => self::money($rows->sum('due')), 'icon' => 'alert', 'tone' => 'danger'],
            ],
        ];
    }

    private static function reportPosSales(int $branchId, array $filters): array
    {
        $rows = DB::table('pos_order_items as oi')
            ->join('pos_orders as o', 'o.id', '=', 'oi.pos_order_id')
            ->leftJoin('outlets as ot', 'ot.id', '=', 'o.outlet_id')
            ->leftJoin('pos_menu_items as mi', 'mi.id', '=', 'oi.pos_menu_item_id')
            ->leftJoin('pos_menu_categories as mc', 'mc.id', '=', 'mi.pos_menu_category_id')
            ->where('o.branch_id', $branchId)
            ->where('o.status', '!=', 'cancelled')
            ->where('o.opened_at', '>=', $filters['from'] . ' 00:00:00')
            ->where('o.opened_at', '<', self::next($filters['to']) . ' 00:00:00')
            ->when($filters['outlet'] ?? null, fn ($q, $id) => $q->where('o.outlet_id', $id))
            ->groupBy('oi.item_name', 'ot.name', 'mc.name')
            ->selectRaw(
                "oi.item_name as item, COALESCE(ot.name, '—') as outlet, COALESCE(mc.name, '—') as category, "
                . 'SUM(oi.qty) as qty, SUM(oi.amount) as amount, SUM(oi.tax_amount) as tax, '
                . 'SUM(oi.total_amount) as total'
            )
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => (object) [
                'item' => $row->item,
                'category' => $row->category,
                'outlet' => $row->outlet,
                'qty' => round((float) $row->qty, 2),
                'amount' => round((float) $row->amount, 2),
                'tax' => round((float) $row->tax, 2),
                'total' => round((float) $row->total, 2),
            ]);

        return [
            'columns' => [
                'item' => ['label' => 'Item'],
                'category' => ['label' => 'Category'],
                'outlet' => ['label' => 'Outlet'],
                'qty' => ['label' => 'Sold', 'num' => true],
                'amount' => ['label' => 'Value', 'num' => true, 'money' => true],
                'tax' => ['label' => 'Tax', 'num' => true, 'money' => true],
                'total' => ['label' => 'Total', 'num' => true, 'money' => true],
            ],
            'rows' => $rows,
            'totals' => ['qty' => $rows->sum('qty'), 'amount' => $rows->sum('amount'), 'tax' => $rows->sum('tax'), 'total' => $rows->sum('total')],
            'summary' => [
                ['label' => 'Items sold', 'value' => number_format($rows->sum('qty'), 0), 'icon' => 'bag'],
                ['label' => 'Best seller', 'value' => $rows->first()->item ?? '—', 'icon' => 'star'],
                ['label' => 'Value', 'value' => self::money($rows->sum('total')), 'icon' => 'wallet', 'tone' => 'success'],
            ],
            'note' => 'Cancelled orders are left out. Comped items count as sold with a value of nothing, '
                . 'which is what makes a No Charge report possible at all.',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Operations
    |--------------------------------------------------------------------------
    */

    private static function reportHousekeeping(int $branchId, array $filters): array
    {
        $rows = DB::table('housekeeping_logs as hl')
            ->leftJoin('rooms as ro', 'ro.id', '=', 'hl.room_id')
            ->leftJoin('users as u', 'u.user_id', '=', 'hl.created_by')
            ->leftJoin('users as hk', 'hk.user_id', '=', 'hl.housekeeper_id')
            ->where('hl.branch_id', $branchId)
            ->where('hl.created_at', '>=', $filters['from'] . ' 00:00:00')
            ->where('hl.created_at', '<', self::next($filters['to']) . ' 00:00:00')
            ->orderByDesc('hl.id')
            ->get([
                'hl.created_at', 'hl.from_status', 'hl.to_status', 'hl.remark',
                'ro.room_no', 'u.name as by', 'hk.name as housekeeper',
            ])
            ->map(fn ($row) => (object) [
                'when' => CarbonImmutable::parse($row->created_at)->format('d M Y, h:i A'),
                'room_no' => $row->room_no ?: '—',
                'from' => \App\Models\Master\Room::HOUSEKEEPING[$row->from_status] ?? ($row->from_status ?: '—'),
                'to' => \App\Models\Master\Room::HOUSEKEEPING[$row->to_status] ?? $row->to_status,
                'housekeeper' => $row->housekeeper ?: '—',
                'by' => $row->by ?: '—',
                'remark' => $row->remark ?: '—',
            ]);

        return [
            'columns' => [
                'when' => ['label' => 'When'],
                'room_no' => ['label' => 'Room'],
                'from' => ['label' => 'From'],
                'to' => ['label' => 'To'],
                'housekeeper' => ['label' => 'Housekeeper'],
                'by' => ['label' => 'Changed by'],
                'remark' => ['label' => 'Remark'],
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Changes', 'value' => $rows->count(), 'icon' => 'refresh'],
                ['label' => 'Rooms made ready', 'value' => $rows->whereIn('to', ['Cleaned', 'Inspect'])->count(), 'icon' => 'check-circle', 'tone' => 'success'],
                ['label' => 'Marked dirty', 'value' => $rows->where('to', 'Dirty')->count(), 'icon' => 'alert', 'tone' => 'warning'],
            ],
            'note' => 'This is the answer to "the guest says the room was never cleaned" — every change, '
                . 'with the person who made it.',
        ];
    }

    private static function reportWorkOrders(int $branchId, array $filters): array
    {
        /*
         * Explicit columns, and an alias on every one that both tables have.
         * A `SELECT *` across a join silently lets the second table's `status`
         * overwrite the first's — here that would have shown every job as
         * "1", which is the room's status, and nobody would have known why.
         */
        $rows = DB::table('work_orders as w')
            ->leftJoin('rooms as ro', 'ro.id', '=', 'w.room_id')
            ->leftJoin('users as u', 'u.user_id', '=', 'w.assigned_to')
            ->where('w.branch_id', $branchId)
            // A job card has no date of its own — when it was raised is when
            // the row was written.
            ->where('w.created_at', '>=', $filters['from'] . ' 00:00:00')
            ->where('w.created_at', '<', self::next($filters['to']) . ' 00:00:00')
            ->orderByDesc('w.id')
            ->get([
                'w.order_no', 'w.category', 'w.title', 'w.priority', 'w.status',
                'w.due_date', 'w.completed_on', 'w.created_at',
                'ro.room_no as room_no', 'u.name as assigned',
            ])
            ->map(fn ($row) => (object) [
                'order_date' => self::day($row->created_at),
                'order_no' => $row->order_no ?: '—',
                'room_no' => $row->room_no ?: '—',
                'category' => config('pms.work_order_categories.' . ($row->category ?: ''), $row->category ?: '—'),
                'job' => $row->title ?: '—',
                'assigned' => $row->assigned ?: 'nobody',
                'priority' => ucfirst((string) $row->priority),
                'status' => ucfirst(str_replace('_', ' ', (string) $row->status)),
                'due_date' => $row->due_date ? self::day($row->due_date) : '—',
                'closed_on' => $row->completed_on ? self::day($row->completed_on) : '—',
            ]);

        return [
            'columns' => [
                'order_date' => ['label' => 'Raised'],
                'order_no' => ['label' => 'Order'],
                'room_no' => ['label' => 'Room'],
                'category' => ['label' => 'Kind'],
                'job' => ['label' => 'Job'],
                'assigned' => ['label' => 'With'],
                'priority' => ['label' => 'Priority'],
                'status' => ['label' => 'Status'],
                'due_date' => ['label' => 'Due'],
                'closed_on' => ['label' => 'Closed'],
            ],
            'rows' => $rows,
            'summary' => [
                ['label' => 'Raised', 'value' => $rows->count(), 'icon' => 'cog'],
                ['label' => 'Still open', 'value' => $rows->where('closed_on', '—')->count(), 'icon' => 'clock', 'tone' => 'warning'],
                ['label' => 'Closed', 'value' => $rows->where('closed_on', '!=', '—')->count(), 'icon' => 'check-circle', 'tone' => 'success'],
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Pool, Hall & Car
    |--------------------------------------------------------------------------
    */

    private static function reportPool(int $branchId, array $filters): array
    {
        $rows = DB::table('pool_bookings as pb')
            ->leftJoin('pools as p', 'p.id', '=', 'pb.pool_id')
            ->where('pb.branch_id', $branchId)
            ->where('pb.booking_date', '>=', $filters['from'])
            ->where('pb.booking_date', '<', self::next($filters['to']))
            ->orderBy('pb.booking_date')
            // Explicit columns: `pool_bookings` and `pools` both have `status`,
            // and a SELECT * would quietly show every booking as the pool's
            // status instead of its own.
            ->get([
                'pb.booking_no', 'pb.booking_date', 'pb.from_time', 'pb.to_time',
                'pb.guest_name', 'pb.room_no', 'pb.adults', 'pb.children',
                'pb.status as status', 'pb.total_amount',
                'p.name as pool_name',
            ])
            ->map(fn ($row) => (object) [
                'date' => self::day($row->booking_date),
                'booking_no' => $row->booking_no,
                'pool' => $row->pool_name ?: '—',
                'guest' => $row->guest_name ?: 'Walk-in',
                'room_no' => $row->room_no ?: '—',
                'slot' => substr((string) $row->from_time, 0, 5) . ' – ' . substr((string) $row->to_time, 0, 5),
                'pax' => (int) $row->adults + (int) $row->children,
                'status' => ucfirst(str_replace('_', ' ', (string) $row->status)),
                'amount' => (float) $row->total_amount,
            ]);

        return [
            'columns' => [
                'date' => ['label' => 'Day'],
                'booking_no' => ['label' => 'Booking'],
                'pool' => ['label' => 'Pool'],
                'guest' => ['label' => 'Guest'],
                'room_no' => ['label' => 'Room'],
                'slot' => ['label' => 'Time'],
                'pax' => ['label' => 'People', 'num' => true],
                'status' => ['label' => 'Status'],
                'amount' => ['label' => 'Charged', 'num' => true, 'money' => true],
            ],
            'rows' => $rows,
            'totals' => ['pax' => $rows->sum('pax'), 'amount' => $rows->sum('amount')],
            'summary' => [
                ['label' => 'Bookings', 'value' => $rows->count(), 'icon' => 'globe'],
                ['label' => 'Swimmers', 'value' => $rows->sum('pax'), 'icon' => 'users'],
                ['label' => 'Charged', 'value' => self::money($rows->sum('amount')), 'icon' => 'wallet', 'tone' => 'success'],
            ],
        ];
    }

    private static function reportHall(int $branchId, array $filters): array
    {
        $rows = DB::table('hall_bookings as hb')
            ->leftJoin('halls as h', 'h.id', '=', 'hb.hall_id')
            ->where('hb.branch_id', $branchId)
            ->where('hb.to_date', '>=', $filters['from'])
            ->where('hb.from_date', '<', self::next($filters['to']))
            ->orderBy('hb.from_date')
            ->get([
                'hb.booking_no', 'hb.from_date', 'hb.guest_name', 'hb.event_type', 'hb.pax',
                'hb.status as status', 'hb.total_amount', 'hb.advance',
                'h.name as hall_name',
            ])
            ->map(fn ($row) => (object) [
                'from_date' => self::day($row->from_date),
                'booking_no' => $row->booking_no,
                'hall' => $row->hall_name ?: '—',
                'guest' => $row->guest_name,
                'event' => $row->event_type ?: '—',
                'pax' => (int) $row->pax,
                'status' => ucfirst(str_replace('_', ' ', (string) $row->status)),
                'amount' => (float) $row->total_amount,
                'advance' => (float) $row->advance,
                'due' => round((float) $row->total_amount - (float) $row->advance, 2),
            ]);

        return [
            'columns' => [
                'from_date' => ['label' => 'From'],
                'booking_no' => ['label' => 'Booking'],
                'hall' => ['label' => 'Hall'],
                'guest' => ['label' => 'Host'],
                'event' => ['label' => 'Event'],
                'pax' => ['label' => 'Pax', 'num' => true],
                'status' => ['label' => 'Status'],
                'amount' => ['label' => 'Total', 'num' => true, 'money' => true],
                'advance' => ['label' => 'Advance', 'num' => true, 'money' => true],
                'due' => ['label' => 'Due', 'num' => true, 'money' => true],
            ],
            'rows' => $rows,
            'totals' => ['amount' => $rows->sum('amount'), 'advance' => $rows->sum('advance'), 'due' => $rows->sum('due')],
            'summary' => [
                ['label' => 'Events', 'value' => $rows->count(), 'icon' => 'grid'],
                ['label' => 'Booked value', 'value' => self::money($rows->sum('amount')), 'icon' => 'chart'],
                ['label' => 'Advance taken', 'value' => self::money($rows->sum('advance')), 'icon' => 'wallet'],
                ['label' => 'Still due', 'value' => self::money($rows->sum('due')), 'icon' => 'alert', 'tone' => 'warning'],
            ],
        ];
    }

    private static function reportParking(int $branchId, array $filters): array
    {
        $rows = DB::table('parking_records as pr')
            ->leftJoin('parking_slots as ps', 'ps.id', '=', 'pr.parking_slot_id')
            ->where('pr.branch_id', $branchId)
            ->where('pr.in_at', '>=', $filters['from'] . ' 00:00:00')
            ->where('pr.in_at', '<', self::next($filters['to']) . ' 00:00:00')
            ->orderByDesc('pr.in_at')
            ->get([
                'pr.ticket_no', 'pr.vehicle_no', 'pr.guest_name', 'pr.room_no',
                'pr.in_at', 'pr.out_at', 'pr.hours', 'pr.is_chargeable', 'pr.total_amount',
                'ps.code as bay',
            ])
            ->map(fn ($row) => (object) [
                'ticket_no' => $row->ticket_no,
                'vehicle_no' => $row->vehicle_no,
                'guest' => $row->guest_name ?: 'Visitor',
                'room_no' => $row->room_no ?: '—',
                'slot' => $row->bay ?: '—',
                'in_at' => CarbonImmutable::parse($row->in_at)->format('d M, h:i A'),
                'out_at' => $row->out_at ? CarbonImmutable::parse($row->out_at)->format('d M, h:i A') : 'still here',
                'hours' => round((float) $row->hours, 2),
                'charged' => (int) $row->is_chargeable === 1 ? 'Yes' : 'Free',
                'amount' => (float) $row->total_amount,
            ]);

        return [
            'columns' => [
                'ticket_no' => ['label' => 'Ticket'],
                'vehicle_no' => ['label' => 'Vehicle'],
                'guest' => ['label' => 'Whose'],
                'room_no' => ['label' => 'Room'],
                'slot' => ['label' => 'Bay'],
                'in_at' => ['label' => 'In'],
                'out_at' => ['label' => 'Out'],
                'hours' => ['label' => 'Hours', 'num' => true],
                'charged' => ['label' => 'Charged'],
                'amount' => ['label' => 'Amount', 'num' => true, 'money' => true],
            ],
            'rows' => $rows,
            'totals' => ['hours' => $rows->sum('hours'), 'amount' => $rows->sum('amount')],
            'summary' => [
                ['label' => 'Vehicles', 'value' => $rows->count(), 'icon' => 'package'],
                ['label' => 'Parked free', 'value' => $rows->where('charged', 'Free')->count(), 'icon' => 'check-circle', 'tone' => 'success'],
                ['label' => 'Charged', 'value' => self::money($rows->sum('amount')), 'icon' => 'wallet'],
            ],
            'note' => 'Parking is free by default in this system — a ticket only costs the guest anything '
                . 'when somebody ticked the box on the way out.',
        ];
    }

    private static function reportTrips(int $branchId, array $filters): array
    {
        $rows = DB::table('guest_trips as t')
            ->leftJoin('vehicles as v', 'v.id', '=', 't.vehicle_id')
            ->where('t.branch_id', $branchId)
            ->where('t.trip_date', '>=', $filters['from'])
            ->where('t.trip_date', '<', self::next($filters['to']))
            ->orderBy('t.trip_date')
            ->orderBy('t.trip_time')
            ->get([
                't.trip_no', 't.trip_type', 't.trip_date', 't.trip_time', 't.guest_name',
                't.from_place', 't.to_place', 't.driver_name', 't.km',
                't.status as status', 't.total_amount',
                'v.name as vehicle_name',
            ])
            ->map(fn ($row) => (object) [
                'date' => self::day($row->trip_date),
                'time' => substr((string) $row->trip_time, 0, 5),
                'trip_no' => $row->trip_no,
                'kind' => ucfirst((string) $row->trip_type),
                'guest' => $row->guest_name,
                'route' => $row->from_place . ' → ' . $row->to_place,
                'vehicle' => $row->vehicle_name ?: '—',
                'driver' => $row->driver_name ?: '—',
                'km' => round((float) $row->km, 2),
                'status' => ucfirst((string) $row->status),
                'amount' => (float) $row->total_amount,
            ]);

        return [
            'columns' => [
                'date' => ['label' => 'Day'],
                'time' => ['label' => 'Time'],
                'trip_no' => ['label' => 'Trip'],
                'kind' => ['label' => 'Way'],
                'guest' => ['label' => 'Guest'],
                'route' => ['label' => 'Route'],
                'vehicle' => ['label' => 'Car'],
                'driver' => ['label' => 'Driver'],
                'km' => ['label' => 'Km', 'num' => true],
                'status' => ['label' => 'Status'],
                'amount' => ['label' => 'Charged', 'num' => true, 'money' => true],
            ],
            'rows' => $rows,
            'totals' => ['km' => $rows->sum('km'), 'amount' => $rows->sum('amount')],
            'summary' => [
                ['label' => 'Trips', 'value' => $rows->count(), 'icon' => 'arrow-right'],
                ['label' => 'Pickups', 'value' => $rows->where('kind', 'Pickup')->count(), 'icon' => 'arrow-down'],
                ['label' => 'Kilometres', 'value' => number_format($rows->sum('km'), 1), 'icon' => 'activity'],
                ['label' => 'Charged', 'value' => self::money($rows->sum('amount')), 'icon' => 'wallet'],
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /** The day after — every "on or before" test in this file is written this way. */
    private static function next(string $date): string
    {
        return rescue(
            fn () => CarbonImmutable::parse($date)->addDay()->toDateString(),
            $date,
            false
        );
    }

    /** A date column, however it comes back, as "16 Sep 2026". */
    private static function day($value): string
    {
        return $value
            ? CarbonImmutable::parse(substr((string) $value, 0, 10))->format('d M Y')
            : '—';
    }

    private static function money(float|int|null $value): string
    {
        return '₹ ' . number_format((float) $value, 2);
    }
}
