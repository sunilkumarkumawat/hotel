@extends('layouts.app')

@section('title', 'Birthdays & Anniversaries')

@php
    use App\Support\GuestCrm;

    $span = fn (int $d) => request()->fullUrlWithQuery(['days' => $d]);

    // Grouped by the day itself — a list of dates is what somebody plans from,
    // and a flat list of names sorted by date is not.
    $byDay = $occasions->groupBy('on');
@endphp

@section('content')
    <x-page-header
        title="Birthdays & Anniversaries"
        subtitle="Guests worth a message in the next {{ $days }} days."
        :crumbs="['Home' => url('/'), 'Guest CRM', 'Birthdays']"
    >
        <x-slot:actions>
            <div class="nv-tabs">
                @foreach ([7 => 'A week', 14 => 'A fortnight', 30 => 'A month'] as $value => $label)
                    <a href="{{ $span($value) }}" @class(['nv-tab', 'is-active' => $days === $value])>{{ $label }}</a>
                @endforeach
            </div>
        </x-slot:actions>
    </x-page-header>

    @if ($occasions->isEmpty())
        <div class="nv-mt">
            <x-card>
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="calendar" /></span>
                    <strong>Nothing coming up</strong>
                    <p>
                        Nobody on file has a birthday or anniversary in the next {{ $days }} days — or the
                        dates have not been recorded. They can be added on a guest's profile.
                    </p>
                </div>
            </x-card>
        </div>
    @else
        <div class="nv-mt nv-stack">
            @foreach ($byDay as $date => $rows)
                @php $when = \Carbon\CarbonImmutable::parse($date); @endphp

                <x-card :title="$when->format('l, d M Y')"
                        :subtitle="$rows->first()->in_days === 0
                            ? 'Today'
                            : ($rows->first()->in_days === 1 ? 'Tomorrow' : 'In ' . $rows->first()->in_days . ' days')"
                        :flush="true">
                    <div class="nv-table-wrap">
                        <table class="nv-table nv-table-compact">
                            <thead>
                                <tr>
                                    <th>Guest</th>
                                    <th>Occasion</th>
                                    <th>Tier</th>
                                    <th class="is-end">Stays</th>
                                    <th>Mobile</th>
                                    <th>Email</th>
                                    <th class="is-end">&nbsp;</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    <tr>
                                        <td>
                                            <div class="nv-crm-who">
                                                <x-avatar :name="$row->name" size="sm" />
                                                <strong>{{ $row->name }}</strong>
                                            </div>
                                        </td>
                                        <td>
                                            <span @class(['nv-crm-occasion', 'is-anniversary' => $row->occasion === 'Anniversary'])>
                                                {{ $row->occasion }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="nv-crm-tier is-{{ $row->guest->tier ?: 'guest' }}">
                                                {{ GuestCrm::tierLabel($row->guest->tier) }}
                                            </span>
                                        </td>
                                        <td class="is-end">{{ number_format((int) $row->guest->stays) }}</td>
                                        <td>{{ $row->guest->mobile ?: '—' }}</td>
                                        <td>{{ $row->guest->email ?: '—' }}</td>
                                        <td class="is-end">
                                            <a href="{{ route('crm.guests.show', $row->guest->id) }}"
                                               class="nv-btn nv-btn-sm nv-btn-ghost" title="View profile">
                                                <x-icon name="eye" />
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif

    <div class="nv-mt">
        <x-alert tone="info" title="Matched on the day and month, never the year">
            Otherwise a birthday would match once and never again. Dates come from the guest's profile —
            the booking screen asks for a date of birth, and the anniversary is typed in on the profile.
        </x-alert>
    </div>
@endsection
