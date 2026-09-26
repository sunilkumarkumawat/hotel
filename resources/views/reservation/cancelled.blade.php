@extends('layouts.app')

@section('title', 'Cancel Reservation List')

@section('content')
    <x-page-header
        title="Cancel Reservation List"
        subtitle="Bookings that were cancelled before the guest arrived."
        :crumbs="['Home' => url('/'), 'Reservations' => route('reservation.index'), 'Cancelled']"
    />

    <x-card flush>
        <form method="GET" class="nv-toolbar">
            <div class="nv-field-search">
                <x-icon name="search" />
                <input type="search" name="q" value="{{ $term }}" class="nv-input"
                       placeholder="Reservation no., guest or mobile…" />
            </div>
            <button type="submit" class="nv-btn nv-btn-outline"><x-icon name="filter" /> Filter</button>
            @if ($term)
                <a href="{{ route('reservation.cancelled') }}" class="nv-btn nv-btn-ghost">Reset</a>
            @endif
        </form>

        @if ($reservations->count())
            <div class="nv-table-wrap">
                <table class="nv-table">
                    <thead>
                        <tr>
                            <th>Reservation</th>
                            <th>Guest</th>
                            <th>Cancelled on</th>
                            <th>Reason</th>
                            <th class="is-num">Net was</th>
                            <th class="is-end"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($reservations as $reservation)
                            <tr>
                                <td class="nv-nowrap">
                                    <strong class="nv-mono">{{ $reservation->reservation_no }}</strong>
                                    <span class="nv-sub">{{ $reservation->reservation_date->format('d M Y') }}</span>
                                </td>
                                <td>
                                    <strong>{{ $reservation->guest_name }}</strong>
                                    <span class="nv-sub">{{ $reservation->mobile }}</span>
                                </td>
                                <td class="nv-nowrap nv-muted">{{ $reservation->cancelled_on?->format('d M Y') ?? '—' }}</td>
                                <td class="nv-muted">{{ $reservation->cancel_reason ?: '—' }}</td>
                                <td class="is-num">₹{{ number_format($reservation->net_amount, 2) }}</td>
                                <td class="is-end">
                                    <a href="{{ route('reservation.show', $reservation) }}"
                                       class="nv-btn nv-btn-ghost nv-btn-sm" aria-label="Open">
                                        <x-icon name="external" />
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="nv-empty">
                <span class="nv-empty-icon"><x-icon name="check-circle" /></span>
                <strong>Nothing cancelled</strong>
                <p>No booking has been cancelled{{ $term ? ' that matches that search' : '' }}.</p>
            </div>
        @endif

        <x-slot:footer>
            {{ $reservations->links() }}
        </x-slot:footer>
    </x-card>
@endsection
