@extends('layouts.app')

@section('title', 'Police Register')

@php
    use App\Support\Compliance;

    /*
        One row per PERSON, not per room — the register a station asks for
        lists names, and a family of four is four lines.

        ID numbers are masked unless the signed-in user has Delete on this
        screen. That is a deliberate use of an odd permission: there is no
        "reveal" flag in the matrix, and Delete is the one nobody gets by
        accident. It is stated on screen so nobody has to guess.
    */
    $full = can_here('delete');
    $when = \Carbon\CarbonImmutable::parse($date);
@endphp

@section('content')
    <x-page-header
        title="Police Register"
        :subtitle="'Everybody who slept here on the night of ' . $when->format('d M Y') . '.'"
        :crumbs="['Home' => url('/'), 'Compliance', 'Police Register']"
    >
        <x-slot:actions>
            <a href="{{ route('compliance.police-register.print', request()->query()) }}" target="_blank"
               class="nv-btn nv-btn-outline">
                <x-icon name="file" /> Print
            </a>

            @if ($full)
                <a href="{{ route('compliance.police-register.export', request()->query()) }}"
                   class="nv-btn nv-btn-primary">
                    <x-icon name="download" /> Export CSV
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="nv-grid nv-grid-4">
        <x-stat label="People" :value="$summary['people']" icon="users" />
        <x-stat label="Rooms" :value="$summary['rooms']" icon="desktop" tone="info" />
        <x-stat label="Foreign nationals" :value="$summary['foreign']" icon="globe"
                :tone="$summary['foreign'] ? 'warning' : 'primary'"
                caption="Each needs a Form C" />
        <x-stat label="No ID recorded" :value="$summary['no_id']" icon="alert"
                :tone="$summary['no_id'] ? 'danger' : 'success'"
                caption="The one an officer will ask about" />
    </div>

    <div class="nv-mt">
        <x-card flush>
            <form method="GET" class="nv-toolbar">
                <x-field label="Night of" name="date">
                    <x-input type="date" name="date" :value="$date" />
                </x-field>

                <div class="nv-field-search">
                    <x-icon name="search" />
                    <input type="search" name="q" value="{{ $filters['q'] }}" class="nv-input"
                           placeholder="Name, mobile or room…" />
                </div>

                <label class="nv-check-inline">
                    <input type="checkbox" name="foreign" value="1" @checked($filters['foreign']) />
                    Foreign nationals only
                </label>

                <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="search" /> Show</button>

                @if ($filters['q'] || $filters['foreign'])
                    <a href="{{ route('compliance.police-register', ['date' => $date]) }}"
                       class="nv-btn nv-btn-ghost">Reset</a>
                @endif
            </form>

            @if ($rows->isEmpty())
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="users" /></span>
                    <strong>Nobody in the house that night</strong>
                    <p>Either the hotel was empty, or the filters are too narrow.</p>
                </div>
            @else
                <div class="nv-table-wrap">
                    <table class="nv-table nv-table-compact">
                        <thead>
                            <tr>
                                <th style="width:36px">#</th>
                                <th>Room</th>
                                <th>Name</th>
                                <th>Relation</th>
                                <th>Age</th>
                                <th>Nationality</th>
                                <th>ID</th>
                                <th>Mobile</th>
                                <th>Arrived</th>
                                <th>Departs</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $i => $row)
                                <tr @class(['nv-cm-foreign' => $row['is_foreign']])>
                                    <td class="nv-muted">{{ $i + 1 }}</td>
                                    <td><strong>{{ $row['room_no'] }}</strong></td>
                                    <td>
                                        {{ $row['name'] }}
                                        @if ($row['relation'] === 'Self')
                                            <span class="nv-sub">{{ $row['folio_no'] }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $row['relation'] }}</td>
                                    <td>{{ $row['age'] ?: '—' }}</td>
                                    <td>{{ $row['nationality'] }}</td>
                                    <td>
                                        @if (trim((string) $row['id_number']) === '')
                                            <span class="nv-cm-warn">Not recorded</span>
                                        @else
                                            <span class="nv-cm-id">{{ Compliance::reveal($row['id_number'], $row['id_type']) }}</span>
                                            <span class="nv-sub">{{ Compliance::idLabel($row['id_type']) }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $row['mobile'] ?: '—' }}</td>
                                    <td>{{ \Carbon\CarbonImmutable::parse($row['arrived'])->format('d/m/Y') }}</td>
                                    <td>{{ \Carbon\CarbonImmutable::parse($row['departs'])->format('d/m/Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
    </div>

    <div class="nv-mt">
        <x-alert :tone="$full ? 'warning' : 'info'" :title="$full ? 'You are seeing full ID numbers' : 'ID numbers are masked'">
            @if ($full)
                Your account has Delete on this screen, which is what unlocks the whole number and the CSV
                export. Everybody else sees the last four digits only. A register full of ID numbers is the
                most sensitive thing this system holds — take it off the machine only when the station asks
                for it.
            @else
                Only the last four digits are shown, which is enough to confirm a guest at the desk. The whole
                number and the CSV export need the Delete permission on this screen, granted under
                Administration → Users.
            @endif
        </x-alert>
    </div>
@endsection
