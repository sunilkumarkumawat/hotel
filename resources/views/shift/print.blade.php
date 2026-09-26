@extends('layouts.print')

@section('title', 'Shift Report ' . $shift->shift_no)

@php
    /*
        The shift close as a sheet of paper, because in most hotels this is
        what gets signed and put in the drawer with the money. Plain on
        purpose: no colour, no cards, the figures in the order they are read
        out — what was taken, what was counted, and the difference, with a
        line for two signatures at the bottom.
    */
    $money = fn ($n) => number_format((float) ($n ?? 0), 2);

    $variance = (float) $shift->variance;
    $balanced = abs($variance) < 0.005;

    $floatUsed = false;
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
        </div>
    </div>

    <h1 class="pr-title">Shift Report</h1>

    <table class="pr-table pr-grid">
        <tr>
            <td class="is-key">Shift No.</td>
            <td>{{ $shift->shift_no }}</td>
            <td class="is-key">Cashier</td>
            <td>{{ $shift->cashier }}</td>
        </tr>
        <tr>
            <td class="is-key">Opened</td>
            <td>{{ $shift->opened_at->format('d/m/Y h:i A') }}</td>
            <td class="is-key">Closed</td>
            <td>{{ $shift->closed_at?->format('d/m/Y h:i A') ?: 'Still open' }}</td>
        </tr>
        <tr>
            <td class="is-key">Shift</td>
            <td>{{ $shift->name ?: '—' }}</td>
            <td class="is-key">Length</td>
            <td>{{ $shift->length }}</td>
        </tr>
        <tr>
            <td class="is-key">Opening Float</td>
            <td>{{ $money($shift->opening_float) }}</td>
            <td class="is-key">Closed By</td>
            <td>{{ $shift->closer?->name ?: '—' }}</td>
        </tr>
    </table>

    <p class="pr-block-label">Collection By Pay Mode</p>

    <table class="pr-table">
        <thead>
            <tr>
                <th>Pay Mode</th>
                <th>Type</th>
                <th>Payments</th>
                <th>Received</th>
                <th>Paid Out</th>
                <th>Expected</th>
                <th>Counted</th>
                <th>Difference</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($figures['modes'] as $mode)
                @php
                    $isCash = $mode['type'] === 'cash';
                    $float = $isCash && ! $floatUsed ? (float) $shift->opening_float : 0.0;
                    $floatUsed = $floatUsed || $isCash;

                    $expected = round($mode['net'] + $float, 2);
                    $counted = (float) data_get($shift->declared, (string) $mode['id'], 0);
                    $off = round($counted - $expected, 2);
                @endphp

                <tr>
                    <td>{{ $mode['name'] }}</td>
                    <td>{{ ucfirst($mode['type']) }}</td>
                    <td class="is-num">{{ $mode['count'] }}</td>
                    <td class="is-num">{{ $money($mode['in']) }}</td>
                    <td class="is-num">{{ $mode['out'] > 0 ? $money($mode['out']) : '-' }}</td>
                    <td class="is-num">{{ $money($expected) }}</td>
                    <td class="is-num">{{ $shift->isOpen() ? '-' : $money($counted) }}</td>
                    <td class="is-num">{{ $shift->isOpen() || abs($off) < 0.005 ? '-' : ($off < 0 ? '(' . $money(abs($off)) . ')' : $money($off)) }}</td>
                </tr>
            @endforeach

            <tr>
                <td colspan="3"><b>Total</b></td>
                <td class="is-num"><b>{{ $money($figures['totals']['in']) }}</b></td>
                <td class="is-num"><b>{{ $money($figures['totals']['out']) }}</b></td>
                <td class="is-num"><b>{{ $money(round((float) $figures['totals']['net'] + (float) $shift->opening_float, 2)) }}</b></td>
                <td class="is-num"></td>
                <td class="is-num"></td>
            </tr>
        </tbody>
    </table>

    <div class="pr-split">
        <div class="pr-col">
            <p class="pr-block-label">Cash Position</p>

            <table class="pr-table">
                <tbody>
                    <tr><td>Opening Float</td><td class="is-num">{{ $money($figures['cash']['float'] ?? $shift->opening_float) }}</td></tr>
                    <tr><td>Cash Received</td><td class="is-num">{{ $money($figures['cash']['in'] ?? 0) }}</td></tr>
                    <tr><td>Cash Paid Out</td><td class="is-num">({{ $money($figures['cash']['out'] ?? 0) }})</td></tr>
                    <tr><td><b>Cash Expected In Drawer</b></td><td class="is-num"><b>{{ $money($shift->cash_expected) }}</b></td></tr>
                    <tr><td><b>Cash Counted</b></td><td class="is-num"><b>{{ $money($shift->cash_counted) }}</b></td></tr>
                    <tr>
                        <td><b>{{ $balanced ? 'Balanced' : ($variance < 0 ? 'Short By' : 'Over By') }}</b></td>
                        <td class="is-num"><b>{{ $balanced ? '-' : $money(abs($variance)) }}</b></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="pr-col">
            <p class="pr-block-label">Where It Came From</p>

            <table class="pr-table">
                <tbody>
                    @foreach ([
                        'rooms' => 'Room Bills',
                        'deposits' => 'Advance Deposits',
                        'pos' => 'Restaurant And Bar',
                        'petty_in' => 'Cash Receipts',
                        'refunds' => 'Deposit Refunds',
                        'petty_out' => 'Cash Paid Out',
                    ] as $key => $label)
                        <tr>
                            <td>{{ $label }}</td>
                            <td class="is-num">
                                {{ in_array($key, ['refunds', 'petty_out'], true) && ($figures['sources'][$key] ?? 0) > 0
                                    ? '(' . $money($figures['sources'][$key]) . ')'
                                    : $money($figures['sources'][$key] ?? 0) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if ($shift->close_note)
        <p class="pr-block-label">Note</p>
        <p class="pr-note">{{ $shift->close_note }}</p>
    @endif

    <p class="pr-block-label">Transactions ({{ count($lines) }})</p>

    <table class="pr-table">
        <thead>
            <tr>
                <th>Time</th>
                <th>Source</th>
                <th>Particulars</th>
                <th>Pay Mode</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($lines as $line)
                <tr>
                    <td>{{ \Carbon\CarbonImmutable::parse($line['at'])->format('d/m h:i A') }}</td>
                    <td>{{ $line['source'] }}</td>
                    <td>{{ $line['particulars'] }}</td>
                    <td>{{ $modes[$line['pay_mode_id']]['name'] ?? 'Not Stated' }}</td>
                    <td class="is-num">
                        {{ $line['direction'] === 'out' ? '(' . $money($line['amount']) . ')' : $money($line['amount']) }}
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">No transactions in this shift.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="pr-sign">
        <div class="pr-sign-box">Cashier</div>
        <div class="pr-sign-box">Received By</div>
    </div>
@endsection
