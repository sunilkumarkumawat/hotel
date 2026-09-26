{{--
    The bookings sitting in one cell of the status grid.
    Rendered on its own so it can be dropped into a panel without a layout.
--}}
@php
    $tones = ['confirmed' => 'success', 'tentative' => 'warning', 'checked_in' => 'primary'];
@endphp

@if ($bookings->isEmpty())
    <p class="nv-muted" style="font-size:13px;padding:14px">Nothing booked on {{ $date }}.</p>
@else
    <div class="nv-table-wrap">
        <table class="nv-table nv-table-compact">
            <thead>
                <tr>
                    <th>Reservation</th>
                    <th>Guest</th>
                    <th>Room</th>
                    <th>Stay</th>
                    <th class="is-num">Rms</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($bookings as $booking)
                    <tr>
                        <td class="nv-nowrap">
                            {{-- Only a link for someone who may actually open it. --}}
                            @canView('reservation/booking-details')
                                <a href="{{ route('reservation.show', $booking->id) }}" class="nv-mono"
                                   style="color:var(--nv-primary);font-weight:650">{{ $booking->reservation_no }}</a>
                            @else
                                <strong class="nv-mono">{{ $booking->reservation_no }}</strong>
                            @endCanView
                        </td>
                        <td>
                            <strong>{{ trim(($booking->title ? $booking->title . ' ' : '') . $booking->first_name . ' ' . $booking->last_name) }}</strong>
                            <span class="nv-sub">{{ $booking->mobile }}</span>
                        </td>
                        <td>
                            {{ $booking->room_no ?: '—' }}
                            <span class="nv-sub">{{ $booking->room_type ?: '' }}</span>
                        </td>
                        <td class="nv-nowrap nv-muted">
                            {{ \Illuminate\Support\Carbon::parse($booking->arrival_date)->format('d M') }}
                            →
                            {{ \Illuminate\Support\Carbon::parse($booking->checkout_date)->format('d M') }}
                        </td>
                        <td class="is-num">{{ $booking->no_of_rooms }}</td>
                        <td>
                            <x-badge :tone="$tones[$booking->status] ?? null">
                                {{ \App\Models\Reservation\Reservation::STATUSES[$booking->status] ?? $booking->status }}
                            </x-badge>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
