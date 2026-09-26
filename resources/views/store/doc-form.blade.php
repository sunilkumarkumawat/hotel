@extends('layouts.app')

@section('title', ($doc ? 'Edit ' : 'New ') . strtolower($shape['label']))

@php
    /*
        One form for five kinds of document. Which fields appear is decided by
        the shape, not by the person filling it in — a wastage note has no
        supplier and a purchase order has no invoice number, and offering them
        would only invite somebody to fill them in.
    */
    $isVendor = $shape['party'] === 'vendor';
    $isDept = $shape['party'] === 'department';
    $isOutlet = $shape['party'] === 'outlet';

    // Whichever of the two this document is receiving against — a PO for a
    // GRN, a transfer_out for a transfer_in. Never both at once.
    $against = $po ?: ($transferOut ?? null);

    // After a failed save, the rows that came back — so an error lands on the
    // line it belongs to. Otherwise whatever the document or the against-doc
    // gave us, and failing that three blank lines.
    $rows = old('lines', $prefill ?: [[], [], []]);

    $boot = [
        'items' => $items->map(fn ($i) => [
            'id' => (int) $i->id,
            'name' => $i->name,
            'unit' => $i->unit,
            'code' => $i->code,
            'rate' => (float) $i->avg_rate,
            'last' => (float) $i->last_rate,
            'tax' => (float) $i->tax_percent,
            'stock' => (float) $i->current_qty,
        ])->values()->all(),
        'kind' => $kind,
        'signed' => $kind === 'adjustment',
    ];
@endphp

