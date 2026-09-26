@extends('layouts.print')

@section('title', 'Guest Register')

@php
    use App\Support\Compliance;

    /*
        The printed register carries whole ID numbers only if the person who
        pressed Print is allowed to see them. A sheet handed over the counter
        is the same disclosure as a CSV, and it is made the same way.
    */
    $full = can_do('compliance/police-register', 'delete');
    $when = \Carbon\CarbonImmutable::parse($date);
@endphp

@section('content')
    <div class="pr-head">
        <div>
            <p class="pr-hotel-name">{{ $branch?->legal_name ?: $branch?->branch_name }}</p>

            @if ($branch?->address)
                <p class="pr-hotel-line">{{ $branch->address }}{{ $branch->pin_code ? ' - ' . $branch->pin_code : '' }}</p>
            @endif

            @if ($branch?->police_station)
                <p class="pr-hotel-line">Police Station: {{ $branch->police_station }}</p>
            @endif
        </div>
    </div>

    <h1 class="pr-title">Guest Register — night of {{ $when->format('d M Y') }}</h1>

    <table class="pr-table pr-grid">
        <tr>
            <td class="is-key">Persons</td>
            <td>{{ $summary['people'] }}</td>
            <td class="is-key">Rooms occupied</td>
            <td>{{ $summary['rooms'] }}</td>
            <td class="is-key">Foreign nationals</td>
            <td>{{ $summary['foreign'] }}</td>
        </tr>
    </table>

    <table class="pr-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Room</th>
                <th>Name</th>
                <th>Relation</th>
                <th>Age</th>
                <th>Nationality</th>
                <th>ID type</th>
                <th>ID number</th>
                <th>Arrived</th>
                <th>Departs</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $i => $row)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $row['room_no'] }}</td>
                    <td>{{ $row['name'] }}</td>
                    <td>{{ $row['relation'] }}</td>
                    <td>{{ $row['age'] ?: '—' }}</td>
                    <td>{{ $row['nationality'] }}</td>
                    <td>{{ Compliance::idLabel($row['id_type']) }}</td>
                    <td>
                        {{ trim((string) $row['id_number']) === ''
                            ? '—'
                            : ($full ? strtoupper($row['id_number']) : Compliance::mask($row['id_number'], $row['id_type'])) }}
                    </td>
                    <td>{{ \Carbon\CarbonImmutable::parse($row['arrived'])->format('d/m/Y') }}</td>
                    <td>{{ \Carbon\CarbonImmutable::parse($row['departs'])->format('d/m/Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="10">Nobody stayed in the house on this night.</td></tr>
            @endforelse
        </tbody>
    </table>

    @unless ($full)
        <p class="pr-note">
            ID numbers are shown in part. A register with whole numbers can be printed by a user with the
            Delete permission on this screen.
        </p>
    @endunless

    <div class="pr-sign" style="margin-top:12mm">
        <div class="pr-sign-box">For {{ $branch?->branch_name }}</div>
        <div class="pr-sign-box">Received by</div>
    </div>

    <div class="pr-foot">
        <span>Printed on {{ now()->format('d M Y h:i A') }}</span>
        <span>Printed By {{ auth()->user()?->name ?? auth()->user()?->username }}</span>
        <span>{{ $summary['people'] }} {{ \Illuminate\Support\Str::plural('person', $summary['people']) }}</span>
    </div>
@endsection
