@extends('layouts.app')

@section('title', 'Transfers Received')

@php
    $money = fn ($n) => '₹' . number_format((float) $n, 2);
    $qty = fn ($n) => rtrim(rtrim(number_format((float) $n, 3), '0'), '.');
@endphp

@section('content')
    <x-page-header
        title="Transfers Received"
        subtitle="What other outlets have sent here. Receive one to put it on this outlet's shelf."
        :crumbs="['Home' => url('/'), 'Store', 'Stock Transfers' => route('store.docs', 'transfer'), 'Transfers Received']"
    />

    <div class="nv-mt">
        <x-card flush>
            @if ($incoming->isEmpty())
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="package" /></span>
                    <strong>Nothing on its way</strong>
                    <p>When another outlet sends stock here, it waits in this list until it is received.</p>
                </div>
            @else
                <div class="nv-table-wrap">
                    <table class="nv-table">
                        <thead>
                            <tr>
                                <th>Transfer</th>
                                <th>Date</th>
                                <th>From</th>
                                <th class="is-end">Lines</th>
                                <th class="is-end">Received</th>
                                <th class="is-end">Value</th>
                                <th>Status</th>
                                <th class="is-end">&nbsp;</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($incoming as $t)
                                <tr>
                                    <td><strong>{{ $t->doc_no }}</strong></td>
                                    <td>{{ $t->doc_date->format('d M Y') }}</td>
                                    <td>{{ $t->branch?->branch_name ?: '—' }}</td>
                                    <td class="is-end">{{ $t->items->count() }}</td>
                                    <td class="is-end">
                                        @php
                                            $sent = $t->items->sum(fn ($l) => (float) $l->qty);
                                            $got = $t->items->sum(fn ($l) => (float) $l->received_qty);
                                        @endphp
                                        {{ $qty($got) }} / {{ $qty($sent) }}
                                    </td>
                                    <td class="is-end">{{ $money($t->net_amount) }}</td>
                                    <td><x-badge :tone="$t->status_tone">{{ ucfirst($t->status) }}</x-badge></td>
                                    <td class="is-end">
                                        <div class="nv-row-actions">
                                            @canAdd('store/transfer')
                                                <a href="{{ route('store.docs.create', ['kind' => 'transfer_in', 'from' => $t->id]) }}"
                                                   class="nv-btn nv-btn-sm nv-btn-primary">
                                                    <x-icon name="package" /> Receive
                                                </a>
                                            @endCanAdd

                                            <a href="{{ route('store.docs.show', ['kind' => 'transfer_out', 'doc' => $t]) }}"
                                               class="nv-btn nv-btn-sm nv-btn-ghost">
                                                <x-icon name="external" /> Open
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
    </div>
@endsection
