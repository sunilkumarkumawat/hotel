@extends('layouts.app')

@section('title', $meta['label'])

@php
    // Money is right-aligned and formatted; everything else is printed as it
    // comes. The column definition decides, so one view renders all eighteen.
    $cell = function (array $column, $value) {
        if ($value === null || $value === '') {
            return '—';
        }

        return ($column['money'] ?? false)
            ? '₹ ' . number_format((float) $value, 2)
            : (is_float($value) ? rtrim(rtrim(number_format($value, 2), '0'), '.') : $value);
    };

    // The Settle button and its shared modal (below) work off any report
    // whose rows carry a check_in_id — Outstanding (billed, unpaid stays)
    // and Check In / Check Out (every stay, billed or not) both do.
    $canSettle = in_array($slug, ['outstanding', 'check-in-out'], true);
@endphp

@section('content')
    <x-page-header
        :title="$meta['label']"
        :subtitle="$meta['about']"
        :crumbs="['Home' => url('/'), 'Reports' => route('reports.index'), $meta['label']]"
    >
        <x-slot:actions>
            <a href="{{ route('reports.export', ['report' => $slug] + array_filter($filters)) }}"
               class="nv-btn nv-btn-outline"><x-icon name="download" /> Export</a>
        </x-slot:actions>
    </x-page-header>

    @if ($summary)
        <div @class(['nv-grid', 'nv-grid-' . min(4, max(2, count($summary)))])>
            @foreach ($summary as $tile)
                <x-stat
                    :label="$tile['label']"
                    :value="$tile['value']"
                    :icon="$tile['icon'] ?? 'chart'"
                    :tone="$tile['tone'] ?? 'primary'" />
            @endforeach
        </div>
    @endif

    <div class="nv-mt">
        <x-card>
            <form method="GET" class="nv-toolbar">
                @if (in_array('range', $meta['filters'], true))
                    <input type="date" name="from" value="{{ $filters['from'] }}" class="nv-input"
                           style="width:160px" aria-label="From" />
                    <input type="date" name="to" value="{{ $filters['to'] }}" class="nv-input"
                           style="width:160px" aria-label="To" />
                @endif

                @foreach (['room_type' => 'Every room type', 'pay_mode' => 'Every pay mode', 'outlet' => 'Every outlet', 'room' => 'Every room', 'booking_source' => 'Every source', 'staff' => 'Every staff'] as $filter => $blank)
                    @if (isset($options[$filter]))
                        <select name="{{ $filter }}" class="nv-select" style="width:180px" aria-label="{{ $blank }}">
                            <option value="">{{ $blank }}</option>
                            @foreach ($options[$filter] as $id => $name)
                                <option value="{{ $id }}" @selected($filters[$filter] === $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                    @endif
                @endforeach

                @if (in_array('status', $meta['filters'], true))
                    <select name="status" class="nv-select" style="width:170px" aria-label="Check-in or check-out">
                        <option value="both" @selected($filters['status'] === 'both')>Check-in &amp; check-out</option>
                        <option value="checkin" @selected($filters['status'] === 'checkin')>Check-in only</option>
                        <option value="checkout" @selected($filters['status'] === 'checkout')>Check-out only</option>
                    </select>
                @endif

                @if (in_array('payment_status', $meta['filters'], true))
                    <select name="payment_status" class="nv-select" style="width:160px" aria-label="Payment status">
                        <option value="">Any payment status</option>
                        <option value="due" @selected($filters['payment_status'] === 'due')>Due</option>
                        <option value="paid" @selected($filters['payment_status'] === 'paid')>Fully paid</option>
                    </select>
                @endif

                <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="filter" /> Show</button>
                <a href="{{ route('reports.show', $slug) }}" class="nv-btn nv-btn-ghost">Reset</a>

                <span class="nv-muted" style="margin-left:auto">{{ $rows->count() }} row(s)</span>
            </form>

            @if ($note)
                <p class="nv-help">{{ $note }}</p>
            @endif
        </x-card>
    </div>

    <div class="nv-mt">
        <x-card flush>
            @if ($rows->isEmpty())
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon :name="$meta['icon']" /></span>
                    <strong>Nothing to show</strong>
                    <p>Widen the dates, or clear the filters.</p>
                </div>
            @else
                <div class="nv-table-wrap">
                    <table class="nv-table">
                        <thead>
                            <tr>
                                @foreach ($columns as $column)
                                    <th @class(['is-num' => $column['num'] ?? false])>{{ $column['label'] }}</th>
                                @endforeach
                                @if ($canSettle)
                                    <th class="is-num">Settle</th>
                                @endif
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    @foreach ($columns as $key => $column)
                                        <td @class(['is-num' => $column['num'] ?? false])>
                                            {{ $cell($column, $row->{$key} ?? null) }}
                                        </td>
                                    @endforeach
                                    @if ($canSettle)
                                        <td class="is-num">
                                            @if ($row->check_in_id && $row->due > 0)
                                                <button type="button" class="nv-btn nv-btn-sm nv-btn-primary"
                                                        data-open="settle-due"
                                                        data-settle-action="{{ route('front-office.check-out-guest.pay', $row->check_in_id) }}"
                                                        data-settle-guest="{{ $row->guest }}"
                                                        data-settle-bill="{{ $row->bill_no ?? $row->folio_no ?? '' }}"
                                                        data-settle-due="{{ number_format($row->due, 2, '.', '') }}">
                                                    Settle
                                                </button>
                                            @else
                                                <span class="nv-muted">—</span>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>

                        @if ($totals)
                            <tfoot>
                                <tr>
                                    @foreach ($columns as $key => $column)
                                        @if ($loop->first)
                                            <th>Total</th>
                                        @else
                                            <th @class(['is-num' => $column['num'] ?? false])>
                                                @isset($totals[$key])
                                                    {{ $cell($column, $totals[$key]) }}
                                                @endisset
                                            </th>
                                        @endif
                                    @endforeach
                                    @if ($canSettle)
                                        <th></th>
                                    @endif
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            @endif
        </x-card>
    </div>

    @if ($canSettle)
        {{--
            One modal shared by every row's Settle button on either report —
            this is exactly the "Multiple Pay Mode" form the check-out screen
            already uses (see front-office/partials/checkout-modals.blade.php),
            so a payment recorded from here looks no different from one taken
            at the desk. The button that opens it sets which stay it posts to
            and fills in the guest/ref/due line; see @push('scripts') below.
        --}}
        <div class="nv-modal-backdrop" data-modal="settle-due">
            <div class="nv-modal nv-modal-wide" role="dialog" aria-modal="true" aria-labelledby="settle-due-title">
                <div class="nv-modal-head">
                    <strong id="settle-due-title">Settle a due payment</strong>
                    <button type="button" class="nv-icon-btn" data-modal-close aria-label="Close"><x-icon name="x" /></button>
                </div>

                <p class="nv-modal-note">
                    <strong data-settle-guest></strong> · Ref. <strong data-settle-bill></strong> ·
                    Due right now is <strong>₹<span data-settle-due>0.00</span></strong>.
                </p>

                <form method="POST" data-settle-form>
                    @csrf

                    <div class="nv-form-grid nv-grid-3">
                        <x-field label="Date" name="settle_date" required>
                            <x-input name="settle_date" type="date" :value="now()->toDateString()" />
                        </x-field>

                        <x-field label="Amount" name="amount" required>
                            <x-input name="amount" type="number" step="0.01" min="0.01" placeholder="0.00" data-settle-amount />
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

        @push('scripts')
            <script>
                (function () {
                    var backdrop = document.querySelector('[data-modal="settle-due"]');
                    if (!backdrop) return;

                    var form = backdrop.querySelector('[data-settle-form]');
                    var amount = backdrop.querySelector('[data-settle-amount]');

                    // Opening: each button carries its own stay, so the one
                    // shared form is retargeted and refilled every time.
                    document.querySelectorAll('[data-open="settle-due"]').forEach(function (button) {
                        button.addEventListener('click', function () {
                            form.action = button.getAttribute('data-settle-action');
                            backdrop.querySelector('[data-settle-guest]').textContent = button.getAttribute('data-settle-guest') || '';
                            backdrop.querySelector('[data-settle-bill]').textContent = button.getAttribute('data-settle-bill') || '';
                            backdrop.querySelector('[data-settle-due]').textContent = button.getAttribute('data-settle-due') || '0.00';
                            amount.value = button.getAttribute('data-settle-due') || '';

                            backdrop.classList.add('is-open');
                            document.body.style.overflow = 'hidden';
                        });
                    });

                    function close() {
                        backdrop.classList.remove('is-open');
                        document.body.style.overflow = '';
                    }

                    backdrop.querySelectorAll('[data-modal-close]').forEach(function (b) {
                        b.addEventListener('click', close);
                    });
                    backdrop.addEventListener('click', function (event) {
                        if (event.target === backdrop) close();
                    });
                    document.addEventListener('keydown', function (event) {
                        if (event.key === 'Escape' && backdrop.classList.contains('is-open')) close();
                    });

                    // Same card-details show/hide the check-out screen uses —
                    // duplicated here in miniature rather than pulling in the
                    // whole check-out.js file for one behaviour this page
                    // does not otherwise need.
                    var payType = form.querySelector('[data-pay-type]');
                    var cardFields = form.querySelectorAll('[data-card-field]');
                    var needsCard = ['credit_card', 'debit_card'];

                    function syncCardFields() {
                        var show = needsCard.indexOf(payType.value) !== -1;
                        cardFields.forEach(function (field) {
                            field.style.display = show ? '' : 'none';
                        });
                    }

                    if (payType) {
                        payType.addEventListener('change', syncCardFields);
                        syncCardFields();
                    }
                })();
            </script>
        @endpush
    @endif
@endsection
