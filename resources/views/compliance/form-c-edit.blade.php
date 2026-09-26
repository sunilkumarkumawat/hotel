@extends('layouts.app')

@section('title', 'Form C — ' . $checkIn->guest_name)

@php
    /*
        The form is grouped the way the FRRO's own is, and the groups are in the
        order the desk can actually fill them: what we already know, then the
        passport, then the visa, then the journey. Everything in the first group
        is pre-filled from the booking, so a clerk with a passport in their hand
        starts at group two.
    */
    $v = function (string $key, $default = null) use ($entry, $prefill) {
        $stored = $entry?->{$key};

        if ($stored instanceof \Carbon\CarbonInterface) {
            $stored = $stored->toDateString();
        }

        return old($key, $stored ?? ($prefill[$key] ?? $default));
    };
@endphp

@section('content')
    <x-page-header
        :title="$entry ? 'Form C — ' . $entry->person : 'Form C'"
        :subtitle="'Room ' . ($checkIn->room?->room_no ?: '—') . ' · ' . $checkIn->folio_no
            . ' · ' . $checkIn->checkin_date->format('d M Y') . ' to '
            . \Carbon\CarbonImmutable::parse($checkIn->departsOn())->format('d M Y')"
        :crumbs="['Home' => url('/'), 'Compliance', 'Form C' => route('compliance.form-c'), $checkIn->guest_name]"
    >
        <x-slot:actions>
            <a href="{{ route('compliance.form-c') }}" class="nv-btn nv-btn-outline">
                <x-icon name="chevron-left" /> Back
            </a>
            @if ($entry)
                <a href="{{ route('compliance.form-c.print', $entry) }}" target="_blank" class="nv-btn nv-btn-primary">
                    <x-icon name="file" /> Print
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($errors->any())
        <div class="nv-mt">
            <x-alert tone="danger" title="Please fix {{ $errors->count() }} thing(s)">{{ $errors->first() }}</x-alert>
        </div>
    @endif

    {{-- Whose form is this? A room can hold four people and each needs one. --}}
    @if ($checkIn->pax->isNotEmpty())
        <div class="nv-mt">
            <x-card title="Whose form is this?" subtitle="One form per person. The room holds {{ $checkIn->pax->count() + 1 }}.">
                <div class="nv-cm-people">
                    <a href="{{ route('compliance.form-c.create', $checkIn) }}"
                       @class(['nv-cm-person', 'is-active' => ! $paxId])>
                        <strong>{{ $checkIn->guest_name }}</strong>
                        <small>The booking is in this name</small>
                    </a>

                    @foreach ($checkIn->pax as $person)
                        <a href="{{ route('compliance.form-c.create', ['checkIn' => $checkIn, 'pax' => $person->id]) }}"
                           @class(['nv-cm-person', 'is-active' => (int) $paxId === (int) $person->id])>
                            <strong>{{ $person->name }}</strong>
                            <small>{{ $person->relation ?: 'Accompanying' }}{{ $person->age ? ', ' . $person->age : '' }}</small>
                        </a>
                    @endforeach
                </div>
            </x-card>
        </div>
    @endif

    <form method="POST" action="{{ route('compliance.form-c.store', $checkIn) }}">
        @csrf
        <input type="hidden" name="check_in_pax_id" value="{{ $paxId }}" />

        <div class="nv-mt">
            <x-card title="The guest" subtitle="Filled in from the booking — correct anything that is wrong.">
                <div class="nv-form-grid">
                    <x-field label="Full name, as on the passport" name="name" required>
                        <x-input name="name" :value="$v('name')" required />
                    </x-field>

                    <x-field label="Nationality" name="nationality_id" required>
                        <x-select name="nationality_id" :options="$countries->all()"
                                  :selected="$v('nationality_id')" placeholder="Choose…" />
                    </x-field>

                    <x-field label="Date of birth" name="date_of_birth">
                        <x-input type="date" name="date_of_birth" :value="$v('date_of_birth')" />
                    </x-field>

                    <x-field label="Sex" name="sex">
                        <x-select name="sex" :options="['male' => 'Male', 'female' => 'Female', 'other' => 'Other']"
                                  :selected="$v('sex')" placeholder="Choose…" />
                    </x-field>
                </div>
            </x-card>
        </div>

        <div class="nv-mt">
            <x-card title="Passport" subtitle="Copy it exactly — a single wrong character is a rejected form.">
                <div class="nv-form-grid">
                    <x-field label="Passport number" name="passport_no" required>
                        <x-input name="passport_no" :value="$v('passport_no')" required
                                 placeholder="As printed" />
                    </x-field>

                    <x-field label="Place of issue" name="passport_place_of_issue">
                        <x-input name="passport_place_of_issue" :value="$v('passport_place_of_issue')" />
                    </x-field>

                    <x-field label="Issued on" name="passport_issue_date">
                        <x-input type="date" name="passport_issue_date" :value="$v('passport_issue_date')" />
                    </x-field>

                    <x-field label="Expires on" name="passport_expiry_date">
                        <x-input type="date" name="passport_expiry_date" :value="$v('passport_expiry_date')" />
                    </x-field>
                </div>
            </x-card>
        </div>

        <div class="nv-mt">
            <x-card title="Visa" subtitle="If the visa expires before they leave, that is something to raise today.">
                <div class="nv-form-grid">
                    <x-field label="Visa number" name="visa_no">
                        <x-input name="visa_no" :value="$v('visa_no')" />
                    </x-field>

                    <x-field label="Visa type" name="visa_type" help="Tourist, Business, e-Visa, Employment…">
                        <x-input name="visa_type" :value="$v('visa_type')" />
                    </x-field>

                    <x-field label="Place of issue" name="visa_place_of_issue">
                        <x-input name="visa_place_of_issue" :value="$v('visa_place_of_issue')" />
                    </x-field>

                    <x-field label="Issued on" name="visa_issue_date">
                        <x-input type="date" name="visa_issue_date" :value="$v('visa_issue_date')" />
                    </x-field>

                    <x-field label="Expires on" name="visa_expiry_date">
                        <x-input type="date" name="visa_expiry_date" :value="$v('visa_expiry_date')" />
                    </x-field>
                </div>
            </x-card>
        </div>

        <div class="nv-mt">
            <x-card title="The journey" subtitle="Where they came from, why, and where they go next.">
                <div class="nv-form-grid">
                    <x-field label="Arrived in India on" name="arrived_in_india_on">
                        <x-input type="date" name="arrived_in_india_on" :value="$v('arrived_in_india_on')" />
                    </x-field>

                    <x-field label="Arrived from" name="arrived_from" help="City and country.">
                        <x-input name="arrived_from" :value="$v('arrived_from')" />
                    </x-field>

                    <x-field label="Purpose of visit" name="purpose_of_visit">
                        <select name="purpose_of_visit" class="nv-select">
                            <option value="">Choose…</option>
                            @foreach ($purposes as $purpose)
                                <option value="{{ $purpose }}" @selected($v('purpose_of_visit') === $purpose)>
                                    {{ $purpose }}
                                </option>
                            @endforeach
                        </select>
                    </x-field>

                    <x-field label="Next destination" name="next_destination">
                        <x-input name="next_destination" :value="$v('next_destination')" />
                    </x-field>

                    <x-field label="Leaving here on" name="next_destination_on">
                        <x-input type="date" name="next_destination_on" :value="$v('next_destination_on')" />
                    </x-field>

                    <x-field label="Employer in India" name="employer" help="Only if they are working here.">
                        <x-input name="employer" :value="$v('employer')" />
                    </x-field>

                    <x-field label="Permanent address" name="permanent_address" :wide="true">
                        <x-textarea name="permanent_address" :value="$v('permanent_address')" rows="2" />
                    </x-field>

                    <x-field label="Address in India" name="address_in_india" :wide="true"
                             help="This hotel, unless they said otherwise.">
                        <x-textarea name="address_in_india" :value="$v('address_in_india')" rows="2" />
                    </x-field>

                    <x-field label="Note" name="remark" :wide="true">
                        <x-textarea name="remark" :value="$v('remark')" rows="2" />
                    </x-field>
                </div>

                <hr class="nv-hr" />

                <label class="nv-check">
                    <input type="checkbox" name="employed_in_india" value="1"
                           @checked(old('employed_in_india', $entry?->employed_in_india)) />
                    <span>Employed in India</span>
                </label>
            </x-card>
        </div>

        <div class="nv-actions" style="justify-content:flex-end;margin-top:22px">
            <a href="{{ route('compliance.form-c') }}" class="nv-btn nv-btn-outline">Cancel</a>
            <button type="submit" class="nv-btn nv-btn-primary">
                <x-icon name="check" /> {{ $entry ? 'Save changes' : 'Save the form' }}
            </button>
        </div>
    </form>
@endsection
