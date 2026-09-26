@extends('layouts.app')

@section('title', 'POS Setup')

@section('content')
    <x-page-header
        title="Setup"
        subtitle="Everything a till has to be told before it can sell anything. Outlets first — nothing else can be made without one."
        :crumbs="['Home' => url('/'), 'Point Of Sale', 'Setup']"
    />

    <div class="nv-grid nv-grid-3">
        @foreach ($cards as $card)
            @if ($card['route'])
                <a href="{{ route($card['route']) }}" class="nv-master-card">
                    <span class="nv-matrix-icon"><x-icon :name="$card['icon']" /></span>

                    <div class="nv-master-body">
                        <strong>{{ $card['label'] }}</strong>
                        <p>{{ $card['intro'] }}</p>
                    </div>

                    <x-badge :tone="$card['count'] ? 'success' : 'warning'" plain>
                        {{ $card['count'] ?: 'empty' }}
                    </x-badge>
                </a>
            @else
                {{-- Listed rather than hidden: a blank space in a setup index
                     reads as a bug, a Soon pill reads as a plan. --}}
                <div class="nv-master-card is-soon" aria-disabled="true">
                    <span class="nv-matrix-icon"><x-icon :name="$card['icon']" /></span>

                    <div class="nv-master-body">
                        <strong>{{ $card['label'] }}</strong>
                        <p>{{ $card['intro'] }}</p>
                    </div>

                    <x-badge tone="warning" plain>Soon</x-badge>
                </div>
            @endif
        @endforeach
    </div>

    @if ($cards->isEmpty())
        <div class="nv-mt">
            <x-alert tone="info" title="Nothing to set up here">
                Your user has no Point Of Sale setup permissions yet. An administrator can grant them
                under Administration → Users.
            </x-alert>
        </div>
    @endif
@endsection
