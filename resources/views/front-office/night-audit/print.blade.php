@extends('layouts.print')

@section('title', 'Night Audit Report')

@php
    /*
        The manager's report as a sheet of paper.

        A night audit ends with something an owner can be handed at breakfast,
        so this is deliberately plain: no cards, no colour, the figures in the
        order they get read out — house, revenue, collection, movement.
    */
    $money = fn ($n) => number_format((float) ($n ?? 0), 2);
    $num = fn ($n) => number_format((int) ($n ?? 0));

    $rooms = $figures['rooms'] ?? [];
    $move = $figures['movement'] ?? [];
    $rev = $figures['revenue'] ?? [];
    $coll = $figures['collection'] ?? [];
    $perf = $figures['performance'] ?? [];
@endphp

@section('content')
    <div class="pr-head">
        <div>
            <p class="pr-hotel-name">{{ $branch?->legal_name ?: $branch?->branch_name }}</p>

            @if ($branch?->address)
                <p class="pr-hotel-line">{{ $branch->address }}{{ $branch->pin_code ? ' - ' . $branch->pin_code : '' }}</p>
            @endif

            @if ($branch?->mobile_number)
                <p class="pr-hotel-line">Mobile No. :- {{ $branch->mobile_number }}</p>
            @endif

            @if ($branch?->gst_no)
                <p class="pr-hotel-line">GSTNo: {{ $branch->gst_no }}</p>
            @endif
        </div>
    </div>

    <h1 class="pr-title">Night Audit Report</h1>

    <table class="pr-table pr-grid">
        <tr>
            <td class="is-key">Business Date</td>
            <td>{{ $day->business_date->format('d M Y') }} ({{ $day->business_date->format('l') }})</td>
            <td class="is-key">Audited On</td>
            <td>{{ $day->closed_at?->format('d/m/Y h:i A') ?? '—' }}</td>
        </tr>
        <tr>
            <td class="is-key">Audited By</td>
            <td>{{ $day->closer?->name ?? '—' }}</td>
            <td class="is-key">Room Nights Posted</td>
            <td>{{ $num($day->nights_posted) }}</td>
        </tr>
        <tr>
            <td class="is-key">Bookings Marked No Show</td>
            <td>{{ $num($day->no_shows) }}</td>
            <td class="is-key">Rooms Sold</td>
            <td>{{ $num($day->rooms_sold) }}</td>
        </tr>
        @if ($day->note)
            <tr>
                <td class="is-key">Note</td>
                <td colspan="3">{{ $day->note }}</td>
            </tr>
        @endif
    </table>

    <p class="pr-block-label">Occupancy</p>

    <table class="pr-table">
        <thead>
            <tr>
                <th>Rooms Available</th>
                <th>Rooms Sold</th>
                <th>Rooms Free</th>
                <th>Occupancy %</th>
                <th>Guests In House</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="is-num">{{ $num($rooms['available'] ?? 0) }}</td>
                <td class="is-num">{{ $num($rooms['sold'] ?? 0) }}</td>
                <td class="is-num">{{ $num($rooms['free'] ?? 0) }}</td>
                <td class="is-num">{{ $rooms['occupancy'] ?? 0 }}%</td>
                <td class="is-num">{{ $num($rooms['guests'] ?? 0) }}</td>
            </tr>
        </tbody>
    </table>

    <p class="pr-block-label">Revenue</p>

    <table class="pr-table">
        <thead>
            <tr>
                <th>Particulars</th>
                <th>Amount</th>
                <th>Tax</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Room Rent</td>
                <td class="is-num">{{ $money($rev['room'] ?? 0) }}</td>
                <td class="is-num">{{ $money($rev['room_tax'] ?? 0) }}</td>
                <td class="is-num">{{ $money(($rev['room'] ?? 0) + ($rev['room_tax'] ?? 0)) }}</td>
            </tr>
            <tr>
                <td>Services and Sundries</td>
                <td class="is-num">{{ $money($rev['other'] ?? 0) }}</td>
                <td class="is-num">{{ $money($rev['other_tax'] ?? 0) }}</td>
                <td class="is-num">{{ $money(($rev['other'] ?? 0) + ($rev['other_tax'] ?? 0)) }}</td>
            </tr>
            <tr>
                <td>Restaurant and Bar ({{ $num($rev['pos_orders'] ?? 0) }} orders)</td>
                <td class="is-num">{{ $money(($rev['pos'] ?? 0) - ($rev['pos_tax'] ?? 0)) }}</td>
                <td class="is-num">{{ $money($rev['pos_tax'] ?? 0) }}</td>
                <td class="is-num">{{ $money($rev['pos'] ?? 0) }}</td>
            </tr>
            <tr>
                <td><b>Total Revenue</b></td>
                <td class="is-num"></td>
                <td class="is-num"></td>
                <td class="is-num"><b>{{ $money($rev['total'] ?? 0) }}</b></td>
            </tr>
        </tbody>
    </table>

    <div class="pr-split">
        <div class="pr-col">
            <p class="pr-block-label">Collection</p>

            <table class="pr-table">
                <tbody>
                    <tr>
                        <td>Settlements Against Bills</td>
                        <td class="is-num">{{ $money($coll['settlements'] ?? 0) }}</td>
                    </tr>
                    <tr>
                        <td>Advances and Deposits (net of refunds)</td>
                        <td class="is-num">{{ $money($coll['deposits'] ?? 0) }}</td>
                    </tr>
                    <tr>
                        <td><b>Total Collected</b></td>
                        <td class="is-num"><b>{{ $money($coll['total'] ?? 0) }}</b></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="pr-col">
            <p class="pr-block-label">Movement</p>

            <table class="pr-table">
                <tbody>
                    <tr><td>Arrivals</td><td class="is-num">{{ $num($move['arrivals'] ?? 0) }}</td></tr>
                    <tr><td>Departures</td><td class="is-num">{{ $num($move['departures'] ?? 0) }}</td></tr>
                    <tr><td>Stays In House</td><td class="is-num">{{ $num($move['in_house'] ?? 0) }}</td></tr>
                    <tr><td>No Shows</td><td class="is-num">{{ $num($move['no_shows'] ?? 0) }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <p class="pr-block-label">Performance</p>

    <table class="pr-table">
        <thead>
            <tr>
                <th>ADR (Average Daily Rate)</th>
                <th>RevPAR (Revenue Per Available Room)</th>
                <th>Average Rate With Tax</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="is-num">{{ $money($perf['adr'] ?? 0) }}</td>
                <td class="is-num">{{ $money($perf['revpar'] ?? 0) }}</td>
                <td class="is-num">{{ $money($perf['arr_with_tax'] ?? 0) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="pr-sign" style="margin-top:12mm">
        <div class="pr-sign-box">Night Auditor</div>
        <div class="pr-sign-box">For {{ $branch?->branch_name }}</div>
    </div>

    <div class="pr-foot">
        <span>Printed on {{ now()->format('d M Y h:i A') }}</span>
        <span>Printed By {{ auth()->user()?->name ?? auth()->user()?->username }}</span>
        <span>Page 1 of 1</span>
    </div>
@endsection
