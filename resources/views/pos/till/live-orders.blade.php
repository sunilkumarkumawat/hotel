@extends('layouts.app')

@section('title', 'Live Orders')

@section('content')
    <x-page-header
        title="Live Orders"
        subtitle="Everything on the floor right now, oldest first"
        :crumbs="['Home' => url('/'), 'POS' => route('point-of-sale.pos'), 'Live Orders']"
    />

    @include('pos.till.partials.nav', ['current' => 'live'])

    <div class="nv-grid nv-grid-4 nv-mt">
        <x-stat label="Being taken" :value="$running" icon="inbox" />
        <x-stat label="Billed, not paid" :value="$awaiting" icon="alert" tone="warning" />
        <x-stat label="On the floor" :value="'₹ ' . number_format($value, 2)" icon="wallet" />
        <x-stat
            label="Oldest order"
            :value="$oldest ? (intdiv($oldest, 3600) ? intdiv($oldest, 3600) . 'h ' . intdiv($oldest % 3600, 60) . 'm' : intdiv($oldest, 60) . 'm') : '—'"
            icon="clock"
            :tone="$oldest > 5400 ? 'warning' : 'primary'"
        />
    </div>

    <div class="nv-mt">
        <x-card flush>
            <x-slot:title>{{ $orders->count() }} open</x-slot:title>

            <div class="nv-table-wrap">
                <table class="nv-table">
                    <thead>
                        <tr>
                            <th>Where</th>
                            <th>Order</th>
                            <th>Opened</th>
                            <th>Running</th>
                            <th>Steward</th>
                            <th class="is-num">Items</th>
                            <th class="is-num">KOTs</th>
                            <th class="is-num">Amount</th>
                            <th>State</th>
                            <th class="is-end">Open</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($orders as $row)
                            <tr data-since="{{ $row->opened_at?->timestamp }}">
                                <td><strong>{{ $row->where_label }}</strong></td>
                                <td>
                                    {{ $row->order_no }}
                                    <span class="nv-muted">· {{ $row->outlet?->name }}</span>
                                </td>
                                <td>{{ $row->opened_at?->format('h:i A') }}</td>
                                <td>
                                    <span class="nv-tick"><b data-clock-value>—</b></span>
                                </td>
                                <td>{{ $row->steward?->name ?: '—' }}</td>
                                <td class="is-num">{{ $row->items->count() }}</td>
                                <td class="is-num">{{ $row->kot_count }}</td>
                                <td class="is-num">₹ {{ number_format((float) $row->net_amount, 2) }}</td>
                                <td>
                                    <x-badge :tone="$row->status === 'billed' ? 'warning' : 'success'">
                                        {{ $row->status === 'billed' ? 'Payment due' : 'Running' }}
                                    </x-badge>
                                </td>
                                <td class="is-end">
                                    <a href="{{ route('point-of-sale.pos.order', $row->id) }}"
                                       class="nv-btn nv-btn-ghost nv-btn-sm">
                                        <x-icon name="arrow-right" /> Bill
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10">
                                    <div class="nv-empty">
                                        <span class="nv-empty-icon"><x-icon name="check-circle" /></span>
                                        <strong>Nothing running</strong>
                                        <p>Every table is clear and every bill is settled.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/pos-till.js') }}?v={{ filemtime(public_path('js/pos-till.js')) }}" defer></script>
@endpush
