@extends('layouts.app')

@section('title', $item->name . ' — movements')

@php
    $money = fn ($n) => '₹' . number_format((float) $n, 2);
    $qty = fn ($n) => rtrim(rtrim(number_format((float) $n, 3), '0'), '.');

    $in = $rows->where('direction', 'in');
    $out = $rows->where('direction', 'out');
@endphp

@section('content')
    <x-page-header
        :title="$item->name"
        :subtitle="'Every movement, newest first. ' . ($item->category?->name ?: 'Uncategorised')
            . ' · measured in ' . $item->unit"
        :crumbs="['Home' => url('/'), 'Store', 'Stock' => route('store.stock'), $item->name]"
    >
        <x-slot:actions>
            <a href="{{ route('store.stock') }}" class="nv-btn nv-btn-outline">
                <x-icon name="chevron-left" /> Stock sheet
            </a>

            @canEdit('store/stock')
                <form method="POST" action="{{ route('store.stock.rebuild', $item) }}"
                      data-confirm="Replay every movement and write the balances again?"
                      data-confirm-title="Recalculate from the ledger"
                      data-confirm-action="Recalculate">
                    @csrf
                    <button type="submit" class="nv-btn nv-btn-primary">
                        <x-icon name="refresh" /> Recalculate
                    </button>
                </form>
            @endCanEdit
        </x-slot:actions>
    </x-page-header>

    <div class="nv-grid nv-grid-4">
        <x-stat label="In stock" :value="$qty($item->current_qty) . ' ' . $item->unit" icon="package"
                :tone="$item->isShort() ? 'danger' : ($item->needsReorder() ? 'warning' : 'primary')"
                :caption="(float) $item->reorder_level > 0 ? 'Reorder at ' . $qty($item->reorder_level) : null" />
        <x-stat label="Average rate" :value="$money($item->avg_rate)" icon="wallet"
                :caption="'Last paid ' . $money($item->last_rate)" />
        <x-stat label="Value" :value="$money($item->value)" icon="chart" tone="success" />
        <x-stat label="Movements shown" :value="$rows->count()" icon="activity" tone="info"
                :caption="$in->count() . ' in, ' . $out->count() . ' out'" />
    </div>

    <div class="nv-mt">
        <x-card flush>
            <form method="GET" class="nv-toolbar">
                <x-field label="From" name="from"><x-input type="date" name="from" :value="$from" /></x-field>
                <x-field label="To" name="to"><x-input type="date" name="to" :value="$to" /></x-field>
                <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="search" /> Show</button>
                <a href="{{ route('store.stock.ledger', $item) }}" class="nv-btn nv-btn-ghost">This month</a>
            </form>

            @if ($rows->isEmpty())
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="activity" /></span>
                    <strong>No movements in this period</strong>
                    <p>
                        @if ((float) $item->opening_qty > 0)
                            The balance is still the opening quantity of {{ $qty($item->opening_qty) }}
                            {{ $item->unit }}.
                        @else
                            Nothing has ever been received against this item.
                        @endif
                    </p>
                </div>
            @else
                <div class="nv-table-wrap">
                    <table class="nv-table nv-table-compact">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>What</th>
                                <th>Document</th>
                                <th class="is-end">In</th>
                                <th class="is-end">Out</th>
                                <th class="is-end">Rate</th>
                                <th class="is-end">Value</th>
                                <th class="is-end">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr @class(['nv-st-reversal' => $row->kind === 'reversal'])>
                                    <td>{{ \Carbon\CarbonImmutable::parse($row->entry_date)->format('d M Y') }}</td>
                                    <td>
                                        <span @class(['nv-st-kind', 'is-in' => $row->direction === 'in'])>
                                            {{ \App\Support\Store::KINDS[$row->doc_kind] ?? ucfirst($row->kind) }}
                                        </span>
                                        @if ($row->department)
                                            <span class="nv-sub">{{ \App\Support\Store::DEPARTMENTS[$row->department] ?? $row->department }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $row->doc_no ?: ($row->reference ?: '—') }}
                                    </td>
                                    <td class="is-end">{{ $row->direction === 'in' ? $qty($row->qty) : '' }}</td>
                                    <td class="is-end">{{ $row->direction === 'out' ? $qty($row->qty) : '' }}</td>
                                    <td class="is-end">{{ $money($row->rate) }}</td>
                                    <td class="is-end">{{ $money($row->value) }}</td>
                                    <td @class(['is-end', 'nv-st-qty', 'is-short' => (float) $row->balance_qty < 0])>
                                        <strong>{{ $qty($row->balance_qty) }}</strong>
                                        <span class="nv-sub">at {{ $money($row->balance_rate) }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
    </div>

    <div class="nv-mt">
        <x-alert tone="info" title="Nothing here is ever edited">
            A correction is another row and a cancelled document posts its reverse, so the history reads as
            what actually happened rather than as what somebody last decided it should look like. If the
            balance on the stock sheet ever disagrees with this list, this list is right — and Recalculate
            will prove it.
        </x-alert>
    </div>
@endsection
