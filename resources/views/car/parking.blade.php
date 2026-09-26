@extends('layouts.app')

@section('title', 'Parking')

@section('content')
    <x-page-header
        title="Parking"
        subtitle="Cars in, cars out. Parking is free unless somebody says otherwise."
        :crumbs="['Home' => url('/'), 'Car & Parking', 'Parking']"
    />

    <div class="nv-grid nv-grid-4">
        <x-stat label="In the park" :value="$counts['in']" icon="package" tone="info" />
        <x-stat label="Bays free" :value="$counts['free']" icon="grid" tone="success" />
        <x-stat label="In today" :value="$counts['today']" icon="calendar" />
        <x-stat label="Charged in range" :value="'₹ ' . number_format((float) $counts['charged'], 2)" icon="wallet" />
    </div>

    {{-- ── Park a car ─────────────────────────────────────────────────────── --}}
    @canAdd('car/parking')
        <div class="nv-mt">
            <x-card title="Park a car" subtitle="Nothing is charged here. What a car costs, if anything, is decided on the way out.">
                <form method="POST" action="{{ route('car.parking.in') }}">
                    @csrf

                    <div class="nv-grid nv-grid-4">
                        <x-field label="Vehicle number" name="vehicle_no" required>
                            <x-input name="vehicle_no" placeholder="RJ 14 AB 1234" />
                        </x-field>

                        <x-field label="Kind" name="vehicle_type" required>
                            <x-select name="vehicle_type" :options="$vehicleTypes" selected="car" />
                        </x-field>

                        <x-field label="Bay" name="parking_slot_id" help="Only the free ones are listed.">
                            <select name="parking_slot_id" id="parking_slot_id" class="nv-select">
                                <option value="">No particular bay</option>
                                @foreach ($freeSlots as $slot)
                                    <option value="{{ $slot->id }}">{{ $slot->label }}</option>
                                @endforeach
                            </select>
                        </x-field>

                        <x-field label="Make / model" name="make_model">
                            <x-input name="make_model" placeholder="Swift, white" />
                        </x-field>

                        <x-field label="In-house guest" name="check_in_id" help="Leave empty for a visitor.">
                            <select name="check_in_id" id="check_in_id" class="nv-select" data-stay-pick>
                                <option value="">Visitor — not staying</option>
                                @foreach ($stays as $stay)
                                    <option value="{{ $stay->id }}"
                                            data-name="{{ $stay->guest_name }}"
                                            data-mobile="{{ $stay->mobile }}"
                                            data-room="{{ $stay->room?->room_no ?: $stay->folio_no }}">
                                        {{ $stay->room?->room_no ? $stay->room->room_no . ' — ' : '' }}{{ $stay->guest_name }}
                                    </option>
                                @endforeach
                            </select>
                        </x-field>

                        <x-field label="Name" name="guest_name">
                            {{-- :value picks up ?guest_name= when Quick Actions sends someone
                                 here for a named guest; a plain visit leaves it null exactly
                                 as before. --}}
                            <x-input name="guest_name" data-guest-name :value="request('guest_name')" />
                        </x-field>

                        <x-field label="Mobile" name="mobile">
                            <x-input name="mobile" data-guest-mobile :value="request('mobile')" />
                        </x-field>

                        <x-field label="Room" name="room_no">
                            <x-input name="room_no" data-guest-room />
                        </x-field>

                        <x-field label="Driver" name="driver_name">
                            <x-input name="driver_name" />
                        </x-field>

                        <x-field label="Driver mobile" name="driver_mobile">
                            <x-input name="driver_mobile" />
                        </x-field>

                        <x-field label="In at" name="in_at" help="Leave empty for now.">
                            <x-input name="in_at" type="datetime-local" />
                        </x-field>

                        <x-field label="Remark" name="remark">
                            <x-input name="remark" />
                        </x-field>
                    </div>

                    <div class="nv-actions" style="justify-content:flex-end;margin-top:14px">
                        <button type="submit" class="nv-btn nv-btn-primary">
                            <x-icon name="check" /> Park it — no charge
                        </button>
                    </div>
                </form>
            </x-card>
        </div>
    @endCanAdd

    {{-- ── The list ───────────────────────────────────────────────────────── --}}
    <div class="nv-mt">
        <x-card>
            <form method="GET" class="nv-toolbar">
                <div class="nv-field-search">
                    <x-icon name="search" />
                    <input type="search" name="q" value="{{ $filters['q'] }}" class="nv-input"
                           placeholder="Plate, ticket, guest or room…" />
                </div>

                <select name="status" class="nv-select" style="width:170px" aria-label="Status">
                    <option value="">Everything</option>
                    @foreach ($statuses as $key => $label)
                        <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>

                <input type="date" name="from" value="{{ $filters['from'] }}" class="nv-input" style="width:160px"
                       aria-label="From date" />
                <input type="date" name="to" value="{{ $filters['to'] }}" class="nv-input" style="width:160px"
                       aria-label="To date" />

                <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="filter" /> Show</button>
                <a href="{{ route('car.parking') }}" class="nv-btn nv-btn-ghost">Reset</a>
            </form>

            <p class="nv-help">
                Dates only narrow cars that have already left — a van left in the park on Friday is
                still on this screen on Monday, which is exactly when somebody needs to see it.
            </p>
        </x-card>
    </div>

    <div class="nv-mt">
        <x-card flush>
            @if ($rows->isEmpty())
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="package" /></span>
                    <strong>Nothing to show</strong>
                    <p>Park a car above, or widen the filter.</p>
                </div>
            @else
                <div class="nv-table-wrap">
                    <table class="nv-table">
                        <thead>
                            <tr>
                                <th>Ticket</th>
                                <th>Vehicle</th>
                                <th>Whose</th>
                                <th>Bay</th>
                                <th>In</th>
                                <th>Out</th>
                                <th class="is-num">Charge</th>
                                <th>Status</th>
                                <th style="width:110px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    <td><strong>{{ $row->ticket_no }}</strong></td>

                                    <td>
                                        {{ $row->vehicle_no }}
                                        @if ($row->make_model)
                                            <span class="nv-sub">{{ $row->make_model }}</span>
                                        @endif
                                    </td>

                                    <td>
                                        {{ $row->guest_name ?: 'Visitor' }}
                                        @if ($row->room_no)
                                            <span class="nv-sub">Room {{ $row->room_no }}</span>
                                        @endif
                                    </td>

                                    <td>{{ $row->slot?->code ?? '—' }}</td>

                                    <td>{{ $row->in_at?->format('d M, h:i A') }}</td>

                                    <td>
                                        @if ($row->out_at)
                                            {{ $row->out_at->format('d M, h:i A') }}
                                            <span class="nv-sub">{{ rtrim(rtrim(number_format((float) $row->hours, 2), '0'), '.') }} hr</span>
                                        @else
                                            <span class="nv-muted">still here · {{ rtrim(rtrim(number_format($row->standing_hours, 2), '0'), '.') }} hr</span>
                                        @endif
                                    </td>

                                    <td class="is-num">
                                        @if ($row->charges())
                                            ₹ {{ number_format((float) $row->total_amount, 2) }}
                                            @if ($row->folio_charge_id)
                                                <span class="nv-sub">on the room bill</span>
                                            @endif
                                        @else
                                            <span class="nv-muted">free</span>
                                        @endif
                                    </td>

                                    <td><x-badge :tone="$row->status_tone">{{ $row->status_label }}</x-badge></td>

                                    <td class="nv-fac-row-actions">
                                        @if ($row->isParked())
                                            @canEdit('car/parking')
                                                <button type="button" class="nv-btn nv-btn-soft nv-btn-sm"
                                                        data-open="out"
                                                        data-action="{{ route('car.parking.out', $row->id) }}"
                                                        data-ticket="{{ $row->ticket_no }}"
                                                        data-vehicle="{{ $row->vehicle_no }}"
                                                        data-checkin="{{ $row->check_in_id ? '1' : '' }}"
                                                        data-hours="{{ $row->standing_hours }}">
                                                    Out
                                                </button>
                                            @endCanEdit

                                            @canDelete('car/parking')
                                                <form method="POST" action="{{ route('car.parking.cancel', $row->id) }}"
                                                      data-confirm="Cancel ticket {{ $row->ticket_no }}?">
                                                    @csrf
                                                    <button type="submit" class="nv-icon-btn is-danger" title="Cancel the ticket">
                                                        <x-icon name="x-circle" />
                                                    </button>
                                                </form>
                                            @endCanDelete
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if ($rows->hasPages())
                <x-slot:footer>{{ $rows->links() }}</x-slot:footer>
            @endif
        </x-card>
    </div>

    {{-- ── Taking a car out ───────────────────────────────────────────────── --}}
    <div class="nv-modal-backdrop" data-modal="out">
        <div class="nv-modal nv-modal-wide" role="dialog" aria-modal="true" aria-labelledby="nv-out-title">
            <div class="nv-modal-head">
                <strong id="nv-out-title">Take <span data-fill="vehicle">the car</span> out</strong>
                <button type="button" class="nv-icon-btn" data-modal-close aria-label="Close"><x-icon name="x" /></button>
            </div>

            <form method="POST" action="">
                @csrf

                <div class="nv-form-grid">
                    <p class="nv-help nv-span-2">
                        Ticket <strong data-fill="ticket">—</strong>. It has been here about
                        <strong data-fill="hours">0</strong> hours. <strong>Leave the tick off and this
                        costs the guest nothing</strong> — that is the default and it stays the default.
                    </p>

                    <x-field label="Out at" name="out_at" help="Leave empty for now.">
                        <input type="datetime-local" name="out_at" class="nv-input" />
                    </x-field>

                    <x-field label="Hours used" name="hours_preview" help="Worked out again on save.">
                        <input type="number" name="hours_preview" step="0.01" min="0" class="nv-input"
                               data-fill="hours" readonly />
                    </x-field>

                    <x-field label="Charge for it?" name="is_chargeable" wide>
                        <label class="nv-check">
                            <input type="hidden" name="is_chargeable" value="0" />
                            <input type="checkbox" name="is_chargeable" value="1" @checked($chargeByDefault) />
                            <span>Yes — charge this car</span>
                        </label>
                    </x-field>

                    <x-field label="Rate per hour" name="rate">
                        <input type="number" name="rate" step="0.01" min="0" class="nv-input"
                               value="{{ $defaultRate }}" />
                    </x-field>

                    <x-field label="Tax" name="tax_choice">
                        <x-tax-select :choices="$taxChoices" />
                    </x-field>

                    <x-field label="Total" name="preview">
                        <p class="nv-fac-total" data-total-for="parking">₹ 0.00</p>
                    </x-field>

                    <x-field label="Put it on the room bill" name="post_to_room" wide
                             help="Only possible when the ticket names an in-house guest.">
                        <label class="nv-check">
                            <input type="hidden" name="post_to_room" value="0" />
                            <input type="checkbox" name="post_to_room" value="1" />
                            <span>Charge it to the folio</span>
                        </label>
                    </x-field>

                    <x-field label="Remark" name="remark" wide>
                        <input type="text" name="remark" class="nv-input" />
                    </x-field>
                </div>

                <div class="nv-actions" style="justify-content:flex-end;margin-top:16px">
                    <button type="button" class="nv-btn nv-btn-ghost" data-modal-close>Cancel</button>
                    <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="check" /> Take it out</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/facility.js') }}?v={{ file_exists(public_path('js/facility.js')) ? filemtime(public_path('js/facility.js')) : time() }}" defer></script>
@endpush
