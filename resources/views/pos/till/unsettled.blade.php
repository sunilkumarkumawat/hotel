@extends('layouts.app')

@section('title', 'Unsettled Invoices')

@section('content')
    <x-page-header
        title="Unsettled Invoices"
        subtitle="Bills that were printed and never paid for"
        :crumbs="['Home' => url('/'), 'POS' => route('point-of-sale.pos'), 'Unsettled Invoices']"
    />

    @include('pos.till.partials.nav', ['current' => 'unsettled'])

    <div class="nv-grid nv-grid-3 nv-mt">
        <x-stat label="Bills open" :value="$invoices->count()" icon="file"
                :tone="$invoices->isEmpty() ? 'success' : 'warning'" />
        <x-stat label="Still owed" :value="'₹ ' . number_format($owed, 2)" icon="wallet"
                :tone="$owed > 0 ? 'warning' : 'success'" />
        <x-stat label="Oldest" :value="$oldest ? $oldest->diffForHumans(null, true) : '—'" icon="clock" />
    </div>

    @if ($invoices->isNotEmpty())
        <div class="nv-mt">
            <x-alert tone="warning" title="This is the screen to clear before going home">
                Every line here is food that left the kitchen and money that did not arrive. A bill
                signed to a room is not on this list — it moved to the guest's folio.
            </x-alert>
        </div>
    @endif

    <div class="nv-mt">
        <x-card flush>
            <div class="nv-table-wrap">
                <table class="nv-table">
                    <thead>
                        <tr>
                            <th>Bill</th>
                            <th>Outlet</th>
                            <th>Where</th>
                            <th>Guest</th>
                            <th>Printed</th>
                            <th class="is-num">Bill</th>
                            <th class="is-num">Paid</th>
                            <th class="is-num">Owed</th>
                            <th class="is-end">Settle</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($invoices as $invoice)
                            <tr>
                                <td><strong>{{ $invoice->invoice_no }}</strong></td>
                                <td>{{ $invoice->outlet?->name ?: '—' }}</td>
                                <td>{{ $invoice->order?->where_label ?: '—' }}</td>
                                <td>{{ $invoice->guest_name ?: '—' }}</td>
                                <td>{{ $invoice->invoice_at?->format('d M, h:i A') }}</td>
                                <td class="is-num">₹ {{ number_format((float) $invoice->net_amount, 2) }}</td>
                                <td class="is-num">₹ {{ number_format((float) $invoice->paid_amount, 2) }}</td>
                                <td class="is-num"><strong>₹ {{ number_format($invoice->balance(), 2) }}</strong></td>
                                <td class="is-end">
                                    @if ($invoice->pos_order_id)
                                        <a href="{{ route('point-of-sale.pos.order', $invoice->pos_order_id) }}"
                                           class="nv-btn nv-btn-outline nv-btn-sm">
                                            <x-icon name="credit-card" /> Take payment
                                        </a>
                                    @else
                                        <span class="nv-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9">
                                    <div class="nv-empty">
                                        <span class="nv-empty-icon"><x-icon name="check-circle" /></span>
                                        <strong>Nothing outstanding</strong>
                                        <p>Every bill this outlet has printed has been paid for.</p>
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
