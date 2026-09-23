{{--
    The manager's report for one night.

    Used twice: live on the audit screen before the close, and frozen on the
    report afterwards. One partial on purpose — a preview that showed different
    figures from the report it produced would be worse than no preview at all.

    Every value is read defensively. The report of a night closed last year was
    written by whatever this file looked like then, and a key added since must
    show as an empty cell rather than bring the page down.
--}}
@php
    $rooms = $figures['rooms'] ?? [];
    $move = $figures['movement'] ?? [];
    $rev = $figures['revenue'] ?? [];
    $coll = $figures['collection'] ?? [];
    $perf = $figures['performance'] ?? [];

    $money = fn ($value) => '₹' . number_format((float) ($value ?? 0), 2);
    $num = fn ($value) => number_format((int) ($value ?? 0));
@endphp

<div class="nv-grid nv-grid-4">
    <x-stat label="Rooms sold" :value="$num($rooms['sold'] ?? 0)" icon="desktop" tone="info"
            :caption="($rooms['occupancy'] ?? 0) . '% of ' . $num($rooms['available'] ?? 0) . ' rooms'" />
    <x-stat label="Room revenue" :value="$money($rev['room'] ?? 0)" icon="wallet" tone="success"
            :caption="'plus ' . $money($rev['room_tax'] ?? 0) . ' tax'" />
    <x-stat label="ADR" :value="$money($perf['adr'] ?? 0)" icon="trending-up"
            caption="Average rate a sold room fetched" />
    <x-stat label="RevPAR" :value="$money($perf['revpar'] ?? 0)" icon="chart" tone="warning"
            caption="Room revenue over every room in the house" />
</div>

<div class="nv-grid nv-grid-2 nv-mt">
    <x-card title="Revenue" subtitle="What the hotel earned on this date, before collection.">
        <div class="nv-total-list">
            <div class="nv-total-row"><span>Room rent</span><b>{{ $money($rev['room'] ?? 0) }}</b></div>
            <div class="nv-total-row"><span>Services and sundries</span><b>{{ $money($rev['other'] ?? 0) }}</b></div>
            <div class="nv-total-row">
                <span>Restaurant and bar
                    <small class="nv-muted">· {{ $num($rev['pos_orders'] ?? 0) }} orders</small>
                </span>
                <b>{{ $money($rev['pos'] ?? 0) }}</b>
            </div>
            <div class="nv-total-row">
                <span>Tax collected</span>
                <b>{{ $money(($rev['room_tax'] ?? 0) + ($rev['other_tax'] ?? 0) + ($rev['pos_tax'] ?? 0)) }}</b>
            </div>
            <div class="nv-total-row nv-na-grand"><span>Total revenue</span><b>{{ $money($rev['total'] ?? 0) }}</b></div>
        </div>
    </x-card>

    <x-card title="Money in" subtitle="What was actually taken at the desk on this date.">
        <div class="nv-total-list">
            <div class="nv-total-row"><span>Settlements against bills</span><b>{{ $money($coll['settlements'] ?? 0) }}</b></div>
            <div class="nv-total-row"><span>Advances and deposits<small class="nv-muted"> · net of refunds</small></span><b>{{ $money($coll['deposits'] ?? 0) }}</b></div>
            <div class="nv-total-row nv-na-grand"><span>Total collected</span><b>{{ $money($coll['total'] ?? 0) }}</b></div>
        </div>

        {{--
            Earned and collected are two different things and a manager should
            see the gap rather than work it out. A positive gap is money owed
            to the hotel; a negative one is guests paying ahead.
        --}}
        @php $gap = round((float) ($rev['total'] ?? 0) - (float) ($coll['total'] ?? 0), 2); @endphp

        <p class="nv-na-gap">
            <x-icon name="info" />
            @if (abs($gap) < 1)
                Everything earned on this date was collected on it.
            @elseif ($gap > 0)
                <b>{{ $money($gap) }}</b> earned but not yet collected — it is sitting on open folios.
            @else
                <b>{{ $money(abs($gap)) }}</b> collected beyond what was earned — advances against future stays.
            @endif
        </p>
    </x-card>
</div>

<div class="nv-grid nv-grid-2 nv-mt">
    <x-card title="The house" subtitle="Where the rooms were on this night.">
        <div class="nv-na-figures">
            <div><span>Rooms available</span><b>{{ $num($rooms['available'] ?? 0) }}</b></div>
            <div><span>Rooms sold</span><b>{{ $num($rooms['sold'] ?? 0) }}</b></div>
            <div><span>Rooms free</span><b>{{ $num($rooms['free'] ?? 0) }}</b></div>
            <div><span>Occupancy</span><b>{{ $rooms['occupancy'] ?? 0 }}%</b></div>
            <div><span>Guests in house</span><b>{{ $num($rooms['guests'] ?? 0) }}</b></div>
            <div><span>Average rate with tax</span><b>{{ $money($perf['arr_with_tax'] ?? 0) }}</b></div>
        </div>
    </x-card>

    <x-card title="Movement" subtitle="Who came, who went, and who never turned up.">
        <div class="nv-na-figures">
            <div><span>Arrivals</span><b>{{ $num($move['arrivals'] ?? 0) }}</b></div>
            <div><span>Departures</span><b>{{ $num($move['departures'] ?? 0) }}</b></div>
            <div><span>Stays in house</span><b>{{ $num($move['in_house'] ?? 0) }}</b></div>
            <div><span>No shows</span><b>{{ $num($move['no_shows'] ?? 0) }}</b></div>
        </div>
    </x-card>
</div>
