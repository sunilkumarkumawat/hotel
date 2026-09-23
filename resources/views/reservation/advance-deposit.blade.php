@extends('layouts.app')

@section('title', 'Advance Deposit Details')

@php
    /** @var \App\Models\Reservation\AdvanceDeposit|null $editing */
    $old = fn (string $key, $fallback = null) => old($key, $editing?->{$key} ?? $fallback);

    // `deposit_date` is cast to a Carbon, and an <input type="date"> only
    // understands Y-m-d — a datetime string leaves the field blank.
    $depositDate = old('deposit_date', $editing?->deposit_date?->toDateString() ?? now()->toDateString());

    $action = $editing
        ? route('reservation.advance-deposit.update', $editing)
        : route('reservation.advance-deposit.store');

    // No point printing an Action column a read-only user can do nothing with.
    $canAct = can_do('reservation/advance-deposit', 'edit')
        || can_do('reservation/advance-deposit', 'delete');
@endphp

@section('content')
    <x-page-header
        title="Advance Deposit Details"
        subtitle="Money taken before or during a stay. Every entry updates the balance on its reservation."
        :crumbs="['Home' => url('/'), 'Reservations' => route('reservation.index'), 'Advance Deposit']"
    >
        <x-slot:actions>
            @if ($editing)
                <a href="{{ route('reservation.advance-deposit') }}" class="nv-btn nv-btn-ghost">Close</a>
            @endif

            @canAdd('reservation/advance-deposit')
                <button type="reset" form="deposit-form" class="nv-btn nv-btn-outline">Reset</button>
                <button type="submit" form="deposit-form" class="nv-btn nv-btn-primary">
                    <x-icon name="check" /> Save
                </button>
            @endCanAdd
        </x-slot:actions>
    </x-page-header>

    <div class="nv-grid nv-grid-4">
        <x-stat label="Received" :value="'₹' . number_format($totals['received'], 0)" icon="wallet" tone="success" />
        <x-stat label="Refunded" :value="'₹' . number_format($totals['refunded'], 0)" icon="refresh" tone="danger" />
        <x-stat label="Net held" :value="'₹' . number_format($totals['net'], 0)" icon="credit-card" tone="info" />
        <x-stat label="Entries" :value="$totals['count']" icon="file" />
    </div>

    {{-- ── The form ─────────────────────────────────────────────────────── --}}
    @canAdd('reservation/advance-deposit')
        <div class="nv-mt">
            <x-card :title="$editing ? 'Edit entry' : 'New entry'"
                    subtitle="Card fields appear only when the pay type is a card.">
                @if ($editing)
                    <x-slot:actions>
                        <x-badge tone="warning">Editing #{{ $editing->id }}</x-badge>
                    </x-slot:actions>
                @endif

                @if ($errors->any())
                    <div style="margin-bottom:18px">
                        <x-alert tone="danger" title="Please fix {{ $errors->count() }} field(s)">
                            {{ $errors->first() }}
                        </x-alert>
                    </div>
                @endif

                <form method="POST" action="{{ $action }}" id="deposit-form" data-deposit-form>
                    @csrf
                    @if ($editing) @method('PUT') @endif

                    <div class="nv-form-grid nv-grid-4">
                        <x-field label="Payment" name="type" required help="Receipt takes money in; refund gives it back.">
                            <x-select name="type" :options="$types" :selected="$old('type', 'deposit')" />
                        </x-field>

                        <x-field label="Guest" name="source" help="Which list to pick the booking from.">
                            <x-select name="source" :options="$sources"
                                      :selected="old('source', 'all')" data-guest-source />
                        </x-field>

                        <x-field label="Guest Name" name="reservation_id" required wide>
                            <select name="reservation_id" id="reservation_id" class="nv-select"
                                    data-guest-picker required>
                                <option value="">Loading bookings…</option>
                            </select>
                            <p class="nv-help" data-guest-balance hidden></p>
                        </x-field>

                        <x-field label="Received Date" name="deposit_date" required>
                            <x-input name="deposit_date" type="date" :value="$depositDate"
                                     max="{{ now()->toDateString() }}" />
                        </x-field>

                        <x-field label="Pay Mode" name="pay_mode_id" required>
                            <x-select name="pay_mode_id" :options="$payModes->all()"
                                      :selected="$old('pay_mode_id')" placeholder="Select" />
                        </x-field>

                        <x-field label="Pay Details" name="pay_type" required help="How the money actually moved.">
                            <x-select name="pay_type" :options="$payTypes" :selected="$old('pay_type')"
                                      placeholder="Select" data-pay-type />
                        </x-field>

                        <x-field label="Received Amount" name="amount" required>
                            <x-input name="amount" type="number" step="0.01" min="0.01"
                                     :value="$old('amount')" placeholder="0.00" />
                        </x-field>

                        <x-field label="Card Type" name="card_type" data-card-field>
                            <x-select name="card_type" :options="$cardTypes" :selected="$old('card_type')"
                                      placeholder="Select" />
                        </x-field>

                        <x-field label="Name of Card" name="card_name" data-card-field>
                            <x-input name="card_name" :value="$old('card_name')" placeholder="As printed on the card" />
                        </x-field>

                        <x-field label="Card no. (last 4)" name="card_last4" data-card-field
                                 help="Only the last four digits are stored — never the full number.">
                            <x-input name="card_last4" inputmode="numeric" maxlength="4"
                                     :value="$old('card_last4')" placeholder="1234" />
                        </x-field>

                        <x-field label="Pan Card No." name="pan_no">
                            <x-input name="pan_no" maxlength="10" style="text-transform:uppercase"
                                     :value="$old('pan_no')" placeholder="ABCDE1234F" />
                        </x-field>

                        <x-field label="Reference no." name="reference_no" help="UTR, cheque or approval code.">
                            <x-input name="reference_no" :value="$old('reference_no')" />
                        </x-field>

                        <x-field label="Remark" name="remark" wide>
                            <x-textarea name="remark" :value="$old('remark')" rows="2" />
                        </x-field>
                    </div>

                    <div class="nv-actions" style="justify-content:flex-end">
                        @if ($editing)
                            <a href="{{ route('reservation.advance-deposit') }}" class="nv-btn nv-btn-ghost">Cancel</a>
                        @endif
                        <button type="reset" class="nv-btn nv-btn-outline">Reset</button>
                        <button type="submit" class="nv-btn nv-btn-primary">
                            <x-icon name="check" /> {{ $editing ? 'Save changes' : 'Save' }}
                        </button>
                    </div>
                </form>
            </x-card>
        </div>
    @endCanAdd

    {{-- ── The list ─────────────────────────────────────────────────────── --}}
    <div class="nv-mt">
        <x-card flush>
            <form method="GET" class="nv-toolbar">
                <div class="nv-field-search">
                    <x-icon name="search" />
                    <input type="search" name="q" value="{{ $filters['q'] }}" class="nv-input"
                           placeholder="Reservation no., guest or mobile…" />
                </div>

                <select name="type" class="nv-select" style="width:170px" onchange="this.form.submit()">
                    <option value="">All entries</option>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected($filters['type'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>

                <input type="date" name="from" value="{{ $filters['from'] }}" class="nv-input" style="width:160px" />
                <input type="date" name="to" value="{{ $filters['to'] }}" class="nv-input" style="width:160px" />

                <select name="per_page" class="nv-select" style="width:130px" onchange="this.form.submit()">
                    @foreach ([10, 25, 50, 100] as $size)
                        <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }} per page</option>
                    @endforeach
                </select>

                <button type="submit" class="nv-btn nv-btn-outline"><x-icon name="filter" /> Filter</button>

                @if (array_filter($filters))
                    <a href="{{ route('reservation.advance-deposit') }}" class="nv-btn nv-btn-ghost">Reset</a>
                @endif
            </form>

            @if ($deposits->count())
                <div class="nv-table-wrap">
                    <table class="nv-table nv-table-compact">
                        <thead>
                            <tr>
                                <th class="is-num">Sr. No</th>
                                <th>Party Name</th>
                                <th>Date &amp; Time</th>
                                <th class="is-num">Amount</th>
                                <th>Pay Mode</th>
                                <th>Pay Type</th>
                                <th>Card Type</th>
                                <th>Card Number</th>
                                <th>Pan Card No.</th>
                                <th>Remark</th>
                                @if ($canAct)
                                    <th class="is-end">Action</th>
                                @endif
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($deposits as $deposit)
                                <tr>
                                    <td class="is-num nv-muted">
                                        {{ $deposits->firstItem() + $loop->index }}
                                    </td>

                                    <td>
                                        <strong>{{ $deposit->reservation?->guest_name ?? 'Booking removed' }}</strong>
                                        <span class="nv-sub">
                                            @if ($deposit->reservation)
                                                @canView('reservation/booking-details')
                                                    <a href="{{ route('reservation.show', $deposit->reservation_id) }}"
                                                       class="nv-mono" style="color:var(--nv-primary)">{{ $deposit->reservation->reservation_no }}</a>
                                                @else
                                                    {{ $deposit->reservation->reservation_no }}
                                                @endCanView
                                            @endif
                                        </span>
                                    </td>

                                    <td class="nv-nowrap nv-muted">
                                        {{ $deposit->deposit_date->format('d M Y') }}
                                        <span class="nv-sub">{{ $deposit->created_at?->format('H:i') }}</span>
                                    </td>

                                    <td class="is-num nv-nowrap">
                                        <strong @style(['color:var(--nv-danger)' => $deposit->isRefund()])>
                                            {{ $deposit->isRefund() ? '− ' : '' }}₹{{ number_format($deposit->amount, 2) }}
                                        </strong>
                                    </td>

                                    <td>{{ $deposit->payMode?->name ?? '—' }}</td>
                                    <td>{{ $deposit->pay_type_label }}</td>
                                    <td>{{ $deposit->card_type ? $deposit->card_type_label : '—' }}</td>
                                    <td class="nv-mono nv-nowrap">{{ $deposit->card_number ?? '—' }}</td>
                                    <td class="nv-mono">{{ $deposit->pan_no ?: '—' }}</td>
                                    <td class="nv-muted">{{ $deposit->remark ?: '—' }}</td>

                                    @if ($canAct)
                                    <td class="is-end">
                                        <div class="nv-row-actions">
                                            @canEdit('reservation/advance-deposit')
                                                <a href="{{ route('reservation.advance-deposit', ['edit' => $deposit->id]) }}"
                                                   class="nv-btn nv-btn-ghost nv-btn-sm" aria-label="Edit entry {{ $deposit->id }}">
                                                    <x-icon name="pencil" />
                                                </a>
                                            @endCanEdit

                                            @canDelete('reservation/advance-deposit')
                                                <form method="POST"
                                                      action="{{ route('reservation.advance-deposit.destroy', $deposit) }}"
                                                      data-confirm="₹{{ number_format($deposit->amount, 2) }} against {{ $deposit->reservation?->reservation_no }} will be removed and the balance recalculated."
                                                      data-confirm-title="Delete entry?"
                                                      data-confirm-action="Delete entry">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="nv-btn nv-btn-ghost nv-btn-sm"
                                                            aria-label="Delete entry {{ $deposit->id }}">
                                                        <x-icon name="trash" />
                                                    </button>
                                                </form>
                                            @endCanDelete
                                        </div>
                                    </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="wallet" /></span>
                    <strong>Nothing recorded yet</strong>
                    <p>{{ array_filter($filters) ? 'Nothing matches these filters.' : 'Take a deposit with the form above and it appears here.' }}</p>
                </div>
            @endif

            <x-slot:footer>
                {{ $deposits->links() }}
            </x-slot:footer>
        </x-card>
    </div>
