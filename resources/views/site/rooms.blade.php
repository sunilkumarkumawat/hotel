@extends('layouts.site')

@section('title', 'Rooms & Rates · ' . $site->branch->branch_name)
@section('meta_description', 'Live room availability and rates at ' . $site->branch->branch_name . '.')

@section('content')
    @include('site.partials.header', ['showHotelsLink' => true])

    <div class="site-panel-head">
        <div class="site-wrap">
            <span class="site-eyebrow">{{ $site->branch->branch_name }}</span>
            <h1>Rooms &amp; Rates</h1>
        </div>
    </div>

    <div class="site-wrap">
        <form class="site-search" action="{{ route('site.rooms', $site->slug) }}" method="GET" style="margin-top: -46px;">
            <div class="site-field">
                <label for="checkin">Check-in</label>
                <input type="date" id="checkin" name="checkin" min="{{ now()->toDateString() }}" value="{{ $dates['arrival_date'] }}" required />
            </div>
            <div class="site-field">
                <label for="checkout">Check-out</label>
                <input type="date" id="checkout" name="checkout" min="{{ \Illuminate\Support\Carbon::parse($dates['arrival_date'])->addDay()->toDateString() }}" value="{{ $dates['checkout_date'] }}" required />
            </div>
            <div class="site-field">
                <label for="adults">Adults</label>
                <select id="adults" name="adults">
                    @for ($i = 1; $i <= 8; $i++)
                        <option value="{{ $i }}" @selected($i === $dates['adults'])>{{ $i }}</option>
                    @endfor
                </select>
            </div>
            <div class="site-field">
                <label for="rooms">Rooms</label>
                <select id="rooms" name="rooms">
                    @for ($i = 1; $i <= 5; $i++)
                        <option value="{{ $i }}" @selected($i === $dates['no_of_rooms'])>{{ $i }}</option>
                    @endfor
                </select>
            </div>
            <button type="submit" class="site-btn site-btn-primary">Update</button>
        </form>

        <section class="site-section-tight">
            <p style="text-align: center; color: var(--site-text-2); font-size: 14.5px; margin: 0 0 28px;">
                Showing availability for <strong>{{ \Illuminate\Support\Carbon::parse($dates['arrival_date'])->format('d M Y') }}</strong>
                to <strong>{{ \Illuminate\Support\Carbon::parse($dates['checkout_date'])->format('d M Y') }}</strong>
                &middot; {{ \Illuminate\Support\Carbon::parse($dates['arrival_date'])->diffInDays(\Illuminate\Support\Carbon::parse($dates['checkout_date'])) }} night(s)
            </p>

            @if ($roomTypes->isEmpty())
                <div class="site-empty">
                    <h3>No rooms configured yet</h3>
                    <p>Please check back soon, or call us directly to book.</p>
                </div>
            @else
                <div class="site-room-grid">
                    @foreach ($roomTypes as $roomType)
                        @include('site.partials.room-card', [
                            'roomType' => $roomType,
                            'site' => $site,
                            'leftCount' => $left[$roomType->id] ?? 0,
                            'dates' => $dates,
                        ])
                    @endforeach
                </div>
            @endif
        </section>
    </div>

    @include('site.partials.footer')
@endsection
