@extends('layouts.print')

@section('title', 'Guest Registration Card')

@php
    /**
     * One template for both the filled card and the blank one.
     *
     * `$card` is null for a blank: every value falls back to an empty string
     * and the rules underneath are what the guest writes on. That is the whole
     * difference — printing two near-identical templates is how they drift
     * apart the first time a field is added.
     */
    $v = fn (?string $key = null) => $card[$key] ?? '';
    $rows = $card['rooms'] ?? [];
@endphp

@section('content')
    {{-- ── Letterhead ────────────────────────────────────────────────────── --}}
    <div class="pr-head">
        @if ($branch->logo && file_exists(public_path($branch->logo)))
            <img src="{{ asset($branch->logo) }}" alt="" class="pr-logo" />
        @endif

        <div>
            <p class="pr-hotel-name">{{ $branch->legal_name ?: $branch->branch_name }}</p>

            @if ($branch->legal_name && $branch->legal_name !== $branch->branch_name)
                <p class="pr-hotel-line">{{ $branch->branch_name }}</p>
            @endif

            @if ($branch->address)
                <p class="pr-hotel-line">{{ $branch->address }}{{ $branch->pin_code ? ' - ' . $branch->pin_code : '' }}</p>
            @endif

            @if ($branch->mobile_number)
                <p class="pr-hotel-line">Mobile No. :- {{ $branch->mobile_number }}</p>
            @endif

            @if ($branch->email)
                <p class="pr-hotel-line">Email ID :{{ $branch->email }}</p>
            @endif

            @if ($branch->gst_no)
                <p class="pr-hotel-line">GSTNo:{{ $branch->gst_no }}</p>
            @endif

            @if ($branch->sac_code)
                <p class="pr-hotel-line">HEADER SACCODE : {{ $branch->sac_code }}</p>
            @endif
        </div>
    </div>

    <h1 class="pr-title">Guest Registration Card</h1>

    {{-- ── Guest and stay ────────────────────────────────────────────────── --}}
    <div class="pr-fields">
        <div class="pr-col">
            <div class="pr-line">
                <span class="pr-line-label">Reservation No. :</span>
                <span class="pr-line-value">{{ $v('reservation_no') }}</span>
            </div>

            <div class="pr-line">
                <span class="pr-line-label">Name :</span>
                <span class="pr-line-value">{{ $v('name') }}</span>
            </div>

            <div class="pr-line is-tall">
                <span class="pr-line-label">Permanent Address :</span>
                <span class="pr-line-value">{{ $v('address') }}</span>
            </div>

            <div class="pr-line">
                <span class="pr-line-label">Nationality :</span>
                <span class="pr-line-value">{{ $v('nationality') }}</span>
            </div>

            <div class="pr-line">
                <span class="pr-line-label">Email Address :</span>
                <span class="pr-line-value">{{ $v('email') }}</span>
            </div>

            <div class="pr-line">
                <span class="pr-line-label">Contact No.(M) :</span>
                <span class="pr-line-value">{{ $v('mobile') }}</span>
            </div>

            <div class="pr-line">
                <span class="pr-line-label">Company/Organization :</span>
                <span class="pr-line-value">{{ $v('company') }}</span>
            </div>

            <div class="pr-line">
                <span class="pr-line-label">Booked By :</span>
                <span class="pr-line-value">{{ $v('booked_by') }}</span>
            </div>

            <div class="pr-line">
                <span class="pr-line-label">GSTIN :</span>
                <span class="pr-line-value">{{ $v('company_gst_no') }}</span>
            </div>
        </div>

        <div class="pr-col">
            <div class="pr-line">
                <span class="pr-line-label">Date of Arrival in India :</span>
                <span class="pr-line-value">{{ $v('arrival_in_india') }}</span>
            </div>

            <div class="pr-line">
                <span class="pr-line-label">Arrived From :</span>
                <span class="pr-line-value">{{ $v('arrived_from') }}</span>
            </div>

            <div class="pr-line">
                <span class="pr-line-label">Departure To :</span>
                <span class="pr-line-value">{{ $v('departure_to') }}</span>
            </div>

            <div class="pr-line">
                <span class="pr-line-label">Employed In India :</span>
                <span class="pr-line-value pr-choice">Yes / No</span>
            </div>

            <div class="pr-line">
                <span class="pr-line-label">Purpose of Visit :</span>
                <span class="pr-line-value">{{ $v('purpose') }}</span>
            </div>

            <div class="pr-line">
                <span class="pr-line-label">Proposed Duration of Stay :</span>
                <span class="pr-line-value">{{ $v('duration') }}</span>
            </div>

            <div class="pr-line">
                <span class="pr-line-label">C Form No. :</span>
                <span class="pr-line-value">{{ $v('c_form_no') }}</span>
            </div>

            <div class="pr-line">
                <span class="pr-line-label">Arrival Date/Time :</span>
                <span class="pr-line-value">{{ $v('arrival_at') }}</span>
            </div>

            <div class="pr-line">
                <span class="pr-line-label">Departure Date/Time :</span>
                <span class="pr-line-value">{{ $v('departure_at') }}</span>
            </div>
        </div>
    </div>

    {{-- ── Rooms ─────────────────────────────────────────────────────────── --}}
    <table class="pr-table">
        <thead>
            <tr>
                <th>Room No</th>
                <th>Occupancy</th>
                <th>Room Category</th>
                <th>Plan</th>
                <th>M</th>
                <th>F</th>
                <th>Child</th>
                <th>Rate</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="is-center">{{ $row['room_no'] }}</td>
                    <td class="is-center">{{ $row['occupancy'] }}</td>
                    <td>{{ $row['category'] }}</td>
                    <td class="is-center">{{ $row['plan'] }}</td>
                    <td class="is-center">{{ $row['male'] }}</td>
                    <td class="is-center">{{ $row['female'] }}</td>
                    <td class="is-center">{{ $row['child'] }}</td>
                    <td class="is-num">{{ $row['rate'] }}</td>
                </tr>
            @empty
                {{-- A blank card needs somewhere to write the room in. --}}
                <tr class="is-blank"><td colspan="8"></td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- ── Billing and terms ─────────────────────────────────────────────── --}}
    <div class="pr-block">
        <p class="pr-block-label">Billing Instruction :</p>
        <div class="pr-rule">{{ $v('billing_instruction') }}</div>
    </div>

    <div class="pr-block">
        <p class="pr-block-label">Terms &amp; Condition :-</p>

        @if ($terms)
            <ol class="pr-terms">
                @foreach ($terms as $term)
                    <li>{{ $term }}</li>
                @endforeach
            </ol>
        @else
            <div class="pr-rule"></div>
            <div class="pr-rule"></div>
        @endif
    </div>

    <div class="pr-sign">
        <div class="pr-sign-box">Guest Signature</div>
        <div class="pr-sign-box">For {{ $branch->branch_name }}</div>
    </div>

    @unless ($terms)
        <p class="pr-note">
            No terms are set for this branch yet. Add them under
            <strong>Administration → Branches → {{ $branch->branch_name }} → Registration card terms</strong>
            and they will print here.
        </p>
    @endunless

    <div class="pr-foot">
        <span>Printed on {{ now()->format('d M Y h:i A') }}</span>
        <span>Printed By {{ auth()->user()?->name ?? auth()->user()?->username }}</span>
        <span>Page 1 of 1</span>
    </div>
@endsection
