@extends('layouts.app')

@section('title', 'Seasons')

@php
    $mayAdd = can_here('add');
    $mayEdit = can_here('edit');
    $mayDelete = can_here('delete');

    $colours = \App\Models\Rate\RateSeason::COLOURS;
@endphp

@section('content')
    <x-page-header
        title="Seasons"
        subtitle="Named stretches of the calendar, so a rate can say “Peak” instead of repeating two dates."
        :crumbs="['Home' => url('/'), 'Rate Management', 'Seasons']"
    >
        <x-slot:actions>
            @canView('rates/calendar')
                <a href="{{ route('rates.calendar') }}" class="nv-btn nv-btn-outline">
                    <x-icon name="calendar" /> Rate calendar
                </a>
            @endCanView
            @canView('rates/rules')
                <a href="{{ route('rates.rules') }}" class="nv-btn nv-btn-primary">
                    <x-icon name="grid" /> The rate grid
                </a>
            @endCanView
        </x-slot:actions>
    </x-page-header>

    @if ($errors->any())
        <div class="nv-mt">
            <x-alert tone="danger" title="Not saved">{{ $errors->first() }}</x-alert>
        </div>
    @endif

    @if ($mayAdd && ! $editing)
        <form id="season-new" method="POST" action="{{ route('rates.seasons.store') }}">
            @csrf
            <input type="hidden" name="status" value="1" />
        </form>
    @endif

    @if ($mayEdit && $editing)
        <form id="season-edit" method="POST" action="{{ route('rates.seasons.update', $editing) }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="status" value="0" />
        </form>
    @endif

    <div class="nv-mt">
        <x-card flush>
            <div class="nv-table-wrap">
                <table class="nv-table nv-setup-grid">
                    <thead>
                        <tr>
                            <th style="min-width:180px">Season</th>
                            <th style="width:90px">Code</th>
                            <th style="width:160px">From</th>
                            <th style="width:160px">To</th>
                            <th style="width:120px">Band</th>
                            <th style="width:86px">Priority</th>
                            <th style="width:72px">Rates</th>
                            <th style="width:170px">&nbsp;</th>
                        </tr>
                    </thead>

                    <tbody>
                        @if ($mayAdd && ! $editing)
                            <tr class="is-new">
                                <td>
                                    <input form="season-new" name="name" class="nv-input"
                                           value="{{ old('name') }}" placeholder="Peak" required />
                                </td>
                                <td>
                                    <input form="season-new" name="code" class="nv-input" value="{{ old('code') }}" />
                                </td>
                                <td>
                                    <input form="season-new" type="date" name="from_date" class="nv-input"
                                           value="{{ old('from_date') }}" required />
                                </td>
                                <td>
                                    <input form="season-new" type="date" name="to_date" class="nv-input"
                                           value="{{ old('to_date') }}" required />
                                </td>
                                <td>
                                    <select form="season-new" name="colour" class="nv-select">
                                        @foreach ($colours as $key => $label)
                                            <option value="{{ $key }}" @selected(old('colour') === $key)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input form="season-new" type="number" name="priority" class="nv-input"
                                           value="{{ old('priority', 0) }}" min="0" max="255" />
                                </td>
                                <td>—</td>
                                <td>
                                    <button form="season-new" type="submit" class="nv-btn nv-btn-sm nv-btn-primary">
                                        <x-icon name="plus" /> Add season
                                    </button>
                                </td>
                            </tr>
                        @endif

                        @forelse ($rows as $row)
                            @if ($editing === $row->id)
                                <tr class="is-editing">
                                    <td>
                                        <input form="season-edit" name="name" class="nv-input"
                                               value="{{ old('name', $row->name) }}" required />
                                    </td>
                                    <td>
                                        <input form="season-edit" name="code" class="nv-input"
                                               value="{{ old('code', $row->code) }}" />
                                    </td>
                                    <td>
                                        <input form="season-edit" type="date" name="from_date" class="nv-input"
                                               value="{{ old('from_date', $row->from_date?->toDateString()) }}" required />
                                    </td>
                                    <td>
                                        <input form="season-edit" type="date" name="to_date" class="nv-input"
                                               value="{{ old('to_date', $row->to_date?->toDateString()) }}" required />
                                    </td>
                                    <td>
                                        <select form="season-edit" name="colour" class="nv-select">
                                            @foreach ($colours as $key => $label)
                                                <option value="{{ $key }}" @selected(old('colour', $row->colour) === $key)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input form="season-edit" type="number" name="priority" class="nv-input"
                                               value="{{ old('priority', $row->priority) }}" min="0" max="255" />
                                    </td>
                                    <td>
                                        <label class="nv-check-inline">
                                            <input form="season-edit" type="checkbox" name="status" value="1"
                                                   @checked(old('status', $row->status)) />
                                            On
                                        </label>
                                    </td>
                                    <td>
                                        <div class="nv-row-actions">
                                            <a href="{{ route('rates.seasons') }}" class="nv-btn nv-btn-sm nv-btn-ghost">Cancel</a>
                                            <button form="season-edit" type="submit" class="nv-btn nv-btn-sm nv-btn-primary">
                                                <x-icon name="check" /> Update
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @else
                                <tr @class(['is-off' => ! $row->isActive()])>
                                    <td>
                                        <span class="nv-season-dot is-{{ $row->colour ?: 'plum' }}"></span>
                                        <strong>{{ $row->name }}</strong>
                                        <span class="nv-sub">{{ $row->nights }} nights</span>
                                    </td>
                                    <td>{{ $row->code ?: '—' }}</td>
                                    <td>{{ $row->from_date->format('d M Y') }}</td>
                                    <td>{{ $row->to_date->format('d M Y') }}</td>
                                    <td>{{ $colours[$row->colour] ?? '—' }}</td>
                                    <td>{{ $row->priority }}</td>
                                    <td>{{ $row->rules_count }}</td>
                                    <td>
                                        <div class="nv-row-actions">
                                            @if ($mayEdit)
                                                <a href="{{ route('rates.seasons', ['edit' => $row->id]) }}"
                                                   class="nv-btn nv-btn-sm nv-btn-ghost">
                                                    <x-icon name="pencil" /> Edit
                                                </a>
                                            @endif

                                            @if ($mayDelete)
                                                <form method="POST" action="{{ route('rates.seasons.destroy', $row) }}"
                                                      data-confirm="Delete the season &quot;{{ $row->name }}&quot;?"
                                                      data-confirm-title="Delete season">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="nv-btn nv-btn-sm nv-btn-ghost">
                                                        <x-icon name="trash" />
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="8">
                                    <div class="nv-empty">
                                        <span class="nv-empty-icon"><x-icon name="calendar" /></span>
                                        <strong>No season yet</strong>
                                        <p>
                                            Seasons are optional — a rate can carry its own two dates. They earn
                                            their keep once the same stretch is priced for several room types.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>

    <div class="nv-mt">
        <x-alert tone="info" title="Seasons may overlap">
            A short “Diwali” laid over a long “Peak” is the normal case. The season with the higher priority
            wins the nights they share, so a special can be laid over a season without editing the season.
        </x-alert>
    </div>
@endsection
