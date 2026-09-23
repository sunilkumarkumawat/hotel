{{--
    The four side actions on Check Out Guest. Each is a plain form in a modal,
    so every one of them still works if the JavaScript never loads — the modal
    is only how they are revealed.
--}}

@php $today = now()->toDateString(); @endphp

{{-- ── Add Folio ─────────────────────────────────────────────────────────── --}}
<div class="nv-modal-backdrop" data-modal="folio">
    <div class="nv-modal nv-modal-wide" role="dialog" aria-modal="true" aria-labelledby="folio-title">
        <div class="nv-modal-head">
            <strong id="folio-title">Add Folio</strong>
            <button type="button" class="nv-icon-btn" data-modal-close aria-label="Close"><x-icon name="x" /></button>
        </div>

        <form method="POST" action="{{ route('front-office.check-out-guest.add-charge', $checkIn) }}">
            @csrf

            <div class="nv-form-grid nv-grid-3">
                <x-field label="Date" name="charge_date" required>
                    <x-input name="charge_date" type="date" :value="$today" />
                </x-field>

                <x-field label="Department" name="charge_type" required>
                    <x-select name="charge_type" :options="$chargeTypes" selected="service" />
                </x-field>

                <x-field label="Service" name="service_id" help="Pick one to fill the rate and tax in.">
                    <select name="service_id" class="nv-select" data-service-pick>
                        <option value="">Custom charge</option>
                        @foreach ($services as $service)
                            <option value="{{ $service->id }}"
                                    data-name="{{ $service->name }}"
                                    data-price="{{ $service->price }}"
                                    {{-- The tax this service *suggests*. It fills
                                         the dropdown in; it does not lock it, and a
                                         service with no tax attached suggests
                                         "No Tax" like everything else. --}}
                                    data-tax-choice="{{ \App\Support\Tax::suggestFor($service->tax_master_id) }}">{{ $service->name }}</option>
                        @endforeach
                    </select>
                </x-field>

                <x-field label="Particulars" name="particulars" required wide>
                    <x-input name="particulars" data-charge-name placeholder="Restaurant bill, laundry…" />
                </x-field>

                <x-field label="Qty" name="qty" required>
                    <x-input name="qty" type="number" step="0.01" min="0.01" value="1" />
                </x-field>

                <x-field label="Price" name="price" required>
                    <x-input name="price" type="number" step="0.01" min="0" value="0" data-charge-price />
                </x-field>

                {{--
                    Tax is picked, never assumed. The list opens on "No Tax" and
                    a charge added without touching it carries none — which is
                    the whole point of the dropdown being here rather than a
                    percent being filled in for you.
                --}}
                <x-field label="Tax" name="tax_choice">
                    <x-select name="tax_choice" :options="$taxChoices" :selected="$defaultTaxChoice" data-charge-tax-choice />
                </x-field>

                <x-field label="Tax Type" name="tax_type" required>
                    <x-select name="tax_type" :options="['exclusive' => 'Exclusive', 'inclusive' => 'Inclusive']" />
                </x-field>

                <x-field label="Remark" name="remark" wide>
                    <x-input name="remark" />
                </x-field>
            </div>

            <div class="nv-actions" style="justify-content:flex-end;margin-top:16px">
                <button type="button" class="nv-btn nv-btn-ghost" data-modal-close>Cancel</button>
                <button type="submit" class="nv-btn nv-btn-primary">Add to folio</button>
            </div>
        </form>
    </div>
</div>

