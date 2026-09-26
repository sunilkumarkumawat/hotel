@extends('layouts.app')

@section('title', $title)

@php
    use App\Support\Ledgers;

    // Positive is Dr and negative is Cr throughout this module, so a balance is
    // shown as its size plus the side it is on rather than as a minus sign.
    $side = fn (float $value) => Ledgers::side($value) ? strtoupper(Ledgers::side($value)) : '';
@endphp

@section('content')
    <x-page-header
        :title="$title"
        :subtitle="$subtitle ?? 'Opening balance, every movement, and where it closes.'"
        :crumbs="['Home' => url('/'), 'Accounting' => route('accounting.day-book'), $title]"
    >
        <x-slot:actions>
            @if ($chosen && $title === 'Ledger Statement')
                <a href="{{ route('accounting.ledger-statement.export', ['ledger' => $chosen->id, 'from' => $from, 'to' => $to]) }}"
                   class="nv-btn nv-btn-outline"><x-icon name="download" /> Export</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="nv-mt">
        <x-card>
            <form method="GET" class="nv-toolbar">
                <select name="ledger" class="nv-select" style="width:260px" aria-label="Ledger">
                    @foreach ($ledgers as $ledger)
                        <option value="{{ $ledger->id }}" @selected($chosen && $chosen->id === $ledger->id)>
                            {{ $ledger->display_name }}
                        </option>
                    @endforeach
                </select>

                <input type="date" name="from" value="{{ $from }}" class="nv-input" style="width:160px" aria-label="From" />
                <input type="date" name="to" value="{{ $to }}" class="nv-input" style="width:160px" aria-label="To" />

                <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="filter" /> Show</button>
            </form>
        </x-card>
    </div>

    @if (! $chosen)
        <div class="nv-mt">
            <x-card>
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="wallet" /></span>
                    <strong>Nothing to show yet</strong>
                    <p>{{ $emptyHint ?? 'No ledger has been set up yet — add one under Accounting → Ledger.' }}</p>
                </div>
            </x-card>
        </div>
    @else
        <div class="nv-grid nv-grid-4 nv-mt">
            <x-stat label="Opening" :value="'₹ ' . number_format(abs($statement['opening']), 2) . ' ' . $side($statement['opening'])" icon="clock" />
            <x-stat label="Debit" :value="'₹ ' . number_format($statement['debit'], 2)" icon="arrow-down" tone="info" />
            <x-stat label="Credit" :value="'₹ ' . number_format($statement['credit'], 2)" icon="arrow-up" tone="warning" />
            <x-stat label="Closing" :value="'₹ ' . number_format(abs($statement['closing']), 2) . ' ' . $side($statement['closing'])" icon="wallet" tone="success" />
        </div>

        <div class="nv-mt">
            <x-card flush>
                <x-slot:title>{{ $chosen->name }}</x-slot:title>
                <x-slot:actions>
                    <span class="nv-muted">{{ $chosen->group?->name }} · {{ $from }} to {{ $to }}</span>
                </x-slot:actions>

                <div class="nv-table-wrap">
                    <table class="nv-table">
                        <thead>
                            <tr>
                                <th style="width:110px">Date</th>
                                <th style="width:150px">Voucher</th>
                                <th>Particulars</th>
                                <th>Narration</th>
                                <th class="is-num" style="width:130px">Debit</th>
                                <th class="is-num" style="width:130px">Credit</th>
                                <th class="is-num" style="width:150px">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="nv-led-day">
                                <th colspan="4">Opening balance</th>
                                <th class="is-num"></th>
                                <th class="is-num"></th>
                                <th class="is-num">
                                    ₹ {{ number_format(abs($statement['opening']), 2) }} {{ $side($statement['opening']) }}
                                </th>
                            </tr>

                            @forelse ($statement['lines'] as $line)
                                <tr>
                                    <td>{{ \Carbon\CarbonImmutable::parse($line->voucher_date)->format('d M Y') }}</td>
                                    <td>
                                        <strong>{{ $line->voucher_no }}</strong>
                                        <span class="nv-sub">{{ ucfirst($line->voucher_type) }}</span>
                                    </td>
                                    <td>{{ $line->against }}</td>
                                    <td>{{ $line->line_narration ?: ($line->narration ?: '—') }}</td>
                                    <td class="is-num">{{ $line->debit > 0 ? '₹ ' . number_format($line->debit, 2) : '' }}</td>
                                    <td class="is-num">{{ $line->credit > 0 ? '₹ ' . number_format($line->credit, 2) : '' }}</td>
                                    <td class="is-num">
                                        ₹ {{ number_format(abs($line->running), 2) }} {{ $side($line->running) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="nv-muted" style="text-align:center;padding:26px">
                                        Nothing moved on this ledger between those dates.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>

                        <tfoot>
                            <tr>
                                <th colspan="4">Closing balance</th>
                                <th class="is-num">₹ {{ number_format($statement['debit'], 2) }}</th>
                                <th class="is-num">₹ {{ number_format($statement['credit'], 2) }}</th>
                                <th class="is-num">
                                    ₹ {{ number_format(abs($statement['closing']), 2) }} {{ $side($statement['closing']) }}
                                </th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </x-card>
        </div>
    @endif
@endsection
