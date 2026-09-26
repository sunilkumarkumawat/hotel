@extends('layouts.print')

@section('title', ($invoice->invoice_no ?? $order->order_no) . ' — ' . ($outlet->name ?? 'Bill'))

@php
    use App\Models\Pos\PosOrder;

    $taxed = $lines->where('tax_percent', '>', 0)->groupBy(fn ($line) => (float) $line->tax_percent);
@endphp

@section('content')
    {{--
        A thermal receipt, not an A4 invoice. The width, the margin and the
        header font all come off the outlet, because that is where the hotel
        was asked what its printer is — see Setup → Outlets → Print Details.
    --}}
    <div class="pr-bill" style="--roll:{{ (int) ($outlet->page_width ?? 80) }}mm;--edge:{{ (int) ($outlet->print_margin ?? 6) }}mm">

        <div class="pr-bill-head"
             style="font-family:{{ $outlet->header_font ?? 'Arial' }};
                    {{ ($outlet->header_font_size ?? 0) > 0 ? 'font-size:' . (int) $outlet->header_font_size . 'px;' : '' }}
                    font-weight:{{ ($outlet->header_font_bold ?? true) ? '700' : '400' }}">

            @if ($outlet?->logoUrl())
                <img src="{{ $outlet->logoUrl() }}" alt="" class="pr-bill-logo" />
            @endif

            <strong>{{ $outlet->print_header ?: ($outlet->name ?? config('app.name')) }}</strong>

            @if ($outlet?->address_line)
                <span>{{ $outlet->address_line }}</span>
            @endif

            @if ($outlet?->phone1)
                <span>Ph {{ $outlet->phone1 }}{{ $outlet->phone2 ? ', ' . $outlet->phone2 : '' }}</span>
            @endif

            @if ($outlet?->gst_no)
                <span>GSTIN {{ $outlet->gst_no }}</span>
            @endif
        </div>

        <div class="pr-bill-title">{{ $outlet->tax_invoice_name ?: 'Tax Invoice' }}</div>

        <div class="pr-bill-meta">
            <span>Bill</span><b>{{ $invoice->invoice_no ?? '— not raised —' }}</b>
            <span>Date</span><b>{{ ($invoice->invoice_at ?? now())->format('d/m/Y h:i A') }}</b>
            <span>{{ $order->order_type === 'room_service' ? 'Room' : 'Table' }}</span><b>{{ $order->table_no ?: '—' }}</b>
            <span>Order</span><b>{{ $order->order_no }}</b>
            @if ($order->steward)
                <span>Steward</span><b>{{ $order->steward->name }}</b>
            @endif
            <span>Covers</span><b>{{ $order->pax }}</b>
            @if ($order->guest_name)
                <span>Guest</span><b>{{ $order->guest_name }}</b>
            @endif
        </div>

        <table class="pr-bill-lines">
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="is-num">Qty</th>
                    <th class="is-num">Rate</th>
                    <th class="is-num">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($lines as $line)
                    <tr>
                        <td>
                            {{ $line->item_name }}
                            @if ($line->has_modifiers && $line->modifiers->isNotEmpty())
                                <i>{{ $line->modifiers->pluck('name')->implode(', ') }}</i>
                            @endif
                            @if ($line->is_nc)
                                <i>(no charge)</i>
                            @endif
                            @if ($line->remark)
                                <i>{{ $line->remark }}</i>
                            @endif
                        </td>
                        <td class="is-num">{{ rtrim(rtrim(number_format((float) $line->qty, 2, '.', ''), '0'), '.') }}</td>
                        <td class="is-num">{{ number_format((float) $line->price, 2) }}</td>
                        <td class="is-num">{{ number_format((float) $line->amount, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="pr-bill-sums">
            <div><span>Sub total</span><b>{{ number_format((float) $order->sub_total, 2) }}</b></div>

            @if ((float) $order->discount_total > 0)
                <div><span>Discount</span><b>− {{ number_format((float) $order->discount_total, 2) }}</b></div>
            @endif

            @if ((float) $order->service_charge > 0)
                <div><span>Service charge</span><b>{{ number_format((float) $order->service_charge, 2) }}</b></div>
            @endif

            {{-- Tax broken out by rate, which is what a GST invoice has to show. --}}
            @foreach ($taxed as $percent => $group)
                <div>
                    <span>GST {{ rtrim(rtrim(number_format((float) $percent, 2), '0'), '.') }}%</span>
                    <b>{{ number_format($group->sum(fn ($line) => (float) $line->tax_amount), 2) }}</b>
                </div>
            @endforeach

            @if ((float) $order->round_off != 0)
                <div><span>Round off</span><b>{{ number_format((float) $order->round_off, 2) }}</b></div>
            @endif

            <div class="is-net"><span>Total</span><b>₹ {{ number_format((float) $order->net_amount, 2) }}</b></div>
        </div>

        @if ($payments->isNotEmpty())
            <div class="pr-bill-paid">
                @foreach ($payments as $payment)
                    <div>
                        <span>{{ $payment->payMode?->name ?: 'Paid' }}{{ $payment->reference_no ? ' · ' . $payment->reference_no : '' }}</span>
                        <b>{{ number_format((float) $payment->amount, 2) }}</b>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($invoice?->folio_charge_id)
            <p class="pr-bill-note">Signed to room {{ $order->table_no }} — payable at checkout.</p>
        @endif

        @if ($order->is_complimentary)
            <p class="pr-bill-note">No charge{{ $order->ncType ? ' · ' . $order->ncType->name : '' }}.</p>
        @endif

        @if ($outlet?->guest_signature_print)
            <div class="pr-bill-sign">
                <span></span>
                <small>Guest signature</small>
            </div>
        @endif

        <p class="pr-bill-foot">
            {{ $outlet->print_footer ?: 'Thank you, please visit again.' }}
        </p>

        @if (($invoice->print_count ?? 0) > 1)
            <p class="pr-bill-note">Reprint #{{ $invoice->print_count }}</p>
        @endif
    </div>
@endsection

@push('styles')
    <style>
        /*
         * Receipt-only, and deliberately inline rather than in print.css: the
         * roll width and the margin are per-outlet, and everything below is
         * scoped to .pr-bill so it cannot reach the registration cards and
         * folios that share this layout.
         */
        .pr-bill { width: var(--roll); max-width: 100%; margin: 0 auto; padding: var(--edge); font-size: 12px; }
        .pr-bill-head { text-align: center; display: grid; gap: 2px; margin-bottom: 8px; }
        .pr-bill-head strong { font-size: 1.25em; line-height: 1.25; }
        .pr-bill-head span { font-size: 11px; font-weight: 400; }
        .pr-bill-logo { max-width: 60%; max-height: 60px; margin: 0 auto 4px; display: block; }
        .pr-bill-title { text-align: center; font-weight: 700; letter-spacing: .08em; text-transform: uppercase;
                         border-top: 1px dashed #000; border-bottom: 1px dashed #000; padding: 4px 0; margin-bottom: 6px; }
        .pr-bill-meta { display: grid; grid-template-columns: auto 1fr; gap: 1px 8px; font-size: 11px; margin-bottom: 6px; }
        .pr-bill-meta b { text-align: right; }
        .pr-bill-lines { width: 100%; border-collapse: collapse; font-size: 11px; }
        .pr-bill-lines th { text-align: left; border-top: 1px dashed #000; border-bottom: 1px dashed #000; padding: 3px 2px; }
        .pr-bill-lines td { padding: 2px; vertical-align: top; }
        .pr-bill-lines .is-num { text-align: right; white-space: nowrap; }
        .pr-bill-lines i { display: block; font-size: 10px; font-style: italic; }
        .pr-bill-sums { border-top: 1px dashed #000; margin-top: 4px; padding-top: 4px; font-size: 11px; }
        .pr-bill-sums div { display: flex; justify-content: space-between; gap: 12px; padding: 1px 2px; }
        .pr-bill-sums .is-net { border-top: 1px solid #000; margin-top: 3px; padding-top: 4px; font-size: 13px; font-weight: 700; }
        .pr-bill-paid { border-top: 1px dashed #000; margin-top: 4px; padding-top: 4px; font-size: 11px; }
        .pr-bill-paid div { display: flex; justify-content: space-between; gap: 12px; padding: 1px 2px; }
        .pr-bill-note { text-align: center; font-size: 10px; margin: 6px 0 0; }
        .pr-bill-sign { margin-top: 22px; text-align: center; }
        .pr-bill-sign span { display: block; border-top: 1px solid #000; width: 70%; margin: 0 auto 2px; }
        .pr-bill-sign small { font-size: 10px; }
        .pr-bill-foot { text-align: center; font-size: 11px; margin: 10px 0 0; }

        @media print {
            /* The sheet wrapper is for A4 documents; a receipt sets its own width. */
            .pr-sheet { padding: 0; box-shadow: none; width: auto; }
            @page { margin: 0; }
        }
    </style>
@endpush
