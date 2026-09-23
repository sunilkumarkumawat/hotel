@extends('layouts.app')

@section('title', 'Reservation Status View')

@php
    $keys = $dates->map(fn ($d) => $d->toDateString());
    $params = fn (array $extra = []) => array_merge(['date' => $start->toDateString(), 'days' => $days], $extra);
@endphp

@section('content')
    <x-page-header
        title="Reservation Status View"
        subtitle="How many rooms are sold, held, blocked and still free — category by category, night by night."
        :crumbs="['Home' => url('/'), 'Reservations' => route('reservation.index'), 'Status View']"
    >
        <x-slot:actions>
            <a href="{{ route('reservation.status.export', $params()) }}" class="nv-btn nv-btn-outline">
                <x-icon name="file" /> Export
            </a>

            @canAdd('reservation/new-reservation')
                <a href="{{ route('reservation.create') }}" class="nv-btn nv-btn-primary">
                    <x-icon name="plus" /> New Reservation
                </a>
            @endCanAdd
        </x-slot:actions>
    </x-page-header>

    <div class="nv-grid nv-grid-4">
        <x-stat label="Rooms" :value="$stats['rooms']" icon="home" />
        <x-stat label="Sold today" :value="$stats['booked']" icon="calendar" tone="info" />
        <x-stat label="Free today" :value="$stats['available']" icon="check-circle" tone="success" />
        <x-stat label="Occupancy today" :value="$stats['occupancy'] . '%'" icon="trending-up"
                :tone="$stats['occupancy'] >= 80 ? 'danger' : ($stats['occupancy'] >= 50 ? 'warning' : null)" />
    </div>

    <div class="nv-mt">
        <x-card flush>
            {{-- ── Date navigation ──────────────────────────────────────── --}}
            <form method="GET" class="nv-toolbar">
                <a href="{{ route('reservation.status', $params(['date' => $prev])) }}" class="nv-btn nv-btn-outline">
                    <x-icon name="chevron-left" /> Previous
                </a>

                <input type="date" name="date" value="{{ $start->toDateString() }}" class="nv-input" style="width:170px" />

                <select name="days" class="nv-select" style="width:130px">
                    @foreach ($spans as $value => $label)
                        <option value="{{ $value }}" @selected($days === $value)>{{ $label }}</option>
                    @endforeach
                </select>

                <button type="submit" class="nv-btn nv-btn-primary">Go</button>

                <a href="{{ route('reservation.status', $params(['date' => $next])) }}" class="nv-btn nv-btn-outline">
                    Next <x-icon name="chevron-right" />
                </a>

                <a href="{{ route('reservation.status', ['days' => $days]) }}" class="nv-btn nv-btn-ghost">Today</a>

                <span class="nv-toolbar-spacer"></span>

                <span class="nv-legend">
                    <span><i class="nv-legend-key is-booked"></i> Sold</span>
                    <span><i class="nv-legend-key is-tentative"></i> Tentative</span>
                    <span><i class="nv-legend-key is-blocked"></i> Blocked</span>
                    <span><i class="nv-legend-key is-free"></i> Free</span>
                </span>
            </form>

            {{-- ── The grid ─────────────────────────────────────────────── --}}
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
                            {{-- Sold and held --}}
                            <tr class="nv-cal-section">
                                <td colspan="{{ $dates->count() + 1 }}">Sold &middot; Tentative</td>
                            </tr>

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
                                                @foreach (['booked' => 'sold', 'tentative' => 'tentative'] as $field => $word)
                                                    @if ($cell[$field])
                                                        <button type="button"
                                                                @class(['nv-pill', 'is-' . $field])
                                                                data-cell="{{ $key }}"
                                                                data-cell-category="{{ $category->key }}"
                                                                data-cell-label="{{ $category->name }} — {{ $dates->firstWhere(fn ($d) => $d->toDateString() === $key)?->format('D, j M Y') }}"
                                                                title="{{ $cell[$field] }} {{ $word }} — click to see the bookings">{{ $cell[$field] }}</button>
                                                    @else
                                                        <b class="nv-pill is-{{ $field }} is-zero">0</b>
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
                                            <b @class(['nv-pill', 'is-booked', 'is-zero' => ! $grid['totals'][$key]['booked']])>{{ $grid['totals'][$key]['booked'] }}</b>
                                            <b @class(['nv-pill', 'is-tentative', 'is-zero' => ! $grid['totals'][$key]['tentative']])>{{ $grid['totals'][$key]['tentative'] }}</b>
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

                            {{-- Free to sell --}}
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
                                        @php
                                            $free = $grid['rows'][$category->key][$key]['available'];
                                            $full = $free === 0;
                                        @endphp

                                        <td @class(['is-center', 'is-today' => $key === $today])>
                                            @if ($full)
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
                    A night belongs to the arrival date, not the checkout date — a guest leaving on the 9th
                    frees that room for the 9th. Cancelled and no-show bookings are not counted.
                </p>
            </x-slot:footer>
        </x-card>
    </div>
@endsection

{{-- ── Drill-down: which bookings sit in this cell ───────────────────────── --}}
<div class="nv-modal-backdrop" data-cell-modal>
    <div class="nv-modal" style="max-width:760px" role="dialog" aria-modal="true" aria-label="Bookings in this cell">
        <div class="nv-modal-head">
            <strong data-cell-title>Bookings</strong>
            <button type="button" class="nv-icon-btn" data-cell-close aria-label="Close">
                <x-icon name="x" />
            </button>
        </div>

        <div class="nv-modal-body" data-cell-body>
            <p class="nv-muted" style="font-size:13px;padding:14px">Loading…</p>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    'use strict';

    var modal = document.querySelector('[data-cell-modal]');
    var body = document.querySelector('[data-cell-body]');
    var title = document.querySelector('[data-cell-title]');
    if (!modal) return;

    var url = @json(route('reservation.status.cell'));

    function close() { modal.classList.remove('is-open'); }

    document.querySelectorAll('[data-cell]').forEach(function (pill) {
        pill.addEventListener('click', function () {
            title.textContent = pill.dataset.cellLabel || 'Bookings';
            body.innerHTML = '<p class="nv-muted" style="font-size:13px;padding:14px">Loading…</p>';
            modal.classList.add('is-open');

            var query = new URLSearchParams({
                date: pill.dataset.cell,
                category: pill.dataset.cellCategory,
            });

            fetch(url + '?' + query.toString(), { headers: { Accept: 'text/html' } })
                .then(function (r) { return r.ok ? r.text() : null; })
                .then(function (html) {
                    body.innerHTML = html !== null
                        ? html
                        : '<p class="nv-muted" style="font-size:13px;padding:14px">Could not load these bookings.</p>';
                })
                .catch(function () {
                    body.innerHTML = '<p class="nv-muted" style="font-size:13px;padding:14px">Could not reach the server.</p>';
                });
        });
    });

    document.querySelectorAll('[data-cell-close]').forEach(function (b) {
        b.addEventListener('click', close);
    });

    modal.addEventListener('click', function (e) { if (e.target === modal) close(); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) close();
    });
})();
</script>
@endpush
