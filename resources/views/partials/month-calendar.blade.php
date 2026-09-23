{{--
    The month, coloured by how full the house is.

    Occupancy is a magnitude, so it gets a magnitude's encoding: one hue, light
    to dark, in five steps. A rainbow here would say "these are different kinds
    of day", which is exactly wrong — they are the same kind of day, more or
    less full.

    Colour alone never carries it. Every cell also prints "sold / total", so a
    colour-blind user, a printed sheet and a photocopy all still work; the tint
    is the thing that lets a manager find the busy week without reading.

    Expects: $month (CarbonImmutable, first of the month), $calendar (per-day
    figures from RoomBoard::month), $totalRooms, $today (the day the dashboard
    is showing).
--}}

@php
    use Carbon\CarbonImmutable;

    $first = $month->startOfMonth();
    $last = $month->endOfMonth();

    // Weeks start on Sunday here, the way an Indian desk calendar prints.
    $lead = (int) $first->dayOfWeek;
    $total = max(1, (int) $totalRooms);

    $bucket = function (int $sold) use ($total) {
        if ($sold <= 0) return 0;

        $pct = $sold / $total * 100;

        return match (true) {
            $pct >= 95 => 5,
            $pct >= 75 => 4,
            $pct >= 50 => 3,
            $pct >= 25 => 2,
            default => 1,
        };
    };

    $busiest = collect($calendar)->max('sold') ?: 0;
    $soldTotal = collect($calendar)->sum('sold');
    $nights = max(1, count($calendar) * $total);
@endphp

<div class="nv-mt">
    <x-card>
        <x-slot:title>{{ $first->format('F Y') }}</x-slot:title>

        <x-slot:actions>
            <span class="nv-mcal-summary">
                {{ round($soldTotal / $nights * 100) }}% of the month sold
                @if ($busiest)
                    · busiest night {{ $busiest }} of {{ $total }}
                @endif
            </span>

            <a href="{{ route('dashboard', ['date' => $today, 'month' => $first->subMonth()->toDateString()]) }}"
               class="nv-btn nv-btn-ghost nv-btn-sm" aria-label="Previous month">
                <x-icon name="chevron-left" />
            </a>

            <a href="{{ route('dashboard', ['date' => $today, 'month' => $first->addMonth()->toDateString()]) }}"
               class="nv-btn nv-btn-ghost nv-btn-sm" aria-label="Next month">
                <x-icon name="chevron-right" />
            </a>
        </x-slot:actions>

        <div class="nv-mcal">
            @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday)
                <div class="nv-mcal-head">{{ $weekday }}</div>
            @endforeach

            {{-- The blanks before the 1st. Marked aria-hidden so a screen reader
                 reads a month, not a week of nothing. --}}
            @for ($i = 0; $i < $lead; $i++)
                <div class="nv-mcal-day is-blank" aria-hidden="true"></div>
            @endfor

            @for ($day = $first; $day <= $last; $day = $day->addDay())
                @php
                    $key = $day->toDateString();
                    $row = $calendar[$key] ?? ['sold' => 0, 'arrivals' => 0, 'departures' => 0, 'blocked' => 0];
                    $free = max(0, $total - $row['sold'] - $row['blocked']);
                @endphp

                <a href="{{ route('dashboard', ['date' => $key, 'month' => $first->toDateString()]) }}"
                   @class([
                       'nv-mcal-day',
                       'is-o' . $bucket($row['sold']),
                       'is-today' => $key === $today,
                       'is-past' => $day->lt(CarbonImmutable::parse($today)->startOfDay()),
                   ])
                   title="{{ $day->format('D, d M') }} — {{ $row['sold'] }} of {{ $total }} sold, {{ $free }} free">

                    <span class="nv-mcal-date">{{ $day->format('j') }}</span>

                    <span class="nv-mcal-sold">{{ $row['sold'] }}<i>/{{ $total }}</i></span>

                    <span class="nv-mcal-moves">
                        @if ($row['arrivals'])
                            <b class="is-in" title="{{ $row['arrivals'] }} arriving">&#9660;{{ $row['arrivals'] }}</b>
                        @endif
                        @if ($row['departures'])
                            <b class="is-out" title="{{ $row['departures'] }} leaving">&#9650;{{ $row['departures'] }}</b>
                        @endif
                        @if ($row['blocked'])
                            <b class="is-blk" title="{{ $row['blocked'] }} blocked">&#9632;{{ $row['blocked'] }}</b>
                        @endif
                    </span>
                </a>
            @endfor
        </div>

        <div class="nv-mcal-legend">
            <span>Empty</span>
            @for ($i = 0; $i <= 5; $i++)
                <span class="nv-mcal-swatch is-o{{ $i }}"></span>
            @endfor
            <span>Full</span>

            <span class="nv-mcal-legend-gap"></span>

            <span class="nv-legend-item"><b class="is-in">&#9660;</b> arriving</span>
            <span class="nv-legend-item"><b class="is-out">&#9650;</b> leaving</span>
            <span class="nv-legend-item"><b class="is-blk">&#9632;</b> blocked</span>
        </div>
    </x-card>
</div>
