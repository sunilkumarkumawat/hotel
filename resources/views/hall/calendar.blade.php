@extends('layouts.app')

@section('title', 'Hall Calendar')

@section('content')
    <x-page-header
        title="Hall Calendar"
        subtitle="A week of the diary. A wedding that runs Friday to Sunday shows on all three days."
        :crumbs="['Home' => url('/'), 'Banquet Hall' => route('hall.bookings'), 'Calendar']"
    >
        <x-slot:actions>
            <form method="GET" class="nv-actions">
                <a href="{{ route('hall.calendar', ['date' => \Carbon\CarbonImmutable::parse($start)->subWeek()->toDateString()]) }}"
                   class="nv-icon-btn" title="Previous week"><x-icon name="chevron-left" /></a>

                <input type="date" name="date" value="{{ $anchor }}" class="nv-input" style="width:160px"
                       onchange="this.form.submit()" aria-label="Week of" />

                <a href="{{ route('hall.calendar', ['date' => \Carbon\CarbonImmutable::parse($start)->addWeek()->toDateString()]) }}"
                   class="nv-icon-btn" title="Next week"><x-icon name="chevron-right" /></a>
            </form>

            @canAdd('hall/bookings')
                <a href="{{ route('hall.bookings.create', ['date' => $start]) }}" class="nv-btn nv-btn-primary">
                    <x-icon name="plus" /> New booking
                </a>
            @endCanAdd
        </x-slot:actions>
    </x-page-header>

    <div class="nv-mt">
        <x-card flush>
            @if ($halls->isEmpty())
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="grid" /></span>
                    <strong>No halls set up</strong>
                    <p>Add one under Banquet Hall → Halls first.</p>
                </div>
            @else
                <div class="nv-table-wrap">
                    <table class="nv-table nv-fac-week">
                        <thead>
                            <tr>
                                <th style="width:170px">Hall</th>
                                @foreach ($days as $day)
                                    <th @class(['is-today' => $day->isToday()])>
                                        {{ $day->format('D') }}
                                        <span class="nv-sub">{{ $day->format('d M') }}</span>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($halls as $hall)
                                <tr>
                                    <td>
                                        <strong>{{ $hall->name }}</strong>
                                        @if ($hall->capacity)
                                            <span class="nv-sub">{{ $hall->capacity }} seats</span>
                                        @endif
                                    </td>

                                    @foreach ($days as $day)
                                        @php $cell = $grid[$hall->id][$day->toDateString()] ?? []; @endphp

                                        <td class="nv-fac-week-cell">
                                            @forelse ($cell as $booking)
                                                <a class="nv-fac-chip is-{{ $booking->status }}"
                                                   href="{{ route('hall.bookings.edit', $booking->id) }}"
                                                   title="{{ $booking->guest_name }} · {{ $booking->event_type }}">
                                                    <strong>{{ \Illuminate\Support\Str::limit($booking->guest_name, 18) }}</strong>
                                                    <small>{{ \Carbon\CarbonImmutable::parse((string) $booking->from_time)->format('h:i A') }}</small>
                                                </a>
                                            @empty
                                                @canAdd('hall/bookings')
                                                    <a class="nv-fac-week-free"
                                                       href="{{ route('hall.bookings.create', ['date' => $day->toDateString()]) }}">free</a>
                                                @else
                                                    <span class="nv-fac-week-free">free</span>
                                                @endCanAdd
                                            @endforelse
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
    </div>
@endsection
