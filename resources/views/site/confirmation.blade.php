@extends('layouts.site')

@section('title', 'Booking ' . ($payment->status === 'paid' ? 'Confirmed' : 'Status'))

@section('content')
    @php
        $branch = $payment->branch;
        $site = $branch?->website;
        $reservation = $payment->reservation;
        $stay = $reservation?->rooms->first();
    @endphp

    @if ($site)
        @include('site.partials.header', ['showHotelsLink' => false])
    @endif

    <div class="site-wrap" style="max-width: 680px; padding: 60px 22px 80px;">
        @if ($payment->status === 'paid' && $reservation)
            <div class="site-card" style="text-align: center;">
                <div class="site-confirm-tick">
                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" /></svg>
                </div>
                <h1 style="font-size: 26px;">Booking Confirmed</h1>
                <p style="color: var(--site-text-2);">Thank you, {{ $reservation->guest_name }} — your room is booked and paid in full.</p>

                <div style="text-align: left; margin-top: 28px; border-top: 1px solid var(--site-border); padding-top: 20px;">
                    <div class="site-summary-row"><span>Booking Reference</span><span><strong>{{ $reservation->reservation_no }}</strong></span></div>
                    @if ($stay)
                        <div class="site-summary-row"><span>Room</span><span>{{ $stay->type?->name }} &times; {{ $stay->no_of_rooms }}</span></div>
                        <div class="site-summary-row"><span>Check-in</span><span>{{ $stay->arrival_date->format('d M Y') }} &middot; {{ $branch?->website?->checkin_time ?: '12:00 PM' }}</span></div>
                        <div class="site-summary-row"><span>Check-out</span><span>{{ $stay->checkout_date->format('d M Y') }} &middot; {{ $branch?->website?->checkout_time ?: '11:00 AM' }}</span></div>
                    @endif
                    <div class="site-summary-row"><span>Amount Paid</span><span>₹{{ number_format($payment->amount, 2) }}</span></div>
                    <div class="site-summary-row"><span>Payment Reference</span><span>{{ $payment->gateway_payment_id }}</span></div>
                </div>

                <p style="font-size: 13.5px; color: var(--site-muted); margin-top: 24px;">
                    A confirmation has been sent to your email and mobile number. Please keep your booking reference handy at check-in.
                </p>

                @if ($branch)
                    <div style="margin-top: 24px; display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                        @if ($branch->mobile_number)
                            <a href="tel:{{ $branch->mobile_number }}" class="site-btn site-btn-outline site-btn-sm">Call the Hotel</a>
                        @endif
                        @if ($site)
                            <a href="{{ route('site.home', $site->slug) }}" class="site-btn site-btn-primary site-btn-sm">Back to {{ $branch->branch_name }}</a>
                        @endif
                    </div>
                @endif
            </div>
        @elseif ($payment->status === 'needs_review')
            <div class="site-card" style="text-align: center;">
                <div class="site-confirm-tick" style="background: var(--site-warning-soft); color: var(--site-warning-text);">!</div>
                <h1 style="font-size: 24px;">Confirming Your Booking</h1>
                <p style="color: var(--site-text-2);">
                    Your payment of ₹{{ number_format($payment->amount, 2) }} was received, but we hit a snag confirming the exact room automatically.
                    Our team has been notified and will reach out to you shortly to confirm — and if we can't accommodate you, you'll receive a full refund.
                </p>
                <p style="font-size: 13.5px; color: var(--site-muted); margin-top: 16px;">Payment reference: {{ $payment->gateway_payment_id }}</p>
                @if ($branch?->mobile_number)
                    <a href="tel:{{ $branch->mobile_number }}" class="site-btn site-btn-primary site-btn-sm" style="margin-top: 10px;">Call the Hotel</a>
                @endif
            </div>
        @else
            <div class="site-card" style="text-align: center;">
                <h1 style="font-size: 24px;">Payment Not Yet Received</h1>
                <p style="color: var(--site-text-2);">
                    We haven't received a completed payment for this booking. If you believe you were charged, please contact the hotel with the
                    reference below and we'll sort it out right away. Otherwise, you're welcome to start a fresh booking.
                </p>
                <p style="font-size: 13.5px; color: var(--site-muted); margin-top: 16px;">Reference: {{ $payment->gateway_order_id }}</p>
                <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                    @if ($branch?->mobile_number)
                        <a href="tel:{{ $branch->mobile_number }}" class="site-btn site-btn-outline site-btn-sm">Call the Hotel</a>
                    @endif
                    @if ($site)
                        <a href="{{ route('site.rooms', $site->slug) }}" class="site-btn site-btn-primary site-btn-sm">Try Booking Again</a>
                    @endif
                </div>
            </div>
        @endif
    </div>

    @if ($site)
        @include('site.partials.footer')
    @endif
@endsection
