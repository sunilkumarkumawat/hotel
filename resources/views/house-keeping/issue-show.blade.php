@extends('layouts.app')

@section('title', $issue->issue_no)

@php
    $money = fn ($n) => '₹' . number_format((float) $n, 2);
    $qty = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
@endphp

@section('content')
    <x-page-header
        :title="$issue->issue_no"
        :subtitle="($issue->vendor?->name ?? 'No vendor') . ' · ' . $issue->issue_date->format('d M Y')"
        :crumbs="['Home' => url('/'), 'House Keeping', 'Issue' => route('house-keeping.issue'), $issue->issue_no]"
    >
        <x-slot:actions>
            <a href="{{ route('house-keeping.issue') }}" class="nv-btn nv-btn-outline">Back to list</a>

            @canAdd('house-keeping/received')
                <a href="{{ route('house-keeping.received.create', ['vendor' => $issue->vendor_id]) }}"
                   class="nv-btn nv-btn-primary">
                    <x-icon name="download" /> Receive from this vendor
                </a>
            @endCanAdd
        </x-slot:actions>
    </x-page-header>

    <div class="nv-grid nv-grid-4">
        <x-stat label="Pieces out" :value="$qty($issue->total_qty)" icon="upload" tone="warning" />
        <x-stat label="Amount" :value="$money($issue->total_amount)" icon="wallet" />
        <x-stat label="Lines" :value="$issue->lines->count()" icon="layers" tone="info" />
        <x-stat label="Written by" :value="$issue->creator?->name ?? '—'" icon="user" />
    </div>

    <div class="nv-mt">
        <x-card flush title="What went out">
            <div class="nv-table-wrap">
                <table class="nv-table nv-table-compact">
                    <thead>
                        <tr>
                            <th class="is-num">Sr.</th>
                            <th>Item Name</th>
                            <th class="is-num">Prev Qty</th>
                            <th class="is-num">Std Qty</th>
                            <th class="is-num">Exp Qty</th>
                            <th class="is-num">ReWash</th>
                            <th class="is-num">Std Rate</th>
                            <th class="is-num">Exp Rate</th>
                            <th class="is-num">Amount</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($issue->lines as $line)
                            <tr>
                                <td class="is-num">{{ $loop->iteration }}</td>
                                <td><strong>{{ $line->item?->name ?? '—' }}</strong></td>
                                <td class="is-num nv-muted">{{ $qty($line->prev_qty) }}</td>
                                <td class="is-num">{{ $qty($line->std_qty) }}</td>
                                <td class="is-num">{{ $qty($line->exp_qty) }}</td>
                                <td class="is-num">{{ $qty($line->rewash_qty) }}</td>
                                <td class="is-num nv-muted">{{ $money($line->std_rate) }}</td>
                                <td class="is-num nv-muted">{{ $money($line->exp_rate) }}</td>
                                <td class="is-num"><strong>{{ $money($line->amount) }}</strong></td>
                            </tr>
                        @endforeach
                    </tbody>

                    <tfoot>
                        <tr>
                            <th colspan="3">Total</th>
                            <th class="is-num">{{ $qty($issue->lines->sum('std_qty')) }}</th>
                            <th class="is-num">{{ $qty($issue->lines->sum('exp_qty')) }}</th>
                            <th class="is-num">{{ $qty($issue->lines->sum('rewash_qty')) }}</th>
                            <th colspan="2"></th>
                            <th class="is-num">{{ $money($issue->total_amount) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <x-slot:footer>
                <p class="nv-help" style="margin:0">
                    <b>Prev Qty</b> is what this vendor was holding when the note was written — it is kept
                    as it was on the day, so a later receipt never rewrites a note the laundry has a copy of.
                    ReWash went out free.
                    @if ($issue->remark) <br><b>Remark:</b> {{ $issue->remark }} @endif
                </p>
            </x-slot:footer>
        </x-card>
    </div>
@endsection
