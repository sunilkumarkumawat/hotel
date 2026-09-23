@extends('layouts.print')

@section('title', 'Form C')

@php
    $d = fn ($date) => $date ? $date->format('d/m/Y') : '—';
    $t = fn ($value) => trim((string) $value) !== '' ? $value : '—';

    $checkIn = $entry->checkIn;
@endphp

@section('content')
    <div class="pr-head">
        <div>
            <p class="pr-hotel-name">{{ $branch?->legal_name ?: $branch?->branch_name }}</p>

            @if ($branch?->address)
                <p class="pr-hotel-line">{{ $branch->address }}{{ $branch->pin_code ? ' - ' . $branch->pin_code : '' }}</p>
            @endif

            @if ($branch?->mobile_number)
                <p class="pr-hotel-line">Mobile No. :- {{ $branch->mobile_number }}</p>
            @endif

            @if ($branch?->frro_hotel_code)
                <p class="pr-hotel-line">FRRO Hotel Code: {{ $branch->frro_hotel_code }}</p>
            @endif
        </div>
    </div>

    <h1 class="pr-title">Form C — Arrival Report of Foreigner</h1>

    <p class="pr-stamp">
        To be furnished by the keeper of the hotel in respect of every foreign national staying there.
    </p>

    {{-- ── The guest ─────────────────────────────────────────────────────── --}}
    <p class="pr-block-label">Particulars of the foreigner</p>

    <table class="pr-table pr-grid">
        <tr>
            <td class="is-key">Name in full</td>
            <td colspan="3">{{ $t($entry->name) }}</td>
        </tr>
        <tr>
            <td class="is-key">Nationality</td>
            <td>{{ $t($entry->nationality?->name) }}</td>
            <td class="is-key">Sex</td>
            <td>{{ $entry->sex ? ucfirst($entry->sex) : '—' }}</td>
        </tr>
        <tr>
            <td class="is-key">Date of birth</td>
            <td>{{ $d($entry->date_of_birth) }}</td>
            <td class="is-key">Employed in India</td>
            <td>{{ $entry->employed_in_india ? 'Yes — ' . $t($entry->employer) : 'No' }}</td>
        </tr>
        <tr>
            <td class="is-key">Permanent address</td>
            <td colspan="3">{{ $t($entry->permanent_address) }}</td>
        </tr>
        <tr>
            <td class="is-key">Address in India</td>
            <td colspan="3">{{ $t($entry->address_in_india) }}</td>
        </tr>
    </table>

    {{-- ── Passport and visa ─────────────────────────────────────────────── --}}
    <p class="pr-block-label">Passport and visa</p>

    <table class="pr-table pr-grid">
        <tr>
            <td class="is-key">Passport no.</td>
            <td>{{ $t($entry->passport_no) }}</td>
            <td class="is-key">Place of issue</td>
            <td>{{ $t($entry->passport_place_of_issue) }}</td>
        </tr>
        <tr>
            <td class="is-key">Passport issued on</td>
            <td>{{ $d($entry->passport_issue_date) }}</td>
            <td class="is-key">Valid until</td>
            <td>{{ $d($entry->passport_expiry_date) }}</td>
        </tr>
        <tr>
            <td class="is-key">Visa no.</td>
            <td>{{ $t($entry->visa_no) }}</td>
            <td class="is-key">Visa type</td>
            <td>{{ $t($entry->visa_type) }}</td>
        </tr>
        <tr>
            <td class="is-key">Visa issued at</td>
            <td>{{ $t($entry->visa_place_of_issue) }}</td>
            <td class="is-key">Visa issued on</td>
            <td>{{ $d($entry->visa_issue_date) }}</td>
        </tr>
        <tr>
            <td class="is-key">Visa valid until</td>
            <td>{{ $d($entry->visa_expiry_date) }}</td>
            <td class="is-key">&nbsp;</td>
            <td>&nbsp;</td>
        </tr>
    </table>

    {{-- ── The stay ──────────────────────────────────────────────────────── --}}
    <p class="pr-block-label">Arrival and stay</p>

    <table class="pr-table pr-grid">
        <tr>
            <td class="is-key">Arrived in India on</td>
            <td>{{ $d($entry->arrived_in_india_on) }}</td>
            <td class="is-key">Arrived from</td>
            <td>{{ $t($entry->arrived_from) }}</td>
        </tr>
        <tr>
            <td class="is-key">Arrived at this hotel</td>
            <td>{{ $checkIn?->checkin_date?->format('d/m/Y') }} {{ substr((string) $checkIn?->checkin_time, 0, 5) }}</td>
            <td class="is-key">Room no.</td>
            <td>{{ $checkIn?->room?->room_no ?: '—' }}</td>
        </tr>
        <tr>
            <td class="is-key">Intended departure</td>
            <td>{{ $checkIn ? \Carbon\CarbonImmutable::parse($checkIn->departsOn())->format('d/m/Y') : '—' }}</td>
            <td class="is-key">Purpose of visit</td>
            <td>{{ $t($entry->purpose_of_visit) }}</td>
        </tr>
        <tr>
            <td class="is-key">Next destination</td>
            <td>{{ $t($entry->next_destination) }}</td>
            <td class="is-key">On</td>
            <td>{{ $d($entry->next_destination_on) }}</td>
        </tr>
        @if ($entry->remark)
            <tr>
                <td class="is-key">Remarks</td>
                <td colspan="3">{{ $entry->remark }}</td>
            </tr>
        @endif
    </table>

    @if ($entry->isFiled())
        <p class="pr-note">
            Reported to the FRRO on {{ $entry->filed_at->format('d M Y') }}{{ $entry->reference_no ? ' — reference ' . $entry->reference_no : '' }}.
        </p>
    @endif

    <div class="pr-sign" style="margin-top:14mm">
        <div class="pr-sign-box">Signature of the foreigner</div>
        <div class="pr-sign-box">Signature of the hotel keeper</div>
    </div>

    <div class="pr-foot">
        <span>Printed on {{ now()->format('d M Y h:i A') }}</span>
        <span>Printed By {{ auth()->user()?->name ?? auth()->user()?->username }}</span>
        <span>Page 1 of 1</span>
    </div>
@endsection
