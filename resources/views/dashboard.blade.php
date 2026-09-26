@extends('layouts.app')

@section('title', 'Dashboard')

@php
    use Carbon\CarbonImmutable;

    $greeting = now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening');
    $canSeeRooms = can_do('front-office/room-calendar', 'view');
@endphp

@section('content')
    <x-page-header
        :title="'Good ' . $greeting . ', ' . str($me->name ?: $me->username)->before(' ')"
        :subtitle="$headline
            ? 'The house on ' . CarbonImmutable::parse($date)->format('l, d F Y') . '.'
            : 'Set up a branch and some rooms and this screen fills itself in.'"
    >
        <x-slot:actions>
            @if ($headline)
                <form method="GET" class="nv-date-jump">
                    <input type="date" name="date" value="{{ $date }}" class="nv-input nv-input-sm"
                           aria-label="Show the house on this date" onchange="this.form.submit()" />
                    @if ($date !== today()->toDateString())
                        <a href="{{ route('dashboard') }}" class="nv-btn nv-btn-ghost nv-btn-sm">Today</a>
                    @endif
                </form>

                @canView('front-office/room-calendar')
                    <a href="{{ route('front-office.room-calendar', ['date' => $date]) }}" class="nv-btn nv-btn-outline">
                        <x-icon name="grid" /> Room Calendar
                    </a>
                @endCanView
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($headline)
        {{-- ── AI Copilot ─────────────────────────────────────────────────── --}}
        <div class="nv-ai-banner">
            <span class="nv-ai-banner-icon"><x-icon name="sparkles" :size="20" /></span>
            <span class="nv-ai-banner-text">
                <strong>AI Hotel Copilot</strong>
                <span>Ask about today's numbers, or see what it has already flagged below.</span>
            </span>
            <a href="#ai-assistant" class="nv-btn nv-btn-sm"><x-icon name="arrow-right" /> Ask now</a>
        </div>

        {{-- ── What today is worth ───────────────────────────────────────── --}}
        <div class="nv-grid nv-grid-6 nv-mt">
            <x-stat label="Occupancy" icon="activity" tone="primary"
                    :value="($headline['rooms'] ? round($headline['occupied'] / $headline['rooms'] * 100) : 0) . '%'"
                    :caption="$headline['occupied'] . ' of ' . $headline['rooms'] . ' rooms'" />
            <x-stat label="Revenue Today" icon="wallet" tone="success"
                    :value="'₹ ' . number_format($today->revenue ?? 0)" />
            <x-stat label="ADR" icon="trending-up" tone="info" caption="Average daily rate"
                    :value="'₹ ' . number_format($today->adr ?? 0)" />
            <x-stat label="RevPAR" icon="chart" tone="info" caption="Revenue per available room"
                    :value="'₹ ' . number_format($today->revpar ?? 0)" />
            <x-stat label="In-house Guests" icon="users" tone="primary" :value="$overview['guests']" />
            <x-stat label="Settlements Pending" icon="credit-card" :tone="$overview['pending'] > 0 ? 'warning' : 'primary'"
                    :value="$overview['pending']" />
        </div>

        {{-- ── What the house is doing ───────────────────────────────────── --}}
        <div class="nv-grid nv-grid-6 nv-mt">
            <x-stat label="Occupied Rooms" :value="$headline['occupied']" icon="lock" tone="danger"
                    :caption="$headline['rooms'] . ' rooms in all'" />
            <x-stat label="Vacant Rooms" :value="$headline['vacant']" icon="home" tone="success"
                    caption="free to sell tonight" />
            <x-stat label="Expected Arrival" :value="$headline['expected_arrival']" icon="arrow-down" tone="info" />
            <x-stat label="Expected Departure" :value="$headline['expected_departure']" icon="arrow-up" tone="warning" />
            <x-stat label="Checked In" :value="$headline['checked_in']" icon="check-circle" tone="success" />
            <x-stat label="Checked Out" :value="$headline['checked_out']" icon="logout" tone="primary" />
        </div>

        {{-- ── Live Hotel View: List, or the isometric 3D floor view ──────── --}}
        @php
            $floors = $categories
                ->flatMap(fn ($category) => $category['rooms'])
                ->groupBy(fn ($room) => $room->floor !== null && $room->floor !== '' ? $room->floor : 'Ground')
                ->sortKeys();
        @endphp

        <div class="nv-mt">
            <x-card title="Live Hotel View" subtitle="Every room, right now" flush data-tabs>
                <x-slot:actions>
                    <div class="nv-tabs" style="margin-right:8px">
                        <button type="button" class="nv-tab is-active" data-tab="list">List View</button>
                        <button type="button" class="nv-tab" data-tab="iso">3D View</button>
                    </div>

                    <button type="button" class="nv-btn nv-btn-ghost nv-btn-sm" data-collapse-all hidden>
                        <span data-collapse-label>Collapse all</span>
                    </button>
                </x-slot:actions>

                <div class="nv-tab-panel is-active" data-tab-panel="list">
                <div class="nv-legend nv-legend-pad">
                    @foreach ($states as $key => $label)
                        @continue(($legend[$key] ?? 0) === 0)
                        <span class="nv-legend-item">
                            <span class="nv-legend-key is-{{ str_replace('_', '-', $key) }}"></span>
                            {{ $label }} <b>{{ $legend[$key] }}</b>
                        </span>
                    @endforeach
                </div>

                @forelse ($categories as $category)
                    <details class="nv-cat" open data-outlet-block>
                        <summary class="nv-cat-bar">
                            <span class="nv-cat-name">
                                <x-icon name="chevron-right" />
                                {{ $category['name'] }}
                            </span>

                            <span class="nv-cat-counts">
                                <span class="nv-pill">{{ $category['counts']['total'] }} rooms</span>
                                <span class="nv-pill is-checkin">{{ $category['counts']['occupied'] }} occupied</span>
                                <span class="nv-pill is-clean">{{ $category['counts']['vacant'] }} vacant</span>
                                @if ($category['counts']['blocked'])
                                    <span class="nv-pill is-blocked">{{ $category['counts']['blocked'] }} blocked</span>
                                @endif

                                <span class="nv-cat-meter" role="img"
                                      aria-label="{{ $category['occupancy'] }} per cent occupied">
                                    <span style="width:{{ $category['occupancy'] }}%"></span>
                                </span>
                                <b class="nv-cat-pct">{{ $category['occupancy'] }}%</b>
                            </span>
                        </summary>

                        <div class="nv-room-grid">
                            @foreach ($category['rooms'] as $room)
                                @php
                                    $cell = $board[$room->id];
                                    $state = $cell['state'];
                                    $stay = $cell['check_in'];
                                    $booking = $cell['booking'];
                                    $who = $stay->guest_name
                                        ?? trim(($booking->first_name ?? '') . ' ' . ($booking->last_name ?? ''));
                                    $tag = 'is-' . str_replace('_', '-', $state);
                                @endphp

                                <{{ $canSeeRooms ? 'a' : 'div' }}
                                    @class(['nv-room', $tag])
                                    @if ($canSeeRooms)
                                        href="{{ route('front-office.room-calendar', ['date' => $date, 'room' => $room->id]) }}"
                                    @endif
                                    title="{{ $room->room_no }} — {{ $states[$state] }}">

                                    <span class="nv-room-head">
                                        <b>{{ $room->room_no }}</b>
                                        <span class="nv-room-state">{{ $states[$state] }}</span>
                                    </span>

                                    <span class="nv-room-type">{{ $room->type?->name ?: ($room->category?->name ?: '—') }}</span>

                                    @if ($who)
                                        <span class="nv-room-guest">{{ $who }}</span>

                                        @if ($stay)
                                            <span class="nv-room-dates">
                                                {{ CarbonImmutable::parse($stay->expected_checkout_date)->format('d M') }} out
                                            </span>
                                        @elseif ($booking)
                                            <span class="nv-room-dates">
                                                {{ CarbonImmutable::parse($booking->arrival_date)->format('d M') }} –
                                                {{ CarbonImmutable::parse($booking->checkout_date)->format('d M') }}
                                            </span>
                                        @endif
                                    @else
                                        <span class="nv-room-guest is-empty">
                                            {{ $room->floor ? 'Floor ' . $room->floor : 'No guest' }}
                                        </span>
                                    @endif
                                </{{ $canSeeRooms ? 'a' : 'div' }}>
                            @endforeach
                        </div>
                    </details>
                @empty
                    <div class="nv-empty">
                        <span class="nv-empty-icon"><x-icon name="home" /></span>
                        <strong>No rooms yet</strong>
                        <p>Add rooms under Masters → Room and the whole house appears here.</p>
                        @canView('masters')
                            <a href="{{ route('masters.index', 'room') }}" class="nv-btn nv-btn-primary">
                                <x-icon name="plus" /> Add rooms
                            </a>
                        @endCanView
                    </div>
                @endforelse
                </div>

                <div class="nv-tab-panel" data-tab-panel="iso">
                    @if ($floors->isEmpty())
                        <div class="nv-empty">
                            <span class="nv-empty-icon"><x-icon name="home" /></span>
                            <strong>No rooms yet</strong>
                        </div>
                    @else
                        <div class="nv-iso-card">
                            <div class="nv-iso-stage">
                                @foreach ($floors as $floorKey => $rooms)
                                    <div class="nv-iso-grid" data-floor-panel="{{ $floorKey }}"
                                         @if (! $loop->first) hidden @endif>
                                        @foreach ($rooms as $room)
                                            @php
                                                $cell = $board[$room->id];
                                                $state = $cell['state'];
                                                $tag = 'is-' . str_replace('_', '-', $state);
                                                $stay = $cell['check_in'];
                                                $booking = $cell['booking'];
                                                $who = $stay->guest_name
                                                    ?? trim(($booking->first_name ?? '') . ' ' . ($booking->last_name ?? ''));
                                            @endphp

                                            <{{ $canSeeRooms ? 'a' : 'div' }}
                                                @class(['nv-iso-room', $tag])
                                                @if ($canSeeRooms)
                                                    href="{{ route('front-office.room-calendar', ['date' => $date, 'room' => $room->id]) }}"
                                                @endif
                                                title="{{ $room->room_no }} — {{ $states[$state] }}{{ $who ? ' · ' . $who : '' }}">
                                                <b>{{ $room->room_no }}</b>
                                                <span>{{ $states[$state] }}</span>
                                            </{{ $canSeeRooms ? 'a' : 'div' }}>
                                        @endforeach
                                    </div>
                                @endforeach

                                <div class="nv-iso-legend">
                                    @foreach ($states as $key => $label)
                                        @continue(($legend[$key] ?? 0) === 0)
                                        <span class="nv-legend-item">
                                            <span class="nv-legend-key is-{{ str_replace('_', '-', $key) }}"></span>
                                            {{ $label }} <b>{{ $legend[$key] }}</b>
                                        </span>
                                    @endforeach
                                </div>
                            </div>

                            <div class="nv-iso-floor-list">
                                @foreach ($floors as $floorKey => $rooms)
                                    <button type="button" @class(['nv-iso-floor-btn', 'is-active' => $loop->first])
                                            data-floor-select="{{ $floorKey }}">
                                        Floor {{ $floorKey }} <span style="opacity:.65">· {{ $rooms->count() }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </x-card>
        </div>

        {{-- ── AI Insights, and the AI Assistant ───────────────────────────── --}}
        <div class="nv-grid nv-grid-2 nv-mt">
            <x-card title="AI Insights" subtitle="What today's numbers are already saying">
                @if (count($insights))
                    <div class="nv-insight-list">
                        @foreach ($insights as $insight)
                            <div @class(['nv-insight', 'is-' . $insight['tone']])>
                                <span class="nv-insight-icon"><x-icon :name="$insight['icon']" /></span>
                                <div class="nv-insight-body">
                                    <strong>{{ $insight['title'] }}</strong>
                                    <p>{{ $insight['detail'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="nv-insight-empty">Nothing needs attention right now — the house is quiet.</div>
                @endif
            </x-card>

            <x-card title="AI Assistant" subtitle="Ask about today's house" id="ai-assistant">
                <div class="nv-ai-chat" data-ai-chat data-ai-endpoint="{{ route('ai.chat') }}">
                    <div class="nv-ai-chat-log" data-ai-log>
                        <div class="nv-ai-msg is-ai">Hi {{ str($me->name ?: $me->username)->before(' ') }} — ask me about today's occupancy, arrivals, or revenue.</div>
                    </div>

                    <div class="nv-ai-quick">
                        <button type="button" class="nv-ai-chip" data-ai-quick="How is occupancy looking today?">Occupancy today?</button>
                        <button type="button" class="nv-ai-chip" data-ai-quick="What's today's revenue and ADR?">Revenue &amp; ADR</button>
                        <button type="button" class="nv-ai-chip" data-ai-quick="Anything I should be worried about right now?">Anything to worry about?</button>
                    </div>

                    <form class="nv-ai-input-row" data-ai-form>
                        <input type="text" class="nv-input" data-ai-input placeholder="Ask the AI Assistant…" autocomplete="off" />
                        <button type="submit" class="nv-btn nv-btn-primary nv-btn-sm"><x-icon name="arrow-right" /></button>
                    </form>
                </div>
            </x-card>
        </div>

        {{-- ── Overview and the fortnight ahead ───────────────────────────── --}}
        @php
            $slices = [
                ['key' => 'occupied', 'label' => 'Occupied', 'value' => $headline['occupied'], 'class' => 'is-checkin'],
                ['key' => 'vacant', 'label' => 'Vacant', 'value' => $headline['vacant'], 'class' => 'is-clean'],
                ['key' => 'blocked', 'label' => 'Blocked / repair', 'value' => $headline['blocked'], 'class' => 'is-blocked'],
            ];
            $sliceTotal = collect($slices)->sum('value') ?: 1;
            $circumference = 2 * M_PI * 54;
            $offset = 0;
        @endphp

        <div class="nv-grid nv-grid-2 nv-mt">
            <x-card title="Overview" :subtitle="'In the house right now'">
                <div class="nv-rd-row">
                    <svg viewBox="0 0 140 140" class="nv-rd" role="img"
                         aria-label="Occupied {{ $headline['occupied'] }}, vacant {{ $headline['vacant'] }}, blocked {{ $headline['blocked'] }} of {{ $headline['rooms'] }} rooms">
                        {{-- Drawn as one arc per slice: a circle whose dash runs
                             for its share and then stops. No library, no canvas —
                             and it prints. --}}
                        <circle cx="70" cy="70" r="54" class="nv-rd-track" />

                        @foreach ($slices as $slice)
                            @php
                                $share = $slice['value'] / $sliceTotal;
                                $length = $share * $circumference;
                            @endphp

                            @if ($slice['value'] > 0)
                                <circle cx="70" cy="70" r="54"
                                        @class(['nv-rd-arc', $slice['class']])
                                        stroke-dasharray="{{ round($length, 2) }} {{ round($circumference - $length, 2) }}"
                                        stroke-dashoffset="{{ round(-$offset, 2) }}"
                                        transform="rotate(-90 70 70)" />
                            @endif

                            @php $offset += $length; @endphp
                        @endforeach

                        <text x="70" y="65" class="nv-rd-value">{{ $headline['rooms'] }}</text>
                        <text x="70" y="84" class="nv-rd-label">rooms</text>
                    </svg>

                    <div class="nv-rd-legend">
                        @foreach ($slices as $slice)
                            <div class="nv-rd-key">
                                <span @class(['nv-legend-key', $slice['class']])></span>
                                <span>{{ $slice['label'] }}</span>
                                <b>{{ $slice['value'] }}</b>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="nv-mini-stats">
                    <div><b>{{ $overview['guests'] }}</b><span>In-house guests</span></div>
                    <div><b>{{ $overview['stays'] }}</b><span>Open folios</span></div>
                    <div><b>{{ $overview['groups'] }}</b><span>In-house groups</span></div>
                    <div @class(['is-alert' => $overview['pending'] > 0])>
                        <b>{{ $overview['pending'] }}</b><span>Settlements pending</span>
                    </div>
                </div>
            </x-card>

            <x-card title="Rooms Available" subtitle="Free to sell, the next fortnight">
                @php
                    $maxFree = max(1, collect($availability)->max('free'));
                    $barW = 720 / max(1, count($availability));
                @endphp

                <svg viewBox="0 0 720 240" class="nv-av-bars" role="img"
                     aria-label="Rooms free to sell over the next {{ count($availability) }} nights">
                    {{-- Four gridlines and nothing else: the numbers are on the
                         bars, so the axis only has to give the eye a datum. --}}
                    @for ($i = 0; $i <= 4; $i++)
                        @php $y = 200 - ($i / 4) * 180; @endphp
                        <line x1="34" x2="716" y1="{{ $y }}" y2="{{ $y }}" class="nv-av-bar-grid" />
                        <text x="28" y="{{ $y + 4 }}" class="nv-av-bar-axis">{{ round($maxFree * $i / 4) }}</text>
                    @endfor

                    @foreach ($availability as $i => $night)
                        @php
                            $h = $maxFree ? ($night['free'] / $maxFree) * 180 : 0;
                            $x = 38 + $i * (678 / count($availability));
                            $w = (678 / count($availability)) - 6;
                        @endphp

                        <rect x="{{ round($x, 1) }}" y="{{ round(200 - $h, 1) }}"
                              width="{{ round($w, 1) }}" height="{{ round(max($h, 1), 1) }}"
                              rx="4" class="nv-av-bar" />

                        <text x="{{ round($x + $w / 2, 1) }}" y="{{ round(200 - $h - 6, 1) }}"
                              class="nv-av-bar-value">{{ $night['free'] }}</text>

                        <text x="{{ round($x + $w / 2, 1) }}" y="218" class="nv-av-bar-label">{{ $night['day'] }}</text>
                        <text x="{{ round($x + $w / 2, 1) }}" y="232" class="nv-av-bar-label is-dim">
                            {{ CarbonImmutable::parse($night['date'])->format('d') }}
                        </text>
                    @endforeach
                </svg>

                <details class="nv-table-view">
                    <summary>Show as a table</summary>
                    <div class="nv-table-wrap">
                        <table class="nv-table">
                            <thead><tr><th>Night</th><th class="is-num">Rooms free</th></tr></thead>
                            <tbody>
                                @foreach ($availability as $night)
                                    <tr>
                                        <td>{{ CarbonImmutable::parse($night['date'])->format('D, d M Y') }}</td>
                                        <td class="is-num">{{ $night['free'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            </x-card>
        </div>

        {{-- ── Guest feedback and the next move ───────────────────────────── --}}
        <div class="nv-grid nv-grid-2 nv-mt">
            <x-card title="Guest Feedback" subtitle="AI-read sentiment from every survey">
                @if ($feedback)
                    @php
                        $fbCircumference = 2 * M_PI * 54;
                        $fbShare = $feedback['average'] !== null ? min(1, $feedback['average'] / 5) : 0;
                        $fbLength = $fbShare * $fbCircumference;
                        $fbTone = match (true) {
                            $feedback['average'] === null => '--nv-muted',
                            $feedback['average'] >= 5 => '--nv-success',
                            $feedback['average'] >= 4 => '--nv-info',
                            $feedback['average'] >= 3 => '--nv-warning',
                            default => '--nv-danger',
                        };
                    @endphp

                    <div class="nv-rd-row">
                        <svg viewBox="0 0 140 140" class="nv-rd" role="img"
                             aria-label="Average guest score {{ $feedback['average'] ?? 'not available' }} out of 5">
                            <circle cx="70" cy="70" r="54" class="nv-rd-track" />

                            @if ($feedback['average'] !== null)
                                <circle cx="70" cy="70" r="54" class="nv-rd-arc"
                                        style="stroke: var({{ $fbTone }})"
                                        stroke-dasharray="{{ round($fbLength, 2) }} {{ round($fbCircumference - $fbLength, 2) }}"
                                        transform="rotate(-90 70 70)" />
                            @endif

                            <text x="70" y="65" class="nv-rd-value">{{ $feedback['average'] ?? '—' }}</text>
                            <text x="70" y="84" class="nv-rd-label">out of 5</text>
                        </svg>

                        <div class="nv-rd-legend">
                            @foreach ($feedback['areas'] as $area)
                                <div class="nv-fb-area">
                                    <span class="nv-fb-area-label">{{ $area['label'] }}</span>
                                    <span class="nv-cat-meter" role="img"
                                          aria-label="{{ $area['label'] }} {{ $area['average'] ?? 'not rated' }} out of 5">
                                        <span style="width:{{ $area['average'] !== null ? round($area['average'] / 5 * 100) : 0 }}%"></span>
                                    </span>
                                    <b class="nv-cat-pct">{{ $area['average'] ?? '—' }}</b>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="nv-mini-stats">
                        <div><b>{{ $feedback['count'] }}</b><span>Responses on file</span></div>
                        <div @class(['is-alert' => $feedback['needs_attention'] > 0])>
                            <b>{{ $feedback['needs_attention'] }}</b><span>Need a follow-up</span>
                        </div>
                    </div>
                @else
                    <div class="nv-insight-empty">No guest feedback answered yet — scores will show up here once guests start responding.</div>
                @endif
            </x-card>

            <x-card title="Quick Actions" subtitle="Straight to the desk's next move">
                <div class="nv-qa-row">
                    @if (can_do('reservation/new-reservation', 'add'))
                        <a href="{{ route('reservation.create') }}" class="nv-btn nv-btn-outline"><x-icon name="plus" /> New Reservation</a>
                    @endif

                    @if (can_do('front-office/check-in-guest', 'add'))
                        <a href="{{ route('front-office.check-in-guest') }}" class="nv-btn nv-btn-outline"><x-icon name="check" /> Check-in</a>
                    @endif

                    @canView('front-office/check-out-guest')
                        <a href="{{ url('front-office/check-out-guest') }}" class="nv-btn nv-btn-outline"><x-icon name="logout" /> Check-out</a>
                    @endCanView

                    @canView('front-office/room-calendar')
                        <a href="{{ route('front-office.room-calendar') }}" class="nv-btn nv-btn-outline"><x-icon name="calendar" /> Room Calendar</a>
                    @endCanView
                </div>
            </x-card>
        </div>

        {{-- ── The month ──────────────────────────────────────────────────── --}}
        @include('partials.month-calendar', [
            'month' => $month,
            'calendar' => $calendar,
            'totalRooms' => $totalRooms,
            'today' => $date,
        ])

        {{-- ── Who is moving today ────────────────────────────────────────── --}}
        <div class="nv-grid nv-grid-2 nv-mt">
            <x-card title="Today's Arrival" :subtitle="$arrivals->count() . ' booked in'" flush>
                @if ($arrivals->count())
                    <div class="nv-table-wrap">
                        <table class="nv-table">
                            <thead>
                                <tr><th>Guest</th><th>Phone</th><th>Room</th><th>Reservation</th><th>Nights</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($arrivals as $row)
                                    <tr>
                                        <td><strong>{{ trim($row->title . ' ' . $row->first_name . ' ' . $row->last_name) }}</strong></td>
                                        <td class="nv-nowrap">{{ $row->mobile ?: '—' }}</td>
                                        <td>{{ $row->room_no ?: 'not allotted' }}</td>
                                        <td class="nv-nowrap">{{ $row->reservation_no }}</td>
                                        <td class="is-num">
                                            {{ CarbonImmutable::parse($row->arrival_date)->diffInDays(CarbonImmutable::parse($row->checkout_date)) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="nv-pad-note nv-muted">Nobody is due in today.</p>
                @endif
            </x-card>

            <x-card title="Today's Departure" :subtitle="$departures->count() . ' due out'" flush>
                @if ($departures->count())
                    <div class="nv-table-wrap">
                        <table class="nv-table">
                            <thead>
                                <tr><th>Guest</th><th>Phone</th><th>Room</th><th>Folio</th><th>Nights</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($departures as $row)
                                    <tr>
                                        <td><strong>{{ $row->guest_name }}</strong></td>
                                        <td class="nv-nowrap">{{ $row->mobile ?: '—' }}</td>
                                        <td>{{ $row->room_no ?: '—' }}</td>
                                        <td class="nv-nowrap">{{ $row->folio_no }}</td>
                                        <td class="is-num">
                                            {{ CarbonImmutable::parse($row->checkin_date)->diffInDays(CarbonImmutable::parse($row->expected_checkout_date)) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="nv-pad-note nv-muted">Nobody is due out today.</p>
                @endif
            </x-card>
        </div>
    @endif

    {{-- ── The system itself, for whoever looks after it ──────────────────── --}}
    @admin
        <div class="nv-mt">
            <x-card title="This installation" subtitle="Only administrators see this">
                <div class="nv-mini-stats">
                    <div><b>{{ $system['users'] }}</b><span>Users · {{ $system['active_users'] }} active</span></div>
                    <div><b>{{ $system['roles'] }}</b><span>Roles</span></div>
                    <div><b>{{ $system['branches'] }}</b><span>Branches</span></div>
                    <div><b>{{ $system['modules'] }}</b><span>Modules · {{ $system['submodules'] }} screens</span></div>
                </div>
            </x-card>
        </div>
    @endAdmin
@endsection

@push('scripts')
    <script src="{{ asset('js/pos-tables.js') }}?v={{ filemtime(public_path('js/pos-tables.js')) }}" defer></script>
@endpush
