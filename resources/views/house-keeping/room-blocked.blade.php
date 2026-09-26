@extends('layouts.app')

@section('title', 'Room Blocked')

@section('content')
    <x-page-header
        title="Room Blocked"
        subtitle="Rooms taken off sale. Tick the ones you want and press Block."
        :crumbs="['Home' => url('/'), 'House Keeping', 'Room Blocked']"
    >
        <x-slot:actions>
            @canView('front-office/room-calendar')
                <a href="{{ route('front-office.room-calendar', ['date' => $date]) }}" class="nv-btn nv-btn-outline">
                    <x-icon name="grid" /> Room Calendar
                </a>
            @endCanView

            @canAdd('house-keeping/room-blocked')
                <button type="button" class="nv-btn nv-btn-primary" data-open="bulk-block">
                    <x-icon name="layers" /> Bulk Block
                </button>
            @endCanAdd
        </x-slot:actions>
    </x-page-header>

    @if (session('error'))
        <div class="nv-mt"><x-alert tone="danger" title="Not saved">{{ session('error') }}</x-alert></div>
    @endif

    @if ($errors->any())
        <div class="nv-mt">
            <x-alert tone="danger" title="Please fix {{ $errors->count() }} thing(s)">{{ $errors->first() }}</x-alert>
        </div>
    @endif

    <div class="nv-grid nv-grid-4">
        <x-stat label="Rooms listed" :value="$counts['rooms']" icon="home" />
        <x-stat label="Blocked on this date" :value="$counts['blocked']" icon="lock" tone="danger" />
        <x-stat label="Maintenance" :value="$counts['maintenance']" icon="alert" tone="warning" />
        <x-stat label="Management" :value="$counts['management']" icon="shield" tone="info" />
    </div>

    {{-- ── Filters ───────────────────────────────────────────────────────── --}}
    <div class="nv-mt">
        <x-card>
            <form method="GET" class="nv-toolbar">
                <input type="date" name="date" value="{{ $date }}" class="nv-input" style="width:170px"
                       aria-label="Date" />

                <select name="category" class="nv-select" style="width:180px" aria-label="Room category">
                    <option value="">Select Category</option>
                    @foreach ($categories as $id => $name)
                        <option value="{{ $id }}" @selected($filters['category'] === $id)>{{ $name }}</option>
                    @endforeach
                </select>

                <select name="room" class="nv-select" style="width:160px" aria-label="Room number">
                    <option value="">Select Room No</option>
                    @foreach ($allRooms as $room)
                        <option value="{{ $room->id }}" @selected($filters['room'] === $room->id)>{{ $room->room_no }}</option>
                    @endforeach
                </select>

                <select name="floor" class="nv-select" style="width:140px" aria-label="Floor">
                    <option value="">All floors</option>
                    @foreach ($floors as $floor)
                        <option value="{{ $floor }}" @selected($filters['floor'] === $floor)>{{ $floor }}</option>
                    @endforeach
                </select>

                <select name="status" class="nv-select" style="width:150px" aria-label="Blocked or free">
                    <option value="">All rooms</option>
                    <option value="blocked" @selected($filters['status'] === 'blocked')>Blocked only</option>
                    <option value="open" @selected($filters['status'] === 'open')>Not blocked</option>
                </select>

                <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="search" /> Search</button>

                <a href="{{ route('house-keeping.room-blocked') }}" class="nv-btn nv-btn-ghost">Reset</a>
            </form>
        </x-card>
    </div>

    {{-- ── The list ──────────────────────────────────────────────────────── --}}
    <form method="POST" action="{{ route('house-keeping.room-blocked.store') }}" data-rb id="block-form">
        @csrf

        <div class="nv-mt">
            <x-card flush>
                @if ($mayPick)
                    {{-- The two buttons appear only when there is something for
                         them to do: Block when a free room is ticked, Release
                         when a blocked one is. Blocking and releasing are two
                         separate permissions, so a user may well have one
                         button and not the other. --}}
                    <div class="nv-rb-bar">
                        <span data-rb-count class="nv-muted">No rooms ticked.</span>

                        <div class="nv-actions" style="margin:0">
                            @canAdd('house-keeping/room-blocked')
                                <button type="button" class="nv-btn nv-btn-danger" data-rb-block hidden>
                                    <x-icon name="lock" /> Block
                                </button>
                            @endCanAdd

                            @canDelete('house-keeping/room-blocked')
                                <button type="submit" form="release-form" class="nv-btn nv-btn-outline"
                                        data-rb-release hidden>
                                    <x-icon name="check-circle" /> Release
                                </button>
                            @endCanDelete
                        </div>
                    </div>
                @endif

                @if ($rooms->count())
                    <div class="nv-table-wrap">
                        <table class="nv-table nv-table-compact">
                            <thead>
                                <tr>
                                    <th class="is-num" style="width:64px">Sr No.</th>
                                    @if ($mayPick)
                                        <th style="width:44px">
                                            <input type="checkbox" class="nv-check" data-rb-all
                                                   aria-label="Tick every room" />
                                        </th>
                                    @endif
                                    <th>Room No.</th>
                                    <th>Room Category</th>
                                    <th>Status</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($rooms as $room)
                                    @php
                                        $block = $blocks[$room->id] ?? null;
                                        $who = $busy[$room->id] ?? null;
                                    @endphp

                                    <tr class="nv-rb-row @if ($block) is-blocked @elseif ($who) is-busy @endif">
                                        <td class="is-num">{{ $loop->iteration }}</td>

                                        @if ($mayPick)
                                            <td>
                                                {{-- A room with a guest in it cannot be blocked, so it
                                                     does not get a tick box to raise hopes with. --}}
                                                @if ($who && ! $block)
                                                    <span class="nv-muted" title="{{ $who }}">—</span>
                                                @else
                                                    <input type="checkbox" name="rooms[]" value="{{ $room->id }}"
                                                           class="nv-check" data-rb-room
                                                           data-block="{{ $block?->id }}"
                                                           aria-label="Room {{ $room->room_no }}" />
                                                @endif
                                            </td>
                                        @endif

                                        <td>
                                            <strong class="nv-mono">{{ $room->room_no }}</strong>
                                            @if ($room->floor)
                                                <span class="nv-sub">Floor {{ $room->floor }}</span>
                                            @endif
                                        </td>

                                        <td>
                                            {{ $room->category?->name ?? '—' }}
                                            @if ($block)
                                                <span class="nv-sub">({{ $block->range }})</span>
                                            @else
                                                <span class="nv-sub">{{ $room->type?->name }}</span>
                                            @endif
                                        </td>

                                        <td>
                                            @if ($block)
                                                <x-badge tone="{{ $block->isMaintenance() ? 'warning' : 'info' }}">
                                                    {{ $block->type_label }}
                                                </x-badge>
                                            @elseif ($who)
                                                <x-badge tone="danger">Busy</x-badge>
                                                <span class="nv-sub">{{ $who }}</span>
                                            @else
                                                <span class="nv-hk-dot is-{{ str_replace('_', '-', $room->housekeeping_status) }}"></span>
                                                {{ strtoupper($statuses[$room->housekeeping_status] ?? $room->housekeeping_status) }}
                                            @endif
                                        </td>

                                        <td class="nv-muted">{{ $block?->reason ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="nv-empty">
                        <span class="nv-empty-icon"><x-icon name="lock" /></span>
                        <strong>No rooms match.</strong>
                        <p>Clear the filters, or add rooms in Masters → Room.</p>
                    </div>
                @endif

                <x-slot:footer>
                    <p class="nv-help" style="margin:0">
                        Everything on this screen is for <b>{{ \Carbon\CarbonImmutable::parse($date)->format('d M Y') }}</b>.
                        A block runs to the day the room comes back, so
                        {{ \Carbon\CarbonImmutable::parse($date)->format('d M') }} →
                        {{ \Carbon\CarbonImmutable::parse($tomorrow)->format('d M') }} is one night.
                        A room with a guest or a booking in it cannot be blocked — the screen says who has it.
                    </p>
                </x-slot:footer>
            </x-card>
        </div>
    </form>

    {{-- Release posts the block ids, not the room ids, so a room blocked twice
         over different weeks never loses the wrong one. Filled in by the
         script from whatever is ticked. --}}
    @canDelete('house-keeping/room-blocked')
        <form method="POST" action="{{ route('house-keeping.room-blocked.release') }}" id="release-form"
              data-rb-release-form hidden
              data-confirm="Those rooms go straight back on sale."
              data-confirm-title="Release the ticked rooms?"
              data-confirm-action="Release">
            @csrf
        </form>
    @endCanDelete

    {{-- ── Block the ticked rooms ────────────────────────────────────────── --}}
    @canAdd('house-keeping/room-blocked')
        <div class="nv-modal-backdrop" data-modal="block">
            <div class="nv-modal" role="dialog" aria-modal="true" aria-labelledby="block-title">
                <div class="nv-modal-head">
                    <strong id="block-title">Block rooms</strong>
                    <button type="button" class="nv-icon-btn" data-modal-close aria-label="Close"><x-icon name="x" /></button>
                </div>

                <p class="nv-help" data-rb-picked style="margin-top:0"></p>

                <div class="nv-form-grid nv-grid-2">
                    <x-field label="From Date" name="from_date" for="block_from_date" required>
                        <input type="date" name="from_date" id="block_from_date" form="block-form" class="nv-input"
                               value="{{ old('from_date', $date) }}" />
                    </x-field>

                    <x-field label="To Date" name="to_date" for="block_to_date" required help="The day the room comes back on sale.">
                        <input type="date" name="to_date" id="block_to_date" form="block-form" class="nv-input"
                               value="{{ old('to_date', $tomorrow) }}" />
                    </x-field>

                    <x-field label="Block Type" name="block_type" for="block_block_type" required
                             help="Maintenance also marks the room Repair in house keeping.">
                        <select name="block_type" id="block_block_type" form="block-form" class="nv-select">
                            @foreach ($types as $value => $label)
                                <option value="{{ $value }}" @selected(old('block_type') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </x-field>

                    <x-field label="Remarks" name="reason" for="block_reason">
                        <input type="text" name="reason" id="block_reason" form="block-form" class="nv-input"
                               value="{{ old('reason') }}" placeholder="Carpet being replaced" />
                    </x-field>
                </div>

                <div class="nv-actions" style="justify-content:flex-end;margin-top:16px">
                    <button type="button" class="nv-btn nv-btn-ghost" data-modal-close>Cancel</button>
                    <button type="submit" form="block-form" class="nv-btn nv-btn-danger">
                        <x-icon name="lock" /> Block these rooms
                    </button>
                </div>
            </div>
        </div>

        {{-- ── Bulk Block: a run of room numbers ─────────────────────────── --}}
        <div class="nv-modal-backdrop" data-modal="bulk-block">
            <div class="nv-modal" role="dialog" aria-modal="true" aria-labelledby="bulk-title">
                <div class="nv-modal-head">
                    <strong id="bulk-title">Bulk Block</strong>
                    <button type="button" class="nv-icon-btn" data-modal-close aria-label="Close"><x-icon name="x" /></button>
                </div>

                <form method="POST" action="{{ route('house-keeping.room-blocked.bulk') }}">
                    @csrf

                    <p class="nv-help" style="margin-top:0">
                        Everything from one room number to another, without ticking a thing —
                        a floor going under renovation. Rooms with a guest or a booking in them
                        are left alone and named afterwards.
                    </p>

                    <div class="nv-form-grid nv-grid-2">
                        <x-field label="From Room No." name="from_room" required>
                            <x-input name="from_room" list="rb-room-list" placeholder="201" />
                        </x-field>

                        <x-field label="To Room No." name="to_room" required>
                            <x-input name="to_room" list="rb-room-list" placeholder="210" />
                        </x-field>

                        <x-field label="From Date" name="from_date" required>
                            <x-input name="from_date" type="date" :value="old('from_date', $date)" />
                        </x-field>

                        <x-field label="To Date" name="to_date" required help="The day they come back on sale.">
                            <x-input name="to_date" type="date" :value="old('to_date', $tomorrow)" />
                        </x-field>

                        <x-field label="Block Type" name="block_type" required>
                            <x-select name="block_type" :options="$types" :selected="old('block_type', 'maintenance')" />
                        </x-field>

                        <x-field label="Remarks" name="reason">
                            <x-input name="reason" placeholder="Second floor renovation" />
                        </x-field>
                    </div>

                    <datalist id="rb-room-list">
                        @foreach ($allRooms as $room)
                            <option value="{{ $room->room_no }}"></option>
                        @endforeach
                    </datalist>

                    <div class="nv-actions" style="justify-content:flex-end;margin-top:16px">
                        <button type="button" class="nv-btn nv-btn-ghost" data-modal-close>Cancel</button>
                        <button type="submit" class="nv-btn nv-btn-danger">
                            <x-icon name="lock" /> Block the range
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endCanAdd
@endsection

@push('scripts')
<script src="{{ asset('js/room-blocked.js') }}?v={{ filemtime(public_path('js/room-blocked.js')) }}" defer></script>
@endpush
