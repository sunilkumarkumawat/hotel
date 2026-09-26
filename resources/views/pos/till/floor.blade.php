@extends('layouts.app')

@section('title', 'POS · ' . $outlet->name)

@php
    $total = collect($summary)->sum();
    $canAdd = can_do('point-of-sale/pos', 'add');
@endphp

@section('content')
    <x-page-header
        :title="$outlet->name"
        :subtitle="$mode === 'room' ? 'Room service — rooms with a guest in them' : 'Tap a table to start or open its bill'"
        :crumbs="['Home' => url('/'), 'Point Of Sale' => route('point-of-sale.dashboard'), 'POS']"
    >
        <x-slot:actions>
            @foreach ($counterTypes as $type)
                @if ($canAdd)
                    <form method="POST" action="{{ route('point-of-sale.pos.counter') }}">
                        @csrf
                        <input type="hidden" name="outlet" value="{{ $outlet->id }}" />
                        <input type="hidden" name="order_type" value="{{ $type }}" />
                        <button type="submit" class="nv-btn nv-btn-outline">
                            <x-icon name="plus" /> {{ \App\Models\Pos\PosOrder::TYPES[$type] }}
                        </button>
                    </form>
                @endif
            @endforeach
        </x-slot:actions>
    </x-page-header>

    @include('pos.till.partials.nav', ['current' => 'floor'])

    @if (session('status'))
        <div class="nv-mt"><x-alert tone="success">{{ session('status') }}</x-alert></div>
    @endif

    @if (session('error'))
        <div class="nv-mt"><x-alert tone="danger" title="Not done">{{ session('error') }}</x-alert></div>
    @endif

    {{--
        The legend and the counts are the same thing said twice on purpose: the
        colours are how you find a table across the room, the words are how you
        are sure you read them right — and how the screen still works for
        somebody who cannot tell the two greens apart.
    --}}
    <div class="nv-mt nv-floor-strip">
        @foreach ($states as $key => $label)
            @continue($key === 'blocked')
            <span class="nv-floor-key is-{{ $key }}">
                <i></i>
                <b>{{ $summary[$key] ?? 0 }}</b>
                {{ $label }}
            </span>
        @endforeach

        <span class="nv-floor-key is-total">
            <b>{{ $total }}</b> {{ $mode === 'room' ? 'rooms occupied' : 'tables' }}
        </span>
    </div>

    @forelse ($sections as $section)
        <div class="nv-mt">
            <x-card>
                @if ($section->group)
                    <x-slot:title>{{ $section->group->name }}</x-slot:title>
                    <x-slot:actions>
                        <span class="nv-muted">{{ $section->group->kindLabel() }}s · {{ $section->tables->count() }}</span>
                    </x-slot:actions>
                @else
                    <x-slot:title>Rooms</x-slot:title>
                    <x-slot:actions>
                        <span class="nv-muted">{{ $section->tables->count() }} with a guest in</span>
                    </x-slot:actions>
                @endif

                @if ($section->tables->isEmpty())
                    <div class="nv-empty">
                        <span class="nv-empty-icon"><x-icon name="grid" /></span>
                        <strong>Nothing here</strong>
                        <p>
                            {{ $mode === 'room'
                                ? 'No room has a guest in it right now.'
                                : 'Add tables to this section under Setup → Tables.' }}
                        </p>
                    </div>
                @else
                    <div class="nv-tiles">
                        @foreach ($section->tables as $tile)
                            @include('pos.till.partials.tile', ['tile' => $tile])
                        @endforeach
                    </div>
                @endif
            </x-card>
        </div>
    @empty
        <div class="nv-mt">
            <x-card>
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="grid" /></span>
                    <strong>{{ $outlet->name }} has no seating yet</strong>
                    <p>Sections and tables are set up under Setup → Tables.</p>
                    @canView('point-of-sale/setup/tables')
                        <a href="{{ route('point-of-sale.setup.tables') }}" class="nv-btn nv-btn-primary">
                            <x-icon name="cog" /> Set up tables
                        </a>
                    @endCanView
                </div>
            </x-card>
        </div>
    @endforelse

    {{--
        Shift dialog. One form for every table on the screen — the button that
        opened it writes the order id in, so the page carries one dialog rather
        than one per tile.
    --}}
    @canEdit('point-of-sale/pos')
        <div class="nv-modal-backdrop" data-modal="shift">
            <div class="nv-modal" role="dialog" aria-modal="true" aria-labelledby="shift-title">
                <div class="nv-modal-head">
                    <strong id="shift-title">Move <span data-shift-name>this table</span></strong>
                    <button type="button" class="nv-icon-btn" data-modal-close aria-label="Close">
                        <x-icon name="x" />
                    </button>
                </div>

                <form method="POST" action="{{ route('point-of-sale.pos.order.shift', 0) }}" data-shift-form>
                    @csrf

                    <x-field label="Move to" name="pos_table_id" required>
                        <select name="pos_table_id" id="pos_table_id" class="nv-select" required>
                            <option value="">Choose a free table</option>
                            @foreach ($sections as $section)
                                @if ($section->group)
                                    <optgroup label="{{ $section->group->name }}">
                                        @foreach ($section->tables as $tile)
                                            @if ($tile->state === 'free')
                                                <option value="{{ $tile->id }}">{{ $tile->name }}</option>
                                            @endif
                                        @endforeach
                                    </optgroup>
                                @endif
                            @endforeach
                        </select>
                    </x-field>

                    <p class="nv-help">
                        A table that already has an order running on it is not offered — two bills
                        becoming one is a different decision, and it is not made by accident here.
                    </p>

                    <div class="nv-actions" style="justify-content:flex-end;margin-top:16px">
                        <button type="button" class="nv-btn nv-btn-ghost" data-modal-close>Cancel</button>
                        <button type="submit" class="nv-btn nv-btn-primary">
                            <x-icon name="arrow-right" /> Move
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endCanEdit
@endsection

@push('scripts')
    <script src="{{ asset('js/pos-till.js') }}?v={{ filemtime(public_path('js/pos-till.js')) }}" defer></script>

    @if (session('print'))
        {{-- Settling sends the cashier back here, so the receipt has to be
             opened from this screen or it never opens at all. --}}
        <script>window.open(@json(session('print') . '?auto=1'), '_blank');</script>
    @endif
@endpush
