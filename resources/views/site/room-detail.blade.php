@extends('layouts.site')

@section('title', $roomType->name . ' · ' . $site->branch->branch_name)
@section('meta_description', \Illuminate\Support\Str::limit($roomType->website?->description ?: ($roomType->name . ' at ' . $site->branch->branch_name . '.'), 155))

@section('content')
    @php
        $images = $roomType->images;
        $cover = $images->firstWhere('is_cover', true) ?? $images->first();
        $highlights = $roomType->website?->highlights ?? [];
        $soldOut = $left <= 0;
        $nights = $quote['nights'];

        $bookQuery = [
            'room_type_id' => $roomType->id,
            'checkin' => $dates['arrival_date'],
            'checkout' => $dates['checkout_date'],
            'adults' => $dates['adults'],
            'children' => $dates['children'],
            'rooms' => $dates['no_of_rooms'],
        ];
    @endphp

    @include('site.partials.header', ['showHotelsLink' => true])

    <div class="site-wrap" style="padding-top: 26px;">
        <nav style="font-size: 13.5px; color: var(--site-muted); margin-bottom: 18px;">
            <a href="{{ route('site.home', $site->slug) }}" style="color: var(--site-muted);">{{ $site->branch->branch_name }}</a>
            <span> / </span>
            <a href="{{ route('site.rooms', $site->slug) }}" style="color: var(--site-muted);">Rooms</a>
            <span> / </span>
            <span style="color: var(--site-text);">{{ $roomType->name }}</span>
        </nav>

        <div class="site-book-grid">
            <div>
                @if ($images->isNotEmpty())
                    <div class="site-gallery-main" id="galleryMain">
                        <img src="{{ $cover->url }}" alt="{{ $roomType->name }}" id="galleryMainImg" />
                    </div>
                    @if ($images->count() > 1)
                        <div class="site-gallery-thumbs">
                            @foreach ($images as $image)
                                <button type="button" data-src="{{ $image->url }}" class="@if($image->is($cover)) is-active @endif">
                                    <img src="{{ $image->url }}" alt="" />
                                </button>
                            @endforeach
                        </div>
                    @endif
                @else
                    <div class="site-gallery-main" style="display:flex; align-items:center; justify-content:center; background: linear-gradient(150deg, var(--site-primary-soft), var(--site-accent-soft)); color: var(--site-primary);">
                        <svg width="60" height="60" viewBox="0 0 24 24" fill="none"><path d="M3 21V9.5L12 3l9 6.5V21" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" /><path d="M8 21v-7h8v7" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    </div>
                @endif

                <h1 style="margin-top: 26px;">{{ $roomType->name }}</h1>

                <div class="site-chip-row" style="margin-bottom: 16px;">
                    <span class="site-chip">{{ $roomType->max_adult }} Adults</span>
                    <span class="site-chip">{{ $roomType->max_child }} Child</span>
                    @if ($roomType->category?->name)
                        <span class="site-chip">{{ $roomType->category->name }}</span>
                    @endif
                    @foreach ($highlights as $highlight)
                        <span class="site-chip">{{ $highlight }}</span>
                    @endforeach
                </div>

                @if ($roomType->website?->description)
                    <p style="color: var(--site-text-2); font-size: 15.5px; line-height: 1.8; white-space: pre-line;">{{ $roomType->website->description }}</p>
                @endif

                @if ($site->amenityLabels())
                    <div style="margin-top: 30px;">
                        <h3 style="font-size: 17px;">Hotel Amenities</h3>
                        <div class="site-amenity-grid">
                            @foreach ($site->amenityLabels() as $amenity)
                                <div class="site-amenity">
                                    <span class="site-amenity-icon">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                                    </span>
                                    <span>{{ $amenity }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <aside class="site-card" style="position: sticky; top: 92px;">
                <form action="{{ route('site.rooms', $site->slug) }}" method="GET" style="margin-bottom: 18px;">
                    <input type="hidden" name="rooms" value="{{ $dates['no_of_rooms'] }}" />
                    <div class="site-form-row">
                        <div class="site-field">
                            <label for="d-checkin">Check-in</label>
                            <input type="date" id="d-checkin" name="checkin" min="{{ now()->toDateString() }}" value="{{ $dates['arrival_date'] }}" onchange="this.form.submit()" />
                        </div>
                        <div class="site-field">
                            <label for="d-checkout">Check-out</label>
                            <input type="date" id="d-checkout" name="checkout" min="{{ \Illuminate\Support\Carbon::parse($dates['arrival_date'])->addDay()->toDateString() }}" value="{{ $dates['checkout_date'] }}" onchange="this.form.submit()" />
                        </div>
                    </div>
                    <div class="site-field">
                        <label for="d-adults">Adults</label>
                        <select id="d-adults" name="adults" onchange="this.form.submit()">
                            @for ($i = 1; $i <= 8; $i++)
                                <option value="{{ $i }}" @selected($i === $dates['adults'])>{{ $i }}</option>
                            @endfor
                        </select>
                    </div>
                </form>

                @if ($soldOut)
                    <div class="site-alert site-alert-danger">No rooms of this type are left for these dates.</div>
                @elseif ($left <= 2)
                    <div class="site-alert site-alert-warning">Only {{ $left }} room(s) left for these dates — booking fast.</div>
                @endif

                <div class="site-summary-row">
                    <span>₹{{ number_format($quote['nightly'], 0) }} &times; {{ $nights }} night{{ $nights > 1 ? 's' : '' }}</span>
                    <span>₹{{ number_format($quote['amount'], 2) }}</span>
                </div>
                <div class="site-summary-row">
                    <span>Taxes @if($quote['tax_percent']) ({{ rtrim(rtrim(number_format($quote['tax_percent'], 2), '0'), '.') }}%) @endif</span>
                    <span>₹{{ number_format($quote['tax_amount'], 2) }}</span>
                </div>
                <div class="site-summary-row is-total">
                    <span>Total</span>
                    <span>₹{{ number_format($quote['net_amount'], 2) }}</span>
                </div>

                <a href="{{ $soldOut ? '#' : route('site.book', $site->slug) . '?' . http_build_query($bookQuery) }}"
                   class="site-btn site-btn-primary site-btn-block @if($soldOut) is-disabled @endif"
                   style="margin-top: 16px;"
                   @if($soldOut) aria-disabled="true" @endif>
                    {{ $soldOut ? 'Sold Out for These Dates' : 'Book This Room' }}
                </a>

                <p style="text-align: center; font-size: 12.5px; color: var(--site-muted); margin: 12px 0 0;">Pay securely online &middot; instant confirmation</p>
            </aside>
        </div>
    </div>

    <div style="height: 60px;"></div>

    @include('site.partials.footer')
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var mainImg = document.getElementById('galleryMainImg');
            var thumbs = document.querySelectorAll('.site-gallery-thumbs button');

            thumbs.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    if (!mainImg) return;
                    mainImg.src = btn.getAttribute('data-src');
                    thumbs.forEach(function (b) { b.classList.remove('is-active'); });
                    btn.classList.add('is-active');
                });
            });
        });
    </script>
@endpush
