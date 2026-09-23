@extends('layouts.print')

@section('title', $title)

@php
    $money = fn ($n) => number_format((float) $n, 2);
    $reservation = $checkIn?->reservation;

    // Tax grouped by rate — an 12% night and an 18% night are two lines on the
    // summary, which is what a GST return needs.
    $taxLines = $charges
        ->filter(fn ($c) => (float) $c->tax_amount > 0)
        ->groupBy(fn ($c) => (string) (float) $c->tax_percent)
        ->map(fn ($lines, $rate) => [
            'rate' => (float) $rate,
            'taxable' => round($lines->sum(fn ($l) => (float) $l->amount), 2),
            'tax' => round($lines->sum(fn ($l) => (float) $l->tax_amount), 2),
            'total' => round($lines->sum(fn ($l) => (float) $l->total_amount), 2),
        ])
        ->sortBy('rate');

    /** Rupees in words — an Indian invoice is expected to carry it. */
    $words = function (float $amount): string {
        $rupees = (int) floor($amount);

        if ($rupees === 0) {
            return 'Zero';
        }

        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
            'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen',
            'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

        $under100 = function (int $n) use ($ones, $tens) {
            if ($n < 20) return $ones[$n];

            return trim($tens[intdiv($n, 10)] . ' ' . $ones[$n % 10]);
        };

        $under1000 = function (int $n) use ($ones, $under100) {
            $out = $n >= 100 ? $ones[intdiv($n, 100)] . ' Hundred' : '';

            return trim($out . ' ' . $under100($n % 100));
        };

        // Indian grouping: crore, lakh, thousand, hundred.
        $parts = [];

        foreach ([10000000 => 'Crore', 100000 => 'Lakh', 1000 => 'Thousand'] as $unit => $name) {
            if ($rupees >= $unit) {
                $parts[] = $under1000(intdiv($rupees, $unit)) . ' ' . $name;
                $rupees %= $unit;
            }
        }

        if ($rupees > 0) {
            $parts[] = $under1000($rupees);
        }

        return implode(' ', $parts);
    };
@endphp

