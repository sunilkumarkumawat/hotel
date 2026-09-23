@extends('layouts.app')

@section('title', 'Pickup & Drop')

@section('content')
    <x-page-header
        title="Pickup & Drop"
        subtitle="Fetching guests and taking them back. Free unless somebody says otherwise."
        :crumbs="['Home' => url('/'), 'Car & Parking', 'Pickup & Drop']"
    >
        <x-slot:actions>
            @canAdd('car/trips')
                <a href="{{ route('car.trips.create', ['type' => 'pickup']) }}" class="nv-btn nv-btn-soft">
                    <x-icon name="arrow-down" /> Book a pickup
                </a>
                <a href="{{ route('car.trips.create', ['type' => 'drop']) }}" class="nv-btn nv-btn-primary">
                    <x-icon name="arrow-up" /> Book a drop
                </a>
            @endCanAdd
        </x-slot:actions>
    </x-page-header>

    <div class="nv-grid nv-grid-4">
        <x-stat label="On today" :value="$counts['today']" icon="calendar" />
        <x-stat label="On the road" :value="$counts['running']" icon="activity" tone="info" />
        <x-stat label="Pickups pending" :value="$counts['pickups']" icon="arrow-down" tone="warning" />
        <x-stat label="Drops pending" :value="$counts['drops']" icon="arrow-up" tone="warning" />
    </div>

    <div class="nv-mt">
        <x-card>
            <form method="GET" class="nv-toolbar">
                <div class="nv-field-search">
                    <x-icon name="search" />
                    <input type="search" name="q" value="{{ $filters['q'] }}" class="nv-input"
                           placeholder="Guest, trip no. or flight…" />
                </div>

                <input type="date" name="from" value="{{ $filters['from'] }}" class="nv-input" style="width:160px"
                       aria-label="From date" />
                <input type="date" name="to" value="{{ $filters['to'] }}" class="nv-input" style="width:160px"
                       aria-label="To date" />

                <select name="type" class="nv-select" style="width:140px" aria-label="Pickup or drop">
                    <option value="">Both ways</option>
                    @foreach ($types as $key => $label)
                        <option value="{{ $key }}" @selected($filters['type'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>

                <select name="status" class="nv-select" style="width:150px" aria-label="Status">
                    <option value="">Any status</option>
                    @foreach ($statuses as $key => $label)
                        <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>

                <select name="vehicle" class="nv-select" style="width:170px" aria-label="Vehicle">
                    <option value="">Any car</option>
                    @foreach ($vehicles as $vehicle)
                        <option value="{{ $vehicle->id }}" @selected($filters['vehicle'] === $vehicle->id)>{{ $vehicle->label }}</option>
                    @endforeach
                </select>

                <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="filter" /> Show</button>
                <a href="{{ route('car.trips') }}" class="nv-btn nv-btn-ghost">Reset</a>
            </form>
        </x-card>
    </div>

    <div class="nv-mt">
        <x-card flush>
            @if ($rows->isEmpty())
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="arrow-right" /></span>
                    <strong>No trips in this range</strong>
                    <p>Book a pickup or a drop above.</p>
                </div>
            @else
                <div class="nv-table-wrap">
                    <table class="nv-table">
                        <thead>
                            <tr>
                                <th>Trip</th>
                                <th>Guest</th>
                                <th>Route</th>
                                <th>When</th>
                                <th>Car and driver</th>
                                <th class="is-num">Charge</th>
                                <th>Status</th>
                                <th style="width:120px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    <td>
                                        <strong>{{ $row->trip_no }}</strong>
                                        <span class="nv-sub">{{ $row->type_label }}</span>
                                    </td>

                                    <td>
                                        {{ $row->guest_name }}
                                        <span class="nv-sub">
                                            {{ $row->pax }} pax{{ $row->room_no ? ' · Room ' . $row->room_no : '' }}
                                        </span>
                                    </td>

                                    <td>
                                        {{ $row->from_place }} → {{ $row->to_place }}
                                        @if ($row->flight_no)
                                            <span class="nv-sub">Flight {{ $row->flight_no }}</span>
                                        @endif
                                    </td>

                                    <td>{{ $row->when }}</td>

                                    <td>
                                        {{ $row->vehicle?->label ?? '—' }}
                                        @if ($row->driver_name)
                                            <span class="nv-sub">{{ $row->driver_name }}{{ $row->driver_mobile ? ' · ' . $row->driver_mobile : '' }}</span>
                                        @endif
                                    </td>

                                    <td class="is-num">
                                        @if ($row->charges())
                                            ₹ {{ number_format((float) $row->total_amount, 2) }}
                                            @if ($row->folio_charge_id)
                                                <span class="nv-sub">on the room bill</span>
                                            @endif
                                        @else
                                            <span class="nv-muted">free</span>
                                        @endif
                                    </td>

                                    <td><x-badge :tone="$row->status_tone">{{ $row->status_label }}</x-badge></td>

                                    <td class="nv-fac-row-actions">
                                        @if (! $row->isClosed())
                                            @canEdit('car/trips')
                                                <a href="{{ route('car.trips.edit', $row->id) }}" class="nv-icon-btn" title="Edit">
                                                    <x-icon name="pencil" />
                                                </a>

                                                <form method="POST" action="{{ route('car.trips.status', $row->id) }}">
                                                    @csrf
                                                    <input type="hidden" name="status"
                                                           value="{{ $row->status === 'scheduled' ? 'started' : 'completed' }}" />
                                                    <button type="submit" class="nv-icon-btn"
                                                            title="{{ $row->status === 'scheduled' ? 'The car has left' : 'Trip finished' }}">
                                                        <x-icon :name="$row->status === 'scheduled' ? 'arrow-right' : 'check'" />
                                                    </button>
                                                </form>
                                            @endCanEdit

                                            @canDelete('car/trips')
                                                <form method="POST" action="{{ route('car.trips.status', $row->id) }}"
                                                      data-confirm="Cancel {{ $row->trip_no }}? Any charge on the room bill comes off too."
                                                      data-confirm-title="Cancel trip">
                                                    @csrf
                                                    <input type="hidden" name="status" value="cancelled" />
                                                    <button type="submit" class="nv-icon-btn is-danger" title="Cancel">
                                                        <x-icon name="x-circle" />
                                                    </button>
                                                </form>
                                            @endCanDelete
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($rows->hasPages())
                <x-slot:footer>{{ $rows->links() }}</x-slot:footer>
            @endif
        </x-card>
    </div>
@endsection
