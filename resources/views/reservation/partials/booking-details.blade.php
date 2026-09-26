{{--
    Reservation Booking Details — one cell of the Reservation Calendar.

    Current booking (in house) on top, advance booking (still to arrive) below,
    then the two added together. Rendered without a layout so it can be dropped
    straight into the modal.
--}}
@php
    $pretty = fn ($d) => \Illuminate\Support\Carbon::parse($d)->format('d M Y');
@endphp

{{-- The modal header already carries the category and the date. --}}
<div class="nv-bd">
    {{-- ── Current booking ──────────────────────────────────────────────── --}}
    <h4 class="nv-bd-head">Current Booking</h4>

    @if ($current->isEmpty())
        <p class="nv-bd-empty">Nobody is in a {{ strtolower($categoryName) }} room on this night.</p>
    @else
        <div class="nv-table-wrap">
            <table class="nv-table nv-table-compact">
                <thead>
                    <tr>
                        <th class="is-num">Sr.</th>
                        <th>Party Name</th>
                        <th>Booking No.</th>
                        <th>Room No.</th>
                        <th>From Date</th>
                        <th>To Date</th>
                        <th class="is-num">Pax</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($current as $line)
                        <tr>
                            <td class="is-num nv-muted">{{ $loop->iteration }}</td>
                            <td>
                                <strong>{{ $line->party }}</strong>
                                <span class="nv-sub">{{ $line->mobile }}</span>
                            </td>
                            <td class="nv-nowrap">
                                @canView('reservation/booking-details')
                                    <a href="{{ route('reservation.show', $line->reservation_id) }}"
                                       class="nv-mono" style="color:var(--nv-primary);font-weight:650">{{ $line->reservation_no }}</a>
                                @else
                                    <strong class="nv-mono">{{ $line->reservation_no }}</strong>
                                @endCanView
                            </td>
                            <td>
                                @if ($line->room_no)
                                    <strong>{{ $line->room_no }}</strong>
                                @else
                                    <x-badge tone="warning">Not allotted</x-badge>
                                @endif
                                <span class="nv-sub">{{ $line->room_type }}</span>
                            </td>
                            <td class="nv-nowrap nv-muted">{{ $pretty($line->from) }}</td>
                            <td class="nv-nowrap nv-muted">{{ $pretty($line->to) }}</td>
                            <td class="is-num">{{ $line->pax }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- ── Advance booking ──────────────────────────────────────────────── --}}
    <h4 class="nv-bd-head">Advance Booking</h4>

    @if ($advance->isEmpty())
        <p class="nv-bd-empty">No advance booking for this night.</p>
    @else
        <div class="nv-table-wrap">
            <table class="nv-table nv-table-compact">
                <thead>
                    <tr>
                        <th class="is-num">Sr.</th>
                        <th>Party Name</th>
                        <th>Adv Booking No.</th>
                        <th class="is-num">Rooms</th>
                        <th>Arrival</th>
                        <th>Departure</th>
                        <th>Coming From</th>
                        <th class="is-num">Pax</th>
                        <th class="is-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($advance as $line)
                        <tr>
                            <td class="is-num nv-muted">{{ $loop->iteration }}</td>
                            <td>
                                <strong>{{ $line->party }}</strong>
                                <span class="nv-sub">{{ $line->mobile }}</span>
                            </td>
                            <td class="nv-nowrap">
                                @canView('reservation/booking-details')
                                    <a href="{{ route('reservation.show', $line->reservation_id) }}"
                                       class="nv-mono" style="color:var(--nv-primary);font-weight:650">{{ $line->reservation_no }}</a>
                                @else
                                    <strong class="nv-mono">{{ $line->reservation_no }}</strong>
                                @endCanView
                                @if ($line->status === 'tentative')
                                    <span class="nv-sub">Tentative</span>
                                @endif
                            </td>
                            <td class="is-num">{{ $line->no_of_rooms }}</td>
                            <td class="nv-nowrap nv-muted">{{ $pretty($line->from) }}</td>
                            <td class="nv-nowrap nv-muted">{{ $pretty($line->to) }}</td>
                            <td class="nv-muted">{{ $line->arrival_from ?: '—' }}</td>
                            <td class="is-num">{{ $line->pax }}</td>
                            <td class="is-end">
                                <div class="nv-row-actions">
                                    @canView('reservation/booking-details')
                                        <a href="{{ route('reservation.show', $line->reservation_id) }}"
                                           class="nv-btn nv-btn-ghost nv-btn-sm" aria-label="Open {{ $line->reservation_no }}">
                                            <x-icon name="external" />
                                        </a>
                                    @endCanView

                                    @if (! $line->room_no)
                                        @canView('reservation/calendar-new')
                                            <a href="{{ route('reservation.calendar', ['date' => $line->from]) }}"
                                               class="nv-btn nv-btn-outline nv-btn-sm">Allot</a>
                                        @endCanView
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- ── Tally ────────────────────────────────────────────────────────── --}}
    <div class="nv-bd-tally">
        <div>
            <span>Current booking</span>
            <b>{{ $tally['current'] }}</b>
        </div>

        <em>+</em>

        <div>
            <span>Advance booking</span>
            <b>{{ $tally['advance'] }}</b>
        </div>

        <em>=</em>

        <div class="is-total">
            <span>Total room booked</span>
            <b>{{ $tally['total'] }}</b>
        </div>
    </div>
</div>
