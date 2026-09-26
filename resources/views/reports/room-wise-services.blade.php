@extends('layouts.app')

@section('title', 'Room Wise Services')

@php
    // The middle columns for this report. The shared table draws the rest.
    $columns = [
        'Date' => fn ($l) => e(\Carbon\CarbonImmutable::parse($l->charge_date)->format('d M Y')),
        'Guest' => fn ($l) => e($l->guest_name) . '<span class="nv-sub">' . e($l->folio_no) . '</span>',
        'Particulars' => fn ($l) => '<strong>' . e($l->particulars) . '</strong>'
            . ($l->service_name ? '<span class="nv-sub">' . e($l->service_name) . '</span>' : ''),
        'Type' => fn ($l) => e($types[$l->charge_type] ?? ucfirst((string) $l->charge_type)),
    ];
@endphp

@section('content')
    <x-page-header
        title="Room Wise Services"
        subtitle="Everything charged to a room that is not the room itself — laundry, pickups, extra beds, sundries"
        :crumbs="['Home' => url('/'), 'Front Office', 'Room Wise Services']"
    >
        <x-slot:actions>
            <a href="{{ route('front-office.room-wise-services.export', array_filter([
                'from' => $from, 'to' => $to, 'room' => $room,
            ])) }}" class="nv-btn nv-btn-outline">
                <x-icon name="download" /> Export
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="nv-mt">
        <x-card>
            <form method="GET" class="nv-toolbar">
                <x-field label="From" name="from" for="from">
                    <input type="date" name="from" id="from" value="{{ $from }}" class="nv-input" />
                </x-field>

                <x-field label="To" name="to" for="to">
                    <input type="date" name="to" id="to" value="{{ $to }}" class="nv-input" />
                </x-field>

                <x-field label="Room" name="room" for="room">
                    <select name="room" id="room" class="nv-select">
                        <option value="">Every room</option>
                        @foreach ($rooms as $id => $number)
                            <option value="{{ $id }}" @selected($room === $id)>{{ $number }}</option>
                        @endforeach
                    </select>
                </x-field>

                <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="filter" /> Show</button>

                <a href="{{ route('front-office.room-wise-services') }}" class="nv-btn nv-btn-ghost">Reset</a>
            </form>

            <p class="nv-help">
                Room nights are left out on purpose — this report is about what a room spends
                <em>beyond</em> its rent. Food sent up on the POS is on Room Service Orders.
            </p>
        </x-card>
    </div>

    <div class="nv-grid nv-grid-3 nv-mt">
        <x-stat label="Rooms that spent" :value="$grand['rooms']" icon="home" />
        <x-stat label="Charges" :value="$grand['lines']" icon="layers" />
        <x-stat label="Value" :value="'₹ ' . number_format($grand['total'], 2)" icon="wallet" tone="success" />
    </div>

    @include('reports.partials.room-table', ['columns' => $columns])
@endsection
