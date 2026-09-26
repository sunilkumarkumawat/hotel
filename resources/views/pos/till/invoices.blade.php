@extends('layouts.app')

@section('title', 'Invoices')

@section('content')
    <x-page-header
        title="Invoices"
        subtitle="Every bill this outlet has raised"
        :crumbs="['Home' => url('/'), 'POS' => route('point-of-sale.pos'), 'Invoices']"
    />

    @include('pos.till.partials.nav', ['current' => 'invoices'])

    <div class="nv-mt">
        <x-card flush>
            <form method="GET" class="nv-toolbar">
                <input type="hidden" name="outlet" value="{{ $outlet?->id }}" />

                <x-field label="From" name="from" for="from">
                    <input type="date" name="from" id="from" value="{{ $from }}" class="nv-input" />
                </x-field>

                <x-field label="To" name="to" for="to">
                    <input type="date" name="to" id="to" value="{{ $to }}" class="nv-input" />
                </x-field>

                <div class="nv-field-search">
                    <x-icon name="search" />
                    <input type="search" name="q" value="{{ $term }}" class="nv-input"
                           placeholder="Bill number or guest…" aria-label="Search invoices" />
                </div>

                <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="filter" /> Show</button>

                <a href="{{ route('point-of-sale.pos.invoices', ['outlet' => $outlet?->id]) }}"
                   class="nv-btn nv-btn-ghost">Reset</a>
            </form>

            <div class="nv-table-wrap">
                <table class="nv-table">
                    <thead>
                        <tr>
                            <th>Bill</th>
                            <th>Raised</th>
                            <th>Where</th>
                            <th>Guest</th>
                            <th class="is-num">Net</th>
                            <th class="is-num">Paid</th>
                            <th>State</th>
                            <th class="is-num">Prints</th>
                            <th class="is-end">Sheet</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($invoices as $invoice)
                            <tr>
                                <td><strong>{{ $invoice->invoice_no }}</strong></td>
                                <td>{{ $invoice->invoice_at?->format('d M Y, h:i A') }}</td>
                                <td>{{ $invoice->order?->where_label ?: '—' }}</td>
                                <td>{{ $invoice->guest_name ?: '—' }}</td>
                                <td class="is-num">₹ {{ number_format((float) $invoice->net_amount, 2) }}</td>
                                <td class="is-num">₹ {{ number_format((float) $invoice->paid_amount, 2) }}</td>
                                <td>
                                    <x-badge :tone="match ($invoice->status) {
                                        'settled' => 'success',
                                        'cancelled' => 'danger',
                                        default => 'warning',
                                    }">
                                        {{ $statuses[$invoice->status] ?? $invoice->status }}
                                    </x-badge>

                                    @if ($invoice->folio_charge_id)
                                        <x-badge tone="info">To room</x-badge>
                                    @endif
                                </td>
                                <td class="is-num">
                                    {{-- A bill printed many times is worth a glance, which is why
                                         the count is on the list rather than buried in the log. --}}
                                    <span @class(['nv-reprints', 'is-many' => $invoice->print_count > 2])>
                                        {{ $invoice->print_count }}
                                    </span>
                                </td>
                                <td class="is-end">
                                    @if ($invoice->pos_order_id)
                                        <a href="{{ route('point-of-sale.pos.order.print', $invoice->pos_order_id) }}"
                                           class="nv-btn nv-btn-ghost nv-btn-sm" target="_blank">
                                            <x-icon name="file" /> Open
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9">
                                    <div class="nv-empty">
                                        <span class="nv-empty-icon"><x-icon name="file" /></span>
                                        <strong>No bills in this range</strong>
                                        <p>Widen the dates, or clear the search.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-slot:footer>
                {{ $invoices->links() }}
            </x-slot:footer>
        </x-card>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/pos-till.js') }}?v={{ filemtime(public_path('js/pos-till.js')) }}" defer></script>
@endpush
