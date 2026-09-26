@extends('layouts.app')

@section('title', $reservation->exists ? 'Edit Reservation' : 'New Reservation')

@php
    /** @var \App\Models\Reservation\Reservation $reservation */
    $editing = $reservation->exists;
    $action = $editing ? route('reservation.update', $reservation) : route('reservation.store');
    $branchIdForTax = \App\Helpers\Helper::getActiveBranchId();

    // Everything the browser needs to do the same maths the server will redo on save.
    $boot = [
        'roomTypes' => $roomTypes->map(fn ($t) => [
            'id' => $t->id,
            'name' => $t->name,
            'category_id' => $t->room_category_id,
            'rent' => (float) $t->base_rent,
            'max_adult' => (int) $t->max_adult,
            'max_child' => (int) $t->max_child,
        ])->values(),
        'planTypes' => $planTypes->map(fn ($p) => [
            'id' => $p->id, 'name' => $p->name, 'charge' => (float) $p->charge,
        ])->values(),
        'categories' => $categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values(),
        'services' => $services->map(fn ($s) => [
            'id' => $s->id,
            'name' => $s->name,
            'price' => (float) $s->price,
            'tax_choice' => \App\Support\Tax::suggestFor($s->tax_master_id),
        ])->values(),
        'taxSlabs' => $taxSlabs,
        /*
         * choice → percent, so the browser can show the same totals the server
         * will store. 'slab' is not in here: it is worked out from the nightly
         * rent by roomTaxPercent(), which is the one rule that needs the rent.
         */
        'taxPercents' => collect($taxChoices)->mapWithKeys(fn ($label, $key) => [
            $key => \App\Support\Tax::percent($key, $branchIdForTax),
        ]),
        'guestTypes' => $guestTypes,
        'rows' => old('rooms', $editing ? $reservation->rooms->map(fn ($r) => [
            'arrival_date' => $r->arrival_date->format('Y-m-d'),
            'arrival_time' => substr((string) $r->arrival_time, 0, 5),
            'checkout_date' => $r->checkout_date->format('Y-m-d'),
            'checkout_time' => substr((string) $r->checkout_time, 0, 5),
            'guest_type' => $r->guest_type,
            'room_category_id' => $r->room_category_id,
            'room_type_id' => $r->room_type_id,
            'plan_type_id' => $r->plan_type_id,
            'no_of_days' => $r->no_of_days,
            'no_of_rooms' => $r->no_of_rooms,
            'tax_type' => $r->tax_type,
            'tax_choice' => $r->tax_choice ?: 'none',
            'room_rent' => (float) $r->room_rent,
            'discount' => (float) $r->discount,
            'male' => $r->male, 'female' => $r->female, 'child' => $r->child,
        ])->values()->all() : []),
        'serviceRows' => old('services', $editing ? $reservation->services->map(fn ($s) => [
            'service_id' => $s->service_id,
            'service_name' => $s->service_name,
            'tax_type' => $s->tax_type,
            'tax_choice' => $s->tax_choice ?: 'none',
            'qty' => (float) $s->qty,
            'price' => (float) $s->price,
            'tax_percent' => (float) $s->tax_percent,
            'remark' => $s->remark,
        ])->values()->all() : []),
        'urls' => [
            'availability' => route('reservation.availability'),
            // What the rate plan says this stay should cost. Absent when the
            // user may not see rates, and the screen then keeps the room
            // type's base rent exactly as it always did.
            'rate' => can_do('rates/calendar', 'view') ? route('rates.quote') : null,
            'guests' => route('reservation.guests'),
            'states' => url('get-state-id'),
            'cities' => url('get-city-id'),
        ],
        'defaults' => [
            'arrival_time' => config('pms.default_arrival_time'),
            'checkout_time' => config('pms.default_checkout_time'),
        ],
        'prefill' => $prefill,
    ];
@endphp

