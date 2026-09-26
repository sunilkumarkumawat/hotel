@extends('layouts.site')

@section('title', 'Choose Your Hotel')
@section('body_class', 'site-picker-body')

@section('content')
    <div class="site-wrap" style="width: 100%;">
        <div style="text-align: center; color: #fff; max-width: 620px; margin: 0 auto 8px;">
            <span class="site-eyebrow" style="color: rgb(255 255 255 / 0.8);">Book Direct</span>
            <h1 style="color: #fff; font-size: clamp(28px, 4.6vw, 42px); margin: 8px 0 12px;">Choose Your Hotel</h1>
            <p style="color: rgb(255 255 255 / 0.85); font-size: 16.5px;">Pick a property to see its rooms, live availability and rates.</p>
        </div>

        @if ($hotels->isEmpty())
            <div class="site-card" style="max-width: 480px; margin: 40px auto 0; text-align: center;">
                <h3>Booking is opening soon</h3>
                <p style="color: var(--site-text-2); margin: 8px 0 0;">Online booking isn't switched on yet — please call us directly and we'll be glad to help with a reservation.</p>
            </div>
        @else
            <div class="site-picker-grid">
                @foreach ($hotels as $hotelSite)
                    @php $branch = $hotelSite->branch; @endphp
                    <a href="{{ route('site.home', $hotelSite->slug) }}" class="site-picker-card">
                        <div class="site-picker-card-photo">
                            @if ($hotelSite->hero_image)
                                <img src="{{ asset($hotelSite->hero_image) }}" alt="{{ $branch->branch_name }}" loading="lazy" />
                            @else
                                <div style="width:100%; height:100%; background: var(--site-hero-gradient); display:flex; align-items:center; justify-content:center; color:#fff;">
                                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none"><path d="M3 21V9.5L12 3l9 6.5V21" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" opacity="0.85" /><path d="M8 21v-7h8v7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" opacity="0.85" /></svg>
                                </div>
                            @endif
                        </div>
                        <div class="site-picker-card-body">
                            <h3 style="font-size: 19px; margin: 0;">{{ $branch->branch_name }}</h3>
                            <p style="margin: 0; color: var(--site-muted); font-size: 13.5px;">
                                {{ collect([$branch->city?->name, $branch->state?->name])->filter()->implode(', ') ?: 'India' }}
                            </p>
                            @if ($hotelSite->tagline)
                                <p style="margin: 6px 0 0; color: var(--site-text-2); font-size: 14px;">{{ $hotelSite->tagline }}</p>
                            @endif
                            <span class="site-btn site-btn-primary site-btn-sm" style="margin-top: 14px; align-self: flex-start;">Explore Hotel</span>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
@endsection
