@extends('layouts.app')

@section('title', $doc->doc_no)

@php
    $money = fn ($n) => '₹' . number_format((float) $n, 2);
    $qty = fn ($n) => rtrim(rtrim(number_format((float) $n, 3), '0'), '.');

    $permission = $shape['permission'];
@endphp

@section('content')
    <x-page-header
        :title="$doc->doc_no"
        :subtitle="$shape['label'] . ' · ' . $doc->doc_date->format('d M Y') . ' · ' . $doc->party"
        :crumbs="['Home' => url('/'), 'Store', $shape['plural'] => route('store.docs', $kind), $doc->doc_no]"
    >
        <x-slot:actions>
            @if ($doc->isDraft() && $isOwner && can_do($permission, 'edit'))
                <a href="{{ route('store.docs.edit', ['kind' => $kind, 'doc' => $doc]) }}"
                   class="nv-btn nv-btn-outline">
                    <x-icon name="pencil" /> Edit
                </a>
            @endif

            @if ($kind === 'po' && in_array($doc->status, ['posted', 'partial'], true) && can_do('store/grn', 'add'))
                <a href="{{ route('store.docs.create', ['kind' => 'grn', 'from' => $doc->id]) }}"
                   class="nv-btn nv-btn-primary">
                    <x-icon name="package" /> Receive against this
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    {{-- ── Where it stands, and the one button that changes that ─────────── --}}
    <div class="nv-mt">
        <div class="nv-st-state">
            <div>
                <x-badge :tone="$doc->status_tone">{{ ucfirst($doc->status) }}</x-badge>

                <p>
                    @if ($doc->isDraft())
                        Nothing has moved yet. Posting is what puts this through the ledger
                        @if (! $doc->movesStock()) — for an order that means marking it placed, not moving stock @endif.
                    @elseif ($doc->isCancelled())
                        Cancelled. The rows it posted are still in the ledger, with matching rows the other way.
                    @elseif ($doc->status === 'partial')
                        Part of this order has arrived. It stays open for the rest.
                    @elseif ($doc->status === 'closed')
                        Everything ordered has been received.
                    @else
                        Posted {{ $doc->posted_at?->format('d M Y, h:i A') }}
                        @if ($doc->poster) by {{ $doc->poster->name }} @endif.
                    @endif
                </p>
            </div>

            <div class="nv-st-state-acts">
                @if ($doc->isDraft() && $isOwner && can_do($permission, 'add'))
                    <form method="POST" action="{{ route('store.docs.post', ['kind' => $kind, 'doc' => $doc]) }}"
                          data-confirm="{{ $doc->movesStock()
                              ? 'Post ' . $doc->doc_no . '? This moves stock and cannot be edited afterwards.'
                              : 'Place ' . $doc->doc_no . '?' }}"
                          data-confirm-title="{{ $shape['post'] }}"
                          data-confirm-action="{{ $shape['post'] }}">
                        @csrf
                        <button type="submit" class="nv-btn nv-btn-primary">
                            <x-icon name="check-circle" /> {{ $shape['post'] }}
                        </button>
                    </form>
                @endif

                @if (! $doc->isCancelled() && $isOwner && can_do($permission, 'delete'))
                    <form method="POST" action="{{ route('store.docs.cancel', ['kind' => $kind, 'doc' => $doc]) }}"
                          data-confirm="Cancel {{ $doc->doc_no }}? Anything it posted is reversed, not deleted."
                          data-confirm-title="Cancel this document">
                        @csrf @method('DELETE')
                        <button type="submit" class="nv-btn nv-btn-outline">
                            <x-icon name="x-circle" /> Cancel
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <div class="nv-grid nv-grid-main nv-mt">
        <div class="nv-stack">
            <x-card title="Items" :flush="true">
                <div class="nv-table-wrap">
                    <table class="nv-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th class="is-end">{{ $kind === 'adjustment' ? 'Change' : 'Quantity' }}</th>
                                @if (in_array($kind, ['po', 'transfer_out'], true))
                                    <th class="is-end">Received</th>
                                    <th class="is-end">Still due</th>
                                @endif
                                <th class="is-end">Rate</th>
                                <th class="is-end">Tax</th>
                                <th class="is-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($doc->items as $line)
                                <tr>
                                    <td>
                                        <strong>{{ $line->item?->name ?: 'Item removed' }}</strong>
                                        @if ($line->remark)
                                            <span class="nv-sub">{{ $line->remark }}</span>
                                        @endif
                                    </td>
                                    <td class="is-end">
                                        {{ $qty($line->qty) }} {{ $line->item?->unit }}
                                    </td>
                                    @if (in_array($kind, ['po', 'transfer_out'], true))
                                        <td class="is-end">{{ $qty($line->received_qty) }}</td>
                                        <td @class(['is-end', 'nv-st-due' => $line->pending > 0])>
                                            {{ $line->pending > 0 ? $qty($line->pending) : '—' }}
                                        </td>
                                    @endif
                                    <td class="is-end">{{ $money($line->rate) }}</td>
                                    <td class="is-end">
                                        {{ (float) $line->tax_amount > 0 ? $money($line->tax_amount) : '—' }}
                                        @if ((float) $line->tax_percent > 0)
                                            <span class="nv-sub">{{ rtrim(rtrim(number_format((float) $line->tax_percent, 2), '0'), '.') }}%</span>
                                        @endif
                                    </td>
                                    <td class="is-end"><strong>{{ $money($line->total_amount) }}</strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <x-slot:footer>
                    <div class="nv-st-totals is-wide">
                        <div><span>Sub total</span><b>{{ $money($doc->sub_total) }}</b></div>
                        <div><span>Tax</span><b>{{ $money($doc->tax_total) }}</b></div>
                        @if ((float) $doc->other_charges > 0)
                            <div><span>Other charges</span><b>{{ $money($doc->other_charges) }}</b></div>
                        @endif
                        <div class="is-net"><span>Net</span><b>{{ $money($doc->net_amount) }}</b></div>
                    </div>
                </x-slot:footer>
            </x-card>

            {{-- An order or an outgoing transfer, and everything received against it. --}}
            @if (in_array($kind, ['po', 'transfer_out'], true) && $doc->receipts->isNotEmpty())
                <x-card :title="$kind === 'po' ? 'Received against this order' : 'Received at the other end'" :flush="true">
                    <div class="nv-table-wrap">
                        <table class="nv-table nv-table-compact">
                            <thead>
                                <tr><th>Receipt</th><th>Date</th><th>Invoice</th><th>Status</th><th class="is-end">Value</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($doc->receipts as $receipt)
                                    <tr>
                                        <td>
                                            <a href="{{ route('store.docs.show', ['kind' => $receipt->kind, 'doc' => $receipt]) }}">
                                                {{ $receipt->doc_no }}
                                            </a>
                                        </td>
                                        <td>{{ $receipt->doc_date->format('d M Y') }}</td>
                                        <td>{{ $receipt->invoice_no ?: '—' }}</td>
                                        <td><x-badge :tone="$receipt->status_tone">{{ ucfirst($receipt->status) }}</x-badge></td>
                                        <td class="is-end">{{ $money($receipt->net_amount) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @endif
        </div>

        <div class="nv-stack">
            <x-card title="Details">
                <div class="nv-crm-facts">
                    <div><span>Number</span><b>{{ $doc->doc_no }}</b></div>
                    <div><span>Date</span><b>{{ $doc->doc_date->format('d M Y') }}</b></div>

                    @if ($doc->vendor)
                        <div><span>Supplier</span><b>{{ $doc->vendor->name }}</b></div>
                        @if ($doc->vendor->mobile)
                            <div><span>Contact</span><b>{{ $doc->vendor->mobile }}</b></div>
                        @endif
                    @endif

                    @if ($doc->kind === 'transfer_out' && $doc->toBranch)
                        <div><span>Sent to</span><b>{{ $doc->toBranch->branch_name }}</b></div>
                    @endif

                    @if ($doc->kind === 'transfer_in' && $doc->against?->branch)
                        <div><span>Received from</span><b>{{ $doc->against->branch->branch_name }}</b></div>
                    @endif

                    @if ($doc->department)
                        <div><span>Department</span><b>{{ $doc->department_label }}</b></div>
                    @endif

                    @if ($doc->issued_to)
                        <div><span>Issued to</span><b>{{ $doc->issued_to }}</b></div>
                    @endif

                    @if ($doc->invoice_no)
                        <div><span>Invoice</span><b>{{ $doc->invoice_no }}</b></div>
                    @endif

                    @if ($doc->invoice_date)
                        <div><span>Invoice date</span><b>{{ $doc->invoice_date->format('d M Y') }}</b></div>
                    @endif

                    @if ($doc->expected_on)
                        <div><span>Expected</span><b>{{ $doc->expected_on->format('d M Y') }}</b></div>
                    @endif

                    @if ($doc->against)
                        <div>
                            <span>Against</span>
                            <b>
                                <a href="{{ route('store.docs.show', ['kind' => $doc->against->kind, 'doc' => $doc->against]) }}">
                                    {{ $doc->against->doc_no }}
                                </a>
                            </b>
                        </div>
                    @endif
                </div>

                @if ($doc->remark)
                    <hr class="nv-hr" />
                    <p class="nv-muted">{{ $doc->remark }}</p>
                @endif
            </x-card>

            @if ($doc->movesStock())
                <x-card title="What posting does">
                    <p class="nv-st-explain">
                        @if (in_array($kind, ['grn', 'transfer_in'], true))
                            Each line goes onto the shelf and the item's average cost is worked out again:
                            held × old average, plus received × paid, over the new total. That is what a
                            month of the same onions at four prices comes out at.
                        @elseif ($kind === 'adjustment')
                            Each line moves the balance by the amount typed — positive adds, negative takes
                            away — and leaves a ledger row saying a count corrected it.
                        @else
                            Each line comes off the shelf <strong>at the average rate</strong>, not at what it
                            was last bought for. Issuing cannot change what the stock still on the shelf cost.
                        @endif
                    </p>
                </x-card>
            @endif
        </div>
    </div>
@endsection
