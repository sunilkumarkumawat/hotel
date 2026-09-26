{{--
    Shared site header — expects $site (BranchWebsite, with branch loaded)
    in scope. Included on every guest page except the hotel picker, which
    has no single hotel to brand itself with yet.
--}}
<header class="site-header">
    <div class="site-wrap site-header-inner">
        <a href="{{ route('site.home', $site->slug) }}" class="site-brand">
            <span class="site-brand-mark">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M3 21V9.5L12 3l9 6.5V21" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                    <path d="M8 21v-7h8v7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </span>
            <span>{{ $site->branch->branch_name }}</span>
        </a>

        <button type="button" class="site-mobile-nav-toggle" id="siteNavToggle" aria-label="Menu" aria-expanded="false">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M3 6h18M3 12h18M3 18h18" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
        </button>

        <nav class="site-nav-links" id="siteNavLinks">
            <a href="{{ route('site.home', $site->slug) }}">Home</a>
            <a href="{{ route('site.rooms', $site->slug) }}">Rooms</a>
            <a href="{{ route('site.home', $site->slug) }}#amenities">Amenities</a>
            <a href="{{ route('site.home', $site->slug) }}#about">About</a>
            <a href="{{ route('site.home', $site->slug) }}#contact">Contact</a>
            @if ($showHotelsLink ?? true)
                <a href="{{ route('site.hotels') }}">Our Hotels</a>
            @endif
            <a href="{{ route('site.rooms', $site->slug) }}" class="site-btn site-btn-primary site-btn-sm">Book Now</a>
        </nav>
    </div>
</header>
