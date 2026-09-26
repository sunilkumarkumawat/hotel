{{--
    One room on the board.

    The rail down the left is the status colour, and it is the only thing on the
    tile a supervisor reads from across the room. Everything else is for when
    they have walked over to it.

    `draggable` and `data-targets` come from App\Support\HousekeepingBoard, and
    the server checks the same rule again on the way in — a rule that lives only
    in the browser is one a stale tab does not have to obey.
--}}

<article @class(['nv-hkb-tile', 'is-' . $tile['stage'], 'is-compact' => $compact])
         data-hk-tile
         data-room="{{ $tile['id'] }}"
         data-stage="{{ $tile['stage'] }}"
         data-targets="{{ implode(',', $tile['drop_targets']) }}"
         @if ($tile['draggable'] && can_do('house-keeping/board', 'edit')) draggable="true" @endif>

    <span class="nv-hkb-rail" aria-hidden="true"></span>

    <header class="nv-hkb-tile-head">
        <strong>{{ $tile['room_no'] }}</strong>

        @if ($tile['departing'])
            <span class="nv-hkb-flag is-out" title="The guest leaves today">Out today</span>
        @elseif ($tile['arriving'])
            <span class="nv-hkb-flag is-in" title="Somebody arrives into this room today">Arrival</span>
        @endif
    </header>

    @unless ($compact)
        <p class="nv-hkb-line">{{ $tile['type'] ?: '—' }}{{ $tile['floor'] ? ' · Floor ' . $tile['floor'] : '' }}</p>
    @endunless

    @if ($tile['guest'])
        <p class="nv-hkb-line">{{ \Illuminate\Support\Str::limit($tile['guest'], 22) }}{{ $tile['pax'] ? ' · ' . $tile['pax'] . 'p' : '' }}</p>
    @endif

    <p class="nv-hkb-line is-muted">
        {{ $tile['stage_label'] }}
        @if ($tile['since'])
            · {{ $tile['since'] }}
        @endif
    </p>

    @if ($tile['housekeeper'])
        <p class="nv-hkb-line is-muted">{{ $tile['housekeeper'] }}</p>
    @endif

    @canEdit('house-keeping/board')
        @unless ($tile['blocked'])
            <div class="nv-hkb-actions">
                @if ($tile['stage'] === 'dirty')
                    @include('house-keeping.partials.board-move', ['room' => $tile['id'], 'to' => 'cleaning', 'label' => 'Start'])
                @elseif ($tile['stage'] === 'cleaning')
                    @include('house-keeping.partials.board-move', ['room' => $tile['id'], 'to' => 'ready', 'label' => 'Done'])
                    @include('house-keeping.partials.board-move', ['room' => $tile['id'], 'to' => 'dirty', 'label' => 'Back'])
                @else
                    @include('house-keeping.partials.board-move', ['room' => $tile['id'], 'to' => 'dirty', 'label' => 'Mark dirty'])
                @endif
            </div>
        @endunless
    @endCanEdit
</article>
