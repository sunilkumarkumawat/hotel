{{--
    One table, or one room.

    The quick actions are the point of this tile, so they are built to be
    reachable three ways rather than one: the rail opens on hover, on keyboard
    focus, and on a tap of the ⋮ handle. A hover-only menu is a menu that does
    not exist on the touchscreen till by the kitchen door, which is the machine
    this screen is actually used on.

    Every action carries a word as well as an icon. Four unlabelled glyphs are
    four things a new waiter has to be taught; four labelled ones are four
    things they can read.

    Expects: $tile (from PosFloor), $mode, $outlet.
--}}

@php
    $busy = $tile->state !== 'free';
    $due = $tile->state === 'due';
    $isRoom = $mode === 'room';

    $open = $tile->order_id
        ? route('point-of-sale.pos.order', $tile->order_id)
        : null;
@endphp

<div @class(['nv-tile', 'is-' . $tile->state, 'is-room' => $isRoom])
     data-tile
     @if ($tile->opened_at) data-since="{{ $tile->opened_at }}" @endif>

    {{-- The headline: what it is, and the one number that matters. --}}
    @if ($open)
        <a href="{{ $open }}" class="nv-tile-face">
    @else
        <form method="POST"
              action="{{ $isRoom
                  ? route('point-of-sale.pos.room', $tile->check_in_id)
                  : route('point-of-sale.pos.table', $tile->id) }}">
            @csrf
            <input type="hidden" name="outlet" value="{{ $outlet->id }}" />
            <button type="submit" class="nv-tile-face" @disabled(! can_do('point-of-sale/pos', 'add'))>
    @endif

        @if ($due)
            <span class="nv-tile-due">
                <x-icon name="alert" /> Payment Due
            </span>
        @endif

        <span class="nv-tile-name">{{ $tile->name }}</span>

        <span class="nv-tile-sub">
            @if ($isRoom)
                {{ $tile->guest ?: 'In house' }}
            @elseif ($tile->capacity)
                {{ $tile->capacity }} covers
            @else
                &nbsp;
            @endif
        </span>

        @if ($busy)
            <span class="nv-tile-amount">₹ {{ number_format($tile->amount, 2) }}</span>

            <span class="nv-tile-meta">
                <span>{{ $tile->items }} item{{ $tile->items === 1 ? '' : 's' }}</span>
                @if ($tile->kots)
                    <span>KOT {{ $tile->kots }}</span>
                @endif
                @if ($tile->pax)
                    <span>{{ $tile->pax }} pax</span>
                @endif
            </span>

            {{--
                The running clock. The server writes the epoch into data-since
                and the browser counts from it, so it keeps ticking between
                page loads and does not depend on the till's own clock being
                right. Without a script it still shows the time it opened.
            --}}
            <span class="nv-tile-clock" data-clock aria-label="Open for">
                <x-icon name="clock" />
                {{-- Carbon 3 reads a bare timestamp as UTC, so the hotel's own zone is
                     named — otherwise this fallback is five and a half hours out. --}}
                <b data-clock-value>{{ \Carbon\CarbonImmutable::createFromTimestamp($tile->opened_at, config('app.timezone'))->format('H:i') }}</b>
            </span>
        @else
            <span class="nv-tile-free">{{ $isRoom ? 'No order running' : 'Free' }}</span>
        @endif

    @if ($open)
        </a>
    @else
            </button>
        </form>
    @endif

    {{-- ── Quick actions ─────────────────────────────────────────────── --}}
    <button type="button" class="nv-tile-handle" data-tile-handle
            aria-expanded="false" aria-label="Quick actions for {{ $tile->name }}">
        <x-icon name="dots" />
    </button>

    <div class="nv-tile-rail" data-tile-rail>
        @unless ($isRoom)
            <a href="{{ route('point-of-sale.pos.table.card', $tile->id) }}" class="nv-tile-act" target="_blank"
               title="A card for the table — the guest scans it and orders from their phone">
                <x-icon name="grid" /> <span>Code</span>
            </a>
        @endunless

        @if ($tile->order_id)
            @canEdit('point-of-sale/pos')
                @unless ($isRoom)
                    <button type="button" class="nv-tile-act" data-shift="{{ $tile->order_id }}"
                            data-shift-from="{{ $tile->name }}">
                        <x-icon name="arrow-right" /> <span>Shift</span>
                    </button>
                @endunless
            @endCanEdit

            <a href="{{ $open }}" class="nv-tile-act">
                <x-icon name="inbox" /> <span>Order</span>
            </a>

            <a href="{{ route('point-of-sale.pos.order.print', $tile->order_id) }}" class="nv-tile-act"
               target="_blank">
                <x-icon name="file" /> <span>Invoice</span>
            </a>
        @else
            <span class="nv-tile-act is-quiet">
                <x-icon name="check-circle" /> <span>{{ $isRoom ? 'Nothing running' : 'Table free' }}</span>
            </span>
        @endif
    </div>
</div>
