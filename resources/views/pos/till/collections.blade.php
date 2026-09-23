@extends('layouts.app')

@section('title', 'Collections')

@section('content')
    <x-page-header
        title="Collections"
        subtitle="The money, by how it arrived and on which day"
        :crumbs="['Home' => url('/'), 'POS' => route('point-of-sale.pos'), 'Collections']"
    />

    @include('pos.till.partials.nav', ['current' => 'collections'])

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
        </x-card>
    </div>

    <div class="nv-grid nv-grid-3 nv-mt">
        <x-stat label="Collected" :value="'₹ ' . number_format($total, 2)" icon="wallet" tone="success" />
        <x-stat label="Pay modes used" :value="$rows->count()" icon="credit-card" />
        <x-stat label="Best day" :value="'₹ ' . number_format((float) $busiest, 2)" icon="trending-up" />
    </div>

    <div class="nv-grid nv-grid-2 nv-mt">
        <x-card>
            <x-slot:title>By pay mode</x-slot:title>

            @if ($rows->isEmpty())
                <div class="nv-empty is-tight">
                    <span class="nv-empty-icon"><x-icon name="wallet" /></span>
                    <strong>Nothing collected</strong>
                    <p>No payment was recorded in this range.</p>
                </div>
            @else
                <table class="nv-table">
                    <thead>
                        <tr>
                            <th>Mode</th>
                            <th class="is-num">Entries</th>
                            <th class="is-num">Amount</th>
                            <th>Share</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php $share = $total > 0 ? round($row->amount / $total * 100) : 0; @endphp

                            <tr>
                                <td><strong>{{ $row->mode }}</strong></td>
                                <td class="is-num">{{ $row->entries }}</td>
                                <td class="is-num">₹ {{ number_format((float) $row->amount, 2) }}</td>
                                <td>
                                    <span class="nv-share">
                                        <span class="nv-share-bar" style="--w:{{ $share }}%"></span>
                                        <b>{{ $share }}%</b>
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-card>

        <x-card>
            <x-slot:title>Day by day</x-slot:title>

            @if ($byDay->isEmpty())
                <div class="nv-empty is-tight">
                    <span class="nv-empty-icon"><x-icon name="calendar" /></span>
                    <strong>No days to show</strong>
                    <p>Pick a range that has trading in it.</p>
                </div>
            @else
                {{--
                    A bar per day, scaled against the best one. One measure, one
                    axis, one colour — this is a magnitude, so nothing here is
                    coloured to mean anything else.
                --}}
                <div class="nv-days">
                    @foreach ($byDay as $day)
                        @php
                            $height = $busiest > 0 ? max(3, round($day->amount / $busiest * 100)) : 3;
                        @endphp

                        <div class="nv-day" title="{{ \Carbon\CarbonImmutable::parse($day->day)->format('D, d M') }} — ₹{{ number_format((float) $day->amount, 2) }}">
                            <span class="nv-day-bar" style="--h:{{ $height }}%"></span>
                            <span class="nv-day-label">{{ \Carbon\CarbonImmutable::parse($day->day)->format('d') }}</span>
                        </div>
                    @endforeach
                </div>

                <table class="nv-table nv-mt">
                    <thead>
                        <tr><th>Day</th><th class="is-num">Collected</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($byDay as $day)
                            <tr>
                                <td>{{ \Carbon\CarbonImmutable::parse($day->day)->format('D, d M Y') }}</td>
                                <td class="is-num">₹ {{ number_format((float) $day->amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-card>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/pos-till.js') }}?v={{ filemtime(public_path('js/pos-till.js')) }}" defer></script>
@endpush
