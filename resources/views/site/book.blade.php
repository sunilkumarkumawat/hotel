@extends('layouts.site')

@section('title', 'Complete Your Booking · ' . $site->branch->branch_name)
@section('body_class', 'site-book-page')

@section('content')
    @include('site.partials.header', ['showHotelsLink' => false])

    <div class="site-panel-head">
        <div class="site-wrap">
            <span class="site-eyebrow">{{ $site->branch->branch_name }}</span>
            <h1>Complete Your Booking</h1>
        </div>
    </div>

    <div class="site-wrap" style="padding-top: 30px; padding-bottom: 70px;">
        @if (! $quote)
            <div class="site-card" style="max-width: 560px; margin: 20px auto; text-align: center;">
                <h3>Pick a room to continue</h3>
                <p style="color: var(--site-text-2); margin: 10px 0 22px;">We couldn't find that room, or none was chosen yet. Head back to our rooms page to pick one — it only takes a moment.</p>
                <a href="{{ route('site.rooms', $site->slug) }}" class="site-btn site-btn-primary">View Rooms &amp; Rates</a>
            </div>
        @else
            @php
                $roomType = $quote['room_type'];
                $soldOut = $left !== null && $left <= 0;
            @endphp

            <div class="site-book-grid">
                <div class="site-card">
                    <h2 style="font-size: 20px; margin-bottom: 4px;">Your Details</h2>
                    <p style="color: var(--site-muted); font-size: 13.5px; margin: 0 0 22px;">We'll send your confirmation to the email and number below.</p>

                    <div id="bookAlert" style="display:none;" class="site-alert site-alert-danger"></div>

                    @if (! $razorpayReady)
                        <div class="site-alert site-alert-warning">
                            Online payment isn't switched on for this hotel yet. Please call us on
                            <a href="tel:{{ $site->branch->mobile_number }}" style="color: inherit; text-decoration: underline;">{{ $site->branch->mobile_number ?: 'the hotel' }}</a>
                            to complete this booking, and we'll do the rest.
                        </div>
                    @elseif ($soldOut)
                        <div class="site-alert site-alert-danger">Sorry — this room type has sold out for these dates. Please go back and try different dates.</div>
                    @endif

                    <form id="bookingForm" novalidate>
                        @csrf
                        <input type="hidden" name="room_type_id" value="{{ $roomType->id }}" />
                        <input type="hidden" name="arrival_date" value="{{ $dates['arrival_date'] }}" />
                        <input type="hidden" name="checkout_date" value="{{ $dates['checkout_date'] }}" />

                        <div class="site-form-row is-triple">
                            <div class="site-field">
                                <label for="title">Title</label>
                                <select id="title" name="title">
                                    <option value="">—</option>
                                    <option value="Mr.">Mr.</option>
                                    <option value="Mrs.">Mrs.</option>
                                    <option value="Ms.">Ms.</option>
                                    <option value="Dr.">Dr.</option>
                                </select>
                            </div>
                            <div class="site-field">
                                <label for="first_name">First Name *</label>
                                <input type="text" id="first_name" name="first_name" required maxlength="255" />
                            </div>
                            <div class="site-field">
                                <label for="last_name">Last Name</label>
                                <input type="text" id="last_name" name="last_name" maxlength="255" />
                            </div>
                        </div>

                        <div class="site-form-row">
                            <div class="site-field">
                                <label for="email">Email *</label>
                                <input type="email" id="email" name="email" required maxlength="255" />
                            </div>
                            <div class="site-field">
                                <label for="mobile">Mobile *</label>
                                <input type="tel" id="mobile" name="mobile" required maxlength="20" placeholder="10-digit mobile number" />
                            </div>
                        </div>

                        <div class="site-form-row">
                            <div class="site-field">
                                <label for="adults">Adults</label>
                                <select id="adults" name="adults">
                                    @for ($i = 1; $i <= $roomType->max_adult * $dates['no_of_rooms']; $i++)
                                        <option value="{{ $i }}" @selected($i === $dates['adults'])>{{ $i }}</option>
                                    @endfor
                                </select>
                            </div>
                            <div class="site-field">
                                <label for="children">Children</label>
                                <select id="children" name="children">
                                    @for ($i = 0; $i <= $roomType->max_child * $dates['no_of_rooms']; $i++)
                                        <option value="{{ $i }}" @selected($i === $dates['children'])>{{ $i }}</option>
                                    @endfor
                                </select>
                            </div>
                        </div>

                        <div class="site-form-row is-single">
                            <div class="site-field">
                                <label for="no_of_rooms">Number of Rooms</label>
                                <select id="no_of_rooms" name="no_of_rooms" onchange="siteBookingChangeRooms(this.value)">
                                    @for ($i = 1; $i <= 10; $i++)
                                        <option value="{{ $i }}" @selected($i === $dates['no_of_rooms'])>{{ $i }} room{{ $i > 1 ? 's' : '' }}</option>
                                    @endfor
                                </select>
                            </div>
                        </div>

                        <div class="site-form-row is-single">
                            <div class="site-field">
                                <label for="remark">Special Requests (optional)</label>
                                <input type="text" id="remark" name="remark" maxlength="1000" placeholder="Early check-in, high floor, anything else we should know" />
                            </div>
                        </div>

                        <button type="submit" id="payBtn" class="site-btn site-btn-primary site-btn-block" @if(! $razorpayReady || $soldOut) disabled @endif style="margin-top: 6px;">
                            Pay ₹{{ number_format($quote['net_amount'], 2) }} &amp; Confirm
                        </button>

                        <p style="text-align:center; font-size: 12px; color: var(--site-muted); margin: 12px 0 0;">
                            You'll be redirected to Razorpay's secure checkout. We never see or store your card details.
                        </p>
                    </form>
                </div>

                <aside class="site-card" style="position: sticky; top: 92px;">
                    <h3 style="font-size: 16.5px;">{{ $roomType->name }}</h3>
                    <p style="color: var(--site-muted); font-size: 13.5px; margin: 4px 0 16px;">{{ $site->branch->branch_name }}</p>

                    <div class="site-summary-row">
                        <span>Check-in</span>
                        <span>{{ \Illuminate\Support\Carbon::parse($dates['arrival_date'])->format('d M Y') }}</span>
                    </div>
                    <div class="site-summary-row">
                        <span>Check-out</span>
                        <span>{{ \Illuminate\Support\Carbon::parse($dates['checkout_date'])->format('d M Y') }}</span>
                    </div>
                    <div class="site-summary-row">
                        <span>Rooms &times; Nights</span>
                        <span>{{ $dates['no_of_rooms'] }} &times; {{ $quote['nights'] }}</span>
                    </div>

                    <div style="height: 1px; background: var(--site-border); margin: 14px 0;"></div>

                    <div class="site-summary-row">
                        <span>Room ({{ $quote['nights'] }} night{{ $quote['nights'] > 1 ? 's' : '' }})</span>
                        <span>₹{{ number_format($quote['amount'], 2) }}</span>
                    </div>
                    <div class="site-summary-row">
                        <span>Taxes</span>
                        <span>₹{{ number_format($quote['tax_amount'], 2) }}</span>
                    </div>
                    <div class="site-summary-row is-total">
                        <span>Total</span>
                        <span>₹{{ number_format($quote['net_amount'], 2) }}</span>
                    </div>

                    @if ($left !== null && $left > 0 && $left <= 2)
                        <div class="site-alert site-alert-warning" style="margin-top: 16px; margin-bottom: 0;">Only {{ $left }} room(s) left — complete your booking soon.</div>
                    @endif
                </aside>
            </div>
        @endif
    </div>

    @include('site.partials.footer')
@endsection

@if ($quote && $razorpayReady)
    @push('scripts')
        <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
        <script>
            function siteBookingChangeRooms(value) {
                var url = new URL(window.location.href);
                url.searchParams.set('rooms', value);
                window.location.href = url.toString();
            }

            document.addEventListener('DOMContentLoaded', function () {
                if (window.SiteBooking) {
                    window.SiteBooking.init({
                        orderUrl: {!! json_encode(route('site.book.order', $site->slug)) !!},
                        verifyUrl: {!! json_encode(route('site.book.verify')) !!},
                    });
                }
            });
        </script>
    @endpush
@endif
