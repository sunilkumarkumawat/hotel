@extends('layouts.app')

@section('title', 'Day Book')

@section('content')
    <x-page-header
        title="Day Book"
        subtitle="Everything posted, in the order it was posted. The screen to open first."
        :crumbs="['Home' => url('/'), 'Accounting', 'Day Book']"
    />

    <div class="nv-grid nv-grid-3">
        <x-stat label="Vouchers" :value="$count" icon="file" />
        <x-stat label="Debit" :value="'₹ ' . number_format($debit, 2)" icon="arrow-down" tone="info" />
        <x-stat label="Credit" :value="'₹ ' . number_format($credit, 2)" icon="arrow-up" tone="success" />
    </div>

    <div class="nv-mt">
        <x-card>
            <form method="GET" class="nv-toolbar">
                <input type="date" name="from" value="{{ $from }}" class="nv-input" style="width:160px" aria-label="From" />
                <input type="date" name="to" value="{{ $to }}" class="nv-input" style="width:160px" aria-label="To" />

                <select name="type" class="nv-select" style="width:170px" aria-label="Kind">
                    <option value="">Every kind</option>
                    @foreach ($types as $key => $label)
                        <option value="{{ $key }}" @selected($type === $key)>{{ $label }}</option>
                    @endforeach
                </select>

                <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="filter" /> Show</button>
                <a href="{{ route('accounting.day-book') }}" class="nv-btn nv-btn-ghost">Today</a>
            </form>

            <p class="nv-help">
                Cancelled vouchers are left out everywhere in this module — they keep their number so the
                series has no holes, and they count for nothing.
            </p>
        </x-card>
    </div>

    <div class="nv-mt">
        <x-card flush>
            @if ($vouchers->isEmpty())
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="inbox" /></span>
                    <strong>Nothing posted</strong>
                    <p>Widen the dates, or post a voucher from one of the screens under Accounting.</p>
                </div>
            @else
                <div class="nv-table-wrap">
                    <table class="nv-table">
                        <thead>
                            <tr>
                                <th style="width:150px">Voucher</th>
                                <th>Particulars</th>
                                <th>Narration</th>
                                <th class="is-num" style="width:140px">Debit</th>
                                <th class="is-num" style="width:140px">Credit</th>
                            </tr>
                        </thead>

                        @foreach ($vouchers as $day => $list)
                            <tbody>
                                <tr class="nv-led-day">
                                    <th colspan="3">{{ \Carbon\CarbonImmutable::parse($day)->format('l, d M Y') }}</th>
                                    <th class="is-num">₹ {{ number_format($list->sum(fn ($v) => (float) $v->entries->sum('debit')), 2) }}</th>
                                    <th class="is-num">₹ {{ number_format($list->sum(fn ($v) => (float) $v->entries->sum('credit')), 2) }}</th>
                                </tr>

                                @foreach ($list as $voucher)
                                    @foreach ($voucher->entries as $entry)
                                        <tr>
                                            <td>
                                                @if ($loop->first)
                                                    <strong>{{ $voucher->voucher_no }}</strong>
                                                    <span class="nv-sub">{{ $voucher->type_label }}</span>
                                                @endif
                                            </td>

                                            <td>
                                                <span @class(['nv-led-cr' => (float) $entry->credit > 0])>
                                                    {{ $entry->ledger?->name ?? 'Ledger #' . $entry->ledger_id }}
                                                </span>
                                            </td>

                                            <td>
                                                @if ($loop->first)
                                                    {{ $voucher->narration ?: ($voucher->reference_no ?: '—') }}
                                                    @if ($voucher->isAuto())
                                                        <span class="nv-sub">posted by the system</span>
                                                    @endif
                                                @else
                                                    {{ $entry->narration ?: '' }}
                                                @endif
                                            </td>

                                            <td class="is-num">{{ (float) $entry->debit > 0 ? '₹ ' . number_format((float) $entry->debit, 2) : '' }}</td>
                                            <td class="is-num">{{ (float) $entry->credit > 0 ? '₹ ' . number_format((float) $entry->credit, 2) : '' }}</td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        @endforeach

                        <tfoot>
                            <tr>
                                <th colspan="3">Everything in range</th>
                                <th class="is-num">₹ {{ number_format($debit, 2) }}</th>
                                <th class="is-num">₹ {{ number_format($credit, 2) }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </x-card>
    </div>
@endsection
