@extends('layouts.app')

@section('title', 'Check in Details')

@php
    $tones = ['in_house' => 'success', 'checked_out' => 'info', 'cancelled' => 'danger'];
@endphp

@section('content')
    <x-page-header
        title="Check in Details"
        subtitle="Every guest who has arrived. One line per room."
        :crumbs="['Home' => url('/'), 'Front Office', 'Check in Details']"
    >
        <x-slot:actions>
            @canView('front-office/pre-reg-card')
                <a href="{{ route('front-office.pre-reg-card') }}" class="nv-btn nv-btn-outline">
                    <x-icon name="file" /> Pre Reg Card
                </a>
            @endCanView

            @canView('reservation/booking-details')
                <a href="{{ route('reservation.index') }}" class="nv-btn nv-btn-outline">
                    <x-icon name="calendar" /> Booking list
                </a>
            @endCanView
        </x-slot:actions>
    </x-page-header>

    <div class="nv-grid nv-grid-4">
        <x-stat label="In house" :value="$counts['in_house']" icon="users" tone="success" />
        <x-stat label="Rooms occupied" :value="$counts['rooms']" icon="home" tone="primary" />
        <x-stat label="Arrived today" :value="$counts['today']" icon="calendar" tone="info" />
        <x-stat label="Records" :value="$counts['total']" icon="file" />
    </div>

    <div class="nv-mt">
        <x-card flush>
            <form method="GET" class="nv-toolbar">
                <div class="nv-field-search">
                    <x-icon name="search" />
                    <input type="search" name="q" value="{{ $filters['q'] }}" class="nv-input"
                           placeholder="Arrival no., guest, mobile or room…" />
                </div>

                <select name="status" class="nv-select" style="width:160px" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>

                <input type="date" name="from" value="{{ $filters['from'] }}" class="nv-input" style="width:160px" />
                <input type="date" name="to" value="{{ $filters['to'] }}" class="nv-input" style="width:160px" />

                <select name="per_page" class="nv-select" style="width:130px" onchange="this.form.submit()">
                    @foreach ([10, 25, 50, 100] as $size)
                        <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }} per page</option>
                    @endforeach
                </select>

                <button type="submit" class="nv-btn nv-btn-outline"><x-icon name="filter" /> Search</button>

                @if (array_filter($filters))
                    <a href="{{ route('front-office.check-in-details') }}" class="nv-btn nv-btn-ghost">Reset</a>
                @endif
            </form>

            @if ($checkIns->count())
                <div class="nv-table-wrap">
                    <table class="nv-table nv-table-compact">
                        <thead>
                            <tr>
                                <th>Arrival No.</th>
                                <th>Client Name</th>
                                <th>Room No.</th>
                                <th>Plan Type</th>
                                <th>Check-IN Date</th>
                                <th class="is-num">No. of Days</th>
                                <th>Expected Check Out Date</th>
                                <th>Booking From</th>
                                <th class="is-num">Total</th>
                                <th class="is-num">Paid</th>
                                <th class="is-num">Balance</th>
                                <th class="is-num">Pax</th>
                                <th>Status</th>
                                <th class="is-end">Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($checkIns as $checkIn)
                                @php $booking = $checkIn->reservation; @endphp

                                <tr>
                                    <td class="nv-nowrap"><strong class="nv-mono">{{ $checkIn->folio_no }}</strong></td>

                                    <td>
                                        <strong>{{ $checkIn->guest_name }}</strong>
                                        <span class="nv-sub">{{ $checkIn->mobile ?: '—' }}</span>
                                    </td>

                                    <td><strong class="nv-mono">{{ $checkIn->room?->room_no ?? '—' }}</strong></td>
                                    <td>{{ $checkIn->plan?->name ?? '—' }}</td>

                                    <td class="nv-nowrap nv-muted">
                                        {{ $checkIn->checkin_date->format('d M Y') }}
                                        <span class="nv-sub">{{ substr((string) $checkIn->checkin_time, 0, 5) }}</span>
                                    </td>

                                    <td class="is-num">{{ $checkIn->nights }}</td>

                                    <td class="nv-nowrap nv-muted">
                                        {{ $checkIn->expected_checkout_date->format('d M Y') }}
                                        <span class="nv-sub">{{ substr((string) $checkIn->expected_checkout_time, 0, 5) }}</span>
                                    </td>

                                    <td class="nv-nowrap">
                                        @if ($booking)
                                            <span class="nv-muted">By Reservation</span>
                                            <span class="nv-sub">
                                                @canView('reservation/booking-details')
                                                    <a href="{{ route('reservation.show', $booking) }}"
                                                       class="nv-mono" style="color:var(--nv-primary)">{{ $booking->reservation_no }}</a>
                                                @else
                                                    {{ $booking->reservation_no }}
                                                @endCanView
                                            </span>
                                        @else
                                            <span class="nv-muted">Walk in</span>
                                        @endif
                                    </td>

                                    <td class="is-num nv-nowrap">₹{{ number_format($booking?->net_amount ?? 0, 2) }}</td>
                                    <td class="is-num nv-nowrap">₹{{ number_format($booking?->advance_paid ?? 0, 2) }}</td>

                                    <td class="is-num nv-nowrap">
                                        @if (($booking?->balance ?? 0) > 0)
                                            <span style="color:var(--nv-danger);font-weight:650">
                                                ₹{{ number_format($booking->balance, 2) }}
                                            </span>
                                        @else
                                            <x-badge tone="success">Paid</x-badge>
                                        @endif
                                    </td>

                                    <td class="is-num">{{ $checkIn->male + $checkIn->female + $checkIn->child }}</td>

                                    <td>
                                        <x-badge :tone="$tones[$checkIn->status] ?? null">
                                            {{ $statuses[$checkIn->status] ?? $checkIn->status }}
                                        </x-badge>
                                    </td>

                                        <td class="is-end">
                                            <div class="nv-row-actions">
                                                @if ($booking)
                                                    @canView('front-office/pre-reg-card')
                                                        <a href="{{ route('front-office.pre-reg-card.show', [$booking, 'auto' => 1]) }}"
                                                           target="_blank" class="nv-btn nv-btn-ghost nv-btn-sm"
                                                           aria-label="Print registration card for {{ $checkIn->folio_no }}">
                                                            <x-icon name="file" />
                                                        </a>
                                                    @endCanView
                                                @endif

                                                @canDelete('front-office/check-in-guest-details')
                                                @if ($checkIn->isInHouse())
                                                    <form method="POST"
                                                          action="{{ route('front-office.check-in-details.undo', $checkIn) }}"
                                                          data-confirm="{{ $checkIn->guest_name }} will be taken out of room {{ $checkIn->room?->room_no }} and the room goes back into the free pool. The booking stays."
                                                          data-confirm-title="Undo this check-in?"
                                                          data-confirm-action="Undo check-in">
                                                        @csrf
                                                        <button type="submit" class="nv-btn nv-btn-ghost nv-btn-sm"
                                                                aria-label="Undo check-in {{ $checkIn->folio_no }}">
                                                            <x-icon name="refresh" />
                                                        </button>
                                                    </form>
                                                @endif
                                                @endCanDelete
                                            </div>
                                        </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="users" /></span>
                    <strong>Nobody has checked in yet</strong>
                    <p>
                        {{ array_filter($filters)
                            ? 'Nothing matches these filters.'
                            : 'Open a booking from the Reservation Booking List and press Check-in.' }}
                    </p>
                </div>
            @endif

            <x-slot:footer>
                {{ $checkIns->links() }}
            </x-slot:footer>
        </x-card>
    </div>
@endsection
