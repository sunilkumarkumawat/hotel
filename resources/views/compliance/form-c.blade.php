@extends('layouts.app')

@section('title', 'Form C')

@php
    /*
        Two lists, and the order is the point: who still needs a form comes
        first, and what has already been done is underneath. A compliance
        screen that opens on a history table is one nobody acts on.
    */
    $mayAdd = can_here('add');
@endphp

@section('content')
    <x-page-header
        title="Form C"
        subtitle="Every foreign national staying here has to be reported to the FRRO."
        :crumbs="['Home' => url('/'), 'Compliance', 'Form C']"
    />

    <div class="nv-grid nv-grid-3">
        <x-stat label="Waiting for a form" :value="$pending->count()" icon="alert"
                :tone="$pending->isEmpty() ? 'success' : 'danger'"
                caption="Foreign guests, last 30 days" />
        <x-stat label="Filed" :value="$entries->where('filed_at', '!=', null)->count()" icon="check-circle" tone="success"
                caption="With a reference recorded" />
        <x-stat label="Written, not yet filed" :value="$entries->whereNull('filed_at')->count()" icon="clock" tone="warning"
                caption="Printed or saved, not sent" />
    </div>

    {{-- ── Who still needs one ───────────────────────────────────────────── --}}
    <div class="nv-mt">
        <x-card title="Needs a Form C"
                :subtitle="$pending->isEmpty()
                    ? 'Every foreign guest of the last thirty days has one.'
                    : 'Foreign guests with no form written yet.'"
                :flush="true">
            @if ($pending->isEmpty())
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="check-circle" /></span>
                    <strong>Nothing outstanding</strong>
                    <p>
                        A stay appears here when it is marked as a foreign guest's — tick Nationality at
                        check-in, or open any stay below and write the form.
                    </p>
                </div>
            @else
                <div class="nv-table-wrap">
                    <table class="nv-table">
                        <thead>
                            <tr>
                                <th>Guest</th>
                                <th>Room</th>
                                <th>Nationality</th>
                                <th>Arrived</th>
                                <th>Departs</th>
                                <th class="is-end">&nbsp;</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pending as $stay)
                                <tr>
                                    <td>
                                        <strong>{{ $stay->guest_name }}</strong>
                                        <span class="nv-sub">{{ $stay->folio_no }}</span>
                                    </td>
                                    <td>{{ $stay->room_no ?: '—' }}</td>
                                    <td>{{ $stay->nationality ?: 'Not recorded' }}</td>
                                    <td>{{ \Carbon\CarbonImmutable::parse($stay->checkin_date)->format('d M Y') }}</td>
                                    <td>
                                        {{ \Carbon\CarbonImmutable::parse($stay->actual_checkout_date ?: $stay->expected_checkout_date)->format('d M Y') }}
                                        @if ($stay->status === 'checked_out')
                                            <span class="nv-sub">already left</span>
                                        @endif
                                    </td>
                                    <td class="is-end">
                                        @if ($mayAdd)
                                            <a href="{{ route('compliance.form-c.create', $stay->id) }}"
                                               class="nv-btn nv-btn-sm nv-btn-primary">
                                                <x-icon name="file" /> Write the form
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
    </div>

    {{-- ── What has been written ─────────────────────────────────────────── --}}
    <div class="nv-mt">
        <x-card title="Forms written" subtitle="The last hundred, newest first." :flush="true">
            <form method="GET" class="nv-toolbar">
                <div class="nv-field-search">
                    <x-icon name="search" />
                    <input type="search" name="q" value="{{ $term }}" class="nv-input" placeholder="Guest name…" />
                </div>
                <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="search" /> Search</button>
                @if ($term)
                    <a href="{{ route('compliance.form-c') }}" class="nv-btn nv-btn-ghost">Reset</a>
                @endif
            </form>

            @if ($entries->isEmpty())
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="file" /></span>
                    <strong>No forms yet</strong>
                    <p>They appear here as they are written.</p>
                </div>
            @else
                <div class="nv-table-wrap">
                    <table class="nv-table">
                        <thead>
                            <tr>
                                <th>Guest</th>
                                <th>Passport</th>
                                <th>Visa</th>
                                <th>Room · stay</th>
                                <th>Filed</th>
                                <th class="is-end">&nbsp;</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($entries as $entry)
                                <tr @class(['is-off' => $entry->isFiled()])>
                                    <td>
                                        <strong>{{ $entry->person }}</strong>
                                        <span class="nv-sub">{{ $entry->nationality?->name ?: 'Nationality not set' }}</span>
                                    </td>
                                    <td>
                                        {{ \App\Support\Compliance::reveal($entry->passport_no, 'passport', 'compliance/form-c') }}
                                        @if ($entry->passport_expiry_date)
                                            <span class="nv-sub">expires {{ $entry->passport_expiry_date->format('d M Y') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $entry->visa_type ?: '—' }}
                                        @if ($entry->visaExpiresDuringStay())
                                            <span class="nv-sub nv-cm-warn">
                                                expires {{ $entry->visa_expiry_date->format('d M Y') }} — before they leave
                                            </span>
                                        @elseif ($entry->visa_expiry_date)
                                            <span class="nv-sub">expires {{ $entry->visa_expiry_date->format('d M Y') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $entry->checkIn?->room?->room_no ?: '—' }}
                                        <span class="nv-sub">{{ $entry->checkIn?->folio_no }}</span>
                                    </td>
                                    <td>
                                        @if ($entry->isFiled())
                                            <x-badge tone="success">{{ $entry->filed_at->format('d M Y') }}</x-badge>
                                            @if ($entry->reference_no)
                                                <span class="nv-sub">{{ $entry->reference_no }}</span>
                                            @endif
                                        @else
                                            <x-badge tone="warning">Not filed</x-badge>
                                        @endif
                                    </td>
                                    <td class="is-end">
                                        <div class="nv-row-actions">
                                            <a href="{{ route('compliance.form-c.print', $entry) }}" target="_blank"
                                               class="nv-btn nv-btn-sm nv-btn-ghost">
                                                <x-icon name="file" /> Print
                                            </a>

                                            @if (can_here('edit'))
                                                <a href="{{ route('compliance.form-c.create', ['checkIn' => $entry->check_in_id, 'pax' => $entry->check_in_pax_id]) }}"
                                                   class="nv-btn nv-btn-sm nv-btn-ghost">
                                                    <x-icon name="pencil" /> Edit
                                                </a>
                                            @endif

                                            @unless ($entry->isFiled())
                                                @if (can_here('edit'))
                                                    <form method="POST" action="{{ route('compliance.form-c.filed', $entry) }}"
                                                          class="nv-cm-filed">
                                                        @csrf
                                                        <input type="text" name="reference_no" class="nv-input"
                                                               placeholder="Reference" maxlength="60" />
                                                        <button type="submit" class="nv-btn nv-btn-sm nv-btn-outline">
                                                            <x-icon name="check" /> Filed
                                                        </button>
                                                    </form>
                                                @endif
                                            @endunless
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
    </div>

    <div class="nv-mt">
        <x-alert tone="info" title="This screen records, it does not file">
            The form is written and printed here, and the reference the portal gives back is recorded against
            it. Submitting to the FRRO is still done on their site — nothing here talks to it, and a form that
            says “Filed” means somebody sent it and typed the reference in.
        </x-alert>
    </div>
@endsection
