@extends('layouts.app')

@section('title', $shift->shift_no)

@php
    $money = fn ($n) => '₹' . number_format((float) $n, 2);

    $variance = (float) $shift->variance;
    $balanced = abs($variance) < 0.005;
@endphp

@section('content')
    <x-page-header
        :title="$shift->shift_no"
        :subtitle="$shift->cashier . ' · ' . $shift->opened_at->format('d M Y, h:i A') . ' · ' . $shift->length"
        :crumbs="['Home' => url('/'), 'Shift', 'Shift Reports' => route('shift.reports'), $shift->shift_no]"
    >
        <x-slot:actions>
            <a href="{{ route('shift.reports.print', $shift->id) }}" target="_blank" class="nv-btn nv-btn-outline">
                <x-icon name="file" /> Print
            </a>
        </x-slot:actions>
    </x-page-header>

    {{-- ── Whether it balanced, said first ──────────────────────────────── --}}
    <div class="nv-mt">
        @if ($shift->isOpen())
            <x-alert tone="info" title="This shift is still open">
                The figures below are live and will keep moving until it is closed. Nothing has been
                counted yet.
            </x-alert>
        @elseif ($balanced)
            <x-alert tone="success" title="The drawer balanced">
                {{ $money($shift->cash_counted) }} counted against {{ $money($shift->cash_expected) }}
                expected. Closed {{ $shift->closed_at?->format('d M Y, h:i A') }}@if ($shift->closer && (int) $shift->closer->user_id !== (int) $shift->user_id) by {{ $shift->closer->name }}@endif.
            </x-alert>
        @else
            <x-alert :tone="$shift->isShort() ? 'danger' : 'warning'"
                     title="{{ $shift->isShort() ? 'Short' : 'Over' }} by {{ $money(abs($variance)) }}">
                {{ $money($shift->cash_counted) }} was counted; the books expected
                {{ $money($shift->cash_expected) }}.
                @if ($shift->isOver())
                    Over usually means a payment was taken and never entered — it is worth finding, because
                    the guest's bill is the one that is wrong.
                @endif
                @if ($shift->close_note)
                    <br /><strong>Note:</strong> {{ $shift->close_note }}
                @endif
            </x-alert>
        @endif
    </div>

    <div class="nv-grid nv-grid-main nv-mt">
        <div class="nv-stack">
            {{-- ── What each pay mode took ─────────────────────────────────── --}}
            <x-card title="By pay mode" :flush="true">
                <div class="nv-table-wrap">
                    <table class="nv-table">
                        <thead>
                            <tr>
                                <th>Mode</th>
                                <th>Type</th>
                                <th class="is-end">Payments</th>
                                <th class="is-end">In</th>
                                <th class="is-end">Out</th>
                                <th class="is-end">Net</th>
                                @if (! $shift->isOpen())
                                    <th class="is-end">Counted</th>
                                    <th class="is-end">Difference</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                // The float sat in the drawer, so it belongs to the cash line — and to
                                // only one of them, or a hotel with two cash modes would expect it twice.
                                $floatUsed = false;
                            @endphp

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
                                    <td>
                                        <strong>{{ $mode['name'] }}</strong>
                                        @if ($float > 0)
                                            <span class="nv-sub">includes {{ $money($float) }} float</span>
                                        @endif
                                    </td>
                                    <td>{{ ucfirst($mode['type']) }}</td>
                                    <td class="is-end">{{ $mode['count'] }}</td>
                                    <td class="is-end nv-sh-in">{{ $money($mode['in']) }}</td>
                                    <td class="is-end nv-sh-out">{{ $mode['out'] > 0 ? '−' . $money($mode['out']) : '—' }}</td>
                                    <td class="is-end"><strong>{{ $money($expected) }}</strong></td>

                                    @if (! $shift->isOpen())
                                        <td class="is-end">{{ $money($counted) }}</td>
                                        <td @class(['is-end', 'nv-sh-off' => abs($off) >= 0.005])>
                                            {{ abs($off) < 0.005 ? '—' : ($off < 0 ? '−' : '+') . $money(abs($off)) }}
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>

            {{-- ── Every payment behind those totals ───────────────────────── --}}
            <x-card title="Every transaction" :subtitle="count($lines) . ' in this shift'" :flush="true">
                @if ($lines === [])
                    <div class="nv-empty">
                        <span class="nv-empty-icon"><x-icon name="inbox" /></span>
                        <strong>Nothing was taken</strong>
                        <p>The drawer was opened and closed without a payment going through it.</p>
                    </div>
                @else
                    <div class="nv-table-wrap">
                        <table class="nv-table nv-table-compact">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Source</th>
                                    <th>Particulars</th>
                                    <th>Mode</th>
                                    <th class="is-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($lines as $line)
                                    <tr>
                                        <td>{{ \Carbon\CarbonImmutable::parse($line['at'])->format('d M, h:i A') }}</td>
                                        <td>{{ $line['source'] }}</td>
                                        <td>
                                            {{ $line['particulars'] }}
                                            @if ($line['reference'])
                                                <span class="nv-sub">Ref {{ $line['reference'] }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $modes[$line['pay_mode_id']]['name'] ?? 'Not stated' }}</td>
                                        <td @class(['is-end', 'nv-sh-in' => $line['direction'] === 'in', 'nv-sh-out' => $line['direction'] === 'out'])>
                                            {{ $line['direction'] === 'out' ? '−' : '' }}{{ $money($line['amount']) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>

        <div class="nv-stack">
            <x-card title="The shift">
                <div class="nv-crm-facts">
                    <div><span>Number</span><b>{{ $shift->shift_no }}</b></div>
                    <div><span>Cashier</span><b>{{ $shift->cashier }}</b></div>
                    <div><span>Opened</span><b>{{ $shift->opened_at->format('d M Y, h:i A') }}</b></div>
                    <div><span>Closed</span>
                        <b>{{ $shift->closed_at?->format('d M Y, h:i A') ?: 'Still open' }}</b></div>
                    <div><span>Length</span><b>{{ $shift->length }}</b></div>
                    <div><span>Opening float</span><b>{{ $money($shift->opening_float) }}</b></div>
                    @if ($shift->closer)
                        <div><span>Closed by</span><b>{{ $shift->closer->name }}</b></div>
                    @endif
                </div>

                @if ($shift->remark)
                    <hr class="nv-hr" />
                    <p class="nv-muted">{{ $shift->remark }}</p>
                @endif
            </x-card>

            <x-card title="Where it came from">
                <div class="nv-crm-facts">
                    @foreach ([
                        'rooms' => 'Room bills',
                        'deposits' => 'Advance deposits',
                        'pos' => 'Restaurant',
                        'petty_in' => 'Cash received',
                        'refunds' => 'Deposit refunds',
                        'petty_out' => 'Cash paid out',
                    ] as $key => $label)
                        @if (($figures['sources'][$key] ?? 0) > 0)
                            <div>
                                <span>{{ $label }}</span>
                                <b @class(['nv-sh-out' => in_array($key, ['refunds', 'petty_out'], true)])>
                                    {{ in_array($key, ['refunds', 'petty_out'], true) ? '−' : '' }}{{ $money($figures['sources'][$key]) }}
                                </b>
                            </div>
                        @endif
                    @endforeach

                    <div class="nv-sh-net">
                        <span>Net through the drawer</span>
                        <b>{{ $money($figures['totals']['net']) }}</b>
                    </div>
                </div>
            </x-card>

            {{-- A manager closing a drawer somebody walked away from. --}}
            @if ($shift->isOpen() && can_do('shift/reports', 'edit'))
                <x-card title="Close this drawer" subtitle="For a cashier who has gone home with it open.">
                    <form method="POST" action="{{ route('shift.reports.close', $shift->id) }}" class="nv-stack"
                          data-confirm="Close {{ $shift->shift_no }} on behalf of {{ $shift->cashier }}?"
                          data-confirm-title="Close somebody else's shift"
                          data-confirm-action="Close it">
                        @csrf

                        <div class="nv-sh-count">
                            @foreach ($figures['modes'] as $mode)
                                <label class="nv-sh-count-row">
                                    <span>
                                        {{ $mode['name'] }}
                                        @if (strcasecmp($mode['name'], $mode['type']) !== 0)
                                            <small>{{ ucfirst($mode['type']) }}</small>
                                        @endif
                                    </span>
                                    <input type="number" step="0.01" min="0"
                                           name="counted[{{ $mode['id'] }}]"
                                           class="nv-input is-num" placeholder="0.00" />
                                </label>
                            @endforeach
                        </div>

                        <x-field label="Why you are closing it" name="close_note" required wide>
                            <x-textarea name="close_note" rows="2"
                                        placeholder="Who counted the drawer, and why the cashier did not." />
                        </x-field>

                        <div class="nv-actions">
                            <button type="submit" class="nv-btn nv-btn-primary">
                                <x-icon name="lock" /> Close on their behalf
                            </button>
                        </div>
                    </form>
                </x-card>
            @endif

            @if (! $shift->isOpen())
                <x-card title="These figures are frozen">
                    <p class="nv-sh-frozen">
                        What is shown here was worked out at the moment the shift closed and stored with
                        it. A bill corrected next week will change the day book — it will not change this
                        report, because somebody has already signed for it.
                    </p>
                    <p class="nv-muted">
                        Taken {{ \Carbon\CarbonImmutable::parse($figures['taken_at'] ?? $shift->closed_at)->format('d M Y, h:i A') }}.
                    </p>
                </x-card>
            @endif
        </div>
    </div>
@endsection
