@extends('layouts.app')

@section('title', 'Check Out Guest')

@php
    $money = fn ($n) => '₹' . number_format((float) $n, 2);
    $reservation = $checkIn->reservation;

    /*
     * Rooms checked in together share a folio number — that is the family. If
     * there is more than one still in house, the desk is offered the choice
     * between settling this room and settling the lot on one bill, because a
     * family of five standing at the counter does not want to be asked five
     * times.
     */
    $family = \App\Models\FrontOffice\CheckIn::query()
        ->where('branch_id', $checkIn->branch_id)
        ->where('folio_no', $checkIn->folio_no)
        ->inHouse()
        ->count();
@endphp

@section('content')
    <x-page-header
        title="Check Out Guest"
        :subtitle="$checkIn->guest_name . ' · ' . ($checkIn->room?->room_no ?? '—') . ' · ' . $checkIn->folio_no"
        :crumbs="['Home' => url('/'), 'Front Office', 'Check Out Guest']"
    >
        <x-slot:actions>
            @if ($family > 1)
                <a href="{{ route('front-office.check-out-guest.folio', $checkIn->folio_no) }}"
                   class="nv-btn nv-btn-outline">
                    <x-icon name="users" /> All {{ $family }} rooms on one bill
                </a>
            @endif

            <a href="{{ route('front-office.check-out-guest.proforma', $checkIn) }}" target="_blank"
               class="nv-btn nv-btn-outline">
                <x-icon name="file" /> Proforma Invoice
            </a>

            @canAdd('front-office/check-out-guest')
                <button type="submit" form="checkout-form" class="nv-btn nv-btn-primary">
                    <x-icon name="check" /> Checkout (F10)
                </button>
            @endCanAdd
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

    {{-- ── Who is leaving ────────────────────────────────────────────────── --}}
    <div class="nv-mt">
        <x-card>
            <div class="nv-form-grid nv-grid-4">
                <x-field label="Room / Guest">
                    <select class="nv-select" onchange="location.href = this.value">
                        @foreach ($inHouse as $stay)
                            <option value="{{ route('front-office.check-out-guest', ['check_in' => $stay->id]) }}"
                                    @selected($stay->id === $checkIn->id)>
                                {{ $stay->room?->room_no ?? '—' }} ({{ $stay->guest_name }})
                            </option>
                        @endforeach
                    </select>
                </x-field>

                <x-field label="Arrival No.">
                    <x-input :value="$checkIn->folio_no" readonly />
                </x-field>

                <x-field label="Check in Date">
                    <x-input :value="$checkIn->checkin_date->format('d/m/Y')" readonly />
                </x-field>

                <x-field label="Check in Time">
                    <x-input :value="substr((string) $checkIn->checkin_time, 0, 5)" readonly />
                </x-field>

                <x-field label="Expected Check Out">
                    <x-input :value="$checkIn->expected_checkout_date->format('d/m/Y')" readonly />
                </x-field>

                <x-field label="Nights on folio">
                    <x-input :value="$totals['nights']" readonly />
                </x-field>

                <x-field label="Pax in room">
                    <x-input :value="$checkIn->paxRemaining() . ' of ' . ($checkIn->male + $checkIn->female + $checkIn->child)" readonly />
                </x-field>

                <x-field label="Booking">
                    <x-input :value="$reservation?->reservation_no ?? 'Walk in'" readonly />
                </x-field>

                {{-- What one night costs, spelled out. A guest looking at
                     ₹5,000 for a ₹3,600 room needs to see the plan. --}}
                <x-field label="Plan">
                    <x-input :value="$checkIn->plan?->name ?? 'European Plan'" readonly />
                </x-field>

                <x-field label="Tariff per night"
                         :help="(float) $checkIn->plan_charge > 0
                            ? $money($checkIn->room_rent) . ' room + ' . $money($checkIn->plan_charge) . ' plan'
                            : 'Room rent, before tax.'">
                    <x-input :value="$money($nightly['nightly'])" readonly />
                </x-field>
            </div>

            <div class="nv-actions nv-mt">
                @canAdd('front-office/check-out-guest')
                    <button type="button" class="nv-btn nv-btn-outline" data-open="folio">
                        <x-icon name="plus" /> Add Folio
                    </button>
                    <button type="button" class="nv-btn nv-btn-outline" data-open="extend">
                        <x-icon name="calendar" /> Extend Checkout
                    </button>
                    <button type="button" class="nv-btn nv-btn-outline" data-open="pax">
                        <x-icon name="users" /> Pax Checkout
                    </button>
                    <button type="button" class="nv-btn nv-btn-outline" data-open="pay">
                        <x-icon name="wallet" /> Multiple Pay Mode
                    </button>
                @endCanAdd
            </div>
        </x-card>
    </div>

    {{-- ── The folio ─────────────────────────────────────────────────────── --}}
    <div class="nv-mt">
        <x-card title="Details" :subtitle="'Booked By: ' . ($reservation?->bookedBy?->name ?? '—') . ' · Company: ' . ($reservation?->company?->name ?? '—')" flush>
            <div class="nv-table-wrap">
                <table class="nv-table nv-table-compact">
                    <thead>
                        <tr>
                            <th class="is-num">Sr. No.</th>
                            <th>Department</th>
                            <th class="is-num">Bill Amount</th>
                            <th class="is-num">Tax</th>
                            <th class="is-num">Total</th>
                            <th class="is-end">Details</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($departments as $dept)
                            <tr>
                                <td class="is-num nv-muted">{{ $loop->iteration }}</td>
                                <td><strong>{{ $dept['label'] }}</strong>
                                    <span class="nv-sub">{{ $dept['lines']->count() }} line(s)</span></td>
                                <td class="is-num">{{ $money($dept['amount']) }}</td>
                                <td class="is-num nv-muted">{{ $money($dept['tax']) }}</td>
                                <td class="is-num"><strong>{{ $money($dept['total']) }}</strong></td>
                                <td class="is-end">
                                    <button type="button" class="nv-btn nv-btn-ghost nv-btn-sm"
                                            data-toggle-lines="{{ $dept['type'] }}">View</button>
                                </td>
                            </tr>

                            <tr class="nv-folio-lines" data-lines="{{ $dept['type'] }}" hidden>
                                <td colspan="6">
                                    <table class="nv-table nv-table-compact nv-folio-inner">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Particulars</th>
                                                <th class="is-num">Qty</th>
                                                <th class="is-num">Price</th>
                                                <th class="is-num">Tax</th>
                                                <th class="is-num">Total</th>
                                                <th class="is-end"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($dept['lines'] as $line)
                                                <tr>
                                                    <td class="nv-nowrap nv-muted">{{ $line->charge_date->format('d/m/Y') }}</td>
                                                    <td>{{ $line->particulars }}</td>
                                                    <td class="is-num">{{ rtrim(rtrim(number_format($line->qty, 2), '0'), '.') }}</td>
                                                    <td class="is-num">{{ $money($line->price) }}</td>
                                                    <td class="is-num nv-muted">{{ $money($line->tax_amount) }}</td>
                                                    <td class="is-num">{{ $money($line->total_amount) }}</td>
                                                    <td class="is-end">
                                                        @canDelete('front-office/check-out-guest')
                                                            @unless ($line->isSystem())
                                                                <form method="POST"
                                                                      action="{{ route('front-office.check-out-guest.remove-charge', $line) }}"
                                                                      data-confirm="{{ $line->particulars }} ({{ $money($line->total_amount) }}) will come off the folio."
                                                                      data-confirm-title="Remove this charge?"
                                                                      data-confirm-action="Remove charge">
                                                                    @csrf @method('DELETE')
                                                                    <button type="submit" class="nv-btn nv-btn-ghost nv-btn-sm"
                                                                            aria-label="Remove {{ $line->particulars }}">
                                                                        <x-icon name="trash" />
                                                                    </button>
                                                                </form>
                                                            @else
                                                                <span class="nv-muted" title="Posted by the system — change the checkout date instead">—</span>
                                                            @endunless
                                                        @endCanDelete
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="nv-muted" style="padding:20px;text-align:center">
                                Nothing on the folio yet.
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>

    {{-- ── Money ─────────────────────────────────────────────────────────── --}}
    <form method="POST" action="{{ route('front-office.check-out-guest.checkout', $checkIn) }}" id="checkout-form">
        @csrf

        <div class="nv-mt nv-co">
            <x-card title="Bill">
                <dl class="nv-co-totals">
                    {{-- The three that come OFF the bill are shown as "− ₹x",
                         not as a negative amount: "₹-5,000.00" reads like a
                         mistake to anybody holding the printout. --}}
                    @foreach ([
                        ['Sub Total', $totals['sub_total'], false],
                        ['Tax Amount', $totals['tax'], false],
                        ['Discount', $totals['discount'], true],
                        ['Advance', $totals['advance'], true],
                        ['Paidup Amount', $totals['paid'], true],
                    ] as [$label, $value, $minus])
                        <div class="nv-co-row">
                            <dt>{{ $label }}</dt>
                            <dd>{{ $minus && $value > 0 ? '− ' : '' }}{{ $money($value) }}</dd>
                        </div>
                    @endforeach

                    <div class="nv-co-row is-net">
                        <dt>Net Amount</dt>
                        <dd>{{ $money($totals['net']) }}</dd>
                    </div>

                    <div class="nv-co-row is-due">
                        <dt>Due Amount</dt>
                        <dd>{{ $money($totals['due']) }}</dd>
                    </div>

                    <div class="nv-co-row">
                        <dt>Remaining Refund</dt>
                        <dd>{{ $money($totals['refund']) }}</dd>
                    </div>
                </dl>

                @if ($reservation)
                    {{-- The figure the guest was quoted, so the desk can see at
                         a glance that the bill agrees with the booking. They
                         differ only once something is added during the stay. --}}
                    <p class="nv-help">
                        Booked value of {{ $reservation->reservation_no }}:
                        <strong>{{ $money($reservation->net_amount) }}</strong>
                        @if ($reservation->rooms->count() > 1 || (int) $reservation->rooms->sum('no_of_rooms') > 1)
                            — across {{ (int) $reservation->rooms->sum('no_of_rooms') }} rooms.
                        @endif
                    </p>
                @endif
            </x-card>

            <x-card title="Settle" subtitle="Discount and the last payment. Card fields appear only for a card.">
                <div class="nv-form-grid nv-grid-3">
                    <x-field label="Discount" name="discount_mode">
                        <select name="discount_mode" class="nv-select" data-discount-mode>
                            <option value="amount" @selected($discount['mode'] === 'amount')>In ₹</option>
                            <option value="percent" @selected($discount['mode'] === 'percent')>In %</option>
                        </select>
                    </x-field>

                    <x-field label="Discount value" name="discount_value"
                             help="Press Apply to see it on the bill before checking out.">
                        <x-input name="discount_value" type="number" step="0.01" min="0"
                                 :value="$discount['value'] ?: null" placeholder="0" />
                    </x-field>

                    {{-- Plain markup, not <x-field>: that component escapes its
                         label, so a blank spacer has to be written by hand. --}}
                    <div class="nv-field">
                        <span class="nv-label" aria-hidden="true">&nbsp;</span>

                        <button type="button" class="nv-btn nv-btn-outline" data-apply-discount>Apply discount</button>
                    </div>

                    <x-field label="Check Out Date" name="checkout_date" required>
                        <x-input name="checkout_date" type="date" :value="old('checkout_date', now()->toDateString())" />
                    </x-field>

                    <x-field label="Rec. Amount" name="amount" help="Leave blank if nothing is being taken now.">
                        <x-input name="amount" type="number" step="0.01" min="0"
                                 :value="old('amount', $totals['due'] > 0 ? $totals['due'] : null)" />
                    </x-field>

                    <x-field label="Pay Mode" name="pay_mode_id">
                        <x-select name="pay_mode_id" :options="$payModes->all()" :selected="old('pay_mode_id')"
                                  placeholder="Select PayMode" />
                    </x-field>

                    <x-field label="Pay Details" name="pay_type">
                        <x-select name="pay_type" :options="$payTypes" :selected="old('pay_type')"
                                  placeholder="Select" data-pay-type />
                    </x-field>

                    <x-field label="Card Type" name="card_type" data-card-field>
                        <x-select name="card_type" :options="$cardTypes" :selected="old('card_type')" placeholder="Select" />
                    </x-field>

                    <x-field label="Name on Card" name="card_name" data-card-field>
                        <x-input name="card_name" :value="old('card_name')" />
                    </x-field>

                    <x-field label="Card no. (last 4)" name="card_last4" data-card-field
                             help="Only the last four digits are stored.">
                        <x-input name="card_last4" inputmode="numeric" maxlength="4" :value="old('card_last4')" />
                    </x-field>

                    <x-field label="Pan Card No" name="pan_no">
                        <x-input name="pan_no" maxlength="10" style="text-transform:uppercase" :value="old('pan_no')" />
                    </x-field>

                    <x-field label="Reference no." name="reference_no">
                        <x-input name="reference_no" :value="old('reference_no')" />
                    </x-field>

                    <x-field label="Billing Instructions" name="billing_instruction_id">
                        <x-select name="billing_instruction_id" :options="$billingInstructions->all()"
                                  :selected="old('billing_instruction_id', $reservation?->billing_instruction_id)"
                                  placeholder="Select Ins" />
                    </x-field>

                    <x-field label="Remark" name="remark" wide>
                        <x-input name="remark" :value="old('remark')" />
                    </x-field>
                </div>

                <div class="nv-actions nv-mt" style="justify-content:flex-end">
                    <a href="{{ route('front-office.check-out-guest.proforma', $checkIn) }}" target="_blank"
                       class="nv-btn nv-btn-outline">Proforma Invoice</a>
                    <button type="submit" class="nv-btn nv-btn-primary">
                        <x-icon name="check" /> Checkout (F10)
                    </button>
                </div>
            </x-card>
        </div>
    </form>

    {{-- ── Payments already taken ────────────────────────────────────────── --}}
    @if ($settlements->count())
        <div class="nv-mt">
            <x-card title="Payments taken" flush>
                <div class="nv-table-wrap">
                    <table class="nv-table nv-table-compact">
                        <thead>
                            <tr>
                                <th>Date</th><th class="is-num">Amount</th><th>Pay Mode</th>
                                <th>Pay Type</th><th>Card</th><th>Reference</th><th class="is-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($settlements as $paid)
                                <tr>
                                    <td class="nv-nowrap nv-muted">{{ $paid->settle_date->format('d M Y') }}</td>
                                    <td class="is-num"><strong>{{ $money($paid->amount) }}</strong></td>
                                    <td>{{ $paid->payMode?->name ?? '—' }}</td>
                                    <td>{{ $paid->pay_type_label }}</td>
                                    <td class="nv-mono">{{ $paid->card_number ?? '—' }}</td>
                                    <td class="nv-muted">{{ $paid->reference_no ?: '—' }}</td>
                                    <td class="is-end">
                                        @canDelete('front-office/check-out-guest')
                                            <form method="POST"
                                                  action="{{ route('front-office.check-out-guest.remove-payment', $paid) }}"
                                                  data-confirm="{{ $money($paid->amount) }} will be removed from this stay."
                                                  data-confirm-title="Remove this payment?"
                                                  data-confirm-action="Remove payment">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="nv-btn nv-btn-ghost nv-btn-sm"
                                                        aria-label="Remove payment">
                                                    <x-icon name="trash" />
                                                </button>
                                            </form>
                                        @endCanDelete
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>
    @endif

    @canAdd('front-office/check-out-guest')
        @include('front-office.partials.checkout-modals')
    @endCanAdd
@endsection

@push('scripts')
<script src="{{ asset('js/check-out.js') }}?v={{ filemtime(public_path('js/check-out.js')) }}" defer></script>
@endpush