@section('content')
    <x-page-header
        :title="$editing ? 'Reservation ' . $reservation->reservation_no : 'New Reservation'"
        subtitle="Guest details, then the rooms. Totals update as you type; the server recalculates them on save."
        :crumbs="['Home' => url('/'), 'Reservations' => route('reservation.index'), $editing ? 'Edit' : 'New']"
    >
        <x-slot:actions>
            <button type="button" class="nv-btn nv-btn-outline" data-open-guest-search>
                <x-icon name="search" /> Customer Search
            </button>
            <a href="{{ route('reservation.index') }}" class="nv-btn nv-btn-ghost">Back</a>
            <button type="submit" form="reservation-form" class="nv-btn nv-btn-primary">
                <x-icon name="check" /> Save
            </button>
        </x-slot:actions>
    </x-page-header>

    @if ($errors->any())
        <div style="margin-bottom:18px">
            <x-alert tone="danger" title="Please fix {{ $errors->count() }} thing(s)">
                {{ $errors->first() }}
            </x-alert>
        </div>
    @endif

    @if ($categories->isEmpty() || $roomTypes->isEmpty())
        <div style="margin-bottom:18px">
            <x-alert tone="warning" title="Set the masters up first">
                A reservation needs at least one room type.
                <a href="{{ route('masters.home') }}" style="color:inherit;font-weight:700;text-decoration:underline">
                    Open Masters
                </a>
                and add a room category, a room type and some rooms.
            </x-alert>
        </div>
    @endif

    <form method="POST" action="{{ $action }}" id="reservation-form" data-reservation>
        @csrf
        @if ($editing) @method('PUT') @endif

        <input type="hidden" name="guest_id" value="{{ old('guest_id', $reservation->guest_id) }}" data-guest-id />

        {{-- ── Tabs ─────────────────────────────────────────────────────── --}}
        <div class="nv-tabs" data-tabs>
            <button type="button" class="nv-tab is-active" data-tab="personal">Personal Details</button>
            <button type="button" class="nv-tab" data-tab="stay">Guest Details</button>
        </div>

        {{-- ── Personal details ─────────────────────────────────────────── --}}
        <div data-tab-panel="personal">
            <x-card title="Guest" subtitle="Who the booking is for. Customer Search fills this in for a returning guest.">
                <div class="nv-form-grid nv-grid-4">
                    <x-field label="Guest" name="title">
                        <x-select name="title"
                                  :options="['Mr.' => 'Mr.', 'Mrs.' => 'Mrs.', 'Ms.' => 'Ms.', 'Dr.' => 'Dr.', 'M/s' => 'M/s']"
                                  :selected="old('title', $reservation->title)" />
                    </x-field>

                    {{-- Typing a name or number a second guest already has on
                         file drops a small "Returning guest" list right here
                         — pick one and applyGuest() in reservation.js fills
                         the rest of this card, the same as Customer Search
                         above does. --}}
                    <div class="nv-typeahead" data-typeahead>
                        <x-field label="First Name" name="first_name" required>
                            <x-input name="first_name" :value="old('first_name', $reservation->first_name)"
                                     placeholder="Aarav" autocomplete="off" />
                        </x-field>
                        <div class="nv-typeahead-results" data-typeahead-results hidden></div>
                    </div>

                    <x-field label="Last Name" name="last_name">
                        <x-input name="last_name" :value="old('last_name', $reservation->last_name)" placeholder="Mehta" />
                    </x-field>

                    <x-field label="Reservation Type" name="reservation_type" required>
                        <x-select name="reservation_type" :options="$types"
                                  :selected="old('reservation_type', $reservation->reservation_type)" />
                    </x-field>

                    <div class="nv-typeahead" data-typeahead>
                        <x-field label="Mobile No" name="mobile" required>
                            <x-input name="mobile" :value="old('mobile', $reservation->mobile)"
                                     placeholder="98765 43210" autocomplete="off" />
                        </x-field>
                        <div class="nv-typeahead-results" data-typeahead-results hidden></div>
                    </div>

                    <x-field label="Mobile No. 2" name="mobile2">
                        <x-input name="mobile2" :value="old('mobile2', $reservation->mobile2)" />
                    </x-field>

                    <x-field label="Email" name="email">
                        <x-input name="email" type="email" :value="old('email', $reservation->email)" />
                    </x-field>

                    <x-field label="Email-2" name="email2">
                        <x-input name="email2" type="email" :value="old('email2', $reservation->email2)" />
                    </x-field>

                    <x-field label="DOB" name="dob">
                        <x-input name="dob" type="date" :value="old('dob', $reservation->dob?->format('Y-m-d'))" />
                    </x-field>

                    <x-field label="Gender" name="gender">
                        <div class="nv-radio-row">
                            @foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $value => $label)
                                <label class="nv-radio">
                                    <input type="radio" name="gender" value="{{ $value }}"
                                           @checked(old('gender', $reservation->gender) === $value) />
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </x-field>

                    <x-field label="Emp" name="emp_id" help="Which staff member took this booking.">
                        <x-select name="emp_id" :options="$employees->all()"
                                  :selected="old('emp_id', $reservation->emp_id)" placeholder="Select Emp" />
                    </x-field>

                    <x-field label="Reservation Date" name="reservation_date" required>
                        <x-input name="reservation_date" type="date"
                                 :value="old('reservation_date', $reservation->reservation_date?->format('Y-m-d'))" />
                    </x-field>

                    <x-field label="Address" name="address" wide>
                        <x-textarea name="address" :value="old('address', $reservation->address)" rows="2"
                                    placeholder="Street, area" />
                    </x-field>

                    <x-field label="Country" name="country_id">
                        <x-select name="country_id" :options="$countries->pluck('name', 'id')->all()"
                                  :selected="old('country_id', $reservation->country_id)" placeholder="Select Country"
                                  data-cascade="state" :data-cascade-url="url('get-state-id')" />
                    </x-field>

                    <x-field label="State" name="state_id">
                        <x-select name="state_id" :options="$states->pluck('name', 'id')->all()"
                                  :selected="old('state_id', $reservation->state_id)" placeholder="Select State"
                                  data-cascade="city" :data-cascade-url="url('get-city-id')" data-cascade-target="state" />
                    </x-field>

                    <x-field label="City" name="city_id">
                        <x-select name="city_id" :options="$cities->pluck('name', 'id')->all()"
                                  :selected="old('city_id', $reservation->city_id)" placeholder="Select City"
                                  data-cascade-target="city" />
                    </x-field>

                    <x-field label="ZIP Code" name="zip_code">
                        <x-input name="zip_code" :value="old('zip_code', $reservation->zip_code)" placeholder="302001" />
                    </x-field>
                </div>
            </x-card>

            <div class="nv-mt">
                <x-card title="Trip and trade" subtitle="Where the guest is coming from, and who sent them.">
                    <div class="nv-form-grid nv-grid-4">
                        <x-field label="Pick and Drop Facility" name="pick_drop_id">
                            <x-select name="pick_drop_id" :options="$pickDrops->all()"
                                      :selected="old('pick_drop_id', $reservation->pick_drop_id)"
                                      placeholder="Select Pick and Drop" />
                        </x-field>

                        <x-field label="Visit Purpose" name="visit_purpose_id">
                            <x-select name="visit_purpose_id" :options="$purposes->all()"
                                      :selected="old('visit_purpose_id', $reservation->visit_purpose_id)"
                                      placeholder="Select Visit Purpose" />
                        </x-field>

                        <x-field label="Arrival From" name="arrival_from">
                            <x-input name="arrival_from" :value="old('arrival_from', $reservation->arrival_from)"
                                     placeholder="Jaipur" />
                        </x-field>

                        <x-field label="Departure To" name="departure_to">
                            <x-input name="departure_to" :value="old('departure_to', $reservation->departure_to)"
                                     placeholder="Udaipur" />
                        </x-field>

                        <x-field label="Transport Mode" name="transport_mode">
                            <x-input name="transport_mode" :value="old('transport_mode', $reservation->transport_mode)"
                                     placeholder="Car / Train / Flight" />
                        </x-field>

                        <x-field label="Confirm Voucher No." name="confirm_voucher_no">
                            <x-input name="confirm_voucher_no"
                                     :value="old('confirm_voucher_no', $reservation->confirm_voucher_no)" />
                        </x-field>

                        <x-field label="Booked By" name="booked_by_id">
                            <x-select name="booked_by_id" :options="$bookedBy->all()"
                                      :selected="old('booked_by_id', $reservation->booked_by_id)"
                                      placeholder="Select Booked By" />
                        </x-field>

                        <x-field label="Business Market" name="business_market_id">
                            <x-select name="business_market_id" :options="$markets->all()"
                                      :selected="old('business_market_id', $reservation->business_market_id)"
                                      placeholder="Select Market" />
                        </x-field>

                        <x-field label="Company" name="company_id">
                            <x-select name="company_id" :options="$companies->all()"
                                      :selected="old('company_id', $reservation->company_id)"
                                      placeholder="Select Company"
                                      data-company-gst="{{ $companies->keys()->mapWithKeys(fn ($id) => [$id => ''])->toJson() }}" />
                        </x-field>

                        <x-field label="Company GstNo" name="company_gst_no">
                            <x-input name="company_gst_no" :value="old('company_gst_no', $reservation->company_gst_no)"
                                     placeholder="08AAACX1234C1ZK" />
                        </x-field>
                    </div>
                </x-card>
            </div>
        </div>

        {{-- ── Rooms, services and totals ───────────────────────────────── --}}
        <div data-tab-panel="stay" hidden>

            {{-- Add a room --}}
            <x-card title="Add Rooms Allotment Information"
                    subtitle="Fill the row and press Add. Nothing is saved until you press Save.">
                <x-slot:actions>
                    <span class="nv-badge is-plain" data-avail-badge>Avl : —</span>
                </x-slot:actions>

                <div class="nv-form-grid nv-grid-5" data-room-entry>
                    <x-field label="Arrival Date">
                        <input type="date" class="nv-input" data-r="arrival_date" value="{{ $prefill['arrival_date'] }}" />
                    </x-field>

                    <x-field label="Arrival Time">
                        <input type="time" class="nv-input" data-r="arrival_time"
                               value="{{ config('pms.default_arrival_time') }}" />
                    </x-field>

                    <x-field label="Checkout Date">
                        <input type="date" class="nv-input" data-r="checkout_date"
                               value="{{ $prefill['checkout_date'] }}" />
                    </x-field>

                    <x-field label="Checkout Time">
                        <input type="time" class="nv-input" data-r="checkout_time"
                               value="{{ config('pms.default_checkout_time') }}" />
                    </x-field>

                    <x-field label="Guest Type">
                        <select class="nv-select" data-r="guest_type">
                            @foreach ($guestTypes as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </x-field>

                    <x-field label="Room Category">
                        <select class="nv-select" data-r="room_category_id">
                            <option value="">Select Category</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}"
                                        @selected($prefill['room_category_id'] === $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </x-field>

                    {{--
                        The free count sits on this field rather than only in
                        the chip at the top, because this is the moment the
                        question is asked: a clerk picking a type for a family
                        of five needs to know right here whether five exist.
                        Every option in the list carries its own count too.
                    --}}
                    <x-field label="Room Type">
                        <select class="nv-select" data-r="room_type_id">
                            <option value="">Select RoomType</option>
                        </select>
                        <p class="nv-help" data-type-free></p>
                    </x-field>

                    <x-field label="Plan Type">
                        <select class="nv-select" data-r="plan_type_id">
                            <option value="">Select Plan</option>
                            @foreach ($planTypes as $plan)
                                <option value="{{ $plan->id }}">
                                    {{ $plan->name }}{{ $plan->charge > 0 ? ' (+₹' . number_format($plan->charge, 0) . ')' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </x-field>

                    <x-field label="No. of Days" help="Counted from the two dates — change those.">
                        <input type="number" class="nv-input" data-r="no_of_days" value="1" min="1" readonly />
                    </x-field>

                    {{--
                        A booking holds a NUMBER of rooms of a type, not a room
                        number. Which actual rooms a family gets is decided when
                        they arrive — by then the house has moved anyway — so
                        this row books five and Front Office allots the five.
                    --}}
                    <x-field label="No. of Room">
                        <input type="number" class="nv-input" data-r="no_of_rooms" value="1" min="1" max="50" />
                        <p class="nv-help" data-rooms-note hidden></p>
                    </x-field>

                    {{--
                        Tax, picked. The list opens on "No Tax" and a room added
                        without touching it is charged no tax at all — the GST
                        slab is one of the choices, not what happens by itself.
                    --}}
                    <x-field label="Tax">
                        <select class="nv-select" data-r="tax_choice">
                            @foreach ($taxChoices as $key => $label)
                                <option value="{{ $key }}" @selected((string) $key === (string) $defaultTaxChoice)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </x-field>

                    <x-field label="Tax Type">
                        <select class="nv-select" data-r="tax_type">
                            <option value="exclusive">Exclusive</option>
                            <option value="inclusive">Inclusive</option>
                        </select>
                    </x-field>

                    <x-field label="Room Rent">
                        <input type="number" class="nv-input" data-r="room_rent" value="0" min="0" step="0.01" />

                        {{-- What the rate plan makes of these dates. Filled in
                             by the rate lookup, and silent until it has an
                             answer — a blank line under a field the clerk has
                             not reached yet is just noise. --}}
                        <p class="nv-rate-note nv-hidden" data-rate-note></p>
                    </x-field>

                    <x-field label="Dis.">
                        <input type="number" class="nv-input" data-r="discount" value="0" min="0" step="0.01" />
                    </x-field>

                    <x-field label="Male">
                        <input type="number" class="nv-input" data-r="male" value="1" min="0" max="50" />
                    </x-field>

                    <x-field label="Female">
                        <input type="number" class="nv-input" data-r="female" value="0" min="0" max="50" />
                    </x-field>

                    <x-field label="Child">
                        <input type="number" class="nv-input" data-r="child" value="0" min="0" max="50" />
                    </x-field>

                    <x-field label="Net for this row">
                        <input type="text" class="nv-input" data-room-preview readonly value="₹0.00" />
                    </x-field>
                </div>

                <div class="nv-actions" style="justify-content:flex-end">
                    <button type="button" class="nv-btn nv-btn-primary" data-add-room>
                        <x-icon name="plus" /> Add
                    </button>
                </div>
            </x-card>

            {{-- Room grid --}}
            <div class="nv-mt">
                <x-card title="Rooms Allotment Details" flush>
                    <div class="nv-table-wrap">
                        <table class="nv-table nv-table-compact">
                            <thead>
                                <tr>
                                    <th>Arrival</th>
                                    <th>Checkout</th>
                                    <th class="is-num">Days</th>
                                    <th>Category</th>
                                    <th>Room Type</th>
                                    <th>Plan</th>
                                    <th>Tax Type</th>
                                    <th class="is-num">Rent</th>
                                    <th class="is-num">Dis.</th>
                                    <th class="is-num">Rms</th>
                                    <th class="is-num">M/F/C</th>
                                    <th class="is-num">Amount</th>
                                    <th class="is-num">Tax</th>
                                    <th class="is-num">Net</th>
                                    <th class="is-end"></th>
                                </tr>
                            </thead>
                            <tbody data-room-rows></tbody>
                        </table>
                    </div>

                    <div class="nv-empty" data-room-empty>
                        <span class="nv-empty-icon"><x-icon name="home" /></span>
                        <strong>No rooms added</strong>
                        <p>Fill the row above and press <strong>Add</strong>. A reservation needs at least one room.</p>
                    </div>
                </x-card>
            </div>

            {{-- Services --}}
            <div class="nv-mt">
                <x-card title="Services" subtitle="Anything charged on top of the room.">
                    <div class="nv-form-grid nv-grid-5" data-service-entry>
                        <x-field label="Service Name">
                            <select class="nv-select" data-s="service_id">
                                <option value="">Select service…</option>
                                @foreach ($services as $service)
                                    <option value="{{ $service->id }}"
                                            data-price="{{ $service->price }}"
                                            data-tax-choice="{{ \App\Support\Tax::suggestFor($service->tax_master_id) }}">
                                        {{ $service->name }}
                                    </option>
                                @endforeach
                                <option value="custom">Other (type it)</option>
                            </select>
                        </x-field>

                        <x-field label="Custom name">
                            <input type="text" class="nv-input" data-s="service_name" placeholder="Only for “Other”" />
                        </x-field>

                        <x-field label="Tax Type">
                            <select class="nv-select" data-s="tax_type">
                                <option value="exclusive">Exclusive</option>
                                <option value="inclusive">Inclusive</option>
                            </select>
                        </x-field>

                        <x-field label="QTY">
                            <input type="number" class="nv-input" data-s="qty" value="1" min="0" step="0.01" />
                        </x-field>

                        <x-field label="Price">
                            <input type="number" class="nv-input" data-s="price" value="0" min="0" step="0.01" />
                        </x-field>

                        <x-field label="Tax">
                            <select class="nv-select" data-s="tax_choice">
                                @foreach ($serviceTaxChoices as $key => $label)
                                    <option value="{{ $key }}" @selected((string) $key === (string) $defaultTaxChoice)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </x-field>

                        <x-field label="Total Amt">
                            <input type="text" class="nv-input" data-service-preview readonly value="₹0.00" />
                        </x-field>

                        <x-field label="Remark" wide>
                            <input type="text" class="nv-input" data-s="remark" placeholder="Remark" />
                        </x-field>
                    </div>

                    <div class="nv-actions" style="justify-content:flex-end">
                        <button type="button" class="nv-btn nv-btn-soft" data-add-service>
                            <x-icon name="plus" /> Add service
                        </button>
                    </div>

                    <div class="nv-table-wrap" style="margin-top:14px">
                        <table class="nv-table nv-table-compact">
                            <thead>
                                <tr>
                                    <th>Service Name</th>
                                    <th>Tax Type</th>
                                    <th class="is-num">QTY</th>
                                    <th class="is-num">Price</th>
                                    <th class="is-num">Tax</th>
                                    <th class="is-num">Total Amt</th>
                                    <th>Remark</th>
                                    <th class="is-end">Action</th>
                                </tr>
                            </thead>
                            <tbody data-service-rows></tbody>
                        </table>
                    </div>
                </x-card>
            </div>

            {{-- Totals + closing details --}}
            <div class="nv-grid nv-grid-main nv-mt">
                <x-card title="Billing">
                    <div class="nv-form-grid">
                        <x-field label="Billing Instructions" name="billing_instruction_id">
                            <x-select name="billing_instruction_id" :options="$instructions->all()"
                                      :selected="old('billing_instruction_id', $reservation->billing_instruction_id)"
                                      placeholder="Select Ins" />
                        </x-field>

                        <x-field label="Pay Mode" name="pay_mode_id">
                            <x-select name="pay_mode_id" :options="$payModes->all()"
                                      :selected="old('pay_mode_id', $reservation->pay_mode_id)"
                                      placeholder="Select PayMode" />
                        </x-field>

                        <x-field label="Remark" name="remark" wide>
                            <x-textarea name="remark" :value="old('remark', $reservation->remark)" rows="2" />
                        </x-field>

                        <x-field label="Special Remark" name="special_remark" wide>
                            <x-textarea name="special_remark" :value="old('special_remark', $reservation->special_remark)" rows="2" />
                        </x-field>
                    </div>

                    @unless ($editing)
                        <hr class="nv-hr" />

                        <h4 style="font-size:13px;font-weight:650;margin:0 0 12px">Advance deposit</h4>

                        <div class="nv-form-grid">
                            <x-field label="Amount" name="advance_amount">
                                <x-input name="advance_amount" type="number" step="0.01" min="0"
                                         :value="old('advance_amount')" placeholder="0.00" />
                            </x-field>

                            <x-field label="Pay mode" name="advance_pay_mode_id">
                                <x-select name="advance_pay_mode_id" :options="$payModes->all()"
                                          :selected="old('advance_pay_mode_id')" placeholder="Same as above" />
                            </x-field>

                            <x-field label="Reference no." name="advance_reference_no" wide>
                                <x-input name="advance_reference_no" :value="old('advance_reference_no')"
                                         placeholder="UTR / cheque / card slip" />
                            </x-field>
                        </div>
                    @endunless
                </x-card>

                <x-card title="Total">
                    <div class="nv-total-list">
                        <div class="nv-total-row">
                            <span>Room total</span>
                            <b data-total="room">₹0.00</b>
                        </div>
                        <div class="nv-total-row">
                            <span>Service total</span>
                            <b data-total="service">₹0.00</b>
                        </div>
                        <div class="nv-total-row">
                            <span>Discount</span>
                            <b data-total="discount">₹0.00</b>
                        </div>
                        <div class="nv-total-row">
                            <span>Tax</span>
                            <b data-total="tax">₹0.00</b>
                        </div>
                        <div class="nv-total-row is-net">
                            <span>NET AMOUNT</span>
                            <b data-total="net">₹0.00</b>
                        </div>
                    </div>

                    <x-slot:footer>
                        <div class="nv-actions" style="justify-content:flex-end">
                            <button type="reset" class="nv-btn nv-btn-outline">Reset</button>
                            <button type="submit" class="nv-btn nv-btn-primary">
                                <x-icon name="check" /> {{ $editing ? 'Save changes' : 'Save reservation' }}
                            </button>
                        </div>
                    </x-slot:footer>
                </x-card>
            </div>
        </div>

        {{-- Serialised grid rows land here as hidden inputs --}}
        <div data-row-inputs hidden></div>
    </form>

    {{-- ── Customer search ──────────────────────────────────────────────── --}}
    <div class="nv-modal-backdrop" data-guest-search>
        <div class="nv-modal" style="max-width:640px" role="dialog" aria-modal="true" aria-label="Customer search">
            <div class="nv-modal-head">
                <strong>Customer Search</strong>
                <button type="button" class="nv-icon-btn" data-close-guest-search aria-label="Close">
                    <x-icon name="x" />
                </button>
            </div>

            <div class="nv-modal-body">
                <div class="nv-field-search" style="margin-bottom:12px">
                    <x-icon name="search" />
                    <input type="search" class="nv-input" data-guest-query
                           placeholder="Name, mobile or email…" autocomplete="off" />
                </div>

                <div class="nv-guest-results" data-guest-results>
                    <p class="nv-muted" style="font-size:13px;padding:14px">Start typing to search past guests.</p>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
window.RESERVATION_BOOT = @json($boot);
</script>
<script src="{{ asset('js/reservation.js') }}?v={{ filemtime(public_path('js/reservation.js')) }}" defer></script>
@endpush
