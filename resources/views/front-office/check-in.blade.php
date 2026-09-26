@extends('layouts.app')

@section('title', 'Check in Guest')

@php
    /** @var \App\Models\Reservation\Reservation $reservation */
    $old = fn (string $key, $fallback = null) => old($key, $reservation->{$key} ?? $fallback);

    // Everything the popup needs, so it never has to guess at a row.
    $rowsBoot = $rows->map(fn ($row) => [
        'id' => $row->id,
        'pending' => $row->pendingCount(),
        'total' => (int) $row->no_of_rooms,
        'checked_in' => $row->checkedInCount(),
        'category' => $row->category?->name ?? '—',
        'type' => $row->type?->name ?? '—',
        'plan' => $row->plan?->name ?? '—',
        'arrival' => $row->arrival_date->format('d/m/Y'),
        'checkout' => $row->checkout_date->format('d/m/Y'),
        'male' => (int) $row->male,
        'female' => (int) $row->female,
        'child' => (int) $row->child,
    ])->values();
@endphp

@section('content')
    <x-page-header
        title="Check in Guest"
        :subtitle="'Booking ' . $reservation->reservation_no . ' — allot a room to every guest who has arrived, then Save.'"
        :crumbs="['Home' => url('/'), 'Reservations' => route('reservation.index'), 'Check in Guest']"
    >
        <x-slot:actions>
            <a href="{{ route('reservation.index') }}" class="nv-btn nv-btn-ghost">Back</a>
            <button type="submit" form="check-in-form" class="nv-btn nv-btn-primary">
                <x-icon name="check" /> Save (F10)
            </button>
        </x-slot:actions>
    </x-page-header>

    @if (session('error'))
        <div class="nv-mt"><x-alert tone="danger" title="Not saved">{{ session('error') }}</x-alert></div>
    @endif

    @if ($errors->any())
        <div class="nv-mt">
            <x-alert tone="danger" title="Please fix {{ $errors->count() }} thing(s)">{{ $errors->first() }}</x-alert>
        </div>
    @endif

    <div class="nv-grid nv-grid-4 nv-mt">
        <x-stat label="Booking" :value="$reservation->reservation_no" icon="calendar" />
        <x-stat label="Rooms to check in" :value="$reservation->pendingRooms()" icon="home" tone="warning" />
        <x-stat label="Already arrived" :value="$reservation->arrivedRooms()" icon="check-circle" tone="success" />
        <x-stat label="Balance" :value="'₹' . number_format($reservation->balance, 2)" icon="wallet" tone="info" />
    </div>

    <form method="POST" action="{{ route('front-office.check-in-guest.store') }}" id="check-in-form"
          enctype="multipart/form-data" data-check-in>
        @csrf
        <input type="hidden" name="reservation_id" value="{{ $reservation->id }}" />

        {{-- ── Guest ─────────────────────────────────────────────────────── --}}
        <div class="nv-mt">
            <x-card title="Personal Details"
                    subtitle="Corrected off the ID card at the desk — saving writes these back to the booking too.">
                <div class="nv-form-grid nv-grid-4">
                    {{-- The same list the booking form uses, and a placeholder
                         so a title that is not on it lands on blank rather
                         than silently rewriting the guest to "Mr.". --}}
                    <x-field label="Guest" name="title">
                        <x-select name="title"
                                  :options="['Mr.' => 'Mr.', 'Mrs.' => 'Mrs.', 'Ms.' => 'Ms.', 'Dr.' => 'Dr.', 'M/s' => 'M/s']"
                                  :selected="$old('title')" placeholder="—" />
                    </x-field>

                    <x-field label="First Name" name="first_name" required>
                        <x-input name="first_name" :value="$old('first_name')" />
                    </x-field>

                    <x-field label="Last Name" name="last_name">
                        <x-input name="last_name" :value="$old('last_name')" />
                    </x-field>

                    <x-field label="Mobile No." name="mobile" required>
                        <x-input name="mobile" :value="$old('mobile')" />
                    </x-field>

                    <x-field label="Email" name="email">
                        <x-input name="email" type="email" :value="$old('email')" />
                    </x-field>

                    <x-field label="Gender" name="gender">
                        <x-select name="gender" :options="['male' => 'Male', 'female' => 'Female', 'other' => 'Other']"
                                  :selected="$old('gender')" placeholder="Select" />
                    </x-field>

                    <x-field label="Address" name="address" wide>
                        <x-input name="address" :value="$old('address')" />
                    </x-field>

                    <x-field label="Pick and Drop Facility" name="pick_drop_id">
                        <x-select name="pick_drop_id" :options="$pickDrops->all()" :selected="$old('pick_drop_id')" placeholder="Select" />
                    </x-field>

                    <x-field label="Visit Purpose" name="visit_purpose_id">
                        <x-select name="visit_purpose_id" :options="$visitPurposes->all()" :selected="$old('visit_purpose_id')" placeholder="Select" />
                    </x-field>

                    <x-field label="Arrival From" name="arrival_from">
                        <x-input name="arrival_from" :value="$old('arrival_from')" />
                    </x-field>

                    <x-field label="Departure To" name="departure_to">
                        <x-input name="departure_to" :value="$old('departure_to')" />
                    </x-field>

                    <x-field label="Booked By" name="booked_by_id">
                        <x-select name="booked_by_id" :options="$bookedBy->all()" :selected="$old('booked_by_id')" placeholder="Select" />
                    </x-field>

                    <x-field label="Business Market" name="business_market_id">
                        <x-select name="business_market_id" :options="$businessMarkets->all()" :selected="$old('business_market_id')" placeholder="Select" />
                    </x-field>

                    <x-field label="Company" name="company_id">
                        <x-select name="company_id" :options="$companies->all()" :selected="$old('company_id')" placeholder="Select" />
                    </x-field>

                    <x-field label="Company GstNo" name="company_gst_no">
                        <x-input name="company_gst_no" :value="$old('company_gst_no')" />
                    </x-field>

                    <x-field label="Check-in Date" name="checkin_date" required>
                        <x-input name="checkin_date" type="date" :value="old('checkin_date', now()->toDateString())" />
                    </x-field>

                    <x-field label="Check-in Time" name="checkin_time">
                        <x-input name="checkin_time" type="time" :value="old('checkin_time', now()->format('H:i'))" />
                    </x-field>
                </div>
            </x-card>
        </div>

        {{-- ── ID Proof ──────────────────────────────────────────────────── --}}
        <div class="nv-mt">
            <x-card title="ID Proof"
                    subtitle="The ID checked at the desk — feeds the police register and, for a foreign guest, the passport onto Form C.">
                <div class="nv-form-grid">
                    <x-field label="ID Type" name="id_type">
                        <x-select name="id_type" :options="$idTypes" :selected="$old('id_type')" placeholder="Select" />
                    </x-field>

                    <x-field label="ID Number" name="id_number">
                        <x-input name="id_number" :value="$old('id_number')" placeholder="As printed on the card" />
                    </x-field>
                </div>

                {{--
                    One hidden file input carries the photo however it was
                    taken — a native camera/gallery pick on a phone (that is
                    what `capture` triggers) or a live desktop capture that
                    check-in.js hands to this same input as a File, via
                    DataTransfer, once it exists. Either way, store() sees one
                    ordinary uploaded file and does not need to know which
                    path it came from.
                --}}
                <div class="nv-id-photo" data-id-photo>
                    <div class="nv-id-photo-preview" data-id-photo-preview>
                        <video data-id-photo-video autoplay playsinline muted hidden></video>

                        <img data-id-photo-img alt="ID proof photo" hidden />

                        <div class="nv-id-photo-empty" data-id-photo-empty>
                            <x-icon name="camera" />
                            <span>No ID photo yet</span>
                        </div>
                    </div>

                    <div class="nv-id-photo-actions">
                        <button type="button" class="nv-btn nv-btn-outline nv-btn-sm" data-id-photo-camera-btn hidden>
                            <x-icon name="camera" /> Use Camera
                        </button>

                        <button type="button" class="nv-btn nv-btn-primary nv-btn-sm" data-id-photo-capture-btn hidden>
                            <x-icon name="check" /> Capture
                        </button>

                        <button type="button" class="nv-btn nv-btn-ghost nv-btn-sm" data-id-photo-cancel-btn hidden>
                            <x-icon name="x" /> Cancel
                        </button>

                        <label class="nv-btn nv-btn-outline nv-btn-sm" for="id_photo" data-id-photo-choose-btn>
                            <x-icon name="upload" /> Choose File
                        </label>
                        <input type="file" name="id_photo" id="id_photo" accept="image/*" capture="environment"
                               data-id-photo-input hidden />

                        <button type="button" class="nv-btn nv-btn-ghost nv-btn-sm" data-id-photo-remove-btn hidden>
                            <x-icon name="trash" /> Remove
                        </button>
                    </div>

                    <p class="nv-help" data-id-photo-status>
                        A clear photo of the front of the card — from the camera on a phone, or Choose File on a desktop.
                    </p>

                    @error('id_photo')
                        <p class="nv-error">{{ $message }}</p>
                    @enderror
                </div>
            </x-card>
        </div>

        {{-- ── Rooms ─────────────────────────────────────────────────────── --}}
        <div class="nv-mt">
            <x-card title="Rooms Allotment Details"
                    subtitle="Press Allot Room on a line, tick the rooms, Save the popup. Nothing is checked in until you Save this page.">
                <x-slot:actions>
                    <x-badge tone="warning" data-allot-summary>0 of {{ $reservation->pendingRooms() }} allotted</x-badge>
                </x-slot:actions>

                <div class="nv-table-wrap">
                    <table class="nv-table nv-table-compact">
                        <thead>
                            <tr>
                                <th>Action</th>
                                <th>Arrival Date</th>
                                <th>Checkout Date</th>
                                <th class="is-num">No. of Days</th>
                                <th>Room No</th>
                                <th>Room Category</th>
                                <th>Plan Type</th>
                                <th>Room Type</th>
                                <th>Tax Type</th>
                                <th class="is-num">Room Charge</th>
                                <th class="is-num">Male</th>
                                <th class="is-num">Female</th>
                                <th class="is-num">Child</th>
                                <th class="is-num">Net Amount</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($rows as $row)
                                <tr data-row="{{ $row->id }}">
                                    <td>
                                        <button type="button" class="nv-btn nv-btn-primary nv-btn-sm"
                                                data-allot="{{ $row->id }}">Allot Room</button>
                                    </td>

                                    <td class="nv-nowrap">{{ $row->arrival_date->format('d/m/Y') }}</td>
                                    <td class="nv-nowrap">{{ $row->checkout_date->format('d/m/Y') }}</td>
                                    <td class="is-num">{{ $row->no_of_days }}</td>

                                    <td data-room-cell>
                                        <span class="nv-muted">Not allotted</span>
                                        @if ($row->pendingCount() < (int) $row->no_of_rooms)
                                            <span class="nv-sub">
                                                {{ $row->checkedInCount() }} of {{ $row->no_of_rooms }} already in
                                            </span>
                                        @elseif ((int) $row->no_of_rooms > 1)
                                            <span class="nv-sub">{{ $row->pendingCount() }} rooms to allot</span>
                                        @endif
                                    </td>

                                    <td>{{ $row->category?->name ?? '—' }}</td>
                                    <td>{{ $row->plan?->name ?? '—' }}</td>
                                    <td>{{ $row->type?->name ?? '—' }}</td>
                                    <td>{{ ucfirst($row->tax_type) }}</td>
                                    <td class="is-num">₹{{ number_format($row->room_rent, 2) }}</td>
                                    <td class="is-num">{{ $row->male }}</td>
                                    <td class="is-num">{{ $row->female }}</td>
                                    <td class="is-num">{{ $row->child }}</td>
                                    <td class="is-num">₹{{ number_format($row->net_amount, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- The popup posts its picks into here. --}}
                <div data-allot-inputs hidden></div>
            </x-card>
        </div>

        {{-- ── Billing ───────────────────────────────────────────────────── --}}
        <div class="nv-mt">
            <x-card>
                <div class="nv-form-grid nv-grid-4">
                    <x-field label="Billing Instructions" name="billing_instruction_id">
                        <x-select name="billing_instruction_id" :options="$billingInstructions->all()"
                                  :selected="$old('billing_instruction_id')" placeholder="Select Ins" />
                    </x-field>

                    <x-field label="Pay Mode" name="pay_mode_id">
                        <x-select name="pay_mode_id" :options="$payModes->all()"
                                  :selected="$old('pay_mode_id')" placeholder="Select PayMode" />
                    </x-field>

                    <x-field label="Remark" name="remark">
                        <x-textarea name="remark" rows="2">{{ old('remark') }}</x-textarea>
                    </x-field>

                    <x-field label="Special Remark" name="special_remark">
                        <x-textarea name="special_remark" rows="2">{{ $old('special_remark') }}</x-textarea>
                    </x-field>
                </div>

                <div class="nv-actions" style="justify-content:flex-end">
                    <a href="{{ route('reservation.index') }}" class="nv-btn nv-btn-ghost">Close</a>
                    <button type="reset" class="nv-btn nv-btn-outline">Reset</button>
                    <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="check" /> Save (F10)</button>
                </div>
            </x-card>
        </div>
    </form>

    {{-- ── Allot Room popup ──────────────────────────────────────────────── --}}
    <div class="nv-modal-backdrop" data-allot-modal>
        <div class="nv-modal nv-modal-wide" role="dialog" aria-modal="true" aria-labelledby="allot-title">
            <div class="nv-modal-head">
                <h3 id="allot-title"><x-icon name="home" /> Allotment Room No</h3>

                <div class="nv-actions">
                    <button type="button" class="nv-btn nv-btn-primary nv-btn-sm" data-allot-save>Save</button>
                    <button type="button" class="nv-btn nv-btn-ghost nv-btn-sm" data-allot-close aria-label="Close">
                        <x-icon name="x" />
                    </button>
                </div>
            </div>

            <div class="nv-modal-body nv-allot">
                <aside class="nv-allot-side">
                    <p class="nv-allot-head">Reservation Details</p>
                    <p class="nv-allot-line" data-allot-booking></p>

                    <p class="nv-allot-head">Allotment Room</p>
                    <p class="nv-allot-line" data-allot-picked></p>

                    <p class="nv-allot-head">Balance Room</p>
                    <p class="nv-allot-line" data-allot-balance></p>
                </aside>

                <div class="nv-allot-main">
                    <p class="nv-allot-pax" data-allot-pax></p>

                    <div class="nv-table-wrap">
                        <table class="nv-table nv-table-compact">
                            <thead>
                                <tr>
                                    <th class="is-num">Sr</th>
                                    <th>Room No</th>
                                    <th>Room Type</th>
                                    <th>Housekeeping</th>
                                    <th>Check-In</th>
                                    <th>Check-Out</th>
                                    <th class="is-end">Checked</th>
                                </tr>
                            </thead>
                            <tbody data-allot-body></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="nv-modal-foot">
                <span class="nv-muted" data-allot-hint></span>
                <button type="button" class="nv-btn nv-btn-primary" data-allot-save>Save</button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
window.__checkIn = {
    rows: @json($rowsBoot),
    roomsUrl: @json(route('front-office.check-in-guest.rooms')),
    pending: @json($reservation->pendingRooms())
};
</script>
<script src="{{ asset('js/check-in.js') }}?v={{ filemtime(public_path('js/check-in.js')) }}"></script>
@endpush