@section('content')
    {{-- ── Letterhead ────────────────────────────────────────────────────── --}}
    <div class="pr-head">
        <div>
            <p class="pr-hotel-name">{{ $branch->legal_name ?: $branch->branch_name }}</p>

            @if ($branch->address)
                <p class="pr-hotel-line">{{ $branch->address }}{{ $branch->pin_code ? ' - ' . $branch->pin_code : '' }}</p>
            @endif

            @if ($branch->mobile_number)
                <p class="pr-hotel-line">Mobile No. :- {{ $branch->mobile_number }}</p>
            @endif

            @if ($branch->email)
                <p class="pr-hotel-line">Email-id :- {{ $branch->email }}</p>
            @endif

            @if ($branch->gst_no)
                <p class="pr-hotel-line">GSTNo: {{ $branch->gst_no }}</p>
            @endif
        </div>
    </div>

    <h1 class="pr-title">{{ $title }}</h1>

    @unless ($bill)
        {{-- A proforma is not a tax invoice, and saying so is the difference
             between a working document and one somebody claims credit on. --}}
        <p class="pr-stamp">PROVISIONAL — not a tax invoice. The final bill is issued at checkout.</p>
    @endunless

    {{-- ── Guest and stay ────────────────────────────────────────────────── --}}
    <table class="pr-table pr-grid">
        <tr>
            <td class="is-key">Bill Date</td>
            <td>{{ ($bill?->bill_date ?? now())->format('d M Y') }}</td>
            <td class="is-key">Room No.</td>
            <td>{{ $checkIn?->room?->room_no ?? '—' }}</td>
            <td class="is-key">Arrival No.</td>
            <td>{{ $checkIn?->folio_no ?? '—' }}</td>
        </tr>
        <tr>
            <td class="is-key">Guest Name</td>
            <td>{{ $checkIn?->guest_name ?? '—' }}</td>
            <td class="is-key">Nationality</td>
            <td>{{ $reservation?->country?->name ?? '—' }}</td>
            <td class="is-key">CheckIn Date</td>
            <td>{{ $checkIn?->checkin_date?->format('d/m/Y') }} {{ substr((string) $checkIn?->checkin_time, 0, 5) }}</td>
        </tr>
        <tr>
            <td class="is-key">Mobile No</td>
            <td>{{ $checkIn?->mobile ?? '—' }}</td>
            <td class="is-key">Adult / Child</td>
            <td>{{ ($checkIn?->male + $checkIn?->female) }} / {{ $checkIn?->child }}</td>
            <td class="is-key">CheckOut Date</td>
            <td>
                {{ optional($checkIn?->actual_checkout_date ?? $checkIn?->expected_checkout_date)->format('d/m/Y') }}
            </td>
        </tr>
        <tr>
            <td class="is-key">Bill To</td>
            <td>{{ $reservation?->company?->name ?? $checkIn?->guest_name }}</td>
            <td class="is-key">Plan Type</td>
            <td>{{ $checkIn?->plan?->name ?? '—' }}</td>
            <td class="is-key">Room Type</td>
            <td>{{ $checkIn?->room?->type?->name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="is-key">Address</td>
            <td colspan="3">{{ $reservation?->address ?: '—' }}</td>
            <td class="is-key">GSTIN</td>
            <td>{{ $reservation?->company_gst_no ?: '—' }}</td>
        </tr>
        @if ($bill)
            <tr>
                <td class="is-key">Bill No.</td>
                <td colspan="5"><strong>{{ $bill->bill_no }}</strong></td>
            </tr>
        @endif
    </table>

    {{-- ── Particulars ───────────────────────────────────────────────────── --}}
    <table class="pr-table">
        <thead>
            <tr>
                <th>Particular</th>
                <th class="is-num">Qty</th>
                <th class="is-num">Amount</th>
                <th class="is-num">Tax %</th>
                <th class="is-num">Tax</th>
                <th class="is-num">TotAmt</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($charges as $line)
                <tr>
                    <td>{{ $line->particulars }}</td>
                    <td class="is-num">{{ rtrim(rtrim(number_format($line->qty, 2), '0'), '.') }}</td>
                    <td class="is-num">{{ $money($line->amount) }}</td>
                    <td class="is-num">{{ rtrim(rtrim(number_format($line->tax_percent, 2), '0'), '.') }}</td>
                    <td class="is-num">{{ $money($line->tax_amount) }}</td>
                    <td class="is-num">{{ $money($line->total_amount) }}</td>
                </tr>
            @empty
                <tr class="is-blank"><td colspan="6"></td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- ── Money ─────────────────────────────────────────────────────────── --}}
    <div class="pr-split">
        <div>
            <p class="pr-block-label">In Words :</p>
            <p class="pr-words">{{ $words($totals['net']) }} Only</p>

            @if ($settlements->count())
                <p class="pr-block-label" style="margin-top:4mm">Payment Mode :-</p>
                <ul class="pr-terms">
                    @foreach ($settlements as $paid)
                        <li>
                            {{ $paid->payMode?->name ?? $paid->pay_type_label }} — ₹{{ $money($paid->amount) }}
                            {{ $paid->card_number ? '(' . $paid->card_number . ')' : '' }}
                            {{ $paid->reference_no ? ' · ' . $paid->reference_no : '' }}
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <table class="pr-table pr-money">
            <tr><td class="is-key">Sub Total</td><td class="is-num">{{ $money($totals['sub_total']) }}</td></tr>
            <tr><td class="is-key">Tax Amount</td><td class="is-num">{{ $money($totals['tax']) }}</td></tr>

            @if ($totals['discount'] > 0)
                <tr><td class="is-key">Discount</td><td class="is-num">− {{ $money($totals['discount']) }}</td></tr>
            @endif

            <tr class="is-strong"><td class="is-key">Net Amount</td><td class="is-num">{{ $money($totals['net']) }}</td></tr>
            <tr><td class="is-key">Advance Amount</td><td class="is-num">{{ $money($totals['advance']) }}</td></tr>
            <tr><td class="is-key">Receive Amount</td><td class="is-num">{{ $money($totals['paid']) }}</td></tr>
            <tr class="is-strong"><td class="is-key">Balance</td><td class="is-num">{{ $money($totals['due']) }}</td></tr>

            @if ($totals['refund'] > 0)
                <tr><td class="is-key">Refund due</td><td class="is-num">{{ $money($totals['refund']) }}</td></tr>
            @endif
        </table>
    </div>

    {{-- ── Tax summary ───────────────────────────────────────────────────── --}}
    @if ($taxLines->count())
        <p class="pr-block-label" style="margin-top:5mm;text-align:center">Tax Summary</p>

        <table class="pr-table">
            <thead>
                <tr>
                    <th>Particular</th>
                    <th class="is-num">Taxable Amt</th>
                    <th class="is-num">Tax Rate</th>
                    <th class="is-num">CGST</th>
                    <th class="is-num">SGST</th>
                    <th class="is-num">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($taxLines as $tax)
                    <tr>
                        <td>Accommodation{{ $branch->sac_code ? ' (' . $branch->sac_code . ')' : '' }}</td>
                        <td class="is-num">{{ $money($tax['taxable']) }}</td>
                        <td class="is-num">{{ rtrim(rtrim(number_format($tax['rate'], 2), '0'), '.') }}%</td>
                        {{-- Within one state GST is split half CGST, half SGST. --}}
                        <td class="is-num">{{ $money($tax['tax'] / 2) }} ({{ $tax['rate'] / 2 }}%)</td>
                        <td class="is-num">{{ $money($tax['tax'] / 2) }} ({{ $tax['rate'] / 2 }}%)</td>
                        <td class="is-num">{{ $money($tax['total']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="pr-sign" style="margin-top:12mm">
        <div class="pr-sign-box">Guest Signature</div>
        <div class="pr-sign-box">For {{ $branch->branch_name }}</div>
    </div>

    <p class="pr-note">
        Certified that the particulars given above are true and correct. I agree that I am responsible
        for the full payment of this bill in the event it is not paid by the company, organisation or
        person indicated.
    </p>

    <div class="pr-foot">
        <span>Printed on {{ now()->format('d M Y h:i A') }}</span>
        <span>Printed By {{ auth()->user()?->name ?? auth()->user()?->username }}</span>
        <span>Page 1 of 1</span>
    </div>
@endsection
