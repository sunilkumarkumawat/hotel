@extends('layouts.app')

@section('title', 'Reservation Calendar Monthly')

@php
    $keys = $data['keys'];
    $params = fn (array $extra = []) => array_merge(['date' => $start->toDateString(), 'days' => $days], $extra);

    // Each row gets a tone so the eye can find the line it wants without
    // reading every label.
    $tones = [
        'available' => 'is-head',
        'expected_checkin' => 'is-in',
        'stay_on' => 'is-stay',
        'occupancy' => '',
        'expected_checkout' => 'is-out',
        'management_block' => '',
        'maintenance_block' => 'is-soft',
        'position' => 'is-position',
        'wait_list' => 'is-soft',
    ];
@endphp

@section('content')
    <x-page-header
        title="Reservation Calendar Monthly"
        subtitle="The position of the house, day by day — who arrives, who stays on, who leaves, and what is left to sell."
        :crumbs="['Home' => url('/'), 'Reservations' => route('reservation.index'), 'Monthly']"
    >
        <x-slot:actions>
            <a href="{{ route('reservation.calendar-monthly.export', $params()) }}" class="nv-btn nv-btn-outline">
                <x-icon name="download" /> Export
            </a>
            <a href="{{ route('reservation.calendar', $params(['days' => 30])) }}" class="nv-btn nv-btn-outline">
                <x-icon name="grid" /> Calendar
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="nv-grid nv-grid-4">
        <x-stat label="Rooms" :value="$data['rooms']" icon="home" />
        <x-stat label="Arriving in this window" :value="$data['totals']['expected_checkin']" icon="arrow-down" tone="success" />
        <x-stat label="Leaving in this window" :value="$data['totals']['expected_checkout']" icon="arrow-up" tone="info" />
        <x-stat label="Occupancy" :value="$data['occupancy_percent']['total'] . '%'" icon="chart" tone="primary" />
    </div>

    <div class="nv-mt">
        <x-card flush>
            <form method="GET" class="nv-toolbar">
                <a href="{{ route('reservation.calendar-monthly', $params(['date' => $start->subDays($days)->toDateString()])) }}"
                   class="nv-btn nv-btn-outline"><x-icon name="chevron-left" /> Previous</a>

                <input type="date" name="date" value="{{ $start->toDateString() }}" class="nv-input" style="width:170px" />

                <select name="days" class="nv-select" style="width:130px">
                    @foreach ($spans as $value => $label)
                        <option value="{{ $value }}" @selected($days === $value)>{{ $label }}</option>
                    @endforeach
                </select>

                <button type="submit" class="nv-btn nv-btn-primary">Go</button>

                <a href="{{ route('reservation.calendar-monthly', $params(['date' => $start->addDays($days)->toDateString()])) }}"
                   class="nv-btn nv-btn-outline">Next <x-icon name="chevron-right" /></a>

                <a href="{{ route('reservation.calendar-monthly', ['days' => $days]) }}" class="nv-btn nv-btn-ghost">Today</a>
            </form>

            <div class="nv-table-wrap nv-pos-wrap">
                <table class="nv-table nv-pos">
                    <thead>
                        <tr>
                            <th class="nv-pos-head">Position</th>

                            @foreach ($dates as $date)
                                <th @class(['is-center', 'is-today' => $date->toDateString() === $today])>
                                    {{ $date->format('M') }}<br>{{ $date->format('d') }}
                                    <span class="nv-pos-dow">{{ $date->format('D') }}</span>
                                </th>
                            @endforeach

                            <th class="is-center nv-pos-total">TOTAL</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($rowLabels as $row => $label)
                            <tr @class(['nv-pos-row', $tones[$row] ?? ''])>
                                <th class="nv-pos-head">{{ $label }}</th>

                                @foreach ($keys as $key)
                                    @php $n = $data['rows'][$row][$key]; @endphp

                                    <td @class([
                                            'is-center',
                                            'is-today' => $key === $today,
                                            'is-zero' => $n === 0,
                                            // A negative Position is an oversell, not a rounding quirk.
                                            'is-over' => $n < 0,
                                        ])
                                        @if ($n < 0) title="Oversold by {{ abs($n) }} room(s) on {{ $key }}" @endif>
                                        {{ $n }}
                                    </td>
                                @endforeach

                                <td class="is-center nv-pos-total">{{ $data['totals'][$row] }}</td>
                            </tr>
                        @endforeach

                        <tr class="nv-pos-row is-percent">
                            <th class="nv-pos-head">Occupancy %</th>

                            @foreach ($keys as $key)
                                @php
                                    // The same five-step ramp the dashboard
                                    // calendar uses, so a dark cell means the
                                    // same thing on both screens.
                                    $pct = (float) $data['occupancy_percent'][$key];
                                    $step = match (true) {
                                        $pct <= 0 => 0,
                                        $pct >= 95 => 5,
                                        $pct >= 75 => 4,
                                        $pct >= 50 => 3,
                                        $pct >= 25 => 2,
                                        default => 1,
                                    };
                                @endphp

                                <td @class([
                                        'is-center',
                                        'nv-heat',
                                        'is-o' . $step,
                                        'is-today' => $key === $today,
                                        'is-zero' => $pct == 0,
                                    ])>{{ rtrim(rtrim(number_format($pct, 1), '0'), '.') }}%</td>
                            @endforeach

                            <td class="is-center nv-pos-total">
                                {{ rtrim(rtrim(number_format($data['occupancy_percent']['total'], 1), '0'), '.') }}%
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>

    {{-- ── Room Typewise Position ────────────────────────────────────────── --}}
    <div class="nv-mt">
        <x-card title="Room Typewise Position"
                subtitle="Rooms of each type still free to sell on that night.">
            @if ($data['types']->count())
                <div class="nv-table-wrap nv-pos-wrap">
                    <table class="nv-table nv-pos">
                        <thead>
                            <tr>
                                <th class="nv-pos-head">Room Type</th>

                                @foreach ($dates as $date)
                                    <th @class(['is-center', 'is-today' => $date->toDateString() === $today])>
                                        {{ $date->format('M') }}<br>{{ $date->format('d') }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($data['types'] as $type)
                                <tr class="nv-pos-row">
                                    <th class="nv-pos-head">
                                        {{ $type->name }} <span class="nv-muted">({{ $type->rooms }})</span>
                                    </th>

                                    @foreach ($keys as $key)
                                        @php $free = $data['typewise'][$type->key][$key]; @endphp

                                        <td @class([
                                                'is-center',
                                                'is-today' => $key === $today,
                                                'is-full' => $free === 0,
                                                'is-over' => $free < 0,
                                            ])
                                            @if ($free < 0) title="Oversold by {{ abs($free) }} room(s)" @endif>{{ $free }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="home" /></span>
                    <strong>No rooms yet</strong>
                    <p>Add rooms in Masters → Room and this fills in on its own.</p>
                </div>
            @endif
        </x-card>
    </div>

    <div class="nv-mt">
        <x-alert tone="info" title="How these are counted">
            {{-- <b>, not <strong>: the alert styles its title strong as a block. --}}
            A night belongs to the arrival date, not the checkout date — a guest leaving on the 9th
            frees that room for the 9th. So <b>Occupancy</b> is arrivals plus guests staying on,
            departures are not counted, and <b>Position</b> is Available − Occupancy − blocks.
            Waiting-list bookings hold no room, so they never come off Position. A red negative
            means more rooms are promised than the house has.
        </x-alert>
    </div>
@endsection