@section('content')
    <x-page-header
        :title="($doc ? 'Edit ' : 'New ') . strtolower($shape['label'])"
        :subtitle="$shape['blurb']"
        :crumbs="['Home' => url('/'), 'Store', $shape['plural'] => route('store.docs', $kind), $doc?->doc_no ?: 'New']"
    >
        <x-slot:actions>
            <a href="{{ route('store.docs', $kind) }}" class="nv-btn nv-btn-outline">
                <x-icon name="chevron-left" /> Back
            </a>
        </x-slot:actions>
    </x-page-header>

    @if ($errors->any())
        <div class="nv-mt">
            <x-alert tone="danger" title="Please fix {{ $errors->count() }} thing(s)">{{ $errors->first() }}</x-alert>
        </div>
    @endif

    @if ($po)
        <div class="nv-mt">
            <x-alert tone="info" title="Receiving against {{ $po->doc_no }}">
                The lines below are what is still outstanding on that order. Change any quantity that arrived
                short — a part delivery is the normal case, and the order stays open for the rest.
            </x-alert>
        </div>
    @elseif ($transferOut ?? null)
        <div class="nv-mt">
            <x-alert tone="info"
                     title="Receiving against {{ $transferOut->doc_no }} from {{ $transferOut->branch?->branch_name }}">
                The lines below are what that outlet sent and is still outstanding here. Change any quantity
                that arrived short — the transfer stays open for the rest.
            </x-alert>
        </div>
    @endif

    <form method="POST"
          action="{{ $doc ? route('store.docs.update', ['kind' => $kind, 'doc' => $doc]) : route('store.docs.store', $kind) }}"
          data-store-doc>
        @csrf
        @if ($doc) @method('PUT') @endif
        @if ($against) <input type="hidden" name="against_id" value="{{ $against->id }}" /> @endif

        <div class="nv-mt">
            <x-card :title="$doc ? $doc->doc_no : 'Number ' . $nextNo">
                <div class="nv-form-grid">
                    <x-field label="Date" name="doc_date" required>
                        <x-input type="date" name="doc_date"
                                 :value="old('doc_date', $doc?->doc_date?->toDateString() ?: today()->toDateString())"
                                 required />
                    </x-field>

                    @if ($isVendor)
                        <x-field label="Supplier" name="vendor_id">
                            <x-select name="vendor_id" :options="$vendors->all()"
                                      :selected="old('vendor_id', $doc?->vendor_id)" placeholder="Choose…" />
                        </x-field>
                    @endif

                    @if ($isDept)
                        <x-field label="Department" name="department" required>
                            <select name="department" class="nv-select" required>
                                <option value="">Choose…</option>
                                @foreach ($departments as $key => $label)
                                    <option value="{{ $key }}" @selected(old('department', $doc?->department) === $key)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </x-field>

                        <x-field label="Issued to" name="issued_to" help="The person who came for it.">
                            <x-input name="issued_to" :value="old('issued_to', $doc?->issued_to)" />
                        </x-field>
                    @endif

                    @if ($isOutlet && $kind === 'transfer_out')
                        <x-field label="Send to" name="to_branch_id" required>
                            <select name="to_branch_id" class="nv-select" required>
                                <option value="">Choose an outlet…</option>
                                @foreach ($branches as $id => $label)
                                    <option value="{{ $id }}"
                                            @selected(old('to_branch_id', $doc?->to_branch_id) == $id)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </x-field>
                    @endif

                    @if ($kind === 'grn')
                        <x-field label="Invoice number" name="invoice_no">
                            <x-input name="invoice_no" :value="old('invoice_no', $doc?->invoice_no)" />
                        </x-field>

                        <x-field label="Invoice date" name="invoice_date">
                            <x-input type="date" name="invoice_date"
                                     :value="old('invoice_date', $doc?->invoice_date?->toDateString())" />
                        </x-field>

                        @if (! $po && $openOrders->isNotEmpty())
                            <x-field label="Against an order" name="against_id"
                                     help="Optional. Picking one closes it off as it is received.">
                                <select name="against_id" class="nv-select">
                                    <option value="">Not against an order</option>
                                    @foreach ($openOrders as $order)
                                        <option value="{{ $order->id }}" @selected(old('against_id', $doc?->against_id) == $order->id)>
                                            {{ $order->doc_no }} — {{ $order->doc_date->format('d M Y') }}
                                        </option>
                                    @endforeach
                                </select>
                            </x-field>
                        @endif
                    @endif

                    @if ($kind === 'po')
                        <x-field label="Expected on" name="expected_on">
                            <x-input type="date" name="expected_on"
                                     :value="old('expected_on', $doc?->expected_on?->toDateString())" />
                        </x-field>
                    @endif

                    <x-field label="Note" name="remark" :wide="true">
                        <x-textarea name="remark" :value="old('remark', $doc?->remark)" rows="2" />
                    </x-field>
                </div>
            </x-card>
        </div>

        {{-- ── The lines ─────────────────────────────────────────────────── --}}
        <div class="nv-mt">
            <x-card title="Items" :flush="true">
                <div class="nv-st-scan">
                    {{--
                        A scanner is a keyboard as far as the browser knows —
                        it types the code and finishes with Enter. The form
                        below already submits the whole document, so Enter
                        here is caught by store-lines.js instead of posting
                        early; it drops the code into an empty line, or adds
                        one, and leaves the quantity box focused.

                        Plain nv-input rather than the .nv-field-search +
                        icon pairing used elsewhere — that icon's sizing is
                        scoped to ".nv-toolbar"/".nv-till-find" in app.css, and
                        this card is neither, so borrowing it here would draw
                        an unstyled, full-size icon instead of a small one.
                    --}}
                    <input type="text" class="nv-input" data-barcode-scan placeholder="Scan a barcode…"
                           aria-label="Scan a barcode" autocomplete="off" style="max-width:320px" />
                    <span class="nv-sub" data-barcode-msg></span>
                </div>

                <div class="nv-table-wrap">
                    <table class="nv-table nv-st-lines" data-lines>
                        <thead>
                            <tr>
                                <th style="min-width:220px">Item</th>
                                <th style="width:120px" class="is-end">
                                    {{ $kind === 'adjustment' ? 'Change by' : 'Quantity' }}
                                </th>
                                <th style="width:120px" class="is-end">Rate</th>
                                <th style="width:96px" class="is-end">Tax %</th>
                                <th style="width:130px" class="is-end">Amount</th>
                                <th style="width:44px">&nbsp;</th>
                            </tr>
                        </thead>

                        <tbody data-line-body>
                            @foreach ($rows as $i => $row)
                                <tr data-line>
                                    <td>
                                        <select name="lines[{{ $i }}][store_item_id]" class="nv-select" data-item>
                                            <option value="">Choose an item…</option>
                                            @foreach ($items as $item)
                                                <option value="{{ $item->id }}"
                                                        @selected(($row['store_item_id'] ?? null) == $item->id)>
                                                    {{ $item->name }} ({{ $item->unit }}){{ $item->code ? ' · ' . $item->code : '' }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <span class="nv-st-stock" data-stock></span>
                                    </td>
                                    <td>
                                        <input type="number" step="0.001" class="nv-input is-num" data-qty
                                               name="lines[{{ $i }}][qty]" value="{{ $row['qty'] ?? '' }}" />
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" min="0" class="nv-input is-num" data-rate
                                               name="lines[{{ $i }}][rate]" value="{{ $row['rate'] ?? '' }}" />
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" min="0" max="100" class="nv-input is-num"
                                               data-tax name="lines[{{ $i }}][tax_percent]"
                                               value="{{ $row['tax_percent'] ?? '' }}" />
                                    </td>
                                    <td class="is-end"><span data-amount>0.00</span></td>
                                    <td>
                                        <button type="button" class="nv-btn nv-btn-sm nv-btn-ghost nv-hidden"
                                                data-line-remove title="Remove this line">
                                            <x-icon name="x" />
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- The blank row every new line is cloned from. --}}
                <template data-line-template>
                    <tr data-line>
                        <td>
                            <select name="lines[__i__][store_item_id]" class="nv-select" data-item>
                                <option value="">Choose an item…</option>
                                @foreach ($items as $item)
                                    <option value="{{ $item->id }}">
                                        {{ $item->name }} ({{ $item->unit }}){{ $item->code ? ' · ' . $item->code : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <span class="nv-st-stock" data-stock></span>
                        </td>
                        <td><input type="number" step="0.001" class="nv-input is-num" data-qty name="lines[__i__][qty]" /></td>
                        <td><input type="number" step="0.01" min="0" class="nv-input is-num" data-rate name="lines[__i__][rate]" /></td>
                        <td><input type="number" step="0.01" min="0" max="100" class="nv-input is-num" data-tax name="lines[__i__][tax_percent]" /></td>
                        <td class="is-end"><span data-amount>0.00</span></td>
                        <td>
                            <button type="button" class="nv-btn nv-btn-sm nv-btn-ghost" data-line-remove>
                                <x-icon name="x" />
                            </button>
                        </td>
                    </tr>
                </template>

                <x-slot:footer>
                    <div class="nv-st-foot">
                        <button type="button" class="nv-btn nv-btn-sm nv-btn-outline" data-line-add>
                            <x-icon name="plus" /> Add a line
                        </button>

                        <div class="nv-st-totals">
                            <div><span>Sub total</span><b data-sub>0.00</b></div>
                            <div><span>Tax</span><b data-tax-total>0.00</b></div>
                            <div class="is-net"><span>Net</span><b data-net>0.00</b></div>
                        </div>
                    </div>
                </x-slot:footer>
            </x-card>
        </div>

        <div class="nv-actions" style="justify-content:flex-end;margin-top:22px">
            <a href="{{ route('store.docs', $kind) }}" class="nv-btn nv-btn-outline">Cancel</a>
            <button type="submit" name="commit" value="draft" class="nv-btn nv-btn-outline">
                <x-icon name="check" /> Save as draft
            </button>
            <button type="submit" name="commit" value="post" class="nv-btn nv-btn-primary">
                <x-icon name="check" /> Save &amp; {{ lcfirst($shape['post']) }}
            </button>
        </div>

        <p class="nv-help" style="text-align:right;margin-top:8px">
            "Save &amp; {{ lcfirst($shape['post']) }}" does both in one step — for when you already know
            this is right. "Save as draft" leaves it for you to check before posting separately.
        </p>
    </form>
@endsection

@push('scripts')
    <script>window.storeDoc = @json($boot);</script>
    <script src="{{ asset('js/store-lines.js') }}?v={{ filemtime(public_path('js/store-lines.js')) }}"></script>
@endpush