@endsection

@push('scripts')
@canAdd('reservation/advance-deposit')
<script>
(function () {
    'use strict';

    var form = document.querySelector('[data-deposit-form]');
    if (!form) return;

    var source = form.querySelector('[data-guest-source]');
    var picker = form.querySelector('[data-guest-picker]');
    var balance = form.querySelector('[data-guest-balance]');
    var payType = form.querySelector('[data-pay-type]');
    var cardFields = Array.prototype.slice.call(form.querySelectorAll('[data-card-field]'));

    var url = @json(route('reservation.advance-deposit.guests'));
    var needsCard = @json(array_values($needsCard));
    var chosen = @json((string) old('reservation_id', $editing?->reservation_id ?? ''));

    /* ── Card fields only for a card payment ────────────────────────────── */

    function syncCard() {
        var isCard = needsCard.indexOf(payType.value) !== -1;

        cardFields.forEach(function (field) {
            field.hidden = !isCard;

            if (!isCard) {
                field.querySelectorAll('input, select').forEach(function (el) { el.value = ''; });
            }
        });
    }

    payType.addEventListener('change', syncCard);
    syncCard();

    /* ── Guest Name list, filtered by the Guest dropdown ────────────────── */

    var bookings = [];

    function loadBookings() {
        picker.innerHTML = '<option value="">Loading bookings…</option>';

        var query = '?source=' + encodeURIComponent(source.value);

        // Keep the booking already on this entry in the list even if its stay
        // has ended — see the controller.
        if (chosen) {
            query += '&include=' + encodeURIComponent(chosen);
        }

        fetch(url + query, { headers: { Accept: 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : []; })
            .then(function (rows) {
                bookings = rows;
                picker.innerHTML = '<option value="">Select a booking…</option>';

                if (!rows.length) {
                    picker.innerHTML = '<option value="">No booking in this list</option>';
                    showBalance(null);

                    return;
                }

                rows.forEach(function (row) {
                    var option = document.createElement('option');
                    option.value = row.id;
                    option.textContent = row.label;
                    picker.appendChild(option);
                });

                // Keep the chosen booking selected across a failed save or an edit.
                if (chosen && picker.querySelector('option[value="' + chosen + '"]')) {
                    picker.value = chosen;
                }

                showBalance(current());
            })
            .catch(function () {
                picker.innerHTML = '<option value="">Could not load bookings</option>';
            });
    }

    function current() {
        return bookings.filter(function (b) { return String(b.id) === picker.value; })[0] || null;
    }

    var money = function (n) {
        return '₹' + Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    };

    function showBalance(row) {
        if (!row) {
            balance.hidden = true;

            return;
        }

        balance.hidden = false;
        balance.textContent =
            'Net ' + money(row.net) + ' · paid ' + money(row.paid) + ' · balance ' + money(row.balance);
    }

    source.addEventListener('change', function () {
        chosen = '';
        loadBookings();
    });

    picker.addEventListener('change', function () {
        chosen = picker.value;
        showBalance(current());
    });

    loadBookings();

    // A reset puts the lists back rather than leaving them stale.
    form.addEventListener('reset', function () {
        setTimeout(function () {
            chosen = '';
            syncCard();
            loadBookings();
        }, 0);
    });
})();
</script>
@endCanAdd
@endpush
