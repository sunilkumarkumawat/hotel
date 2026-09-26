{{--
    One room type card — used on the home page teaser and the full rooms
    listing.

    Expects: $roomType (with 'website' and 'images' eager-loaded), $site.
    Optional: $leftCount (int|null — omit the availability badge entirely
    when null, e.g. the home page teaser where no dates have been searched
    yet), $dates (array|null — arrival_date/checkout_date/adults/children/
    no_of_rooms, carried into the "Book now" link's query string).

    Reads $roomType->images->firstWhere(...) rather than
    $roomType->coverImage — the controllers eager-load 'images', not the
    separate coverImage relation, and calling that here would re-query per
    card.
--}}
@php
    $cover = $roomType->images->firstWhere('is_cover', true) ?? $roomType->images->first();
    $highlights = $roomType->website?->highlights ?? [];
    $soldOut = $leftCount !== null && $leftCount <= 0;
    $low = $leftCount !== null && $leftCount > 0 && $leftCount <= 2;

    $bookQuery = array_filter([
        'room_type_id' => $roomType->id,
        'checkin' => $dates['arrival_date'] ?? null,
        'checkout' => $dates['checkout_date'] ?? null,
        'adults' => $dates['adults'] ?? null,
        'children' => $dates['children'] ?? null,
        'rooms' => $dates['no_of_rooms'] ?? null,
    ]);

    $roomsQuery = array_filter([
        'checkin' => $dates['arrival_date'] ?? null,
        'checkout' => $dates['checkout_date'] ?? null,
        'adults' => $dates['adults'] ?? null,
        'children' => $dates['children'] ?? null,
        'rooms' => $dates['no_of_rooms'] ?? null,
    ]);
@endphp
<article class="site-room-card">
    <div class="site-room-photo @if(! $cover) is-placeholder @endif">
        @if ($cover)
            <img src="{{ $cover->url }}" alt="{{ $roomType->name }}" loading="lazy" />
        @else
            <svg width="52" height="52" viewBox="0 0 24 24" fill="none"><path d="M3 21V9.5L12 3l9 6.5V21" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" opacity="0.5" /><path d="M8 21v-7h8v7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" opacity="0.5" /></svg>
        @endif

        @if ($leftCount !== null)
            <span class="site-room-badge @if($soldOut) is-sold @elseif($low) is-low @endif">
                @if ($soldOut) Sold out
                @elseif ($low) Only {{ $leftCount }} left
                @else {{ $leftCount }} rooms left
                @endif
            </span>
        @endif
    </div>

    <div class="site-room-body">
        <h3>{{ $roomType->name }}</h3>

        <div class="site-chip-row">
            <span class="site-chip">{{ $roomType->max_adult }} Adults · {{ $roomType->max_child }} Child</span>
            @foreach (array_slice($highlights, 0, 2) as $highlight)
                <span class="site-chip">{{ $highlight }}</span>
            @endforeach
        </div>

        @if ($roomType->website?->description)
            <p style="color: var(--site-text-2); font-size: 14px; margin: 0; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">
                {{ $roomType->website->description }}
            </p>
        @endif

        <div class="site-room-foot">
            <div class="site-price">
                ₹{{ number_format($roomType->base_rent, 0) }}
                <small>/ night</small>
            </div>

            <div style="display: flex; gap: 8px;">
                <a href="{{ route('site.room', ['slug' => $site->slug, 'roomType' => $roomType->id]) . ($roomsQuery ? '?' . http_build_query($roomsQuery) : '') }}" class="site-btn site-btn-outline site-btn-sm">Details</a>
                @if (! $soldOut)
                    <a href="{{ route('site.book', $site->slug) . '?' . http_build_query($bookQuery) }}" class="site-btn site-btn-primary site-btn-sm">Book</a>
                @endif
            </div>
        </div>
    </div>
</article>
