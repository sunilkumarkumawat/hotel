@extends('layouts.app')

@section('title', $receipt->receipt_no)

@php $qty = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.'); @endphp

@section('content')
    <x-page-header
        :title="$receipt->receipt_no"
        :subtitle="($receipt->vendor?->name ?? 'No vendor') . ' · ' . $receipt->receive_date->format('d M Y')"
        :crumbs="['Home' => url('/'), 'House Keeping', 'Received' => route('house-keeping.received'), $receipt->receipt_no]"
    >
        <x-slot:actions>
            <a href="{{ route('house-keeping.received') }}" class="nv-btn nv-btn-outline">Back to list</a>
        </x-slot:actions>
    </x-page-header>

    @php
        $lost = $receipt->lines->sum('damaged_qty') + $receipt->lines->sum('missing_qty');
    @endphp

    <div class="nv-grid nv-grid-4">
        <x-stat label="Received" :value="$qty($receipt->total_qty)" icon="download" tone="success" />
        <x-stat label="Written off" :value="$qty($lost)" icon="alert" :tone="$lost > 0 ? 'danger' : 'info'" />
        <x-stat label="Lines" :value="$receipt->lines->count()" icon="layers" />
        <x-stat label="Taken by" :value="$receipt->creator?->name ?? '—'" icon="user" />
    </div>

    <div class="nv-mt">
        <x-card flush title="What came back">
            <div class="nv-table-wrap">
                <table class="nv-table nv-table-compact">
                    <thead>
                        <tr>
                            <th class="is-num">Sr.</th>
                            <th>Item Name</th>
                            <th class="is-num">Pending then</th>
                            <th class="is-num">Received</th>
                            <th class="is-num">Damaged</th>
                            <th class="is-num">Missing</th>
                            <th>Remark</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($receipt->lines as $line)
                            <tr>
                                <td class="is-num">{{ $loop->iteration }}</td>
                                <td><strong>{{ $line->item?->name ?? '—' }}</strong></td>
                                <td class="is-num nv-muted">{{ $qty($line->pending_qty) }}</td>
                                <td class="is-num"><strong>{{ $qty($line->received_qty) }}</strong></td>
                                <td class="is-num">{{ $qty($line->damaged_qty) }}</td>
                                <td class="is-num">{{ $qty($line->missing_qty) }}</td>
                                <td class="nv-muted">{{ $line->remark ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>

                    <tfoot>
                        <tr>
                            <th colspan="3">Total</th>
                            <th class="is-num">{{ $qty($receipt->lines->sum('received_qty')) }}</th>
                            <th class="is-num">{{ $qty($receipt->lines->sum('damaged_qty')) }}</th>
                            <th class="is-num">{{ $qty($receipt->lines->sum('missing_qty')) }}</th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <x-slot:footer>
                <p class="nv-help" style="margin:0">
                    <b>Pending then</b> is what the vendor was holding of that item when this note was
                    written. Damaged and missing pieces came off their list along with the received ones —
                    the hotel is not getting those back either.
                    @if ($receipt->remark) <br><b>Remark:</b> {{ $receipt->remark }} @endif
                </p>
            </x-slot:footer>
        </x-card>
    </div>
@endsection
