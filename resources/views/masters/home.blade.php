@extends('layouts.app')

@section('title', 'Masters')

@section('content')
    <x-page-header
        title="Masters"
        subtitle="The lists every other screen picks from. Fill these in first — a reservation cannot be made without rooms and rates."
        :crumbs="['Home' => url('/'), 'Masters']"
    />

    <div class="nv-grid nv-grid-3">
        @foreach ($masters as $m)
            <a href="{{ route('masters.index', $m['key']) }}" class="nv-master-card">
                <span class="nv-matrix-icon"><x-icon :name="$m['icon']" /></span>

                <div class="nv-master-body">
                    <strong>{{ $m['label'] }}</strong>
                    <p>{{ $m['intro'] }}</p>
                </div>

                <x-badge :tone="$m['count'] ? 'success' : 'warning'" plain>
                    {{ $m['count'] ?: 'empty' }}
                </x-badge>
            </a>
        @endforeach
    </div>
@endsection
