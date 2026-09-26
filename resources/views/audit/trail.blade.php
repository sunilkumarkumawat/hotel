@extends('layouts.app')

@section('title', 'Audit Trail')

@php
    $query = fn (array $extra) => request()->fullUrlWithQuery($extra);
@endphp

@section('content')
    <x-page-header
        title="Audit Trail"
        subtitle="Who changed what, and when."
        :crumbs="['Home' => url('/'), 'Shift & Audit', 'Audit Trail']"
    >
        <x-slot:actions>
            <a href="{{ route('audit.trail.export', request()->query()) }}" class="nv-btn nv-btn-outline">
                <x-icon name="download" /> Export CSV
            </a>
            @canView('audit/logins')
                <a href="{{ route('audit.logins') }}" class="nv-btn nv-btn-primary">
                    <x-icon name="lock" /> Sign-ins
                </a>
            @endCanView
        </x-slot:actions>
    </x-page-header>

    <div class="nv-mt">
        <x-card flush>
            <form method="GET" class="nv-toolbar">
                <div class="nv-field-search">
                    <x-icon name="search" />
                    <input type="search" name="q" value="{{ $filters['q'] }}" class="nv-input"
                           placeholder="A bill number, a guest, a room…" />
                </div>

                <x-field label="From" name="from"><x-input type="date" name="from" :value="$from" /></x-field>
                <x-field label="To" name="to"><x-input type="date" name="to" :value="$to" /></x-field>

                <x-field label="Who" name="user">
                    <select name="user" class="nv-select">
                        <option value="">Everybody</option>
                        @foreach ($users as $id => $name)
                            <option value="{{ $id }}" @selected((int) $filters['user'] === (int) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </x-field>

                <x-field label="Area" name="area">
                    <select name="area" class="nv-select">
                        <option value="">Everything</option>
                        @foreach ($areas as $key => $label)
                            <option value="{{ $key }}" @selected($filters['area'] === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </x-field>

                <x-field label="Action" name="action">
                    <select name="action" class="nv-select">
                        <option value="">Any</option>
                        @foreach ($actions as $key => $label)
                            <option value="{{ $key }}" @selected($filters['action'] === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </x-field>

                <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="search" /> Show</button>

                @if ($filters['q'] || $filters['user'] || $filters['area'] || $filters['action'])
                    <a href="{{ route('audit.trail') }}" class="nv-btn nv-btn-ghost">Reset</a>
                @endif
            </form>

            {{-- How much of each kind there is, and a one-click way into it. --}}
            @if ($counts !== [])
                <div class="nv-au-chips">
                    @foreach ($areas as $key => $label)
                        @if (($counts[$key] ?? 0) > 0)
                            <a href="{{ $query(['area' => $filters['area'] === $key ? '' : $key, 'page' => 1]) }}"
                               @class(['nv-au-chip', 'is-on' => $filters['area'] === $key])>
                                {{ $label }} <b>{{ number_format($counts[$key]) }}</b>
                            </a>
                        @endif
                    @endforeach
                </div>
            @endif

            @if ($rows->isEmpty())
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="shield" /></span>
                    <strong>Nothing matches</strong>
                    <p>
                        Either nobody did anything of that kind in this window, or the window is the
                        wrong one — the trail opens on the last seven days.
                    </p>
                </div>
            @else
                <div class="nv-table-wrap">
                    <table class="nv-table">
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>Who</th>
                                <th>What happened</th>
                                <th>Changed</th>
                                <th>Where from</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    <td class="nv-au-when">
                                        {{ $row->happened_at?->format('d M, h:i A') }}
                                        <span class="nv-sub">{{ $row->happened_at?->diffForHumans() }}</span>
                                    </td>
                                    <td>
                                        <strong>{{ $row->user_name ?: 'System' }}</strong>
                                        <span class="nv-sub">{{ $row->area_label }}</span>
                                    </td>
                                    <td>
                                        <span @class(['nv-au-action', 'is-' . $row->tone])>{{ $row->action_label }}</span>
                                        <div class="nv-au-what">{{ $row->summary ?: $row->subject_label }}</div>
                                    </td>
                                    <td>
                                        @php $changed = $row->changeList(); @endphp

                                        @if ($changed === [])
                                            <span class="nv-muted">—</span>
                                        @else
                                            <div class="nv-au-diff">
                                                @foreach (array_slice($changed, 0, 4) as $change)
                                                    <div>
                                                        <span>{{ $change['column'] }}</span>
                                                        <b>{{ $change['from'] }}</b>
                                                        <x-icon name="arrow-right" />
                                                        <b class="is-to">{{ $change['to'] }}</b>
                                                    </div>
                                                @endforeach

                                                @if (count($changed) > 4)
                                                    <span class="nv-sub">and {{ count($changed) - 4 }} more</span>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td class="nv-au-ip">
                                        {{ $row->ip ?: '—' }}
                                        @if ($row->method && $row->method !== 'GET')
                                            <span class="nv-sub">{{ $row->method }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="nv-card-foot">{{ $rows->links() }}</div>
            @endif
        </x-card>
    </div>

    <div class="nv-mt">
        <x-alert tone="info" title="Nothing here can be edited or deleted">
            Not by a manager, and not by an administrator either — there is no route for it and there is
            not going to be one. A log somebody can tidy up is not evidence of anything.
            Only the columns that actually changed are recorded, with what they were before, so a row
            reads as a sentence rather than as a copy of the whole record.
        </x-alert>
    </div>
@endsection