{{-- ── Guest Checkout Date Extend ────────────────────────────────────────── --}}
<div class="nv-modal-backdrop" data-modal="extend">
    <div class="nv-modal nv-modal-wide" role="dialog" aria-modal="true" aria-labelledby="extend-title">
        <div class="nv-modal-head">
            <strong id="extend-title">Guest Checkout Date Extend</strong>
            <button type="button" class="nv-icon-btn" data-modal-close aria-label="Close"><x-icon name="x" /></button>
        </div>

        <div class="nv-co-strip">
            <span><b>Name:</b> {{ $checkIn->guest_name }}</span>
            <span><b>Check in:</b> {{ $checkIn->checkin_date->format('d/m/Y') }}</span>
            <span><b>Reg No:</b> {{ $checkIn->folio_no }}</span>
            <span><b>Check Out:</b> {{ $checkIn->expected_checkout_date->format('d/m/Y') }}</span>
            <span><b>Booking No:</b> {{ $checkIn->reservation?->reservation_no ?? 'Walk in' }}</span>
            <span><b>Check in Time:</b> {{ substr((string) $checkIn->checkin_time, 0, 5) }}</span>
            <span><b>Check Out Time:</b> {{ substr((string) $checkIn->expected_checkout_time, 0, 5) }}</span>
        </div>

        <div class="nv-table-wrap nv-mt">
            <table class="nv-table nv-table-compact">
                <thead>
                    <tr>
                        <th class="is-num">Sr. No.</th><th>From Date</th><th>To Date</th>
                        <th>Room No</th><th>Room Category</th><th>Room Type</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="is-num">1</td>
                        <td>{{ $checkIn->checkin_date->format('d/m/Y') }}</td>
                        <td>{{ $checkIn->expected_checkout_date->format('d/m/Y') }}</td>
                        <td><strong class="nv-mono">{{ $checkIn->room?->room_no ?? '—' }}</strong></td>
                        <td>{{ $checkIn->room?->category?->name ?? '—' }}</td>
                        <td>{{ $checkIn->room?->type?->name ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <form method="POST" action="{{ route('front-office.check-out-guest.extend', $checkIn) }}">
            @csrf

            <div class="nv-form-grid nv-grid-3 nv-mt">
                <x-field label="New Check Out Date" name="to_date" required
                         help="Extra nights are added to the folio at the same rate.">
                    <x-input name="to_date" type="date"
                             :value="$checkIn->expected_checkout_date->addDay()->toDateString()"
                             min="{{ $checkIn->checkin_date->addDay()->toDateString() }}" />
                </x-field>
            </div>

            <div class="nv-actions" style="justify-content:flex-end;margin-top:16px">
                <button type="button" class="nv-btn nv-btn-ghost" data-modal-close>Close</button>
                <button type="submit" class="nv-btn nv-btn-primary">Update Date</button>
            </div>
        </form>
    </div>
</div>

{{-- ── Pax Checkout ──────────────────────────────────────────────────────── --}}
<div class="nv-modal-backdrop" data-modal="pax">
    <div class="nv-modal nv-modal-wide" role="dialog" aria-modal="true" aria-labelledby="pax-title">
        <div class="nv-modal-head">
            <strong id="pax-title">Pax Checkout</strong>
            <button type="button" class="nv-icon-btn" data-modal-close aria-label="Close"><x-icon name="x" /></button>
        </div>

        <p class="nv-modal-note">
            Some of the people in room {{ $checkIn->room?->room_no ?? '—' }} leaving before the rest.
            {{ $checkIn->paxRemaining() }} of {{ $checkIn->male + $checkIn->female + $checkIn->child }} are still in.
        </p>

        <div class="nv-table-wrap">
            <table class="nv-table nv-table-compact">
                <thead>
                    <tr>
                        <th class="is-num">ID</th><th>Booking No</th><th>Room No</th>
                        <th>Checkout Date</th><th class="is-num">Total Pax</th>
                        <th class="is-num">Check out Pax</th><th>Remark</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($checkIn->paxCheckouts()->orderBy('id')->get() as $out)
                        <tr>
                            <td class="is-num nv-muted">{{ $out->id }}</td>
                            <td>{{ $checkIn->reservation?->reservation_no ?? 'Walk in' }}</td>
                            <td class="nv-mono">{{ $checkIn->room?->room_no ?? '—' }}</td>
                            <td class="nv-nowrap">{{ $out->checkout_date->format('d/m/Y') }}</td>
                            <td class="is-num">{{ $checkIn->male + $checkIn->female + $checkIn->child }}</td>
                            <td class="is-num"><strong>{{ $out->pax }}</strong></td>
                            <td class="nv-muted">{{ $out->remark ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="nv-muted" style="padding:16px;text-align:center">
                            Nobody has left early.
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <form method="POST" action="{{ route('front-office.check-out-guest.pax-checkout', $checkIn) }}">
            @csrf

            <div class="nv-form-grid nv-grid-3 nv-mt">
                <x-field label="Checkout Date" name="checkout_date" required>
                    <x-input name="checkout_date" type="date" :value="$today" />
                </x-field>

                <x-field label="Pax" name="pax" required>
                    <x-input name="pax" type="number" min="1" max="{{ max(1, $checkIn->paxRemaining()) }}" value="1" />
                </x-field>

                <x-field label="Remark" name="remark">
                    <x-input name="remark" />
                </x-field>
            </div>

            <div class="nv-actions" style="justify-content:flex-end;margin-top:16px">
                <button type="button" class="nv-btn nv-btn-ghost" data-modal-close>Close</button>
                <button type="submit" class="nv-btn nv-btn-primary"
                        @disabled($checkIn->paxRemaining() < 1)>Pax Checkout</button>
            </div>
        </form>
    </div>
</div>

{{-- ── Multiple Pay Mode ─────────────────────────────────────────────────── --}}
<div class="nv-modal-backdrop" data-modal="pay">
    <div class="nv-modal nv-modal-wide" role="dialog" aria-modal="true" aria-labelledby="pay-title">
        <div class="nv-modal-head">
            <strong id="pay-title">Take a payment</strong>
            <button type="button" class="nv-icon-btn" data-modal-close aria-label="Close"><x-icon name="x" /></button>
        </div>

        <p class="nv-modal-note">
            Part of the bill on one pay mode. Take as many as you need — ₹2,000 on a card
            and ₹100 in cash is two payments. Due right now is
            <strong>₹{{ number_format($totals['due'], 2) }}</strong>.
        </p>

        <form method="POST" action="{{ route('front-office.check-out-guest.pay', $checkIn) }}">
            @csrf

            <div class="nv-form-grid nv-grid-3">
                <x-field label="Date" name="settle_date" required>
                    <x-input name="settle_date" type="date" :value="$today" />
                </x-field>

                <x-field label="Amount" name="amount" required>
                    <x-input name="amount" type="number" step="0.01" min="0.01" placeholder="0.00" />
                </x-field>

                <x-field label="Pay Mode" name="pay_mode_id" required>
                    <x-select name="pay_mode_id" :options="$payModes->all()" placeholder="Select PayMode" />
                </x-field>

                <x-field label="Pay Details" name="pay_type" required>
                    <x-select name="pay_type" :options="$payTypes" placeholder="Select" data-pay-type />
                </x-field>

                <x-field label="Card Type" name="card_type" data-card-field>
                    <x-select name="card_type" :options="$cardTypes" placeholder="Select" />
                </x-field>

                <x-field label="Name on Card" name="card_name" data-card-field>
                    <x-input name="card_name" />
                </x-field>

                <x-field label="Card no. (last 4)" name="card_last4" data-card-field>
                    <x-input name="card_last4" inputmode="numeric" maxlength="4" />
                </x-field>

                <x-field label="Pan Card No" name="pan_no">
                    <x-input name="pan_no" maxlength="10" style="text-transform:uppercase" />
                </x-field>

                <x-field label="Reference no." name="reference_no">
                    <x-input name="reference_no" />
                </x-field>

                <x-field label="Remark" name="remark" wide>
                    <x-input name="remark" />
                </x-field>
            </div>

            <div class="nv-actions" style="justify-content:flex-end;margin-top:16px">
                <button type="button" class="nv-btn nv-btn-ghost" data-modal-close>Close</button>
                <button type="submit" class="nv-btn nv-btn-primary">Take payment</button>
            </div>
        </form>
    </div>
</div>
