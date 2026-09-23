@extends('layouts.app')

@section('title', 'Night of ' . $day->business_date->format('d M Y'))

@php
    $isLast = \App\Models\FrontOffice\BusinessDay::lastClosed((int) $day->branch_id)?->id === $day->id;
@endphp

@section('content')
    <x-page-header
        :title="'Night of ' . $day->business_date->format('d M Y')"
        :subtitle="'Frozen at the close. ' . $day->business_date->format('l') . ', audited '
            . ($day->closed_at?->format('d M Y \a\t h:i A') ?? '—') . '.'"
        :crumbs="['Home' => url('/'), 'Front Office', 'Night Audit' => route('front-office.night-audit'), $day->business_date->format('d M Y')]"
    >
        <x-slot:actions>
            <a href="{{ route('front-office.night-audit.print', $day) }}" target="_blank" class="nv-btn nv-btn-outline">
                <x-icon name="file" /> Print the report
            </a>

            <a href="{{ route('front-office.night-audit') }}" class="nv-btn nv-btn-ghost">
                <x-icon name="arrow-right" /> Today's audit
            </a>
        </x-slot:actions>
    </x-page-header>

    {{-- What the audit did, as opposed to what it found. --}}
    <div class="nv-na-hero">
        <div class="nv-na-hero-main">
            <p class="nv-na-hero-label">Audited night</p>
            <p class="nv-na-hero-date">{{ $day->business_date->format('d M Y') }}</p>
            <p class="nv-na-hero-day">{{ $day->business_date->format('l') }}</p>
        </div>

        <div class="nv-na-hero-side">
            <span class="nv-badge is-success"><x-icon name="check-circle" /> Closed</span>
            <p>
                <b>{{ $day->nights_posted }}</b> room {{ \Illuminate\Support\Str::plural('night', $day->nights_posted) }}
                posted, <b>{{ $day->no_shows }}</b> {{ \Illuminate\Support\Str::plural('booking', $day->no_shows) }}
                marked no show, by {{ $day->closer?->name ?? 'the system' }}.
            </p>

            @if ($day->note)
                <p class="nv-na-note"><x-icon name="info" /> {{ $day->note }}</p>
            @endif
        </div>
    </div>

    <div class="nv-mt">
        @include('front-office.night-audit.partials.figures', ['figures' => $figures])
    </div>

    {{--
        Reopening is the emergency exit, not a button. It only ever applies to
        the most recent close, and it is kept out of the way at the bottom
        behind a confirmation — a manager who needs it will look for it.
    --}}
    @if ($isLast)
        @canDelete('front-office/night-audit')
            <div class="nv-mt">
                <x-card title="Open this night again"
                        subtitle="Only the most recent close can be reopened, and only until the next one.">
                    <p class="nv-na-warn">
                        The rent this audit posted stays on the guests' folios — it is money they owe, and
                        removing it would take real charges off real bills. Running the audit again after
                        reopening will find those nights already charged and will not charge them twice.
                        What comes back is the ability to fix the day and take its figures afresh.
                    </p>

                    <form method="POST" action="{{ route('front-office.night-audit.reopen', $day) }}"
                          data-confirm="Open the night of {{ $day->business_date->format('d M Y') }} again? The business date moves back to it."
                          data-confirm-title="Reopen an audited night"
                          data-confirm-action="Reopen the night">
                        @csrf
                        @method('DELETE')

                        <button type="submit" class="nv-btn nv-btn-outline nv-btn-sm">
                            <x-icon name="refresh" /> Reopen this night
                        </button>
                    </form>
                </x-card>
            </div>
        @endCanDelete
    @endif
@endsection
