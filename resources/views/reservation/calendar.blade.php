@extends('layouts.app')

@section('title', 'Reservation Calendar New')

@php
    $keys = $dates->map(fn ($d) => $d->toDateString());
    $params = fn (array $extra = []) => array_merge(['date' => $start->toDateString(), 'days' => $days], $extra);

    $legend = [
        'clean' => ['Cleaned', 'is-clean'],
        'dirty' => ['Dirty', 'is-dirty'],
        'inspected' => ['Inspected', 'is-inspected'],
        'out_of_order' => ['Out of order', 'is-ooo'],
        'checked_in' => ['Checked in', 'is-checked_in'],
        'confirmed' => ['Reservation', 'is-confirmed'],
        'tentative' => ['Tentative', 'is-tentative'],
        'blocked' => ['Blocked', 'is-blocked'],
    ];

    $hkTitle = ['clean' => 'Clean', 'dirty' => 'Dirty', 'inspected' => 'Inspected', 'out_of_order' => 'Out of order'];

    // Moving a booking's dates is an edit of that booking.
    $canMove = can_do('reservation/new-reservation', 'edit');
@endphp

@section('content')
    <x-page-header
        title="Reservation Calendar New"
        subtitle="Every room, every night. Drag across free nights to book or block; drop a waiting booking onto a room to allot it."
        :crumbs="['Home' => url('/'), 'Reservations' => route('reservation.index'), 'Calendar']"
    >
        <x-slot:actions>
            <a href="{{ route('reservation.status', $params()) }}" class="nv-btn nv-btn-outline">
                <x-icon name="grid" /> Status View
            </a>

            @canAdd('reservation/new-reservation')
                <a href="{{ route('reservation.create') }}" class="nv-btn nv-btn-primary">
                    <x-icon name="plus" /> New Reservation
                </a>
            @endCanAdd
        </x-slot:actions>
    </x-page-header>

    @if ($chart['conflicts'])
        <div style="margin-bottom:18px">
            <x-alert tone="danger" title="{{ count($chart['conflicts']) }} double booking(s)">
                {{ implode(' · ', array_slice($chart['conflicts'], 0, 3)) }}
            </x-alert>
        </div>
    @endif

    {{-- ── Waiting to be allotted ───────────────────────────────────────── --}}
    @if ($chart['unassigned']->count())
        <div style="margin-bottom:18px">
            <x-card title="Waiting for a room"
                    subtitle="These bookings have no room yet. Pick one and it goes straight onto the chart.">
                <x-slot:actions>
                    <x-badge tone="warning">{{ $chart['unassigned']->count() }}</x-badge>
                </x-slot:actions>

                <div class="nv-table-wrap">
                    <table class="nv-table nv-table-compact">
                        <thead>
                            <tr>
                                <th>Reservation</th>
                                <th>Guest</th>
                                <th>Wants</th>
                                <th>Stay</th>
                                <th class="is-num">Rms</th>
                                <th class="is-end">Allot</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($chart['unassigned'] as $line)
                                @php
                                    $from = substr($line->arrival_date, 0, 10);
                                    $to = substr($line->checkout_date, 0, 10);
                                @endphp

                                <tr>
                                    <td class="nv-nowrap">
                                        @canView('reservation/booking-details')
                                            <a href="{{ route('reservation.show', $line->reservation_id) }}"
                                               class="nv-mono" style="color:var(--nv-primary);font-weight:650">{{ $line->reservation_no }}</a>
                                        @else
                                            <strong class="nv-mono">{{ $line->reservation_no }}</strong>
                                        @endCanView
                                    </td>
                                    <td>
                                        <strong>{{ trim(($line->title ? $line->title . ' ' : '') . $line->first_name . ' ' . $line->last_name) }}</strong>
                                        <span class="nv-sub">{{ $line->mobile }}</span>
                                    </td>
                                    <td>
                                        {{ $line->room_type ?: '—' }}
                                        <span class="nv-sub">{{ $line->category }}</span>
                                    </td>
                                    <td class="nv-nowrap nv-muted">
                                        {{ \Illuminate\Support\Carbon::parse($from)->format('d M') }} →
                                        {{ \Illuminate\Support\Carbon::parse($to)->format('d M') }}
                                    </td>
                                    <td class="is-num">{{ $line->no_of_rooms }}</td>
                                    <td class="is-end">
                                        @canEdit('reservation/new-reservation')
                                            <form method="POST" action="{{ route('reservation.calendar.assign') }}"
                                                  class="nv-assign" data-assign
                                                  data-from="{{ $from }}" data-to="{{ $to }}"
                                                  data-room-type="{{ $line->room_type_id }}">
                                                @csrf
                                                <input type="hidden" name="line_id" value="{{ $line->id }}" />

                                                <select name="room_id" class="nv-select nv-select-sm" data-assign-room required>
                                                    <option value="">Loading rooms…</option>
                                                </select>

                                                <button type="submit" class="nv-btn nv-btn-primary nv-btn-sm">Allot</button>
                                            </form>
                                        @endCanEdit
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>
    @endif

    {{-- ── The chart ────────────────────────────────────────────────────── --}}
    <x-card flush>
        <form method="GET" class="nv-toolbar">
            <a href="{{ route('reservation.calendar', $params(['date' => $prev])) }}" class="nv-btn nv-btn-outline">
                <x-icon name="chevron-left" /> Previous
            </a>

            <input type="date" name="date" value="{{ $start->toDateString() }}" class="nv-input" style="width:170px" />

            <select name="days" class="nv-select" style="width:120px">
                @foreach ($spans as $value => $label)
                    <option value="{{ $value }}" @selected($days === $value)>{{ $label }}</option>
                @endforeach
            </select>

            <button type="submit" class="nv-btn nv-btn-primary">Go</button>

            <a href="{{ route('reservation.calendar', $params(['date' => $next])) }}" class="nv-btn nv-btn-outline">
                Next <x-icon name="chevron-right" />
            </a>

            <a href="{{ route('reservation.calendar', ['days' => $days]) }}" class="nv-btn nv-btn-ghost">Today</a>
        </form>

        <div class="nv-tape-legend">
            @foreach ($legend as $key => [$label, $class])
                <span>
                    <i class="nv-legend-key {{ $class }}"></i>
                    {{ $label }} ({{ $chart['legend'][$key] ?? 0 }})
                </span>
            @endforeach
        </div>

        @if ($chart['rooms'] === 0)
            <div class="nv-empty">
                <span class="nv-empty-icon"><x-icon name="home" /></span>
                <strong>No rooms yet</strong>
                <p>
                    Add some on the
                    <a href="{{ route('masters.index', 'room') }}" style="color:var(--nv-primary);font-weight:600">Rooms</a>
                    screen and this chart fills itself in.
                </p>
            </div>
        @else
            <div class="nv-table-wrap nv-tape-wrap">
                <table class="nv-table nv-tape" data-tape
                       data-can-book="{{ can_do('reservation/new-reservation', 'add') ? '1' : '0' }}">
                    <thead>
                        <tr>
                            <th class="nv-tape-head">Room</th>

                            @foreach ($dates as $date)
                                {{-- data-col-date lets a drag work out which night the
                                     pointer is over, even where a bar's colspan hides
                                     the individual cells underneath. --}}
                                <th data-col-date="{{ $date->toDateString() }}"
                                    @class(['is-center', 'is-today' => $date->toDateString() === $today,
                                            'is-weekend' => $date->isWeekend()])>
                                    <span class="nv-cal-day">{{ $date->format('j M') }}</span>
                                    <span class="nv-cal-dow">{{ $date->format('D') }}</span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($chart['categories'] as $categoryName => $rooms)
                            <tr class="nv-tape-group" data-group="cat-{{ $loop->index }}">
                                <th class="nv-tape-head">
                                    <button type="button" class="nv-tape-toggle" data-group-toggle="cat-{{ $loop->index }}"
                                            aria-expanded="true" aria-label="Collapse {{ $categoryName }}">
                                        <x-icon name="chevron-down" />
                                    </button>
                                    {{ $categoryName }}
                                    <span class="nv-sub">{{ $rooms->count() }} room(s)</span>
                                </th>

                                @foreach ($keys as $key)
                                    <td @class(['is-center', 'is-today' => $key === $today])>
                                        <b class="nv-tape-count">{{ $rooms->count() }}</b>
                                    </td>
                                @endforeach
                            </tr>

                            @foreach ($rooms as $room)
                                <tr data-in-group="cat-{{ $loop->parent->index }}"
                                    data-room-row="{{ $room->id }}"
                                    data-room-row-no="{{ $room->room_no }}">
                                    <th class="nv-tape-head is-room">
                                        <span class="nv-tape-room">
                                            <strong>{{ $room->room_no }}</strong>
                                            <span class="nv-sub">{{ $room->type_name ?: $categoryName }}</span>
                                        </span>

                                        <i @class(['nv-hk', 'is-' . $room->housekeeping_status])
                                           title="{{ $hkTitle[$room->housekeeping_status] ?? $room->housekeeping_status }}"></i>
                                    </th>

                                    @foreach ($chart['rows'][$room->id] as $cell)
                                        @if ($cell['type'] === 'free')
                                            <td class="nv-tape-cell is-free"
                                                data-room="{{ $room->id }}"
                                                data-room-no="{{ $room->room_no }}"
                                                data-date="{{ $cell['date'] }}"
                                                @class(['is-today' => $cell['date'] === $today])></td>
                                        @else
                                            <td class="nv-tape-cell" colspan="{{ $cell['span'] }}">
                                                @php
                                                    $isBlock = $cell['kind'] === 'blocked';
                                                    // Only a booking nobody has arrived for can be dragged
                                                    // to other nights, and only when the whole stay is
                                                    // on screen — see RoomTimeline.
                                                    $canDrag = $canMove && ($cell['movable'] ?? false);
                                                    $title = $isBlock
                                                        ? $cell['label'] . ' · ' . $cell['from'] . ' → ' . $cell['to']
                                                        : $cell['reservation_no'] . ' · ' . $cell['label']
                                                            . ' · ' . $cell['nights'] . ' night(s)'
                                                            . ($canDrag ? ' · drag to change the dates' : '');
                                                @endphp

                                                <span @class([
                                                        'nv-bar',
                                                        'is-' . $cell['kind'],
                                                        'is-movable' => $canDrag,
                                                        'is-open-left' => $cell['continues_left'],
                                                        'is-open-right' => $cell['continues_right'],
                                                      ])
                                                      title="{{ $title }}"
                                                      @if ($canDrag) draggable="true" @endif
                                                      data-bar
                                                      data-bar-line="{{ $cell['line_id'] }}"
                                                      data-bar-movable="{{ $canDrag ? '1' : '0' }}"
                                                      data-bar-kind="{{ $cell['kind'] }}"
                                                      data-bar-label="{{ $cell['label'] }}"
                                                      data-bar-room="{{ $room->room_no }}"
                                                      data-bar-from="{{ $cell['from'] }}"
                                                      data-bar-to="{{ $cell['to'] }}"
                                                      data-bar-nights="{{ $cell['nights'] }}"
                                                      data-bar-reservation="{{ $cell['reservation_id'] }}"
                                                      data-bar-reservation-no="{{ $cell['reservation_no'] }}"
                                                      data-bar-mobile="{{ $cell['mobile'] }}"
                                                      data-bar-block="{{ $cell['block_id'] }}">
                                                    <span class="nv-bar-text">
                                                        {{ $isBlock ? $cell['label'] : $cell['label'] }}
                                                    </span>
                                                </span>
                                            </td>
                                        @endif
                                    @endforeach
                                </tr>
                            @endforeach
                        @endforeach

                        {{-- Footer rows --}}
                        <tr class="nv-cal-total">
                            <th class="nv-tape-head">Room availability</th>

                            @foreach ($keys as $key)
                                <td @class(['is-center', 'is-today' => $key === $today])>
                                    <span class="nv-tape-avail">
                                        <b>{{ $chart['footer'][$key]['free'] }}</b>/{{ $chart['footer'][$key]['occupied'] }}
                                    </span>
                                </td>
                            @endforeach
                        </tr>

                        <tr class="nv-cal-total">
                            <th class="nv-tape-head">Occupancy (%)</th>

                            @foreach ($keys as $key)
                                @php $pct = $chart['footer'][$key]['occupancy']; @endphp

                                <td @class(['is-center', 'is-num', 'is-today' => $key === $today])>
                                    <strong @style(['color:var(--nv-danger)' => $pct >= 90])>{{ $pct }}</strong>
                                </td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
        @endif

        <x-slot:footer>
            <p class="nv-muted" style="font-size:12.5px;line-height:1.6;margin:0">
                <strong>Room availability</strong> reads free / occupied. A night belongs to the arrival date, not the
                checkout date, so a room freed on the 9th is sellable on the 9th.
            </p>
        </x-slot:footer>
    </x-card>

    {{-- ── Drag-select popover ──────────────────────────────────────────── --}}
    <div class="nv-tape-pop" data-tape-pop hidden>
        <div class="nv-tape-pop-head">
            <div>
                <span>Arrival</span>
                <b data-pop-from>—</b>
            </div>
            <div>
                <span>Departure</span>
                <b data-pop-to>—</b>
            </div>
            <button type="button" class="nv-icon-btn nv-icon-btn-sm" data-pop-close aria-label="Close">
                <x-icon name="x" />
            </button>
        </div>

        <p class="nv-tape-pop-room">Room <b data-pop-room>—</b> · <span data-pop-nights>1</span> night(s)</p>

        <div class="nv-tape-pop-actions">
            @canAdd('reservation/new-reservation')
                <a href="#" class="nv-btn nv-btn-primary nv-btn-sm" data-pop-book>Reservation</a>
            @endCanAdd

            @canAdd('reservation/new-reservation')
                <button type="button" class="nv-btn nv-btn-outline nv-btn-sm" data-pop-block>Block Room</button>
            @endCanAdd
        </div>

        @canAdd('reservation/new-reservation')
            <form method="POST" action="{{ route('reservation.calendar.block') }}" class="nv-tape-pop-form" data-block-form hidden>
                @csrf
                <input type="hidden" name="room_id" data-block-room />
                <input type="hidden" name="from_date" data-block-from />
                <input type="hidden" name="to_date" data-block-to />

                <input type="text" name="reason" class="nv-input nv-input-sm" placeholder="Reason (repair, owner use…)" />

                {{-- The Monthly calendar counts these two on separate lines. --}}
                <select name="block_type" class="nv-select nv-input-sm">
                    <option value="management">Management block</option>
                    <option value="maintenance">Maintenance block</option>
                </select>

                <div class="nv-tape-pop-actions">
                    <button type="button" class="nv-btn nv-btn-ghost nv-btn-sm" data-block-cancel>Cancel</button>
                    <button type="submit" class="nv-btn nv-btn-danger nv-btn-sm">Block</button>
                </div>
            </form>
        @endCanAdd
    </div>

    {{-- ── Bar details ──────────────────────────────────────────────────── --}}
    <div class="nv-modal-backdrop" data-bar-modal>
        <div class="nv-modal" style="max-width:460px" role="dialog" aria-modal="true" aria-label="Booking">
            <div class="nv-modal-head">
                <strong data-bar-title>Booking</strong>
                <button type="button" class="nv-icon-btn" data-bar-close aria-label="Close">
                    <x-icon name="x" />
                </button>
            </div>

            <div class="nv-detail-list" data-bar-body></div>

            <div class="nv-actions" style="justify-content:flex-end;margin-top:18px">
                @canAdd('reservation/new-reservation')
                    <form method="POST" action="{{ route('reservation.calendar.unblock') }}" data-unblock hidden>
                        @csrf
                        <input type="hidden" name="block_id" data-unblock-id />
                        <button type="submit" class="nv-btn nv-btn-outline nv-btn-sm">Release room</button>
                    </form>
                @endCanAdd

                @canView('reservation/booking-details')
                    <a href="#" class="nv-btn nv-btn-primary nv-btn-sm" data-bar-open hidden>Open reservation</a>
                @endCanView
            </div>
        </div>
    </div>

    {{-- ── Move a booking to other nights ────────────────────────────────── --}}
    @if ($canMove)
        <div class="nv-modal-backdrop" data-move-modal>
            <div class="nv-modal" style="max-width:470px" role="dialog" aria-modal="true"
                 aria-labelledby="move-title">
                <div class="nv-modal-head">
                    <strong id="move-title">Move this booking?</strong>
                    <button type="button" class="nv-icon-btn" data-move-cancel aria-label="Close">
                        <x-icon name="x" />
                    </button>
                </div>

                <p class="nv-move-guest" data-move-guest></p>

                <div class="nv-move-swap">
                    <div class="nv-move-side">
                        <span class="nv-move-cap">From</span>
                        <strong data-move-old></strong>
                    </div>
                    <span class="nv-move-arrow"><x-icon name="arrow-right" /></span>
                    <div class="nv-move-side is-new">
                        <span class="nv-move-cap">To</span>
                        <strong data-move-new></strong>
                    </div>
                </div>

                <p class="nv-move-note" data-move-note></p>

                <form method="POST" action="{{ route('reservation.calendar.move') }}"
                      class="nv-actions" style="justify-content:flex-end;margin-top:18px">
                    @csrf
                    <input type="hidden" name="line_id" data-move-line />
                    <input type="hidden" name="room_id" data-move-room />
                    <input type="hidden" name="arrival_date" data-move-date />

                    <button type="button" class="nv-btn nv-btn-ghost" data-move-cancel>Cancel</button>
                    <button type="submit" class="nv-btn nv-btn-primary">Move booking</button>
                </form>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
@php
    // Built here rather than inline: @json cannot parse a nested quoted
    // argument, and a placeholder beats trimming an id off the end of a URL.
    $tapeBoot = [
        'bookUrl' => route('reservation.create'),
        'showUrl' => route('reservation.show', '__ID__'),
        'freeRoomsUrl' => route('reservation.calendar.free-rooms'),
        'canBook' => can_do('reservation/new-reservation', 'add'),
        'canMove' => $canMove,
    ];
@endphp

<script>
window.TAPE_BOOT = @json($tapeBoot);
</script>
<script src="{{ asset('js/room-calendar.js') }}?v={{ filemtime(public_path('js/room-calendar.js')) }}" defer></script>
@endpush
