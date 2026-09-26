@extends('layouts.app')

@section('title', 'Reservation Booking Details')

@php
    $tones = [
        'confirmed' => 'success', 'tentative' => 'warning', 'cancelled' => 'danger',
        'checked_in' => 'primary', 'checked_out' => 'info', 'no_show' => 'danger',
    ];
@endphp

@section('content')
    <x-page-header
        title="Reservation Booking Details"
        subtitle="Every booking taken for this branch."
        :crumbs="['Home' => url('/'), 'Reservations']"
    >
        <x-slot:actions>
            @canAdd('front-office/check-in-guest')
                {{-- Enabled by the row checkboxes below — check-in is one booking at a time. --}}
                <a href="#" class="nv-btn nv-btn-outline is-disabled" data-check-in-go
                   aria-disabled="true">
                    <x-icon name="logout" /> Check-in
                </a>
            @endCanAdd

            @canAdd('reservation/new-reservation')
                <a href="{{ route('reservation.create') }}" class="nv-btn nv-btn-primary">
                    <x-icon name="plus" /> New Reservation
                </a>
            @endCanAdd
        </x-slot:actions>
    </x-page-header>

    <div class="nv-grid nv-grid-4">
        <x-stat label="All bookings" :value="$counts['all']" icon="calendar" />
        <x-stat label="Confirmed" :value="$counts['confirmed']" icon="check-circle" tone="success" />
        <x-stat label="Cancelled" :value="$counts['cancelled']" icon="x-circle" tone="danger" />
        <x-stat label="Booked value" :value="'₹' . number_format($counts['revenue'], 0)" icon="wallet" tone="info" />
    </div>

    <div class="nv-mt">
        <x-card flush>
            <form method="GET" class="nv-toolbar">
                <div class="nv-field-search">
                    <x-icon name="search" />
                    <input type="search" name="q" value="{{ $filters['q'] }}" class="nv-input"
                           placeholder="Reservation no., guest or mobile…" />
                </div>

                <select name="status" class="nv-select" style="width:160px" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    @foreach (\App\Models\Reservation\Reservation::STATUSES as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>

                <input type="date" name="from" value="{{ $filters['from'] }}" class="nv-input" style="width:160px" />
                <input type="date" name="to" value="{{ $filters['to'] }}" class="nv-input" style="width:160px" />

                <button type="submit" class="nv-btn nv-btn-outline"><x-icon name="filter" /> Filter</button>

                @if (array_filter($filters))
                    <a href="{{ route('reservation.index') }}" class="nv-btn nv-btn-ghost">Reset</a>
                @endif
            </form>

            @if ($reservations->count())
                <div class="nv-table-wrap">
                    <table class="nv-table">
                        <thead>
                            <tr>
                                @canAdd('front-office/check-in-guest')
                                    <th style="width:44px" aria-label="Pick for check-in"></th>
                                @endCanAdd
                                <th>Reservation</th>
                                <th>Guest</th>
                                <th>Stay</th>
                                <th class="is-num">Rooms</th>
                                <th class="is-num">Net</th>
                                <th class="is-num">Balance</th>
                                <th>Status</th>
                                <th class="is-end">Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($reservations as $reservation)
                                @php $first = $reservation->rooms->first(); @endphp

                                <tr>
                                    @canAdd('front-office/check-in-guest')
                                        <td>
                                            @if ($reservation->isCheckInable())
                                                <input type="checkbox" class="nv-check" data-pick-booking
                                                       value="{{ route('front-office.check-in-guest', ['reservation' => $reservation->id]) }}"
                                                       aria-label="Check in {{ $reservation->reservation_no }}" />
                                            @else
                                                <span class="nv-muted" title="{{ $reservation->status === 'checked_in'
                                                    ? 'Every room has already been checked in'
                                                    : 'This booking is ' . strtolower(\App\Models\Reservation\Reservation::STATUSES[$reservation->status]) }}">—</span>
                                            @endif
                                        </td>
                                    @endCanAdd

                                    <td class="nv-nowrap">
                                        <strong class="nv-mono">{{ $reservation->reservation_no }}</strong>
                                        <span class="nv-sub">{{ $reservation->reservation_date->format('d M Y') }}</span>
                                    </td>

                                    <td>
                                        <div class="nv-user-cell">
                                            <x-avatar :name="$reservation->guest_name" size="sm" />
                                            <div>
                                                <strong>{{ $reservation->guest_name }}</strong>
                                                <span>{{ $reservation->mobile }}</span>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="nv-nowrap nv-muted">
                                        @if ($first)
                                            {{ $first->arrival_date->format('d M') }} →
                                            {{ $first->checkout_date->format('d M Y') }}
                                            <span class="nv-sub">{{ $first->no_of_days }} night(s)</span>
                                        @else
                                            —
                                        @endif
                                    </td>

                                    <td class="is-num">
                                        {{ $reservation->rooms->sum('no_of_rooms') }}
                                        @if ($reservation->arrivedRooms() && $reservation->pendingRooms())
                                            <span class="nv-sub">{{ $reservation->arrivedRooms() }} in</span>
                                        @endif
                                    </td>
                                    <td class="is-num">₹{{ number_format($reservation->net_amount, 2) }}</td>

                                    <td class="is-num">
                                        @if ($reservation->balance > 0)
                                            <span style="color:var(--nv-danger);font-weight:650">
                                                ₹{{ number_format($reservation->balance, 2) }}
                                            </span>
                                        @else
                                            <x-badge tone="success">Paid</x-badge>
                                        @endif
                                    </td>

                                    <td>
                                        <x-badge :tone="$tones[$reservation->status] ?? null">
                                            {{ \App\Models\Reservation\Reservation::STATUSES[$reservation->status] }}
                                        </x-badge>
                                    </td>

                                    <td class="is-end">
                                        <div class="nv-row-actions">
                                            <a href="{{ route('reservation.show', $reservation) }}"
                                               class="nv-btn nv-btn-ghost nv-btn-sm"
                                               aria-label="Open {{ $reservation->reservation_no }}">
                                                <x-icon name="external" />
                                            </a>

                                            @if ($reservation->isEditable())
                                                @canEdit('reservation/new-reservation')
                                                    <a href="{{ route('reservation.edit', $reservation) }}"
                                                       class="nv-btn nv-btn-ghost nv-btn-sm"
                                                       aria-label="Edit {{ $reservation->reservation_no }}">
                                                        <x-icon name="pencil" />
                                                    </a>
                                                @endCanEdit
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
                    <strong>No reservations found</strong>
                    <p>{{ array_filter($filters) ? 'Nothing matches these filters.' : 'Take the first booking to see it here.' }}</p>
                    @canAdd('reservation/new-reservation')
                        <a href="{{ route('reservation.create') }}" class="nv-btn nv-btn-primary">
                            <x-icon name="plus" /> New Reservation
                        </a>
                    @endCanAdd
                </div>
            @endif

            <x-slot:footer>
                {{ $reservations->links() }}
            </x-slot:footer>
        </x-card>
    </div>
@endsection

@push('scripts')
@canAdd('front-office/check-in-guest')
<script>
(function () {
    'use strict';

    var go = document.querySelector('[data-check-in-go]');
    var boxes = Array.prototype.slice.call(document.querySelectorAll('[data-pick-booking]'));

    if (!go || !boxes.length) return;

    /* Check-in runs against one booking, so ticking a second clears the first. */
    function sync(clicked) {
        boxes.forEach(function (box) {
            if (box !== clicked) box.checked = false;
        });

        var chosen = clicked && clicked.checked ? clicked.value : null;

        go.href = chosen || '#';
        go.classList.toggle('is-disabled', !chosen);
        go.setAttribute('aria-disabled', chosen ? 'false' : 'true');
    }

    boxes.forEach(function (box) {
        box.addEventListener('change', function () { sync(box); });
    });

    go.addEventListener('click', function (event) {
        if (go.classList.contains('is-disabled')) {
            event.preventDefault();
            alert('Tick the booking you want to check in first.');
        }
    });

    sync(null);
})();
</script>
@endCanAdd
@endpush
