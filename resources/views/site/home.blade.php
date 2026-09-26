@extends('layouts.site')

@section('title', $site->branch->branch_name)
@section('meta_description', \Illuminate\Support\Str::limit($site->tagline ?: $site->about ?: ($site->branch->branch_name . ' — book direct for the best rate and real-time availability.'), 155))

@section('content')
    @include('site.partials.header', ['showHotelsLink' => true])

    <section class="site-hero" @if($site->hero_image) style="background-image: linear-gradient(140deg, rgb(122 42 94 / 0.88), rgb(15 138 106 / 0.80)), url('{{ asset($site->hero_image) }}'); background-size: cover; background-position: center;" @endif>
        <div class="site-wrap site-hero-inner">
            <span class="site-eyebrow">Book Direct &middot; Best Rate Guaranteed</span>
            <h1>{{ $site->branch->branch_name }}</h1>
            <p>{{ $site->tagline ?: 'Real rooms, real-time availability, and a booking that lands straight on our own front desk.' }}</p>
            <div class="site-hero-cta">
                <a href="{{ route('site.rooms', $site->slug) }}" class="site-btn site-btn-accent">Check Availability</a>
                <a href="#about" class="site-btn site-btn-ghost">Explore the Hotel</a>
            </div>
        </div>
    </section>

    <div class="site-wrap">
        <form class="site-search" action="{{ route('site.rooms', $site->slug) }}" method="GET">
            <div class="site-field">
                <label for="home-checkin">Check-in</label>
                <input type="date" id="home-checkin" name="checkin" min="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}" required />
            </div>
            <div class="site-field">
                <label for="home-checkout">Check-out</label>
                <input type="date" id="home-checkout" name="checkout" min="{{ now()->addDay()->toDateString() }}" value="{{ now()->addDay()->toDateString() }}" required />
            </div>
            <div class="site-field">
                <label for="home-adults">Adults</label>
                <select id="home-adults" name="adults">
                    @for ($i = 1; $i <= 8; $i++)
                        <option value="{{ $i }}" @selected($i === 2)>{{ $i }}</option>
                    @endfor
                </select>
            </div>
            <div class="site-field">
                <label for="home-rooms">Rooms</label>
                <select id="home-rooms" name="rooms">
                    @for ($i = 1; $i <= 5; $i++)
                        <option value="{{ $i }}">{{ $i }}</option>
                    @endfor
                </select>
            </div>
            <button type="submit" class="site-btn site-btn-primary">Search Rooms</button>
        </form>
    </div>

    @if ($site->about)
        <section class="site-section" id="about">
            <div class="site-wrap">
                <div class="site-section-head">
                    <span class="site-eyebrow">About</span>
                    <h2>The Story So Far</h2>
                </div>
                <p class="site-lede" style="margin: 0 auto; text-align: center; white-space: pre-line;">{{ $site->about }}</p>
            </div>
        </section>
    @endif

    @if ($site->amenityLabels())
        <section class="site-section site-section-alt" id="amenities">
            <div class="site-wrap">
                <div class="site-section-head">
                    <span class="site-eyebrow">Amenities</span>
                    <h2>Everything You Need, On the House</h2>
                </div>
                <div class="site-amenity-grid">
                    @foreach ($site->amenityLabels() as $amenity)
                        <div class="site-amenity">
                            <span class="site-amenity-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                            </span>
                            <span>{{ $amenity }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($roomTypes->isNotEmpty())
        <section class="site-section">
            <div class="site-wrap">
                <div class="site-section-head">
                    <span class="site-eyebrow">Rooms &amp; Rates</span>
                    <h2>Choose Your Stay</h2>
                    <p class="site-lede" style="margin: 10px auto 0;">Every room below is real inventory — what you see is what you can book.</p>
                </div>
                <div class="site-room-grid">
                    @foreach ($roomTypes->take(6) as $roomType)
                        @include('site.partials.room-card', ['roomType' => $roomType, 'site' => $site, 'leftCount' => null, 'dates' => null])
                    @endforeach
                </div>

                @if ($roomTypes->count() > 6)
                    <div style="text-align: center; margin-top: 36px;">
                        <a href="{{ route('site.rooms', $site->slug) }}" class="site-btn site-btn-outline">View All Rooms</a>
                    </div>
                @endif
            </div>
        </section>
    @endif

    @if ($site->offers)
        <section class="site-section site-section-alt">
            <div class="site-wrap">
                <div class="site-section-head">
                    <span class="site-eyebrow">Offers</span>
                    <h2>Currently Running</h2>
                </div>
                <div class="site-amenity-grid">
                    @foreach ($site->offers as $offer)
                        <div class="site-card" style="padding: 20px;">
                            <strong style="font-family: var(--site-font-display); font-size: 17px;">{{ is_array($offer) ? ($offer['title'] ?? '') : $offer }}</strong>
                            @if (is_array($offer) && ! empty($offer['detail']))
                                <p style="margin: 8px 0 0; color: var(--site-text-2); font-size: 14px;">{{ $offer['detail'] }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @include('site.partials.footer')
@endsection
