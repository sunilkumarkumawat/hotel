@extends('layouts.app')

@section('title', 'House Keeping Status')

@php
    // What a room is doing, and what housekeeping has it marked as, are two
    // different things — the legend carries both because the supervisor
    // reads them together.
    $stateLabels = [
        'occupied' => 'Occupied',
        'reserved' => 'Reserved',
        'dnr' => 'DNR',
        'blocked' => 'Blocked',
        'available' => 'Available',
    ];
@endphp

@section('content')
    <x-page-header
        title="House Keeping Status"
        subtitle="Tick the rooms, pick what to do with them, press Update."
        :crumbs="['Home' => url('/'), 'House Keeping', 'Status']"
    >
        <x-slot:actions>
            @canView('front-office/room-calendar')
                <a href="{{ route('front-office.room-calendar') }}" class="nv-btn nv-btn-outline">
                    <x-icon name="grid" /> Room Calendar
                </a>
            @endCanView
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
        <x-stat label="Rooms" :value="$counts['rooms']" icon="home" />
        <x-stat label="To clean" :value="$counts['dirty']" icon="alert" tone="warning" />
        <x-stat label="Occupied" :value="$counts['occupied']" icon="users" tone="success" />
        <x-stat label="Nobody assigned" :value="$counts['unassigned']" icon="user" tone="info" />
    </div>

    {{-- ── Filters ───────────────────────────────────────────────────────── --}}
    <div class="nv-mt">
        <x-card>
            <form method="GET" class="nv-toolbar">
                <div class="nv-field-search">
                    <x-icon name="search" />
                    <input type="search" name="q" value="{{ $filters['q'] }}" class="nv-input" placeholder="Room no…" />
                </div>

                <select name="category" class="nv-select" style="width:170px">
                    <option value="">Room Category</option>
                    @foreach ($categories as $id => $name)
                        <option value="{{ $id }}" @selected($filters['category'] === $id)>{{ $name }}</option>
                    @endforeach
                </select>

                <select name="floor" class="nv-select" style="width:140px">
                    <option value="">Floor By</option>
                    @foreach ($floors as $floor)
                        <option value="{{ $floor }}" @selected($filters['floor'] === $floor)>{{ $floor }}</option>
                    @endforeach
                </select>

                <select name="status" class="nv-select" style="width:160px">
                    <option value="">All statuses</option>
                    @foreach ($allStatuses as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>

                <input type="date" name="date" value="{{ $date }}" class="nv-input" style="width:160px" />

                <label class="nv-check-inline">
                    <input type="checkbox" name="to_do" value="1" class="nv-check" @checked($filters['to_do']) />
                    Only rooms to clean
                </label>

                <button type="submit" class="nv-btn nv-btn-outline"><x-icon name="filter" /> Search</button>

                <a href="{{ route('house-keeping.status') }}" class="nv-btn nv-btn-ghost">Reset</a>
            </form>
        </x-card>
    </div>

    {{-- ── The list and the bulk action ──────────────────────────────────── --}}
    <form method="POST" action="{{ route('house-keeping.status.update') }}" data-hk>
        @csrf

        @canEdit('house-keeping/status')
            <div class="nv-mt">
                <x-card>
                    <div class="nv-form-grid nv-grid-4">
                        <x-field label="Set Housekeeping Status" name="action" required>
                            <select name="action" class="nv-select" data-hk-action>
                                <option value="">Select Option</option>
                                @foreach ($actions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('action') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </x-field>

                        {{-- Each action reveals only the field it needs. --}}
                        <x-field label="Assign Status To" name="housekeeping_status" data-hk-for="status">
                            <x-select name="housekeeping_status" :options="$statuses"
                                      :selected="old('housekeeping_status')" placeholder="Select Option" />
                        </x-field>

                        <x-field label="Assign Status To" name="housekeeper_id" data-hk-for="assign">
                            <x-select name="housekeeper_id" :options="$housekeepers->all()"
                                      :selected="old('housekeeper_id')" placeholder="Select Option" />
                        </x-field>

                        <x-field label="Remark" name="remark" data-hk-for="status">
                            <x-input name="remark" :value="old('remark')" placeholder="Optional" />
                        </x-field>

                        {{-- Plain markup, not <x-field>: that component escapes its
                             label, so a blank spacer has to be written by hand. --}}
                        <div class="nv-field">
                            <span class="nv-label" aria-hidden="true">&nbsp;</span>

                            <button type="submit" class="nv-btn nv-btn-primary">
                                <x-icon name="check" /> Update
                            </button>
                        </div>
                    </div>

                    <p class="nv-help" data-hk-count>No rooms ticked.</p>
                </x-card>
            </div>
        @endCanEdit

        <div class="nv-mt">
            <x-card flush>
                @if ($rooms->count())
                    <div class="nv-table-wrap">
                        <table class="nv-table nv-table-compact">
                            <thead>
                                <tr>
                                    @canEdit('house-keeping/status')
                                        <th style="width:44px">
                                            <input type="checkbox" class="nv-check" data-hk-all
                                                   aria-label="Tick every room" />
                                        </th>
                                    @endCanEdit
                                    <th>Room No.</th>
                                    <th class="is-num">Pax</th>
                                    <th>Room Category</th>
                                    <th>Status</th>
                                    <th>Available</th>
                                    <th>Remarks</th>
                                    <th>Name</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($rooms as $room)
                                    @php $o = $occupancy[$room->id]; @endphp

                                    <tr class="nv-hk-row is-{{ $o['state'] }}">
                                        @canEdit('house-keeping/status')
                                            <td>
                                                <input type="checkbox" name="rooms[]" value="{{ $room->id }}"
                                                       class="nv-check" data-hk-room
                                                       aria-label="Room {{ $room->room_no }}" />
                                            </td>
                                        @endCanEdit

                                        <td>
                                            <strong class="nv-mono">{{ $room->room_no }}</strong>
                                            @if ($room->floor)
                                                <span class="nv-sub">Floor {{ $room->floor }}</span>
                                            @endif
                                        </td>

                                        <td class="is-num">{{ $o['pax'] }}</td>

                                        <td>
                                            {{ $room->category?->name ?? '—' }}
                                            <span class="nv-sub">{{ $room->type?->name }}</span>
                                        </td>

                                        <td>
                                            <span class="nv-hk-dot is-{{ str_replace('_', '-', $room->housekeeping_status) }}"></span>
                                            {{ $allStatuses[$room->housekeeping_status] ?? $room->housekeeping_status }}
                                        </td>

                                        <td>
                                            @if ($o['state'] === 'available')
                                                <x-badge tone="success">Available</x-badge>
                                            @else
                                                <x-badge tone="{{ $o['state'] === 'occupied' ? 'danger' : 'warning' }}">
                                                    {{ $stateLabels[$o['state']] }}
                                                </x-badge>

                                                @if ($o['guest'])
                                                    <span class="nv-sub">{{ $o['guest'] }}</span>
                                                @endif
                                            @endif
                                        </td>

                                        <td class="nv-muted">{{ $room->housekeeping_remark ?: '—' }}</td>

                                        <td>
                                            @if ($room->housekeeper)
                                                <strong>{{ $room->housekeeper->name }}</strong>
                                            @else
                                                <span class="nv-muted">Not assigned</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="nv-empty">
                        <span class="nv-empty-icon"><x-icon name="check-circle" /></span>
                        <strong>{{ $filters['to_do'] ? 'Nothing to clean' : 'No rooms match' }}</strong>
                        <p>
                            {{ $filters['to_do']
                                ? 'Every room in this filter is made up.'
                                : 'Clear the filters, or add rooms in Masters → Room.' }}
                        </p>
                    </div>
                @endif

                <x-slot:footer>
                    <div class="nv-legend">
                        @foreach (['occupied' => 'Occupied', 'reserved' => 'Reserved', 'dnr' => 'DNR', 'blocked' => 'Blocked'] as $key => $label)
                            <span class="nv-legend-item">
                                <span class="nv-legend-key is-hk-{{ $key }}"></span>{{ $label }}
                            </span>
                        @endforeach

                        @foreach ($allStatuses as $key => $label)
                            <span class="nv-legend-item">
                                <span class="nv-hk-dot is-{{ str_replace('_', '-', $key) }}"></span>{{ $label }}
                            </span>
                        @endforeach
                    </div>
                </x-slot:footer>
            </x-card>
        </div>
    </form>
@endsection

@push('scripts')
@canEdit('house-keeping/status')
<script>
(function () {
    'use strict';

    var form = document.querySelector('[data-hk]');
    if (!form) return;

    var action = form.querySelector('[data-hk-action]');
    var all = form.querySelector('[data-hk-all]');
    var boxes = Array.prototype.slice.call(form.querySelectorAll('[data-hk-room]'));
    var count = form.querySelector('[data-hk-count]');

    /* Each action needs a different field, so show only that one. */
    function syncAction() {
        Array.prototype.slice.call(form.querySelectorAll('[data-hk-for]')).forEach(function (field) {
            var wanted = field.getAttribute('data-hk-for') === action.value;

            field.hidden = !wanted;

            if (!wanted) {
                field.querySelectorAll('select, input').forEach(function (el) { el.value = ''; });
            }
        });
    }

    function syncCount() {
        var picked = boxes.filter(function (b) { return b.checked; }).length;

        count.textContent = picked
            ? picked + ' room(s) ticked.'
            : 'No rooms ticked.';

        if (all) {
            all.checked = picked > 0 && picked === boxes.length;
            all.indeterminate = picked > 0 && picked < boxes.length;
        }
    }

    action.addEventListener('change', syncAction);
    syncAction();

    boxes.forEach(function (box) { box.addEventListener('change', syncCount); });

    if (all) {
        all.addEventListener('change', function () {
            boxes.forEach(function (box) { box.checked = all.checked; });
            syncCount();
        });
    }

    syncCount();

    // Saving with nothing ticked, or no action picked, would be a no-op the
    // server has to refuse — say so here instead.
    form.addEventListener('submit', function (event) {
        if (!action.value) {
            event.preventDefault();
            alert('Pick what you want to do under Set Housekeeping Status.');

            return;
        }

        if (!boxes.some(function (b) { return b.checked; })) {
            event.preventDefault();
            alert('Tick the rooms you want to update first.');
        }
    });
})();
</script>
@endCanEdit
@endpush
