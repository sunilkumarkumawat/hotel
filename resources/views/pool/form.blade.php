@extends('layouts.app')

@section('title', $row->exists ? 'Edit Pool Booking' : 'New Pool Booking')

@section('content')
    <x-page-header
        :title="$row->exists ? 'Edit ' . $row->booking_no : 'New Pool Booking'"
        subtitle="A pool, a day, two times, and how many people."
        :crumbs="['Home' => url('/'), 'Pool' => route('pool.bookings'), $row->exists ? $row->booking_no : 'New']"
    />

    <form method="POST"
          action="{{ $row->exists ? route('pool.bookings.update', $row->id) : route('pool.bookings.store') }}">
        @csrf
        @if ($row->exists) @method('PUT') @endif

        <div class="nv-grid nv-grid-2 nv-mt">
            <x-card title="The session">
                <div class="nv-grid nv-grid-2">
                    <x-field label="Pool" name="pool_id" required>
                        <select name="pool_id" id="pool_id" class="nv-select">
                            @foreach ($pools as $pool)
                                <option value="{{ $pool->id }}"
                                        data-adult="{{ (float) $pool->adult_rate }}"
                                        data-child="{{ (float) $pool->child_rate }}"
                                        @selected(old('pool_id', $row->pool_id) == $pool->id)>
                                    {{ $pool->name }}{{ $pool->capacity ? ' · holds ' . $pool->capacity : '' }}
                                </option>
                            @endforeach
                        </select>
                    </x-field>

                    <x-field label="Date" name="booking_date" required>
                        <x-input name="booking_date" type="date"
                                 :value="old('booking_date', optional($row->booking_date)->format('Y-m-d') ?: now()->toDateString())" />
                    </x-field>

                    <x-field label="From" name="from_time" required>
                        <x-input name="from_time" type="time" :value="old('from_time', substr((string) $row->from_time, 0, 5) ?: '10:00')" />
                    </x-field>

                    <x-field label="To" name="to_time" required>
                        <x-input name="to_time" type="time" :value="old('to_time', substr((string) $row->to_time, 0, 5) ?: '12:00')" />
                    </x-field>

                    <x-field label="Adults" name="adults" required>
                        <x-input name="adults" type="number" min="0" max="500" :value="old('adults', $row->adults ?? 1)" />
                    </x-field>

                    <x-field label="Children" name="children">
                        <x-input name="children" type="number" min="0" max="500" :value="old('children', $row->children ?? 0)" />
                    </x-field>

                    <x-field label="Status" name="status">
                        <x-select name="status" :options="$statuses" :selected="old('status', $row->status ?: 'booked')" />
                    </x-field>

                    <x-field label="Remark" name="remark" wide>
                        <x-input name="remark" :value="$row->remark" />
                    </x-field>
                </div>
            </x-card>

            <x-card title="Guest">
                <div class="nv-grid nv-grid-2">
                    @include('facility.partials.guest')
                </div>
            </x-card>
        </div>

        <div class="nv-mt">
            <x-card title="Money" subtitle="Tax opens on No Tax — a booking nobody changes is charged none.">
                <div class="nv-grid nv-grid-4">
                    <x-field label="Adult rate" name="adult_rate">
                        <x-input name="adult_rate" type="number" step="0.01" min="0"
                                 :value="old('adult_rate', (float) ($row->adult_rate ?: 0))" />
                    </x-field>

                    <x-field label="Child rate" name="child_rate">
                        <x-input name="child_rate" type="number" step="0.01" min="0"
                                 :value="old('child_rate', (float) ($row->child_rate ?: 0))" />
                    </x-field>

                    <x-field label="Discount" name="discount">
                        <x-input name="discount" type="number" step="0.01" min="0"
                                 :value="old('discount', (float) ($row->discount ?: 0))" />
                    </x-field>

                    <x-field label="Tax" name="tax_choice">
                        <x-tax-select :choices="$taxChoices" :selected="$row->tax_choice" />
                    </x-field>

                    <x-field label="Total" name="preview">
                        <p class="nv-fac-total" data-total-for="pool">₹ 0.00</p>
                    </x-field>

                    <x-field label="Put it on the room bill" name="post_to_room" wide
                             help="Only possible when an in-house guest is picked. The charge comes off again if the booking is cancelled.">
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
            <a href="{{ route('pool.bookings') }}" class="nv-btn nv-btn-ghost">Cancel</a>
            <button type="submit" class="nv-btn nv-btn-primary">
                <x-icon name="check" /> {{ $row->exists ? 'Save changes' : 'Take the booking' }}
            </button>
        </div>
    </form>
@endsection

@push('scripts')
    <script src="{{ asset('js/facility.js') }}?v={{ file_exists(public_path('js/facility.js')) ? filemtime(public_path('js/facility.js')) : time() }}" defer></script>
@endpush
