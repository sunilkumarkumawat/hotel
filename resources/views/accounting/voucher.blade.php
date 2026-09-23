@extends('layouts.app')

@section('title', $kind['title'])

@php
    /*
        One view, six screens. The only things that move are which shape of
        entry grid is drawn and what the two sides are called — everything
        else, including the list underneath, is the same question asked of a
        different `voucher_type`.
    */
    $isJournal = $kind['shape'] === 'journal';
    $isContra = $kind['shape'] === 'contra';
    $hasParties = $kind['party'] !== null;
@endphp

@section('content')
    <x-page-header
        :title="$kind['title']"
        :subtitle="$kind['subtitle']"
        :crumbs="['Home' => url('/'), 'Accounting' => route('accounting.day-book'), $kind['title']]"
    />

    @canAdd($kind['url'])
        <div class="nv-mt">
            <x-card :title="'New ' . $kind['title']" :subtitle="'Next number: ' . $nextNo">
                @if ($hasParties && empty($partyLedgers))
                    <x-alert tone="warning" title="No ledgers to pick from">
                        Nothing is filed under <strong>{{ $kind['party'] }}</strong> yet. Add the group under
                        Accounting → Group, then file the ledgers under it — this screen deliberately shows
                        only that group rather than every ledger in the system.
                    </x-alert>
                @else
                    <form method="POST" action="{{ route('accounting.' . $slug . '.store') }}" data-voucher-form>
                        @csrf

                        <div class="nv-grid nv-grid-4">
                            <x-field label="Date" name="voucher_date" required>
                                <x-input name="voucher_date" type="date" :value="old('voucher_date', today()->toDateString())" />
                            </x-field>

                            <x-field label="Reference" name="reference_no">
                                <x-input name="reference_no" placeholder="Cheque no., bill no." />
                            </x-field>

                            @unless ($isJournal)
                                <x-field :label="$kind['cash_label']" name="cash_ledger_id" required
                                         help="Only ledgers marked as the cash box or a bank account.">
                                    <select name="cash_ledger_id" id="cash_ledger_id" class="nv-select">
                                        <option value="">Pick one</option>
                                        @foreach ($cashLedgers as $ledger)
                                            <option value="{{ $ledger->id }}" @selected(old('cash_ledger_id') == $ledger->id)>
                                                {{ $ledger->display_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </x-field>
                            @endunless

                            @if ($isContra)
                                <x-field :label="$kind['party_label']" name="to_ledger_id" required>
                                    <select name="to_ledger_id" id="to_ledger_id" class="nv-select">
                                        <option value="">Pick one</option>
                                        @foreach ($cashLedgers as $ledger)
                                            <option value="{{ $ledger->id }}" @selected(old('to_ledger_id') == $ledger->id)>
                                                {{ $ledger->display_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </x-field>

                                <x-field label="Amount" name="amount" required>
                                    <x-input name="amount" type="number" step="0.01" min="0" :value="old('amount', 0)" />
                                </x-field>
                            @endif

                            <x-field label="Narration" name="narration" wide>
                                <x-input name="narration" placeholder="What this voucher is for" :value="old('narration')" />
                            </x-field>
                        </div>

                        @unless ($isContra)
                            <div class="nv-table-wrap nv-mt">
                                <table class="nv-table nv-led-lines">
                                    <thead>
                                        <tr>
                                            <th style="width:36%">{{ $kind['party_label'] }}</th>
                                            @if ($isJournal)
                                                <th style="width:150px" class="is-num">Debit</th>
                                                <th style="width:150px" class="is-num">Credit</th>
                                            @else
                                                <th style="width:170px" class="is-num">Amount</th>
                                            @endif
                                            <th>Narration</th>
                                            <th style="width:56px"></th>
                                        </tr>
                                    </thead>
                                    <tbody data-voucher-rows>
                                        @for ($i = 0; $i < 3; $i++)
                                            <tr data-voucher-row>
                                                <td>
                                                    <select name="lines[{{ $i }}][ledger_id]" class="nv-select nv-input-sm" data-line-ledger>
                                                        <option value="">—</option>
                                                        @foreach (($hasParties ? $partyLedgers : $allLedgers) as $id => $name)
                                                            <option value="{{ $id }}">
                                                                {{ $name }}@isset($partyBalances[$id])
                                                                    @if (abs($partyBalances[$id]) >= 0.005)
                                                                        · ₹ {{ number_format($partyBalances[$id], 2) }}
                                                                    @endif
                                                                @endisset
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </td>

                                                @if ($isJournal)
                                                    <td><input type="number" step="0.01" min="0" class="nv-input nv-input-sm is-num"
                                                               name="lines[{{ $i }}][debit]" data-line-debit /></td>
                                                    <td><input type="number" step="0.01" min="0" class="nv-input nv-input-sm is-num"
                                                               name="lines[{{ $i }}][credit]" data-line-credit /></td>
                                                @else
                                                    <td><input type="number" step="0.01" min="0" class="nv-input nv-input-sm is-num"
                                                               name="lines[{{ $i }}][amount]" data-line-amount /></td>
                                                @endif

                                                <td><input type="text" class="nv-input nv-input-sm"
                                                           name="lines[{{ $i }}][narration]" /></td>

                                                <td>
                                                    <button type="button" class="nv-icon-btn is-danger" data-remove-line title="Remove">
                                                        <x-icon name="trash" />
                                                    </button>
                                                </td>
                                            </tr>
                                        @endfor
                                    </tbody>

                                    <tfoot>
                                        <tr>
                                            <th>Total</th>
                                            @if ($isJournal)
                                                <th class="is-num" data-total-debit>0.00</th>
                                                <th class="is-num" data-total-credit>0.00</th>
                                            @else
                                                <th class="is-num" data-total-amount>0.00</th>
                                            @endif
                                            <th colspan="2" data-balance-note></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <template data-line-template>
                                <tr data-voucher-row>
                                    <td>
                                        <select name="lines[__INDEX__][ledger_id]" class="nv-select nv-input-sm" data-line-ledger>
                                            <option value="">—</option>
                                            @foreach (($hasParties ? $partyLedgers : $allLedgers) as $id => $name)
                                                <option value="{{ $id }}">{{ $name }}</option>
                                            @endforeach
                                        </select>
                                    </td>

                                    @if ($isJournal)
                                        <td><input type="number" step="0.01" min="0" class="nv-input nv-input-sm is-num"
                                                   name="lines[__INDEX__][debit]" data-line-debit /></td>
                                        <td><input type="number" step="0.01" min="0" class="nv-input nv-input-sm is-num"
                                                   name="lines[__INDEX__][credit]" data-line-credit /></td>
                                    @else
                                        <td><input type="number" step="0.01" min="0" class="nv-input nv-input-sm is-num"
                                                   name="lines[__INDEX__][amount]" data-line-amount /></td>
                                    @endif

                                    <td><input type="text" class="nv-input nv-input-sm" name="lines[__INDEX__][narration]" /></td>

                                    <td>
                                        <button type="button" class="nv-icon-btn is-danger" data-remove-line title="Remove">
                                            <x-icon name="trash" />
                                        </button>
                                    </td>
                                </tr>
                            </template>

                            <div class="nv-actions" style="justify-content:space-between;margin-top:12px">
                                <button type="button" class="nv-btn nv-btn-soft nv-btn-sm" data-add-line>
                                    <x-icon name="plus" /> Another line
                                </button>

                                <button type="submit" class="nv-btn nv-btn-primary">
                                    <x-icon name="check" /> Post it
                                </button>
                            </div>
                        @else
                            <div class="nv-actions" style="justify-content:flex-end;margin-top:12px">
                                <button type="submit" class="nv-btn nv-btn-primary">
                                    <x-icon name="check" /> Post it
                                </button>
                            </div>
                        @endunless
                    </form>

                    <p class="nv-help">
                        @if ($isJournal)
                            Debit must equal credit to the paisa. A voucher that does not balance is refused
                            outright and nothing is saved — the running total above tells you before you press.
                        @elseif ($isContra)
                            Both sides have to be a ledger marked as the cash box or a bank account. Anything
                            else is a Journal wearing a disguise, and it is refused.
                        @else
                            The {{ strtolower($kind['cash_label']) }} side takes the total of the lines
                            automatically, so the two sides cannot disagree.
                        @endif
                        Tax is never added here: GST on a bill is a line of the voucher, typed by whoever is
                        reading the bill.
                    </p>
                @endif
            </x-card>
        </div>
    @endCanAdd

    {{-- ── What has been posted ───────────────────────────────────────────── --}}
    <div class="nv-mt">
        <x-card>
            <form method="GET" class="nv-toolbar">
                <div class="nv-field-search">
                    <x-icon name="search" />
                    <input type="search" name="q" value="{{ $q }}" class="nv-input" placeholder="Voucher no., reference…" />
                </div>

                <input type="date" name="from" value="{{ $from }}" class="nv-input" style="width:160px" aria-label="From" />
                <input type="date" name="to" value="{{ $to }}" class="nv-input" style="width:160px" aria-label="To" />

                <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="filter" /> Show</button>
                <a href="{{ route('accounting.' . $slug) }}" class="nv-btn nv-btn-ghost">Reset</a>

                <span class="nv-muted" style="margin-left:auto">
                    {{ $rows->total() }} voucher(s) · ₹ {{ number_format($total, 2) }}
                </span>
            </form>
        </x-card>
    </div>

    <div class="nv-mt">
        <x-card flush>
            @if ($rows->isEmpty())
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="file" /></span>
                    <strong>Nothing posted in this range</strong>
                    <p>Widen the dates, or post the first one above.</p>
                </div>
            @else
                <div class="nv-table-wrap">
                    <table class="nv-table">
                        <thead>
                            <tr>
                                <th>Voucher</th>
                                <th>Date</th>
                                <th>Against</th>
                                <th>Narration</th>
                                <th class="is-num">Amount</th>
                                <th style="width:60px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    <td>
                                        <strong>{{ $row->voucher_no }}</strong>
                                        @if ($row->reference_no)
                                            <span class="nv-sub">{{ $row->reference_no }}</span>
                                        @endif
                                    </td>

                                    <td>{{ $row->voucher_date->format('d M Y') }}</td>

                                    <td>
                                        @foreach ($row->entries as $entry)
                                            <span class="nv-led-entry">
                                                {{ $entry->ledger?->name ?? 'Ledger #' . $entry->ledger_id }}
                                                <em>{{ (float) $entry->debit > 0 ? 'Dr' : 'Cr' }}
                                                    {{ number_format((float) $entry->debit > 0 ? (float) $entry->debit : (float) $entry->credit, 2) }}</em>
                                            </span>
                                        @endforeach
                                    </td>

                                    <td>{{ $row->narration ?: '—' }}</td>

                                    <td class="is-num">₹ {{ number_format((float) $row->amount, 2) }}</td>

                                    <td>
                                        @canDelete($kind['url'])
                                            <form method="POST" action="{{ route('accounting.' . $slug . '.cancel', $row->id) }}"
                                                  data-confirm="Cancel {{ $row->voucher_no }}? It keeps its number and stops counting anywhere."
                                                  data-confirm-title="Cancel voucher">
                                                @csrf
                                                <button type="submit" class="nv-icon-btn is-danger" title="Cancel">
                                                    <x-icon name="x-circle" />
                                                </button>
                                            </form>
                                        @endCanDelete
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
@endsection

@push('scripts')
    <script src="{{ asset('js/accounting.js') }}?v={{ file_exists(public_path('js/accounting.js')) ? filemtime(public_path('js/accounting.js')) : time() }}" defer></script>
@endpush
