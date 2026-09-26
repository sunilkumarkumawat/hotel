@extends('layouts.app')

@section('title', 'Cash Balance')

@php
    // What the shift produced, in the order the question is actually asked:
    // what came in, of which this much is cash, this much left on a room key,
    // and this much never arrived.
    $accounted = round($total + $toRoom, 2);
@endphp

@section('content')
    <x-page-header
        title="Cash Balance"
        subtitle="What should be in the drawer, and what went somewhere else"
        :crumbs="['Home' => url('/'), 'POS' => route('point-of-sale.pos'), 'Cash Balance']"
    />

    @include('pos.till.partials.nav', ['current' => 'cash'])

    <div class="nv-mt">
        <x-card>
            <form method="GET" class="nv-toolbar">
                <input type="hidden" name="outlet" value="{{ $outlet?->id }}" />

                <x-field label="Day" name="date" for="date">
                    <input type="date" name="date" id="date" value="{{ $date }}" class="nv-input" />
                </x-field>

                <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="filter" /> Show</button>

                @if ($date !== now()->toDateString())
                    <a href="{{ route('point-of-sale.pos.cash-balance', ['outlet' => $outlet?->id]) }}"
                       class="nv-btn nv-btn-ghost">Today</a>
                @endif
            </form>
        </x-card>
    </div>

    <div class="nv-grid nv-grid-4 nv-mt">
        <x-stat label="In the drawer" :value="'₹ ' . number_format($cash, 2)" icon="wallet" tone="success"
                caption="Pay modes with “cash” in the name" />
        <x-stat label="Taken, all modes" :value="'₹ ' . number_format($total, 2)" icon="credit-card" />
        <x-stat label="Signed to rooms" :value="'₹ ' . number_format($toRoom, 2)" icon="home"
                caption="Now the front desk's to collect" />
        <x-stat label="Still owed" :value="'₹ ' . number_format($owed, 2)" icon="alert"
                :tone="$owed > 0 ? 'warning' : 'success'" />
    </div>

    <div class="nv-mt">
        <x-card flush>
            <x-slot:title>{{ \Carbon\CarbonImmutable::parse($date)->format('l, d F Y') }}</x-slot:title>
            <x-slot:actions>
                <span class="nv-muted">₹ {{ number_format($accounted, 2) }} accounted for</span>
            </x-slot:actions>

            <div class="nv-table-wrap">
                <table class="nv-table">
                    <thead>
                        <tr>
                            <th>Pay mode</th>
                            <th class="is-num">Entries</th>
                            <th class="is-num">Amount</th>
                            <th>Share of the day</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            @php $share = $total > 0 ? round($row->amount / $total * 100) : 0; @endphp

                            <tr>
                                <td><strong>{{ $row->mode }}</strong></td>
                                <td class="is-num">{{ $row->entries }}</td>
                                <td class="is-num">₹ {{ number_format((float) $row->amount, 2) }}</td>
                                <td>
                                    {{-- The bar is the quick read; the number beside it is the answer. --}}
                                    <span class="nv-share">
                                        <span class="nv-share-bar" style="--w:{{ $share }}%"></span>
                                        <b>{{ $share }}%</b>
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <div class="nv-empty">
                                        <span class="nv-empty-icon"><x-icon name="wallet" /></span>
                                        <strong>No money taken on this day</strong>
                                        <p>Either nothing was sold, or nothing has been settled yet.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>

                    @if ($rows->isNotEmpty())
                        <tfoot>
                            <tr>
                                <th>Total</th>
                                <th class="is-num">{{ $rows->sum('entries') }}</th>
                                <th class="is-num">₹ {{ number_format($total, 2) }}</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </x-card>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/pos-till.js') }}?v={{ filemtime(public_path('js/pos-till.js')) }}" defer></script>
@endpush
