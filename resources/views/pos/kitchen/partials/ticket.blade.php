{{--
    One ticket: one KOT round on one order.

    Rendered by the server on first load and by pos-kds.js on every refresh, so
    the two have to agree. The shapes are kept identical on purpose — if you
    change this file, change `render()` in resources/js/pos-kds.js with it.

    Expects: $ticket (an array from KitchenController::tickets).
--}}

@php
    $next = ['pending' => 'preparing', 'preparing' => 'ready', 'ready' => 'served'];
    $words = ['pending' => 'Start cooking', 'preparing' => 'Mark ready', 'ready' => 'Served'];
@endphp

<article class="nv-kot" data-kot data-fired="{{ $ticket['fired_at'] }}" data-key="{{ $ticket['key'] }}">
    <header class="nv-kot-head">
        <div>
            <strong class="nv-kot-where">{{ $ticket['where'] }}</strong>
            <span class="nv-kot-no">KOT {{ $ticket['kot_no'] }} · {{ $ticket['order_no'] }}</span>
        </div>

        {{-- The number the whole screen exists for. --}}
        <span class="nv-kot-clock" data-kot-clock>
            <x-icon name="clock" />
            <b data-kot-clock-value>—</b>
        </span>
    </header>

    <div class="nv-kot-meta">
        <span>{{ $ticket['type'] }}</span>
        @if ($ticket['outlet'])
            <span>{{ $ticket['outlet'] }}</span>
        @endif
        @if ($ticket['steward'])
            <span>{{ $ticket['steward'] }}</span>
        @endif
        <span>{{ $ticket['pax'] }} pax</span>
    </div>

    <ul class="nv-kot-lines">
        @foreach ($ticket['lines'] as $line)
            <li>
                <b>{{ $line['qty'] }}</b>
                <span>
                    {{ $line['name'] }}
                    @if ($line['remark'])
                        <i>{{ $line['remark'] }}</i>
                    @endif
                    @if ($line['nc'])
                        <i>No charge</i>
                    @endif
                </span>
            </li>
        @endforeach
    </ul>

    @canEdit('point-of-sale/kitchen-display')
        <form method="POST" action="{{ route('point-of-sale.kitchen-display.advance') }}" class="nv-kot-act">
            @csrf
            <input type="hidden" name="order_id" value="{{ $ticket['order_id'] }}" />
            <input type="hidden" name="kot_no" value="{{ $ticket['kot_no'] }}" />
            <input type="hidden" name="to" value="{{ $next[$ticket['status']] ?? 'served' }}" />

            <button type="submit" class="nv-btn nv-btn-primary nv-btn-sm">
                <x-icon name="check" /> {{ $words[$ticket['status']] ?? 'Served' }}
            </button>
        </form>
    @endCanEdit
</article>
