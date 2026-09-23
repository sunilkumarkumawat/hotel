@extends('layouts.app')

@section('title', 'Housekeeping Board')

@php
    /*
        Two views of one board. The tab is a query string rather than two routes
        because it is the same data, the same permission and the same filters —
        and a supervisor flicking between them should not lose the floor they
        had narrowed to.
    */
    $tab = fn (string $name) => request()->fullUrlWithQuery(['view' => $name]);

    $stageTone = [
        'occupied' => 'info',
        'dirty' => 'danger',
        'cleaning' => 'warning',
        'ready' => 'success',
        'blocked' => 'muted',
    ];
@endphp

@section('content')
    <x-page-header
        title="Housekeeping Board"
        subtitle="The house as work — what is left to do, and who is on it."
        :crumbs="['Home' => url('/'), 'House Keeping', 'Board']"
    >
        <x-slot:actions>
            <div class="nv-tabs">
                <a href="{{ $tab('rooms') }}" @class(["nv-tab", "is-active" => $view === "rooms"])>
                    <x-icon name="grid" /> Room View
                </a>
                <a href="{{ $tab('pipeline') }}" @class(["nv-tab", "is-active" => $view === "pipeline"])>
                    <x-icon name="layers" /> Pipeline View
                </a>
            </div>
        </x-slot:actions>
    </x-page-header>

    <div class="nv-grid nv-grid-4">
        <x-stat label="Occupied" :value="$counts['occupied']" icon="users" tone="info" />
        <x-stat label="Dirty" :value="$counts['dirty']" icon="alert" tone="danger"
                :caption="$counts['departing'] . ' leaving today'" />
        <x-stat label="Being cleaned" :value="$counts['cleaning']" icon="refresh" tone="warning" />
        <x-stat label="Ready to sell" :value="$counts['ready']" icon="check-circle" tone="success"
                :caption="$counts['arriving'] . ' arriving today'" />
    </div>

    {{-- ── Filters ────────────────────────────────────────────────────────── --}}
    <div class="nv-mt">
        <x-card>
            <form method="GET" class="nv-toolbar">
                <input type="hidden" name="view" value="{{ $view }}" />

                <div class="nv-field-search">
                    <x-icon name="search" />
                    <input type="search" name="q" value="{{ $filters['q'] }}" class="nv-input" placeholder="Room number…" />
                </div>

                <input type="date" name="date" value="{{ $date }}" class="nv-input" style="width:160px" aria-label="Date" />

                <select name="type" class="nv-select" style="width:170px" aria-label="Room type">
                    <option value="">Every type</option>
                    @foreach ($types as $id => $name)
                        <option value="{{ $id }}" @selected($filters['type'] === $id)>{{ $name }}</option>
                    @endforeach
                </select>

                <select name="floor" class="nv-select" style="width:140px" aria-label="Floor">
                    <option value="">Every floor</option>
                    @foreach ($floors as $floor)
                        <option value="{{ $floor }}" @selected($filters['floor'] === (string) $floor)>Floor {{ $floor }}</option>
                    @endforeach
                </select>

                <select name="housekeeper" class="nv-select" style="width:170px" aria-label="Housekeeper">
                    <option value="">Anyone</option>
                    @foreach ($housekeepers as $id => $name)
                        <option value="{{ $id }}" @selected($filters['housekeeper'] === $id)>{{ $name }}</option>
                    @endforeach
                </select>

                <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="filter" /> Show</button>
                <a href="{{ route('house-keeping.board', ['view' => $view]) }}" class="nv-btn nv-btn-ghost">Reset</a>
            </form>
        </x-card>
    </div>

    @if ($view === 'rooms')
        {{-- ══ Room View ═══════════════════════════════════════════════════ --}}
        <div class="nv-mt nv-hkb"
             data-hk-board
             data-move="{{ route('house-keeping.board.move') }}">
            @forelse ($byType as $typeName => $tiles)
                <x-card flush>
                    <x-slot:title>{{ $typeName }}</x-slot:title>

                    <x-slot:actions>
                        <span class="nv-muted">
                            {{ $tiles->count() }} room(s) ·
                            {{ $tiles->where('stage', 'ready')->count() }} ready ·
                            {{ $tiles->where('stage', 'dirty')->count() }} dirty
                        </span>
                    </x-slot:actions>

                    <div class="nv-hkb-grid">
                        @foreach ($tiles as $tile)
                            @include('house-keeping.partials.board-tile', ['tile' => $tile, 'compact' => false])
                        @endforeach
                    </div>
                </x-card>
            @empty
                <x-card>
                    <div class="nv-empty">
                        <span class="nv-empty-icon"><x-icon name="home" /></span>
                        <strong>No rooms match</strong>
                        <p>Clear the filters, or add rooms under Masters → Room.</p>
                    </div>
                </x-card>
            @endforelse
        </div>
    @else
        {{-- ══ Pipeline View ═══════════════════════════════════════════════ --}}
        <div class="nv-mt nv-hkb-pipe"
             data-hk-board
             data-move="{{ route('house-keeping.board.move') }}">
            @foreach ($stages as $stage => $label)
                @php $column = $byStage[$stage] ?? collect(); @endphp

                <section class="nv-hkb-col is-{{ $stage }}"
                         data-hk-column="{{ $stage }}"
                         @if (! in_array($stage, ['dirty'], true)) data-no-drop @endif>
                    <header class="nv-hkb-col-head">
                        <h3>{{ $label }}</h3>
                        <span class="nv-hkb-count">{{ $column->count() }}</span>
                    </header>

                    <p class="nv-hkb-hint">
                        @switch($stage)
                            @case('occupied') A guest is in it. Drag one here to Dirty when they leave. @break
                            @case('dirty') Waiting for somebody. Press Start to begin. @break
                            @case('cleaning') Being made up now — these cannot be dragged. @break
                            @case('ready') Clean, empty and sellable. @break
                        @endswitch
                    </p>

                    <div class="nv-hkb-col-body">
                        @forelse ($column as $tile)
                            @include('house-keeping.partials.board-tile', ['tile' => $tile, 'compact' => true])
                        @empty
                            <p class="nv-hkb-empty">Nothing here.</p>
                        @endforelse
                    </div>

                    @if ($column->isNotEmpty())
                        @canEdit('house-keeping/board')
                            <form method="POST" action="{{ route('house-keeping.board.assign') }}" class="nv-hkb-col-foot">
                                @csrf

                                @foreach ($column as $tile)
                                    <input type="hidden" name="rooms[]" value="{{ $tile['id'] }}" />
                                @endforeach

                                <select name="housekeeper_id" class="nv-select nv-input-sm" required aria-label="Give this column to">
                                    <option value="">Give all {{ $column->count() }} to…</option>
                                    @foreach ($housekeepers as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </select>

                                <button type="submit" class="nv-btn nv-btn-soft nv-btn-sm">
                                    <x-icon name="user" /> Assign
                                </button>
                            </form>
                        @endCanEdit
                    @endif
                </section>
            @endforeach
        </div>

        @if (($byStage['blocked'] ?? collect())->isNotEmpty())
            <div class="nv-mt">
                <x-card flush>
                    <x-slot:title>Out of order</x-slot:title>
                    <x-slot:actions>
                        <span class="nv-muted">
                            Held by a work order or a block — housekeeping cannot put these back on sale.
                        </span>
                    </x-slot:actions>

                    <div class="nv-hkb-grid">
                        @foreach ($byStage['blocked'] as $tile)
                            @include('house-keeping.partials.board-tile', ['tile' => $tile, 'compact' => true])
                        @endforeach
                    </div>
                </x-card>
            </div>
        @endif
    @endif

    {{-- ── What just happened ─────────────────────────────────────────────── --}}
    @if ($recent->isNotEmpty())
        <div class="nv-mt">
            <x-card flush>
                <x-slot:title>Last dozen moves</x-slot:title>
                <x-slot:actions>
                    <span class="nv-muted">Every change is logged — including who made it.</span>
                </x-slot:actions>

                <div class="nv-table-wrap">
                    <table class="nv-table">
                        <thead>
                            <tr><th>When</th><th>Room</th><th>From</th><th>To</th><th>By</th><th>Remark</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($recent as $log)
                                <tr>
                                    <td>{{ $log->created_at?->format('d M, h:i A') }}</td>
                                    <td><strong>{{ $log->room?->room_no ?? '—' }}</strong></td>
                                    <td>{{ $log->from_label }}</td>
                                    <td>{{ $log->to_label }}</td>
                                    <td>{{ $log->user?->name ?? '—' }}</td>
                                    <td>{{ $log->remark ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>
    @endif
@endsection

@push('scripts')
    <script src="{{ asset('js/housekeeping-board.js') }}?v={{ file_exists(public_path('js/housekeeping-board.js')) ? filemtime(public_path('js/housekeeping-board.js')) : time() }}" defer></script>
@endpush
