@extends('layouts.app')

@section('title', 'Kitchen Display System')

@php
    $grouped = collect($tickets)->groupBy('status');
@endphp

@section('content')
    <x-page-header
        title="Kitchen Display System"
        :subtitle="'Tickets move New → Preparing → Ready. Anything past ' . config('pms.kds_warn_minutes', 10) . ' minutes starts saying so.'"
        :crumbs="['Home' => url('/'), 'Point Of Sale' => route('point-of-sale.dashboard'), 'Kitchen']"
    >
        <x-slot:actions>
            <a href="{{ route('point-of-sale.pos', $outlet ? ['outlet' => $outlet->id] : []) }}"
               class="nv-btn nv-btn-outline">
                <x-icon name="grid" /> Till
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="nv-mt">
        <x-card>
            <form method="GET" class="nv-toolbar">
                <x-field label="Outlet" name="outlet" for="outlet">
                    <select name="outlet" id="outlet" class="nv-select" data-autosubmit>
                        <option value="">Every outlet</option>
                        @foreach ($outlets as $row)
                            <option value="{{ $row->id }}" @selected($outlet?->id === $row->id)>{{ $row->name }}</option>
                        @endforeach
                    </select>
                </x-field>

                <x-field label="Department" name="department" for="department">
                    <select name="department" id="department" class="nv-select" data-autosubmit>
                        <option value="">Every department</option>
                        @foreach ($departments as $row)
                            <option value="{{ $row->id }}" @selected($department?->id === $row->id)>{{ $row->name }}</option>
                        @endforeach
                    </select>
                </x-field>

                <noscript>
                    <button type="submit" class="nv-btn nv-btn-primary">Show</button>
                </noscript>

                {{-- The clock keeps running whatever happens to the network; this
                     only says whether the list of tickets is current. --}}
                <span class="nv-kds-pulse" data-kds-pulse>
                    <i></i> <b data-kds-pulse-text>Live</b>
                </span>
            </form>
        </x-card>
    </div>

    @if (session('status'))
        <div class="nv-mt"><x-alert tone="success">{{ session('status') }}</x-alert></div>
    @endif

    <div class="nv-kds"
         data-kds
         data-kds-feed="{{ route('point-of-sale.kitchen-display.feed', array_filter([
             'outlet' => $outlet?->id,
             'department' => $department?->id,
         ])) }}"
         data-kds-refresh="{{ $refresh }}"
         data-kds-warn="{{ $warnAt }}"
         data-kds-late="{{ $lateAt }}"
         data-kds-advance="{{ route('point-of-sale.kitchen-display.advance') }}"
         data-kds-token="{{ csrf_token() }}"
         data-kds-can-edit="{{ can_do('point-of-sale/kitchen-display', 'edit') ? '1' : '0' }}">

        @foreach ($columns as $key => $label)
            <section class="nv-kds-col is-{{ $key }}" data-kds-col="{{ $key }}">
                <header class="nv-kds-col-head">
                    <h2>{{ $label }}</h2>
                    <span class="nv-kds-count" data-kds-count>{{ $grouped->get($key, collect())->count() }}</span>
                </header>

                <div class="nv-kds-list" data-kds-list>
                    @forelse ($grouped->get($key, collect()) as $ticket)
                        @include('pos.kitchen.partials.ticket', ['ticket' => $ticket])
                    @empty
                        <p class="nv-kds-empty">Nothing here.</p>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>

    @if (empty($tickets) || count($tickets) === 0)
        <div class="nv-mt">
            <x-card>
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="check-circle" /></span>
                    <strong>The kitchen is clear</strong>
                    <p>
                        Tickets appear here the moment a till sends a KOT. This screen keeps itself up
                        to date — leave it open.
                    </p>
                </div>
            </x-card>
        </div>
    @endif
@endsection

@push('scripts')
    <script src="{{ asset('js/pos-kds.js') }}?v={{ filemtime(public_path('js/pos-kds.js')) }}" defer></script>
@endpush
