@extends('layouts.app')

@section('title', 'Outlet Orders')

@section('content')
    <x-page-header
        title="Outlet Orders"
        subtitle="Every outlet side by side, broken down by how it was sold"
        :crumbs="['Home' => url('/'), 'POS' => route('point-of-sale.pos'), 'Outlet Orders']"
    />

    @include('pos.till.partials.nav', ['current' => 'outlet-orders'])

    <div class="nv-mt">
        <x-card>
            <form method="GET" class="nv-toolbar">
                <input type="hidden" name="outlet" value="{{ $outlet?->id }}" />

                <x-field label="From" name="from" for="from">
                    <input type="date" name="from" id="from" value="{{ $from }}" class="nv-input" />
                </x-field>

                <x-field label="To" name="to" for="to">
                    <input type="date" name="to" id="to" value="{{ $to }}" class="nv-input" />
                </x-field>

                <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="filter" /> Show</button>
            </form>

            <p class="nv-help">
                This screen deliberately ignores the outlet picked above — it is the one place the
                outlets are meant to be read against each other.
            </p>
        </x-card>
    </div>

    <div class="nv-grid nv-grid-2 nv-mt">
        <x-stat label="Orders" :value="number_format($orders)" icon="inbox" />
        <x-stat label="Sales" :value="'₹ ' . number_format($amount, 2)" icon="wallet" tone="success" />
    </div>

    @forelse ($rows as $name => $group)
        @php
            $groupTotal = $group->sum('amount');
            $share = $amount > 0 ? round($groupTotal / $amount * 100) : 0;
        @endphp

        <div class="nv-mt">
            <x-card flush>
                <x-slot:title>{{ $name }}</x-slot:title>
                <x-slot:actions>
                    <span class="nv-muted">
                        ₹ {{ number_format((float) $groupTotal, 2) }} · {{ $share }}% of the period
                    </span>
                </x-slot:actions>

                <div class="nv-table-wrap">
                    <table class="nv-table">
                        <thead>
                            <tr>
                                <th>Sold as</th>
                                <th class="is-num">Orders</th>
                                <th class="is-num">Covers</th>
                                <th class="is-num">Sales</th>
                                <th class="is-num">Average</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($group as $row)
                                <tr>
                                    <td><strong>{{ $types[$row->order_type] ?? $row->order_type }}</strong></td>
                                    <td class="is-num">{{ number_format($row->orders) }}</td>
                                    <td class="is-num">{{ number_format($row->covers) }}</td>
                                    <td class="is-num">₹ {{ number_format((float) $row->amount, 2) }}</td>
                                    <td class="is-num">
                                        ₹ {{ number_format($row->orders ? $row->amount / $row->orders : 0, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>
    @empty
        <div class="nv-mt">
            <x-card>
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="inbox" /></span>
                    <strong>Nothing sold in this range</strong>
                    <p>Widen the dates, or start taking orders on the Dine In screen.</p>
                </div>
            </x-card>
        </div>
    @endforelse
@endsection

@push('scripts')
    <script src="{{ asset('js/pos-till.js') }}?v={{ filemtime(public_path('js/pos-till.js')) }}" defer></script>
@endpush
