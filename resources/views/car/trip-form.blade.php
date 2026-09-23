@extends('layouts.app')

@section('title', $row->exists ? 'Edit Trip' : 'New Trip')

@section('content')
    <x-page-header
        :title="$row->exists ? 'Edit ' . $row->trip_no : 'New ' . ($row->trip_type === 'drop' ? 'Drop' : 'Pickup')"
        subtitle="Record it first. Whether it costs the guest anything is a tick, and the tick starts off."
        :crumbs="['Home' => url('/'), 'Car & Parking' => route('car.trips'), $row->exists ? $row->trip_no : 'New']"
    />

    <form method="POST"
          action="{{ $row->exists ? route('car.trips.update', $row->id) : route('car.trips.store') }}">
        @csrf
        @if ($row->exists) @method('PUT') @endif

        <div class="nv-grid nv-grid-2 nv-mt">
            <x-card title="The trip">
                <div class="nv-grid nv-grid-2">
                    <x-field label="Which way" name="trip_type" required>
                        <x-select name="trip_type" :options="$types" :selected="old('trip_type', $row->trip_type ?: 'pickup')" />
                    </x-field>

                    <x-field label="Status" name="status">
                        <x-select name="status" :options="$statuses" :selected="old('status', $row->status ?: 'scheduled')" />
                    </x-field>

                    <x-field label="From" name="from_place" required>
                        <x-input name="from_place" :value="$row->from_place" placeholder="Airport T3" />
                    </x-field>

                    <x-field label="To" name="to_place" required>
                        <x-input name="to_place" :value="$row->to_place" placeholder="The hotel" />
                    </x-field>

                    <x-field label="Date" name="trip_date" required>
                        <x-input name="trip_date" type="date"
                                 :value="old('trip_date', optional($row->trip_date)->format('Y-m-d') ?: now()->toDateString())" />
                    </x-field>

                    <x-field label="Time" name="trip_time" required>
                        <x-input name="trip_time" type="time" :value="old('trip_time', substr((string) $row->trip_time, 0, 5) ?: '12:00')" />
                    </x-field>

                    <x-field label="Flight / train" name="flight_no">
                        <x-input name="flight_no" :value="$row->flight_no" />
                    </x-field>

                    <x-field label="Distance (km)" name="km" help="Only used when the trip is charged per kilometre.">
                        <x-input name="km" type="number" step="0.01" min="0" :value="old('km', (float) ($row->km ?: 0))" />
                    </x-field>

                    <x-field label="Remark" name="remark" wide>
                        <x-input name="remark" :value="$row->remark" />
                    </x-field>
                </div>
            </x-card>

            <x-card title="Guest and car">
                <div class="nv-grid nv-grid-2">
                    <x-field label="Against a booking" name="reservation_id"
                             help="A pickup is usually for a guest who has not arrived yet.">
                        <x-select name="reservation_id" :options="$arrivals" :selected="old('reservation_id', $row->reservation_id)"
                                  placeholder="Not against a booking" />
                    </x-field>

                    @include('facility.partials.guest')

                    <x-field label="People" name="pax">
                        <x-input name="pax" type="number" min="1" max="60" :value="old('pax', $row->pax ?? 1)" />
                    </x-field>

                    <x-field label="Bags" name="luggage">
                        <x-input name="luggage" type="number" min="0" max="60" :value="old('luggage', $row->luggage ?? 0)" />
                    </x-field>

                    <x-field label="Car" name="vehicle_id">
                        <select name="vehicle_id" id="vehicle_id" class="nv-select">
                            <option value="">Not decided yet</option>
                            @foreach ($vehicles as $vehicle)
                                <option value="{{ $vehicle->id }}"
                                        data-km="{{ (float) $vehicle->km_rate }}"
                                        data-trip="{{ (float) $vehicle->trip_rate }}"
                                        @selected(old('vehicle_id', $row->vehicle_id) == $vehicle->id)>
                                    {{ $vehicle->label }}{{ $vehicle->seats ? ' · ' . $vehicle->seats . ' seats' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </x-field>

                    <x-field label="Driver" name="driver_name" help="Left empty, the car's usual driver is used.">
                        <x-input name="driver_name" :value="$row->driver_name" />
                    </x-field>

                    <x-field label="Driver mobile" name="driver_mobile">
                        <x-input name="driver_mobile" :value="$row->driver_mobile" />
                    </x-field>
                </div>
            </x-card>
        </div>

        <div class="nv-mt">
            <x-card title="Money" subtitle="Leave the tick off and this trip costs the guest nothing.">
                <div class="nv-grid nv-grid-4">
                    <x-field label="Charge for it?" name="is_chargeable" wide>
                        <label class="nv-check">
                            <input type="hidden" name="is_chargeable" value="0" />
                            <input type="checkbox" name="is_chargeable" value="1"
                                   @checked(old('is_chargeable', $row->is_chargeable)) />
                            <span>Yes — charge this trip</span>
                        </label>
                    </x-field>

                    <x-field label="Charged" name="rate_type">
                        <x-select name="rate_type" :options="$rateTypes" :selected="old('rate_type', $row->rate_type ?: 'trip')" />
                    </x-field>

                    <x-field label="Rate" name="rate" help="Left at 0, the car's own rate is used.">
                        <x-input name="rate" type="number" step="0.01" min="0" :value="old('rate', (float) ($row->rate ?: 0))" />
                    </x-field>

                    <x-field label="Tax" name="tax_choice">
                        <x-tax-select :choices="$taxChoices" :selected="$row->tax_choice" />
                    </x-field>

                    <x-field label="Total" name="preview">
                        <p class="nv-fac-total" data-total-for="trip">₹ 0.00</p>
                    </x-field>

                    <x-field label="Put it on the room bill" name="post_to_room" wide
                             help="Only possible when an in-house guest is picked.">
                        <label class="nv-check">
                            <input type="hidden" name="post_to_room" value="0" />
                            <input type="checkbox" name="post_to_room" value="1"
                                   @checked(old('post_to_room', $row->post_to_room)) />
                            <span>Charge it to the folio</span>
                        </label>
                    </x-field>
                </div>
            </x-card>
        </div>

        <div class="nv-actions nv-mt" style="justify-content:flex-end">
            <a href="{{ route('car.trips') }}" class="nv-btn nv-btn-ghost">Cancel</a>
            <button type="submit" class="nv-btn nv-btn-primary">
                <x-icon name="check" /> {{ $row->exists ? 'Save changes' : 'Book it' }}
            </button>
        </div>
    </form>
@endsection

@push('scripts')
    <script src="{{ asset('js/facility.js') }}?v={{ file_exists(public_path('js/facility.js')) ? filemtime(public_path('js/facility.js')) : time() }}" defer></script>
@endpush
