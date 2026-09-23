@extends('layouts.app')

@section('title', 'Room Service Orders')

@php
    $columns = [
        'Date' => fn ($l) => e(\Carbon\CarbonImmutable::parse($l->charge_date)->format('d M Y, h:i A')),
        'Order' => fn ($l) => e($l->order_no)
            . ($l->outlet_name ? '<span class="nv-sub">' . e($l->outlet_name) . '</span>' : ''),
        'Item' => fn ($l) => '<strong>' . e($l->item_name) . '</strong>',
        'Guest' => fn ($l) => e($l->guest_name ?: '—'),
    ];
@endphp

@section('content')
    <x-page-header
        title="Room Service Orders"
        subtitle="What the kitchen sent up, room by room"
        :crumbs="['Home' => url('/'), 'Point Of Sale' => route('point-of-sale.dashboard'), 'Room Service Orders']"
    >
        <x-slot:actions>
            <a href="{{ route('point-of-sale.room-service-orders.export', array_filter([
                'from' => $from, 'to' => $to, 'room' => $room, 'outlet' => $outlet,
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

                <x-field label="Outlet" name="outlet" for="outlet">
                    <select name="outlet" id="outlet" class="nv-select">
                        <option value="">Every outlet</option>
                        @foreach ($outlets as $row)
                            <option value="{{ $row->id }}" @selected($outlet === $row->id)>{{ $row->name }}</option>
                        @endforeach
                    </select>
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

                <a href="{{ route('point-of-sale.room-service-orders') }}" class="nv-btn nv-btn-ghost">Reset</a>
            </form>

            <p class="nv-help">
                Cancelled orders are left out. An order shows here the moment it is taken — whether
                the bill was paid at the door or signed to the room is a separate question, and the
                answer to that one is on Invoices.
            </p>
        </x-card>
    </div>

    <div class="nv-grid nv-grid-3 nv-mt">
        <x-stat label="Rooms that ordered" :value="$grand['rooms']" icon="home" />
        <x-stat label="Items sent up" :value="$grand['lines']" icon="bag" />
        <x-stat label="Value" :value="'₹ ' . number_format($grand['total'], 2)" icon="wallet" tone="success" />
    </div>

    @include('reports.partials.room-table', ['columns' => $columns])
@endsection
