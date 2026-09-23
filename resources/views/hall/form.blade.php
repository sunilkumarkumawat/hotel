@extends('layouts.app')

@section('title', $row->exists ? 'Edit Hall Booking' : 'New Hall Booking')

@php
    $extras = old('items', $row->exists
        ? $row->items->map(fn ($i) => [
            'particulars' => $i->particulars,
            'qty' => (float) $i->qty,
            'price' => (float) $i->price,
            'tax_choice' => $i->tax_choice,
        ])->all()
        : []);
@endphp

@section('content')
    <x-page-header
        :title="$row->exists ? 'Edit ' . $row->booking_no : 'New Hall Booking'"
        subtitle="Hold the hall, price the hire, list the extras."
        :crumbs="['Home' => url('/'), 'Banquet Hall' => route('hall.bookings'), $row->exists ? $row->booking_no : 'New']"
    />

    <form method="POST"
          action="{{ $row->exists ? route('hall.bookings.update', $row->id) : route('hall.bookings.store') }}">
        @csrf
        @if ($row->exists) @method('PUT') @endif

        <div class="nv-grid nv-grid-2 nv-mt">
            <x-card title="The event">
                <div class="nv-grid nv-grid-2">
                    <x-field label="Hall" name="hall_id" required>
                        <select name="hall_id" id="hall_id" class="nv-select">
                            @foreach ($halls as $hall)
                                <option value="{{ $hall->id }}"
                                        data-hour="{{ (float) $hall->hour_rate }}"
                                        data-day="{{ (float) $hall->day_rate }}"
                                        @selected(old('hall_id', $row->hall_id) == $hall->id)>
                                    {{ $hall->name }}{{ $hall->capacity ? ' · ' . $hall->capacity . ' seats' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </x-field>

                    <x-field label="Kind of event" name="event_type">
                        <x-input name="event_type" :value="$row->event_type" placeholder="Wedding, Conference…" />
                    </x-field>

                    <x-field label="From date" name="from_date" required>
                        <x-input name="from_date" type="date"
                                 :value="old('from_date', optional($row->from_date)->format('Y-m-d') ?: now()->toDateString())" />
                    </x-field>

                    <x-field label="From time" name="from_time" required>
                        <x-input name="from_time" type="time" :value="old('from_time', substr((string) $row->from_time, 0, 5) ?: '10:00')" />
                    </x-field>

                    <x-field label="To date" name="to_date" required>
                        <x-input name="to_date" type="date"
                                 :value="old('to_date', optional($row->to_date)->format('Y-m-d') ?: now()->toDateString())" />
                    </x-field>

                    <x-field label="To time" name="to_time" required>
                        <x-input name="to_time" type="time" :value="old('to_time', substr((string) $row->to_time, 0, 5) ?: '18:00')" />
                    </x-field>

                    <x-field label="Guests expected" name="pax">
                        <x-input name="pax" type="number" min="0" max="5000" :value="old('pax', $row->pax ?? 0)" />
                    </x-field>

                    <x-field label="Status" name="status">
                        <x-select name="status" :options="$statuses" :selected="old('status', $row->status ?: 'confirmed')" />
                    </x-field>

                    <x-field label="Notes" name="remark" wide>
                        <textarea name="remark" id="remark" rows="2" class="nv-input">{{ old('remark', $row->remark) }}</textarea>
                    </x-field>
                </div>
            </x-card>

            <x-card title="Who it is for">
                <div class="nv-grid nv-grid-2">
                    @include('facility.partials.guest')

                    <x-field label="Email" name="email">
                        <x-input name="email" type="email" :value="$row->email" />
                    </x-field>

                    <x-field label="Company" name="company_id" help="For a corporate booking billed to the company.">
                        <x-select name="company_id" :options="$companies" :selected="old('company_id', $row->company_id)"
                                  placeholder="Not a company booking" />
                    </x-field>
                </div>
            </x-card>
        </div>

        {{-- ── Money ──────────────────────────────────────────────────────── --}}
        <div class="nv-mt">
            <x-card title="The hire" subtitle="Tax opens on No Tax — a booking nobody changes is charged none.">
                <div class="nv-grid nv-grid-4">
                    <x-field label="Quoted" name="rate_type" required>
                        <x-select name="rate_type" :options="$rateTypes" :selected="old('rate_type', $row->rate_type ?: 'event')" />
                    </x-field>

                    <x-field label="Rate" name="rate">
                        <x-input name="rate" type="number" step="0.01" min="0" :value="old('rate', (float) ($row->rate ?: 0))" />
                    </x-field>

                    <x-field label="Hours / days" name="qty"
                             help="Worked out from the dates when left at 0 — change it if the quote says otherwise.">
                        <x-input name="qty" type="number" step="0.01" min="0" :value="old('qty', (float) ($row->qty ?: 0))" />
                    </x-field>

                    <x-field label="Discount" name="discount">
                        <x-input name="discount" type="number" step="0.01" min="0" :value="old('discount', (float) ($row->discount ?: 0))" />
                    </x-field>

                    <x-field label="Tax" name="tax_choice">
                        <x-tax-select :choices="$taxChoices" :selected="$row->tax_choice" />
                    </x-field>

                    <x-field label="Advance taken" name="advance">
                        <x-input name="advance" type="number" step="0.01" min="0" :value="old('advance', (float) ($row->advance ?: 0))" />
                    </x-field>

                    <x-field label="Total, with extras" name="preview">
                        <p class="nv-fac-total" data-total-for="hall">₹ 0.00</p>
                    </x-field>

                    <x-field label="Put it on the room bill" name="post_to_room"
                             help="Only when an in-house guest is picked.">
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

        {{-- ── Extras ─────────────────────────────────────────────────────── --}}
        <div class="nv-mt">
            <x-card title="Extras" subtitle="Décor, a DJ, the buffet — each priced and taxed on its own." flush>
                <div class="nv-table-wrap">
                    <table class="nv-table">
                        <thead>
                            <tr>
                                <th style="width:40%">What</th>
                                <th style="width:110px">Qty</th>
                                <th style="width:140px">Price</th>
                                <th style="width:200px">Tax</th>
                                <th style="width:60px"></th>
                            </tr>
                        </thead>
                        <tbody data-extra-rows>
                            @foreach ($extras as $i => $extra)
                                <tr data-extra-row>
                                    <td><input type="text" class="nv-input nv-input-sm"
                                               name="items[{{ $i }}][particulars]"
                                               value="{{ $extra['particulars'] ?? '' }}" /></td>
                                    <td><input type="number" step="0.01" min="0" class="nv-input nv-input-sm"
                                               name="items[{{ $i }}][qty]" data-extra-qty
                                               value="{{ $extra['qty'] ?? 1 }}" /></td>
                                    <td><input type="number" step="0.01" min="0" class="nv-input nv-input-sm"
                                               name="items[{{ $i }}][price]" data-extra-price
                                               value="{{ $extra['price'] ?? 0 }}" /></td>
                                    <td>
                                        <x-tax-select :name="'items[' . $i . '][tax_choice]'" :choices="$itemTaxChoices"
                                                      :selected="$extra['tax_choice'] ?? null"
                                                      class="nv-input-sm" data-extra-tax />
                                    </td>
                                    <td>
                                        <button type="button" class="nv-icon-btn is-danger" data-remove-extra title="Remove">
                                            <x-icon name="trash" />
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- The blank row the Add button clones. `__INDEX__` is replaced
                     with the row count so the names stay unique. --}}
                <template data-extra-template>
                    <tr data-extra-row>
                        <td><input type="text" class="nv-input nv-input-sm" name="items[__INDEX__][particulars]" /></td>
                        <td><input type="number" step="0.01" min="0" class="nv-input nv-input-sm"
                                   name="items[__INDEX__][qty]" data-extra-qty value="1" /></td>
                        <td><input type="number" step="0.01" min="0" class="nv-input nv-input-sm"
                                   name="items[__INDEX__][price]" data-extra-price value="0" /></td>
                        <td>
                            <select name="items[__INDEX__][tax_choice]" class="nv-select nv-input-sm" data-extra-tax>
                                @foreach ($itemTaxChoices as $key => $label)
                                    <option value="{{ $key }}" data-percent="{{ \App\Support\Tax::percent($key) }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <button type="button" class="nv-icon-btn is-danger" data-remove-extra title="Remove">
                                <x-icon name="trash" />
                            </button>
                        </td>
                    </tr>
                </template>

                <x-slot:footer>
                    <button type="button" class="nv-btn nv-btn-soft nv-btn-sm" data-add-extra>
                        <x-icon name="plus" /> Add an extra
                    </button>
                </x-slot:footer>
            </x-card>
        </div>

        <div class="nv-actions nv-mt" style="justify-content:flex-end">
            <a href="{{ route('hall.bookings') }}" class="nv-btn nv-btn-ghost">Cancel</a>
            <button type="submit" class="nv-btn nv-btn-primary">
                <x-icon name="check" /> {{ $row->exists ? 'Save changes' : 'Hold the hall' }}
            </button>
        </div>
    </form>
@endsection

@push('scripts')
    <script src="{{ asset('js/facility.js') }}?v={{ file_exists(public_path('js/facility.js')) ? filemtime(public_path('js/facility.js')) : time() }}" defer></script>
@endpush
