@extends('layouts.app')

@section('title', 'Table Reservation - Slots')

@php
    $mayAdd = can_do('point-of-sale/setup/slots', 'add');
    $mayEdit = can_do('point-of-sale/setup/slots', 'edit');
    $mayDelete = can_do('point-of-sale/setup/slots', 'delete');
    $live = $slots->whereNull('deleted_at');
@endphp

@section('content')
    <x-page-header
        title="Table Reservation — Slots"
        subtitle="The sittings this outlet takes bookings for, and how many parties it will hold in each."
        :crumbs="['Home' => url('/'), 'Point Of Sale' => route('point-of-sale.setup'), 'Setup' => route('point-of-sale.setup'), 'Slots']"
    >
        <x-slot:actions>
            <a href="{{ route('point-of-sale.setup') }}" class="nv-btn nv-btn-outline">
                <x-icon name="chevron-left" /> Back to Setup
            </a>

            @if ($mayAdd && $outletId)
                <button type="button" class="nv-btn nv-btn-primary" data-slot-form data-outlet="{{ $outletId }}" data-max="0">
                    <x-icon name="plus" /> Create
                </button>
            @endif
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

    <div class="nv-grid nv-grid-3">
        <x-stat label="Slots" :value="$live->count()" icon="clock" />
        <x-stat label="Switched on" :value="$live->where('status', 1)->count()" icon="check-circle" tone="success" />
        <x-stat label="Bookings held" :value="$covers" icon="users" tone="info"
                caption="across every slot on this outlet" />
    </div>

    <div class="nv-mt">
        <x-card flush>
            <form method="GET" class="nv-toolbar">
                <label class="nv-check-inline" for="outlet">Outlet</label>

                <select name="outlet" id="outlet" class="nv-select" style="width:240px">
                    @forelse ($outlets as $outlet)
                        <option value="{{ $outlet->id }}" @selected($outletId === (int) $outlet->id)>{{ $outlet->name }}</option>
                    @empty
                        <option value="">No outlets yet</option>
                    @endforelse
                </select>

                <label class="nv-check-inline">
                    <input type="checkbox" name="deleted" value="1" @checked($showDeleted) />
                    Show deleted
                </label>

                <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="search" /> Search</button>
            </form>

            @if ($slots->count())
                <div class="nv-table-wrap">
                    <table class="nv-table">
                        <thead>
                            <tr>
                                <th>Slot</th>
                                <th class="is-num" style="width:180px">Max. Booking</th>
                                <th style="width:120px">Active</th>
                                <th class="is-end" style="width:190px">Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($slots as $slot)
                                <tr @class(['is-trashed' => $slot->trashed()])>
                                    <td><strong>{{ $slot->time_label }}</strong></td>

                                    <td class="is-num">
                                        @if ($slot->max_booking)
                                            {{ $slot->max_booking }}
                                        @else
                                            <span class="nv-muted" title="No ceiling set, so nothing gets refused.">no limit</span>
                                        @endif
                                    </td>

                                    <td>
                                        @if ($slot->trashed())
                                            <x-badge tone="warning">Deleted</x-badge>
                                        @else
                                            <x-badge :tone="$slot->isActive() ? 'success' : 'danger'">
                                                {{ $slot->isActive() ? 'Active' : 'Inactive' }}
                                            </x-badge>
                                        @endif
                                    </td>

                                    <td class="is-end">
                                        <div class="nv-row-actions">
                                            @if ($slot->trashed())
                                                @if ($mayDelete)
                                                    <form method="POST" action="{{ route('point-of-sale.setup.slots.restore', $slot->id) }}">
                                                        @csrf
                                                        <button type="submit" class="nv-btn nv-btn-outline nv-btn-sm">
                                                            <x-icon name="refresh" /> Restore
                                                        </button>
                                                    </form>
                                                @endif
                                            @else
                                                @if ($mayEdit)
                                                    <button type="button" class="nv-btn nv-btn-ghost nv-btn-sm"
                                                            data-slot-form
                                                            data-id="{{ $slot->id }}"
                                                            data-outlet="{{ $slot->outlet_id }}"
                                                            data-time="{{ $slot->time_value }}"
                                                            data-max="{{ $slot->max_booking }}"
                                                            data-status="{{ (int) $slot->status }}">
                                                        <x-icon name="pencil" /> Edit
                                                    </button>
                                                @endif

                                                @if ($mayDelete)
                                                    <form method="POST" action="{{ route('point-of-sale.setup.slots.destroy', $slot->id) }}"
                                                          data-confirm="The {{ $slot->time_label }} sitting will stop taking bookings."
                                                          data-confirm-title="Delete slot?"
                                                          data-confirm-action="Delete">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="nv-btn nv-btn-ghost nv-btn-sm">
                                                            <x-icon name="trash" />
                                                        </button>
                                                    </form>
                                                @endif
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="calendar" /></span>
                    <strong>{{ $outlets->isEmpty() ? 'No outlets yet' : 'No slots on this outlet' }}</strong>
                    <p>
                        @if ($outlets->isEmpty())
                            A slot belongs to an outlet, so there has to be one first.
                        @else
                            Add the sittings this outlet takes bookings for — 12:30 PM, 1:00 PM, 7:30 PM.
                        @endif
                    </p>
                    @if ($outlets->isEmpty())
                        @canView('point-of-sale/setup/outlets')
                            <a href="{{ route('point-of-sale.setup.outlets') }}" class="nv-btn nv-btn-primary">
                                <x-icon name="inbox" /> Go to Outlets
                            </a>
                        @endCanView
                    @elseif ($mayAdd)
                        <button type="button" class="nv-btn nv-btn-primary" data-slot-form data-outlet="{{ $outletId }}" data-max="0">
                            <x-icon name="plus" /> Create
                        </button>
                    @endif
                </div>
            @endif
        </x-card>
    </div>

    {{-- ── Slot dialog ───────────────────────────────────────────────────── --}}
    @if (($mayAdd || $mayEdit) && $outletId)
        <div class="nv-modal-backdrop" data-modal="slot">
            <div class="nv-modal" role="dialog" aria-modal="true" aria-labelledby="slot-title">
                <div class="nv-modal-head">
                    <strong id="slot-title">Slot</strong>
                    <button type="button" class="nv-icon-btn" data-modal-close aria-label="Close">
                        <x-icon name="x" />
                    </button>
                </div>

                <form method="POST" action="{{ route('point-of-sale.setup.slots.store') }}">
                    @csrf
                    <input type="hidden" name="id" value="" data-field="id" />
                    <input type="hidden" name="outlet_id" value="{{ $outletId }}" data-field="outlet_id" />
                    <input type="hidden" name="status" value="0" />

                    <div class="nv-form-grid">
                        <x-field label="Slot" name="slot_time" required help="The time the sitting starts.">
                            <input type="time" name="slot_time" id="slot_time" class="nv-input"
                                   data-field="slot_time" value="{{ old('slot_time') }}" />
                        </x-field>

                        <x-field label="Max Booking" name="max_booking"
                                 help="How many parties this sitting holds. 0 means no ceiling.">
                            <input type="number" name="max_booking" id="max_booking" min="0" max="9999"
                                   class="nv-input" data-field="max_booking" value="{{ old('max_booking', 0) }}" />
                        </x-field>

                        <label class="nv-check" for="slot_status">
                            <input type="checkbox" name="status" id="slot_status" value="1"
                                   data-field="status" checked />
                            <span>Active</span>
                        </label>
                    </div>

                    <div class="nv-actions" style="justify-content:flex-end;margin-top:16px">
                        <button type="button" class="nv-btn nv-btn-ghost" data-modal-close>Cancel</button>
                        <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="check" /> Save</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script src="{{ asset('js/pos-tables.js') }}?v={{ filemtime(public_path('js/pos-tables.js')) }}" defer></script>
@endpush
