@extends('layouts.print')

@section('title', 'Group bill ' . $groupNo)

@php
    $first = $bills->first();
    $guest = $first->checkIn?->guest_name ?: ($first->guest?->name ?? 'Guest');
@endphp

@section('content')
    {{--
        One sheet, every room.

        The family reads the grand total at the bottom; the hotel's accountant
        reads the numbered bill above each room's lines. Both are on the same
        piece of paper, which is the whole point — nobody wants five sheets for
        one family, and nobody can file one sheet with no bill numbers on it.
    --}}
    <div class="pr-head">
        @if ($branch?->logo)
            <img src="{{ asset('storage/' . $branch->logo) }}" alt="" class="pr-logo" />
        @endif

        <div>
            <p class="pr-hotel-name">{{ $branch?->legal_name ?: ($branch?->branch_name ?: config('app.name')) }}</p>
            @if ($branch?->address)
                <p class="pr-hotel-line">{{ $branch->address }}</p>
            @endif
            @if ($branch?->gst_no)
                <p class="pr-hotel-line">GSTIN {{ $branch->gst_no }}</p>
            @endif
        </div>
    </div>

    <h1 class="pr-title">Bill — {{ $bills->count() }} rooms</h1>

    <div class="pr-fields">
        <div class="pr-col">
            <div class="pr-line"><span class="pr-line-label">Guest</span><span class="pr-line-value">{{ $guest }}</span></div>
            <div class="pr-line"><span class="pr-line-label">Folio</span><span class="pr-line-value">{{ $first->checkIn?->folio_no ?: '—' }}</span></div>
            <div class="pr-line"><span class="pr-line-label">Group bill</span><span class="pr-line-value">{{ $groupNo }}</span></div>
        </div>

        <div class="pr-col">
            <div class="pr-line"><span class="pr-line-label">Date</span><span class="pr-line-value">{{ $first->bill_date?->format('d/m/Y') }}</span></div>
            <div class="pr-line"><span class="pr-line-label">Rooms</span><span class="pr-line-value">{{ $bills->count() }}</span></div>
            @if ($first->billingInstruction)
                <div class="pr-line"><span class="pr-line-label">Billing</span><span class="pr-line-value">{{ $first->billingInstruction->name }}</span></div>
            @endif
        </div>
    </div>

    {{-- ── One block per room ────────────────────────────────────────────── --}}
    @foreach ($bills as $bill)
        @php $charges = $lines[$bill->id] ?? collect(); @endphp

        <div class="pr-block">
            <p class="pr-block-label">
                Room {{ $bill->checkIn?->room?->room_no ?: '—' }}
                · Bill {{ $bill->bill_no }}
                @if ($bill->checkIn?->guest_name && $bill->checkIn->guest_name !== $guest)
                    · {{ $bill->checkIn->guest_name }}
                @endif
            </p>

            <table class="pr-table">
                <thead>
                    <tr>
                        <th>Particulars</th>
                        <th class="is-center">Date</th>
                        <th class="is-num">Qty</th>
                        <th class="is-num">Rate</th>
                        <th class="is-num">Tax</th>
                        <th class="is-num">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($charges as $line)
                        <tr>
                            <td>{{ $line->particulars }}</td>
                            <td class="is-center">{{ $line->charge_date?->format('d/m/Y') }}</td>
                            <td class="is-num">{{ rtrim(rtrim(number_format((float) $line->qty, 2, '.', ''), '0'), '.') }}</td>
                            <td class="is-num">{{ number_format((float) $line->price, 2) }}</td>
                            <td class="is-num">{{ number_format((float) $line->tax_amount, 2) }}</td>
                            <td class="is-num">{{ number_format((float) $line->total_amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr class="is-blank"><td colspan="6">Nothing charged to this room.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="5">Room {{ $bill->checkIn?->room?->room_no }} total</th>
                        <th class="is-num">{{ number_format((float) $bill->net_amount, 2) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endforeach

    {{-- ── What the family pays ──────────────────────────────────────────── --}}
    <div class="pr-block">
        <p class="pr-block-label">The family</p>

        <table class="pr-table">
            <tbody>
                <tr><td>Room charges</td><td class="is-num">{{ number_format($grand['room'], 2) }}</td></tr>
                <tr><td>Services and other charges</td><td class="is-num">{{ number_format($grand['service'], 2) }}</td></tr>
                <tr><td>Tax</td><td class="is-num">{{ number_format($grand['tax'], 2) }}</td></tr>

                @if ($grand['discount'] > 0)
                    <tr><td>Discount</td><td class="is-num">− {{ number_format($grand['discount'], 2) }}</td></tr>
                @endif

                @if ($grand['advance'] > 0)
                    <tr><td>Advance received</td><td class="is-num">− {{ number_format($grand['advance'], 2) }}</td></tr>
                @endif

                @if ($grand['paid'] > 0)
                    <tr><td>Paid</td><td class="is-num">− {{ number_format($grand['paid'], 2) }}</td></tr>
                @endif

                <tr>
                    <th>{{ $grand['refund'] > 0 ? 'Refundable' : 'Net payable' }}</th>
                    <th class="is-num">
                        ₹ {{ number_format($grand['refund'] > 0 ? $grand['refund'] : $grand['due'], 2) }}
                    </th>
                </tr>
                <tr>
                    <td>Bill total for {{ $bills->count() }} rooms</td>
                    <td class="is-num">₹ {{ number_format($grand['net'], 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="pr-sign">
        <div class="pr-sign-box">Guest signature</div>
        <div class="pr-sign-box">For {{ $branch?->branch_name ?: config('app.name') }}</div>
    </div>

    <p class="pr-foot">
        Bills {{ $bills->pluck('bill_no')->implode(', ') }} are printed together as group {{ $groupNo }}.
        Each is a separate tax invoice for its room.
    </p>
@endsection
