@extends('layouts.app')

@section('title', 'Pool Calendar')

@php
    use App\Support\Facility;

    $span = max(1, $closeHour - $openHour);

    // Where a bar sits, as a percentage of the grid — the same arithmetic the
    // tape chart uses, kept in the view because it is presentation and nothing
    // else depends on it.
    $left = fn ($time) => max(0, min(100, ((Facility::minutes($time) - $openHour * 60) / ($span * 60)) * 100));
@endphp

@section('content')
    <x-page-header
        title="Pool Calendar"
        subtitle="One day, every pool, hour by hour."
        :crumbs="['Home' => url('/'), 'Pool' => route('pool.bookings'), 'Calendar']"
    >
        <x-slot:actions>
            <form method="GET" class="nv-actions">
                <a href="{{ route('pool.calendar', ['date' => \Carbon\CarbonImmutable::parse($date)->subDay()->toDateString()]) }}"
                   class="nv-icon-btn" title="Previous day"><x-icon name="chevron-left" /></a>

                <input type="date" name="date" value="{{ $date }}" class="nv-input" style="width:160px"
                       onchange="this.form.submit()" aria-label="Date" />

                <a href="{{ route('pool.calendar', ['date' => \Carbon\CarbonImmutable::parse($date)->addDay()->toDateString()]) }}"
                   class="nv-icon-btn" title="Next day"><x-icon name="chevron-right" /></a>
            </form>

            @canAdd('pool/bookings')
                <a href="{{ route('pool.bookings.create', ['date' => $date]) }}" class="nv-btn nv-btn-primary">
                    <x-icon name="plus" /> New booking
                </a>
            @endCanAdd
        </x-slot:actions>
    </x-page-header>

    <div class="nv-mt">
        <x-card flush>
            @if ($pools->isEmpty())
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="globe" /></span>
                    <strong>No pools set up</strong>
                    <p>Add one under Pool → Pools first.</p>
                </div>
            @else
                <div class="nv-fac-cal">
                    <div class="nv-fac-cal-head">
                        <div class="nv-fac-cal-label">Pool</div>
                        <div class="nv-fac-cal-track">
                            @foreach ($hours as $hour)
                                <span class="nv-fac-cal-hour" style="width:{{ 100 / $span }}%">
                                    {{ str_pad((string) $hour, 2, '0', STR_PAD_LEFT) }}:00
                                </span>
                            @endforeach
                        </div>
                    </div>

                    @foreach ($pools as $pool)
                        <div class="nv-fac-cal-row">
                            <div class="nv-fac-cal-label">
                                <strong>{{ $pool->name }}</strong>
                                @if ($pool->capacity)
                                    <span class="nv-sub">holds {{ $pool->capacity }}</span>
                                @endif
                            </div>

                            <div class="nv-fac-cal-track">
                                @foreach ($hours as $hour)
                                    <span class="nv-fac-cal-cell" style="width:{{ 100 / $span }}%"></span>
                                @endforeach

                                @foreach ($bookings->get($pool->id, collect()) as $booking)
                                    @php
                                        $start = $left($booking->from_time);
                                        $end = $left($booking->to_time);
                                    @endphp

                                    <a class="nv-fac-bar is-{{ $booking->status }}"
                                       style="left:{{ $start }}%;width:{{ max(2, $end - $start) }}%"
                                       href="{{ route('pool.bookings.edit', $booking->id) }}"
                                       title="{{ $booking->guest_name }} · {{ $booking->slot }} · {{ $booking->pax }} people">
                                        <strong>{{ $booking->guest_name ?: $booking->booking_no }}</strong>
                                        <small>{{ $booking->pax }}p</small>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-card>
    </div>
@endsection
