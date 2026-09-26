@extends('layouts.app')

@section('title', 'Group bill · ' . $folioNo)

@php
    $guest = $rows->first()->stay->guest_name ?? 'Guest';
    $canEdit = can_do('front-office/check-out-guest', 'add');
@endphp

@section('content')
    <x-page-header
        :title="$guest . ' — ' . $rows->count() . ' rooms'"
        :subtitle="'Folio ' . $folioNo . ' · one total for the family, one numbered bill for each room'"
        :crumbs="['Home' => url('/'), 'Front Office' => route('front-office.check-out-guest'), 'Group bill']"
    >
        <x-slot:actions>
            <a href="{{ route('front-office.check-out-guest') }}" class="nv-btn nv-btn-outline">
                <x-icon name="chevron-left" /> One room at a time
            </a>
        </x-slot:actions>
    </x-page-header>

    @if (session('error'))
        <div class="nv-mt"><x-alert tone="danger" title="Not done">{{ session('error') }}</x-alert></div>
    @endif

    <div class="nv-grid nv-grid-4 nv-mt">
        <x-stat label="Rooms" :value="$rows->count()" icon="home" />
        <x-stat label="Nights billed" :value="$grand['nights']" icon="calendar" />
        <x-stat label="Family total" :value="'₹ ' . number_format($grand['net'], 2)" icon="wallet" />
        <x-stat
            label="{{ $grand['refund'] > 0 ? 'To refund' : 'Still due' }}"
            :value="'₹ ' . number_format($grand['refund'] > 0 ? $grand['refund'] : $grand['due'], 2)"
            icon="credit-card"
            :tone="$grand['due'] > 0 ? 'warning' : 'success'"
        />
    </div>

    {{--
        Room by room, so the cashier can see where the money is before deciding
        whether the family pays as one. Any single room can still be settled on
        its own from its own row — the group is an option, not a cage.
    --}}
    <div class="nv-mt">
        <x-card flush>
            <x-slot:title>What each room owes</x-slot:title>

            <div class="nv-table-wrap">
                <table class="nv-table">
                    <thead>
                        <tr>
                            <th>Room</th>
                            <th>Guest</th>
                            <th>Arrived</th>
                            <th class="is-num">Nights</th>
                            <th class="is-num">Room</th>
                            <th class="is-num">Services</th>
                            <th class="is-num">Tax</th>
                            <th class="is-num">Advance</th>
                            <th class="is-num">Paid</th>
                            <th class="is-num">Net</th>
                            <th class="is-num">Due</th>
                            <th class="is-end">On its own</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td><strong>{{ $row->stay->room?->room_no ?: '—' }}</strong></td>
                                <td>{{ $row->stay->guest_name }}</td>
                                <td>{{ $row->stay->checkin_date?->format('d M') }}</td>
                                <td class="is-num">{{ $row->totals['nights'] }}</td>
                                <td class="is-num">₹ {{ number_format((float) $row->totals['room_total'], 2) }}</td>
                                <td class="is-num">₹ {{ number_format((float) $row->totals['service_total'], 2) }}</td>
                                <td class="is-num">₹ {{ number_format((float) $row->totals['tax'], 2) }}</td>
                                <td class="is-num">₹ {{ number_format((float) $row->totals['advance'], 2) }}</td>
                                <td class="is-num">₹ {{ number_format((float) $row->totals['paid'], 2) }}</td>
                                <td class="is-num"><strong>₹ {{ number_format((float) $row->totals['net'], 2) }}</strong></td>
                                <td class="is-num">
                                    @if ($row->totals['due'] > 0)
                                        <span class="nv-due">₹ {{ number_format((float) $row->totals['due'], 2) }}</span>
                                    @else
                                        <x-badge tone="success">Clear</x-badge>
                                    @endif
                                </td>
                                <td class="is-end">
                                    <a href="{{ route('front-office.check-out-guest', ['check_in' => $row->stay->id]) }}"
                                       class="nv-btn nv-btn-ghost nv-btn-sm">
                                        <x-icon name="arrow-right" /> Settle this room
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>

                    <tfoot>
                        <tr>
                            <th colspan="4">The family</th>
                            <th class="is-num">₹ {{ number_format($grand['room_total'], 2) }}</th>
                            <th class="is-num">₹ {{ number_format($grand['service_total'], 2) }}</th>
                            <th class="is-num">₹ {{ number_format($grand['tax'], 2) }}</th>
                            <th class="is-num">₹ {{ number_format($grand['advance'], 2) }}</th>
                            <th class="is-num">₹ {{ number_format($grand['paid'], 2) }}</th>
                            <th class="is-num">₹ {{ number_format($grand['net'], 2) }}</th>
                            <th class="is-num">₹ {{ number_format($grand['due'], 2) }}</th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-card>
    </div>

    @if ($canEdit)
        <div class="nv-mt">
            <x-card title="Check the whole family out">
                <form method="POST" action="{{ route('front-office.check-out-guest.group-checkout', $folioNo) }}">
                    @csrf

                    <div class="nv-form-grid nv-grid-3">
                        <x-field label="Checkout date" name="checkout_date" required>
                            <input type="date" name="checkout_date" id="checkout_date"
                                   value="{{ old('checkout_date', $today) }}" class="nv-input" required />
                        </x-field>

                        <x-field label="Discount" name="discount_value"
                                 help="Entered once for the family. A flat amount is split across the rooms in proportion to what each owes.">
                            <div class="nv-input-group">
                                <input type="number" name="discount_value" id="discount_value" step="0.01" min="0"
                                       value="{{ old('discount_value', 0) }}" class="nv-input" />
                                <select name="discount_mode" class="nv-select" style="max-width:120px">
                                    <option value="amount" @selected(old('discount_mode') === 'amount')>₹</option>
                                    <option value="percent" @selected(old('discount_mode') === 'percent')>%</option>
                                </select>
                            </div>
                        </x-field>

                        <x-field label="Billing instruction" name="billing_instruction_id">
                            <select name="billing_instruction_id" id="billing_instruction_id" class="nv-select">
                                <option value="">—</option>
                                @foreach ($instructions as $instruction)
                                    <option value="{{ $instruction->id }}"
                                            @selected(old('billing_instruction_id') == $instruction->id)>
                                        {{ $instruction->name }}
                                    </option>
                                @endforeach
                            </select>
                        </x-field>

                        <x-field label="Amount received" name="amount"
                                 help="Spread across the rooms in the order above, each taking what it owes.">
                            <div class="nv-input-group">
                                <span class="nv-input-addon">₹</span>
                                <input type="number" name="amount" id="amount" step="0.01" min="0"
                                       value="{{ old('amount', number_format($grand['due'], 2, '.', '')) }}"
                                       class="nv-input" />
                            </div>
                        </x-field>

                        <x-field label="Pay mode" name="pay_mode_id">
                            <select name="pay_mode_id" id="pay_mode_id" class="nv-select">
                                <option value="">—</option>
                                @foreach ($payModes as $mode)
                                    <option value="{{ $mode->id }}" @selected(old('pay_mode_id') == $mode->id)>
                                        {{ $mode->name }}
                                    </option>
                                @endforeach
                            </select>
                        </x-field>

                        <x-field label="Reference" name="reference_no">
                            <x-input name="reference_no" placeholder="Card / UPI reference" />
                        </x-field>

                        <x-field label="Remark" name="remark" wide>
                            <x-input name="remark" placeholder="Anything that should print on all the bills" />
                        </x-field>
                    </div>

                    <x-alert tone="info" title="Each room still gets its own bill number">
                        The {{ $rows->count() }} bills share one group number, so they print as one document with
                        one grand total — and any single one can still be handed out on its own.
                    </x-alert>

                    <div class="nv-actions" style="justify-content:flex-end;margin-top:16px">
                        <button type="submit" class="nv-btn nv-btn-primary">
                            <x-icon name="check" /> Check out all {{ $rows->count() }} rooms
                        </button>
                    </div>
                </form>
            </x-card>
        </div>
    @endif
@endsection
