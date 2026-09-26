@extends('layouts.app')

@section('title', 'Night Audit')

@php
    /*
        The screen is a job done in three steps, in the order a night auditor
        does them: check the house, read the figures, close the day. So it is
        laid out top to bottom rather than as tabs — there is no step two
        without step one.
    */
    $when = \Carbon\CarbonImmutable::parse($date);
    $isCurrent = $date === $businessDate;
    $clean = collect($checks)->where('tone', 'success')->count() === count($checks);
@endphp

@section('content')
    <x-page-header
        title="Night Audit"
        subtitle="Charge the night, write off the no-shows, freeze the figures, move the date."
        :crumbs="['Home' => url('/'), 'Front Office', 'Night Audit']"
    >
        <x-slot:actions>
            @if (! $isCurrent)
                <a href="{{ route('front-office.night-audit') }}" class="nv-btn nv-btn-outline">
                    <x-icon name="refresh" /> Back to {{ \Carbon\CarbonImmutable::parse($businessDate)->format('d M Y') }}
                </a>
            @endif

            @if ($day && $closed)
                <a href="{{ route('front-office.night-audit.show', $day) }}" class="nv-btn nv-btn-primary">
                    <x-icon name="file" /> Open the report
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    {{-- ── The date the hotel is trading on ──────────────────────────────── --}}
    <div class="nv-na-hero">
        <div class="nv-na-hero-main">
            <p class="nv-na-hero-label">Business date</p>
            <p class="nv-na-hero-date">{{ $when->format('d M Y') }}</p>
            <p class="nv-na-hero-day">{{ $when->format('l') }}</p>
        </div>

        <div class="nv-na-hero-side">
            @if ($closed)
                <span class="nv-badge is-success"><x-icon name="check-circle" /> Audited</span>
                <p>
                    Closed {{ $day?->closed_at?->format('d M Y, h:i A') }}
                    @if ($day?->closer) by {{ $day->closer->name }} @endif.
                    The hotel is now trading on
                    <b>{{ \Carbon\CarbonImmutable::parse($businessDate)->format('d M Y') }}</b>.
                </p>
            @else
                <span class="nv-badge is-warning"><x-icon name="clock" /> Open</span>
                <p>
                    {{ $stays }} {{ \Illuminate\Support\Str::plural('room', $stays) }} occupied tonight.
                    @if ($pending > 1)
                        <b>{{ $pending }} nights are waiting</b> — they close one at a time, oldest first.
                    @else
                        This is tonight's audit.
                    @endif
                </p>
            @endif
        </div>
    </div>

    @if ($pending > 2 && ! $closed)
        <div class="nv-mt">
            <x-alert tone="warning" title="The audit is behind">
                The last close was {{ $pending }} nights ago. Run it {{ $pending }} times — once for each night,
                oldest first — so every night gets its own rent posted and its own figures. Skipping to today
                would leave the nights in between uncharged.
            </x-alert>
        </div>
    @endif

    {{-- ── Step one: the walk round ──────────────────────────────────────── --}}
    <div class="nv-mt">
        <x-card
            title="Before you close"
            :subtitle="$clean
                ? 'Nothing needs your attention. The house is tidy.'
                : 'Four questions the audit will answer for you if you do not answer them first.'"
        >
            <div class="nv-na-checks">
                @foreach ($checks as $check)
                    <div @class(['nv-na-check', 'is-' . $check['tone']])>
                        <span class="nv-na-check-icon">
                            <x-icon :name="$check['tone'] === 'success' ? 'check-circle' : 'alert'" />
                        </span>

                        <div class="nv-na-check-text">
                            <strong>{{ $check['label'] }}</strong>
                            <small>{{ $check['hint'] }}</small>
                        </div>

                        <span class="nv-na-check-count">{{ $check['count'] }}</span>

                        {{-- The slot is always here, button or no button, so the
                             counts stay in one column down the list. --}}
                        <span class="nv-na-check-go">
                            @if ($check['count'] > 0 && $check['url'] && can_do($check['url'], 'view'))
                                <a href="{{ url($check['url']) }}" class="nv-btn nv-btn-sm nv-btn-outline">
                                    Fix <x-icon name="arrow-right" />
                                </a>
                            @endif
                        </span>
                    </div>
                @endforeach
            </div>
        </x-card>
    </div>

    {{-- ── Step two: the figures ─────────────────────────────────────────── --}}
    <div class="nv-mt">
        <h2 class="nv-na-heading">
            {{ $closed ? 'The figures, as they were frozen' : 'The figures, as they stand right now' }}
        </h2>
        <p class="nv-na-sub">
            @if ($closed)
                This is the photograph taken at the close. Nothing on this screen can move it now.
            @else
                These move every time somebody posts a charge. Closing the night freezes them.
            @endif
        </p>
    </div>

    <div class="nv-mt">
        @include('front-office.night-audit.partials.figures', ['figures' => $figures])
    </div>

    {{-- ── Step three: close it ──────────────────────────────────────────── --}}
    @if (! $closed && $isCurrent)
        @canAdd('front-office/night-audit')
            <div class="nv-mt">
                <x-card title="Close the night" subtitle="This posts money to guest folios. Read the list above first.">
                    <form method="POST" action="{{ route('front-office.night-audit.run') }}"
                          data-confirm="Close the night of {{ $when->format('d M Y') }}? Its figures are frozen, and the business date moves to {{ $when->addDay()->format('d M Y') }}."
                          data-confirm-title="Run the night audit"
                          data-confirm-action="Run the audit">
                        @csrf
                        <input type="hidden" name="date" value="{{ $date }}" />

                        <div class="nv-na-run">
                            <div class="nv-na-run-steps">
                                {{-- "Make sure", not "charge": most nights are already on the folio
                                     from check-in, and this is the sweep that catches the ones that
                                     are not. Saying "charge" would leave a clerk staring at a zero
                                     and wondering what went wrong. --}}
                                <p><span>1</span> Make sure tonight's rent is on the folio of each of the
                                    {{ $stays }} occupied {{ \Illuminate\Support\Str::plural('room', $stays) }}.
                                    Nights already charged are left alone.</p>
                                <p><span>2</span> Mark as No Show every booking due by tonight that nobody arrived for,
                                    and release the rooms they were holding.</p>
                                <p><span>3</span> Freeze the figures above as this night's report.</p>
                                <p><span>4</span> Move the business date to
                                    {{ $when->addDay()->format('d M Y') }}.</p>
                            </div>

                            <div class="nv-na-run-side">
                                <x-field label="Note for the record" name="note"
                                         help="Optional — anything the morning shift should know.">
                                    <x-textarea name="note" rows="3" maxlength="500"
                                                placeholder="e.g. 204 extended by phone, paperwork tomorrow" />
                                </x-field>

                                <button type="submit" class="nv-btn nv-btn-primary nv-btn-lg nv-btn-block">
                                    <x-icon name="check-circle" /> Run the night audit
                                </button>

                                <p class="nv-na-safe">
                                    <x-icon name="shield" />
                                    Safe to run twice — a night already charged is never charged again.
                                </p>
                            </div>
                        </div>
                    </form>
                </x-card>
            </div>
        @endCanAdd
    @endif

    {{-- ── Every night already closed ────────────────────────────────────── --}}
    <div class="nv-mt">
        <x-card title="Audited nights" subtitle="The last thirty closes, newest first." :flush="true">
            @if ($history->isEmpty())
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="calendar" /></span>
                    <strong>No night audited yet</strong>
                    <p>The first close starts the hotel's own calendar.</p>
                </div>
            @else
                <div class="nv-table-wrap">
                    <table class="nv-table">
                        <thead>
                            <tr>
                                <th>Night</th>
                                <th class="is-end">Rooms sold</th>
                                <th class="is-end">Occupancy</th>
                                <th class="is-end">Room revenue</th>
                                <th class="is-end">ADR</th>
                                <th class="is-end">Collected</th>
                                <th>Closed by</th>
                                <th class="is-end">Report</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($history as $row)
                                <tr>
                                    <td>
                                        <strong>{{ $row->business_date->format('d M Y') }}</strong>
                                        <span class="nv-sub">{{ $row->business_date->format('l') }}</span>
                                    </td>
                                    <td class="is-end">{{ $row->rooms_sold }}</td>
                                    <td class="is-end">{{ $row->figure('rooms.occupancy', 0) }}%</td>
                                    <td class="is-end">₹{{ number_format((float) $row->figure('revenue.room', 0), 2) }}</td>
                                    <td class="is-end">₹{{ number_format((float) $row->figure('performance.adr', 0), 2) }}</td>
                                    <td class="is-end">₹{{ number_format((float) $row->figure('collection.total', 0), 2) }}</td>
                                    <td>
                                        {{ $row->closer?->name ?? '—' }}
                                        <span class="nv-sub">{{ $row->closed_at?->format('d/m/Y h:i A') }}</span>
                                    </td>
                                    <td class="is-end">
                                        <a href="{{ route('front-office.night-audit.show', $row) }}"
                                           class="nv-btn nv-btn-sm nv-btn-ghost">
                                            <x-icon name="external" /> Open
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
    </div>
@endsection
