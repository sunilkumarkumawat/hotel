@extends('layouts.app')

@section('title', 'Room Calendar')

@php
    $params = fn (array $extra = []) => array_merge([
        'date' => $date,
        'category' => $filters['category'] ?: null,
        'floor' => $filters['floor'] ?: null,
        'q' => $filters['q'] ?: null,
        'show' => $filters['show'] ?: null,
    ], $extra);
@endphp

@section('content')
    <x-page-header
        title="Room Calendar"
        subtitle="Every room in the house on one day. Click a room to see who is in it."
        :crumbs="['Home' => url('/'), 'Front Office', 'Room Calendar']"
    >
        <x-slot:actions>
            @canView('front-office/check-in-guest-details')
                <a href="{{ route('front-office.check-in-details') }}" class="nv-btn nv-btn-outline">
                    <x-icon name="users" /> Check in Details
                </a>
            @endCanView
        </x-slot:actions>
    </x-page-header>

    <div class="nv-mt">
        <x-card>
            <form method="GET" class="nv-toolbar">
                <div class="nv-seg">
                    @foreach (['' => 'All', 'occupy' => 'Occupy', 'available' => 'Available'] as $value => $label)
                        <a href="{{ route('front-office.room-calendar', $params(['show' => $value ?: null])) }}"
                           @class(['nv-seg-btn', 'is-on' => $filters['show'] === $value])>{{ $label }}</a>
                    @endforeach
                </div>

                <select name="category" class="nv-select" style="width:180px">
                    <option value="">Room Category</option>
                    @foreach ($categories as $id => $name)
                        <option value="{{ $id }}" @selected($filters['category'] === $id)>{{ $name }}</option>
                    @endforeach
                </select>

                <select name="floor" class="nv-select" style="width:150px">
                    <option value="">Floor By</option>
                    @foreach ($floors as $floor)
                        <option value="{{ $floor }}" @selected($filters['floor'] === $floor)>{{ $floor }}</option>
                    @endforeach
                </select>

                <div class="nv-field-search">
                    <x-icon name="search" />
                    <input type="search" name="q" value="{{ $filters['q'] }}" class="nv-input" placeholder="Room no…" />
                </div>

                <input type="date" name="date" value="{{ $date }}" class="nv-input" style="width:165px" />

                <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="search" /> Search</button>

                <a href="{{ route('front-office.room-calendar') }}" class="nv-btn nv-btn-ghost">Today</a>
            </form>

            <div class="nv-legend nv-mt">
                @foreach ($states as $key => $label)
                    <span class="nv-legend-item">
                        <span class="nv-legend-key is-{{ str_replace('_', '-', $key) }}"></span>
                        {{ $label }} ({{ $legend[$key] }})
                    </span>
                @endforeach
            </div>
        </x-card>
    </div>

    <div class="nv-mt nv-rc">
        {{-- ── The tiles ─────────────────────────────────────────────────── --}}
        <div>
            @if ($rooms->count())
                <div class="nv-rc-grid" data-rooms>
                    @foreach ($rooms as $room)
                        @php $s = $state[$room->id]; @endphp

                        <button type="button"
                                @class([
                                    'nv-rc-tile',
                                    'is-' . str_replace('_', '-', $s['state']),
                                    'is-picked' => $selected === $room->id,
                                ])
                                data-room="{{ $room->id }}"
                                title="{{ $room->room_no }} · {{ $states[$s['state']] }}">
                            <strong>{{ $room->room_no }}</strong>
                            <span class="nv-rc-type">{{ $room->type?->name ?? $room->category?->name }}</span>

                            @if ($s['check_in'])
                                <span class="nv-rc-guest">{{ $s['check_in']->guest_name }}</span>
                                <span class="nv-rc-mob">{{ $s['check_in']->mobile }}</span>
                            @elseif ($s['booking'])
                                <span class="nv-rc-guest">
                                    {{ trim($s['booking']->first_name . ' ' . $s['booking']->last_name) }}
                                </span>
                                <span class="nv-rc-mob">{{ $s['booking']->mobile }}</span>
                            @endif
                        </button>
                    @endforeach
                </div>
            @else
                <x-card>
                    <div class="nv-empty">
                        <span class="nv-empty-icon"><x-icon name="home" /></span>
                        <strong>No rooms match</strong>
                        <p>Clear the filters, or add rooms in Masters → Room.</p>
                    </div>
                </x-card>
            @endif
        </div>

        {{-- ── Guest panel ───────────────────────────────────────────────── --}}
        <div>
            <x-card title="Selected Room Guest informations">
                <div data-guest-panel>
                    <div class="nv-rc-empty" data-guest-empty @if ($guest && $guest['found']) hidden @endif>
                        <x-icon name="home" />
                        <p>Click a room to see who is in it.</p>
                    </div>

                    <dl class="nv-rc-info" data-guest-info @unless ($guest && $guest['found']) hidden @endunless>
                        @foreach ([
                            'guest_name' => 'Guest Name',
                            'mobile' => 'Mob',
                            'room_category' => 'Room Category',
                            'plan' => 'Plan',
                            'room_no' => 'Room No',
                            'guest_type' => 'Guest Type',
                            'gstin' => 'GstIn',
                            'company' => 'Company',
                            'arrival' => 'Arrival Date',
                            'departure' => 'Departure Date',
                            'booked_by' => 'Booked By',
                            'housekeeping' => 'Housekeeping',
                        ] as $key => $label)
                            <div class="nv-rc-row">
                                <dt>{{ $label }}</dt>
                                <dd data-info="{{ $key }}">{{ $guest[$key] ?? '--' }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    <div class="nv-actions nv-mt" style="justify-content:space-between">
                        @canAdd('front-office/check-in-guest')
                            <a href="#" class="nv-btn nv-btn-success is-disabled" data-guest-checkin
                               aria-disabled="true">Check In</a>
                        @endCanAdd

                        @canAdd('front-office/check-out-guest')
                            <a href="#" class="nv-btn nv-btn-primary is-disabled" data-guest-checkout
                               aria-disabled="true">Check Out</a>
                        @endCanAdd
                    </div>
                </div>
            </x-card>

            @canEdit('front-office/room-calendar')
                <div class="nv-mt">
                    <x-card title="HouseKeeping Status" subtitle="Change what the picked room is marked as.">
                        <form method="POST" action="{{ route('front-office.room-calendar.housekeeping') }}"
                              class="nv-actions" data-hk-form>
                            @csrf
                            <input type="hidden" name="room_id" data-hk-room />

                            <select name="housekeeping_status" class="nv-select">
                                @foreach (\App\Models\Master\Room::HOUSEKEEPING as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>

                            <button type="submit" class="nv-btn nv-btn-outline is-disabled" data-hk-save
                                    disabled>Update</button>
                        </form>
                    </x-card>
                </div>
            @endCanEdit
        </div>
    </div>
@endsection

@push('scripts')
@php
    $rcBoot = [
        'guestUrl' => route('front-office.room-calendar.guest'),
        'checkOutUrl' => route('front-office.check-out-guest'),
        'checkInUrl' => route('front-office.check-in-guest'),
        'date' => $date,
    ];
@endphp

<script>
window.RC_BOOT = @json($rcBoot);
</script>
<script src="{{ asset('js/room-grid.js') }}?v={{ filemtime(public_path('js/room-grid.js')) }}" defer></script>
@endpush
