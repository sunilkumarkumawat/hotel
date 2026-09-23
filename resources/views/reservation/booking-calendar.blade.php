@extends('layouts.app')

@section('title', 'Reservation Calendar')

@php
    $keys = $dates->map(fn ($d) => $d->toDateString());
    $params = fn (array $extra = []) => array_merge(['date' => $start->toDateString(), 'days' => $days], $extra);
    $dateFor = fn (string $key) => $dates->firstWhere(fn ($d) => $d->toDateString() === $key);
@endphp

@section('content')
    <x-page-header
        title="Reservation Calendar"
        subtitle="Who is in the hotel and who is coming, category by category, night by night. Click any number to see the bookings behind it."
        :crumbs="['Home' => url('/'), 'Reservations' => route('reservation.index'), 'Calendar']"
    >
        <x-slot:actions>
            <a href="{{ route('reservation.booking-calendar.export', $params()) }}" class="nv-btn nv-btn-outline">
                <x-icon name="file" /> Export
            </a>

            @canView('reservation/calendar-new')
                <a href="{{ route('reservation.calendar', $params()) }}" class="nv-btn nv-btn-outline">
                    <x-icon name="layers" /> Room Calendar
                </a>
            @endCanView

            @canAdd('reservation/new-reservation')
                <a href="{{ route('reservation.create') }}" class="nv-btn nv-btn-primary">
                    <x-icon name="plus" /> New Reservation
                </a>
            @endCanAdd
        </x-slot:actions>
    </x-page-header>

    <div class="nv-grid nv-grid-4">
        <x-stat label="Rooms" :value="$stats['rooms']" icon="home" />
        <x-stat label="In house today" :value="$stats['current']" icon="users" tone="success" />
        <x-stat label="Arriving today" :value="$stats['advance']" icon="calendar" tone="warning" />
        <x-stat label="Free today" :value="$stats['available']" icon="check-circle" tone="info" />
    </div>

    <div class="nv-mt">
        <x-card flush>
            <form method="GET" class="nv-toolbar">
                <a href="{{ route('reservation.booking-calendar', $params(['date' => $prev])) }}" class="nv-btn nv-btn-outline">
                    <x-icon name="chevron-left" /> Previous
                </a>

                <input type="date" name="date" value="{{ $start->toDateString() }}" class="nv-input" style="width:170px" />

                <select name="days" class="nv-select" style="width:120px">
                    @foreach ($spans as $value => $label)
                        <option value="{{ $value }}" @selected($days === $value)>{{ $label }}</option>
                    @endforeach
                </select>

                <button type="submit" class="nv-btn nv-btn-primary">Go</button>

                <a href="{{ route('reservation.booking-calendar', $params(['date' => $next])) }}" class="nv-btn nv-btn-outline">
                    Next <x-icon name="chevron-right" />
                </a>

                <a href="{{ route('reservation.booking-calendar', ['days' => $days]) }}" class="nv-btn nv-btn-ghost">Today</a>

                <span class="nv-toolbar-spacer"></span>

                <span class="nv-legend">
                    <span><i class="nv-legend-key is-current"></i> Current booking</span>
                    <span><i class="nv-legend-key is-advance"></i> Advance booking</span>
                    <span><i class="nv-legend-key is-blocked"></i> Blocked</span>
                </span>
            </form>

            @if ($grid['categories']->isEmpty())
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="home" /></span>
                    <strong>No rooms yet</strong>
                    <p>
                        Add some on the
                        <a href="{{ route('masters.index', 'room') }}" style="color:var(--nv-primary);font-weight:600">Rooms</a>
                        screen and this calendar fills itself in.
                    </p>
                </div>
            @else
                <div class="nv-table-wrap nv-cal-wrap">
                    <table class="nv-table nv-cal">
                        <thead>
                            <tr>
                                <th class="nv-cal-head">Room Category</th>

                                @foreach ($dates as $date)
                                    <th @class(['is-center', 'is-today' => $date->toDateString() === $today,
                                                'is-weekend' => $date->isWeekend()])>
                                        <span class="nv-cal-day">{{ $date->format('j M') }}</span>
                                        <span class="nv-cal-dow">{{ $date->format('D') }}</span>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($grid['categories'] as $category)
                                <tr>
                                    <th class="nv-cal-head">
                                        {{ $category->name }}
                                        <span class="nv-sub">{{ $category->rooms }} room(s)</span>
                                    </th>

                                    @foreach ($keys as $key)
                                        @php $cell = $grid['rows'][$category->key][$key]; @endphp

                                        <td @class(['is-center', 'is-today' => $key === $today])>
                                            <span class="nv-cal-pair">
                                                @foreach (['current' => 'in house', 'advance' => 'arriving'] as $field => $word)
                                                    @if ($cell[$field])
                                                        <button type="button"
                                                                @class(['nv-pill', 'is-' . $field])
                                                                data-cell="{{ $key }}"
                                                                data-cell-category="{{ $category->key }}"
                                                                data-cell-label="{{ $category->name }} — {{ $dateFor($key)?->format('D, j M Y') }}"
                                                                title="{{ $cell[$field] }} {{ $word }} — click for the booking details">{{ $cell[$field] }}</button>
                                                    @else
                                                        <button type="button" class="nv-pill is-{{ $field }} is-zero"
                                                                data-cell="{{ $key }}"
                                                                data-cell-category="{{ $category->key }}"
                                                                data-cell-label="{{ $category->name }} — {{ $dateFor($key)?->format('D, j M Y') }}"
                                                                title="Nothing {{ $word }} — click to check">0</button>
                                                    @endif
                                                @endforeach
                                            </span>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach

                            {{-- Roll-ups --}}
                            <tr class="nv-cal-total">
                                <th class="nv-cal-head">
                                    Total
                                    <span class="nv-sub">{{ $grid['rooms'] }} room(s)</span>
                                </th>

                                @foreach ($keys as $key)
                                    <td @class(['is-center', 'is-today' => $key === $today])>
                                        <span class="nv-cal-pair">
                                            <b @class(['nv-pill', 'is-current', 'is-zero' => ! $grid['totals'][$key]['current']])>{{ $grid['totals'][$key]['current'] }}</b>
                                            <b @class(['nv-pill', 'is-advance', 'is-zero' => ! $grid['totals'][$key]['advance']])>{{ $grid['totals'][$key]['advance'] }}</b>
                                        </span>
                                    </td>
                                @endforeach
                            </tr>

                            <tr>
                                <th class="nv-cal-head">Blocked room</th>

                                @foreach ($keys as $key)
                                    <td @class(['is-center', 'is-today' => $key === $today])>
                                        <b @class(['nv-pill', 'is-blocked', 'is-zero' => ! $grid['totals'][$key]['blocked']])>{{ $grid['totals'][$key]['blocked'] }}</b>
                                    </td>
                                @endforeach
                            </tr>

                            <tr class="nv-cal-total">
                                <th class="nv-cal-head">Total available</th>

                                @foreach ($keys as $key)
                                    <td @class(['is-center', 'is-num', 'is-today' => $key === $today])>
                                        <strong>{{ $grid['totals'][$key]['available'] }}</strong>
                                    </td>
                                @endforeach
                            </tr>

                            {{-- Free to sell, per category --}}
                            <tr class="nv-cal-section">
                                <td colspan="{{ $dates->count() + 1 }}">Free to sell</td>
                            </tr>

                            @foreach ($grid['categories'] as $category)
                                <tr>
                                    <th class="nv-cal-head">
                                        {{ $category->name }}
                                        <span class="nv-sub">{{ $category->rooms }} room(s)</span>
                                    </th>

                                    @foreach ($keys as $key)
                                        @php $free = $grid['rows'][$category->key][$key]['available']; @endphp

                                        <td @class(['is-center', 'is-today' => $key === $today])>
                                            @if ($free === 0)
                                                <b class="nv-pill is-free is-full"
                                                   title="{{ $category->name }} is sold out on {{ $key }}">0</b>
                                            @else
                                                @canAdd('reservation/new-reservation')
                                                    <a href="{{ route('reservation.create', ['date' => $key, 'category' => $category->id]) }}"
                                                       class="nv-pill is-free"
                                                       title="{{ $free }} of {{ $category->rooms }} {{ $category->name }} free on {{ $key }} — click to book one">{{ $free }}</a>
                                                @else
                                                    <b class="nv-pill is-free">{{ $free }}</b>
                                                @endCanAdd
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <x-slot:footer>
                <p class="nv-muted" style="font-size:12.5px;line-height:1.6;margin:0">
                    <strong>Current booking</strong> is a guest already checked in; <strong>advance booking</strong>
                    is confirmed or tentative and not arrived yet. A night belongs to the arrival date, not the
                    checkout date. Cancelled and no-show bookings are not counted.
                </p>
            </x-slot:footer>
        </x-card>
    </div>

    {{-- ── Reservation Booking Details ──────────────────────────────────── --}}
    <div class="nv-modal-backdrop" data-bd-modal>
        <div class="nv-modal nv-modal-wide" role="dialog" aria-modal="true" aria-label="Reservation Booking Details">
            <div class="nv-modal-head">
                <strong>
                    Reservation Booking Details
                    <span class="nv-sub" data-bd-sub></span>
                </strong>
                <button type="button" class="nv-icon-btn" data-bd-close aria-label="Close">
                    <x-icon name="x" />
                </button>
            </div>

            <div class="nv-modal-body nv-bd-scroll" data-bd-body>
                <p class="nv-muted" style="font-size:13px;padding:14px">Loading…</p>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    var modal = document.querySelector('[data-bd-modal]');
    if (!modal) return;

    var body = document.querySelector('[data-bd-body]');
    var sub = document.querySelector('[data-bd-sub]');
    var url = @json(route('reservation.booking-calendar.details'));
    var pending = null;

    function close() {
        modal.classList.remove('is-open');
        if (pending) pending.abort();
    }

    document.querySelectorAll('[data-cell]').forEach(function (pill) {
        pill.addEventListener('click', function () {
            sub.textContent = pill.dataset.cellLabel || '';
            body.innerHTML = '<p class="nv-muted" style="font-size:13px;padding:14px">Loading…</p>';
            modal.classList.add('is-open');

            if (pending) pending.abort();
            pending = new AbortController();

            var query = new URLSearchParams({
                date: pill.dataset.cell,
                category: pill.dataset.cellCategory,
            });

            fetch(url + '?' + query.toString(), {
                headers: { Accept: 'text/html' },
                signal: pending.signal,
            })
                .then(function (r) { return r.ok ? r.text() : null; })
                .then(function (html) {
                    body.innerHTML = html !== null
                        ? html
                        : '<p class="nv-muted" style="font-size:13px;padding:14px">Could not load these bookings.</p>';
                })
                .catch(function (e) {
                    if (e.name === 'AbortError') return;
                    body.innerHTML = '<p class="nv-muted" style="font-size:13px;padding:14px">Could not reach the server.</p>';
                });
        });
    });

    document.querySelectorAll('[data-bd-close]').forEach(function (b) {
        b.addEventListener('click', close);
    });

    modal.addEventListener('click', function (e) { if (e.target === modal) close(); });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) close();
    });
})();
</script>
@endpush
