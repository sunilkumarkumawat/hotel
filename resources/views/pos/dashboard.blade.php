@extends('layouts.app')

@section('title', 'POS Dashboard')

@php
    $money = fn ($n) => '₹' . number_format((float) $n, 2);
    $short = function ($n) {
        $n = (float) $n;

        return match (true) {
            $n >= 10000000 => round($n / 10000000, 2) . ' Cr',
            $n >= 100000 => round($n / 100000, 2) . ' L',
            $n >= 1000 => round($n / 1000, 1) . 'k',
            default => (string) round($n),
        };
    };

    // Four categorical slots, in fixed order, validated for both themes
    // against the app's own surfaces. Outlet 5 and beyond folds into the
    // fourth slot rather than inventing a hue.
    $slots = ['s1', 's2', 's3', 's4'];
    $slotOf = fn (int $i) => $slots[min($i, 3)];
@endphp

@section('content')
    <x-page-header
        title="POS Dashboard"
        subtitle="What the outlets sold — restaurant, room service and the bar."
        :crumbs="['Home' => url('/'), 'Point Of Sale', 'Dashboard']"
    />

    {{-- ── The range ─────────────────────────────────────────────────────── --}}
    <div class="nv-mt">
        <x-card>
            <form method="GET" class="nv-toolbar">
                <input type="date" name="from" value="{{ $range['from'] }}" class="nv-input"
                       style="width:170px" aria-label="From date" />
                <input type="date" name="to" value="{{ $range['to'] }}" class="nv-input"
                       style="width:170px" aria-label="To date" />

                <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="refresh" /> Apply</button>

                <span class="nv-toolbar-gap"></span>

                @foreach ($presets as $label => [$pFrom, $pTo])
                    <a href="{{ route('point-of-sale.dashboard', ['from' => $pFrom, 'to' => $pTo]) }}"
                       @class(['nv-btn', 'nv-btn-sm', 'nv-btn-ghost',
                               'is-on' => $range['from'] === $pFrom && $range['to'] === $pTo])>{{ $label }}</a>
                @endforeach
            </form>
        </x-card>
    </div>

    {{-- ── The six figures ───────────────────────────────────────────────── --}}
    <div class="nv-mt nv-grid nv-grid-3">
        <x-stat label="Total Sales" :value="$money($headline['sales'])" icon="trending-up"
                :caption="$headline['invoices'] . ' invoice(s)'" />

        <x-stat label="Collections" :value="$money($headline['collections'])" icon="wallet" tone="success"
                :caption="$headline['collections'] < $headline['sales']
                    ? $money($headline['sales'] - $headline['collections']) . ' still to come in'
                    : 'Everything billed is in'" />

        <x-stat label="Orders" :value="$headline['orders']" icon="bag" tone="warning"
                :caption="$headline['cancelled'] . ' cancelled'" />

        <x-stat label="Complimentary" :value="$headline['complimentary']" icon="star" tone="info"
                caption="Given away, not sold" />

        <x-stat label="Turn Around Time"
                :value="$headline['turnaround'] > 0 ? $headline['turnaround'] . ' min' : '—'"
                icon="clock"
                caption="Order opened to order closed, on average" />

        <x-stat label="Discounts" :value="$money($headline['discounts'])" icon="credit-card" tone="danger"
                :caption="$headline['discount_invoices'] . ' invoice(s)'" />
    </div>

    {{-- ── Revenue Control ───────────────────────────────────────────────── --}}
    <div class="nv-mt">
        <x-card title="Revenue Control" subtitle="Watch these for leakage.">
            <div class="nv-rc-strip">
                @foreach ($controlLabels as $key => $label)
                    <span class="nv-rc-item is-{{ str_replace('_', '-', $key) }}">
                        <span class="nv-rc-dot"></span>
                        {{ $label }}: <strong>{{ $control[$key] ?? 0 }}</strong>
                    </span>
                @endforeach
            </div>

            <p class="nv-help">
                None of these is wrong on its own — a guest does send a dish back, a printer does jam.
                They are counted because a <b>pattern</b> in them is the first sign of a till being
                worked, and a number nobody looks at is a number nobody can act on.
            </p>
        </x-card>
    </div>

    {{-- ── Outlet sales, daily ───────────────────────────────────────────── --}}
    <div class="nv-mt nv-pos-split">
        <x-card title="Outlet Sales — daily" :subtitle="'One column per day, stacked by outlet.'">
            @if ($trend['max'] <= 0)
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="chart" /></span>
                    <strong>No sales in this period.</strong>
                    <p>
                        @if ($outletCount === 0)
                            No outlets are set up yet — the POS screens will add them.
                        @else
                            Nothing has been billed between
                            {{ \Carbon\CarbonImmutable::parse($range['from'])->format('d M') }} and
                            {{ \Carbon\CarbonImmutable::parse($range['to'])->format('d M Y') }}.
                        @endif
                    </p>
                </div>
            @else
                @php
                    // Geometry. Drawn in a fixed user-space box and scaled by the
                    // viewBox, so it stays sharp at any width without JavaScript.
                    $w = 720; $h = 260; $padL = 54; $padB = 28; $padT = 8;
                    $plotW = $w - $padL - 8;
                    $plotH = $h - $padB - $padT;
                    $count = max(1, count($trend['days']));
                    $step = $plotW / $count;
                    $barW = min(34, $step * 0.62);
                    // A round number above the tallest stack, so the axis reads.
                    $topStep = max(1, (int) ceil($trend['max'] / 4));
                    $magnitude = 10 ** max(0, strlen((string) $topStep) - 2);
                    $topStep = (int) (ceil($topStep / $magnitude) * $magnitude);
                    $top = max($topStep * 4, 1);
                @endphp

                <div class="nv-chart">
                    <svg viewBox="0 0 {{ $w }} {{ $h }}" class="nv-chart-svg" role="img"
                         aria-label="Outlet sales per day. The same figures are in the table below.">
                        {{-- Grid and axis: recessive, behind the data. --}}
                        @for ($i = 0; $i <= 4; $i++)
                            @php $y = $padT + $plotH - ($plotH * $i / 4); @endphp
                            <line x1="{{ $padL }}" y1="{{ round($y, 1) }}" x2="{{ $w - 8 }}" y2="{{ round($y, 1) }}"
                                  class="nv-chart-grid" />
                            <text x="{{ $padL - 8 }}" y="{{ round($y + 4, 1) }}" class="nv-chart-tick"
                                  text-anchor="end">{{ $short($topStep * $i) }}</text>
                        @endfor

                        @foreach ($trend['days'] as $d => $day)
                            @php
                                $x = $padL + $step * $d + ($step - $barW) / 2;
                                $cursor = $padT + $plotH;
                                $dayTotal = 0;
                                foreach ($trend['outlets'] as $o) {
                                    $dayTotal += $trend['values'][$o['id']][$day['date']] ?? 0;
                                }
                            @endphp

                            @foreach ($trend['outlets'] as $i => $outlet)
                                @php
                                    $value = $trend['values'][$outlet['id']][$day['date']] ?? 0;
                                    $segH = $top > 0 ? $plotH * $value / $top : 0;
                                @endphp

                                @if ($segH > 0.5)
                                    @php $cursor -= $segH; @endphp
                                    {{-- 2px surface gap between stacked segments. --}}
                                    <rect x="{{ round($x, 1) }}" y="{{ round($cursor, 1) }}"
                                          width="{{ round($barW, 1) }}" height="{{ round(max(1, $segH - 2), 1) }}"
                                          rx="3" class="nv-chart-bar is-{{ $slotOf($i) }}">
                                        <title>{{ $outlet['name'] }} · {{ $day['label'] }} — {{ $money($value) }}</title>
                                    </rect>
                                @endif
                            @endforeach

                            @if ($dayTotal > 0)
                                <text x="{{ round($x + $barW / 2, 1) }}" y="{{ round($cursor - 6, 1) }}"
                                      class="nv-chart-value" text-anchor="middle">{{ $short($dayTotal) }}</text>
                            @endif

                            <text x="{{ round($padL + $step * $d + $step / 2, 1) }}" y="{{ $h - 8 }}"
                                  class="nv-chart-tick" text-anchor="middle">{{ $day['label'] }}</text>
                        @endforeach
                    </svg>
                </div>

                <div class="nv-legend">
                    @foreach ($trend['outlets'] as $i => $outlet)
                        <span class="nv-legend-item">
                            <span class="nv-legend-key is-{{ $slotOf($i) }}"></span>{{ $outlet['name'] }}
                            <span class="nv-muted">{{ $money($trend['totals'][$outlet['id']] ?? 0) }}</span>
                        </span>
                    @endforeach
                </div>

                {{-- The table is the chart in words: it is what a screen reader
                     reads, what prints, and what settles an argument. --}}
                <details class="nv-chart-table">
                    <summary>Show these figures as a table</summary>

                    <div class="nv-table-wrap">
                        <table class="nv-table nv-table-compact">
                            <thead>
                                <tr>
                                    <th>Day</th>
                                    @foreach ($trend['outlets'] as $outlet)
                                        <th class="is-num">{{ $outlet['name'] }}</th>
                                    @endforeach
                                    <th class="is-num">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($trend['days'] as $day)
                                    @php $rowTotal = 0; @endphp
                                    <tr>
                                        <td>{{ $day['label'] }}</td>
                                        @foreach ($trend['outlets'] as $outlet)
                                            @php
                                                $v = $trend['values'][$outlet['id']][$day['date']] ?? 0;
                                                $rowTotal += $v;
                                            @endphp
                                            <td class="is-num">{{ $v > 0 ? $money($v) : '—' }}</td>
                                        @endforeach
                                        <td class="is-num"><strong>{{ $rowTotal > 0 ? $money($rowTotal) : '—' }}</strong></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            @endif
        </x-card>

        {{-- ── Sales by order type ───────────────────────────────────────── --}}
        <x-card title="Sales by order type" subtitle="How it was sold, not which outlet sold it.">
            @php $typeTotal = $orderTypes->sum('amount'); @endphp

            @if ($typeTotal <= 0)
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="chart" /></span>
                    <strong>Nothing sold yet.</strong>
                    <p>Dine-in, room service, delivery and take away will split here.</p>
                </div>
            @else
                @php
                    // A donut drawn with one circle per slice and a dash offset —
                    // no arc maths, no library, and it scales cleanly.
                    $r = 62; $cx = 90; $cy = 90; $circ = 2 * M_PI * $r;
                    $offset = 0.0;
                @endphp

                <div class="nv-chart is-donut">
                    <svg viewBox="0 0 180 180" class="nv-chart-svg" role="img"
                         aria-label="Share of sales by order type. The same figures are listed beside the chart.">
                        <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $r }}" class="nv-donut-track" />

                        @foreach ($orderTypes as $i => $type)
                            @if ($type['amount'] > 0)
                                @php
                                    $len = $circ * $type['amount'] / $typeTotal;
                                    // 2px surface gap between neighbouring slices.
                                    $draw = max(0.5, $len - 2);
                                @endphp
                                <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $r }}"
                                        class="nv-donut-slice is-{{ $slotOf($i) }}"
                                        stroke-dasharray="{{ round($draw, 2) }} {{ round($circ - $draw, 2) }}"
                                        stroke-dashoffset="{{ round(-$offset, 2) }}"
                                        transform="rotate(-90 {{ $cx }} {{ $cy }})">
                                    <title>{{ $type['label'] }} — {{ $money($type['amount']) }} ({{ $type['share'] }}%)</title>
                                </circle>
                                @php $offset += $len; @endphp
                            @endif
                        @endforeach

                        <text x="{{ $cx }}" y="{{ $cy - 4 }}" class="nv-donut-total" text-anchor="middle">{{ $short($typeTotal) }}</text>
                        <text x="{{ $cx }}" y="{{ $cy + 14 }}" class="nv-donut-cap" text-anchor="middle">total</text>
                    </svg>

                    <ul class="nv-donut-key">
                        @foreach ($orderTypes as $i => $type)
                            <li @class(['is-zero' => $type['amount'] <= 0])>
                                <span class="nv-legend-key is-{{ $slotOf($i) }}"></span>
                                <span class="nv-donut-name">{{ $type['label'] }}</span>
                                <span class="nv-donut-val">{{ $money($type['amount']) }}</span>
                                <span class="nv-muted">{{ $type['share'] }}%</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </x-card>
    </div>

    {{-- ── Collections and the two item lists ────────────────────────────── --}}
    <div class="nv-mt nv-grid nv-grid-3">
        <x-card title="Collections by pay mode">
            @if ($payModes->isEmpty())
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="wallet" /></span>
                    <strong>Nothing collected yet.</strong>
                    <p>Cash, card and the rest will rank here.</p>
                </div>
            @else
                {{-- One measure, one hue: this is magnitude, not identity. --}}
                <ul class="nv-hbar">
                    @foreach ($payModes as $mode)
                        <li>
                            <span class="nv-hbar-name">{{ $mode['name'] }}</span>
                            <span class="nv-hbar-track">
                                <span class="nv-hbar-fill" style="width:{{ max(2, $mode['share']) }}%"></span>
                            </span>
                            <span class="nv-hbar-val">{{ $money($mode['amount']) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <x-card title="Top selling items">
            @if ($topItems->isEmpty())
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="star" /></span>
                    <strong>No items sold yet.</strong>
                    <p>The kitchen's best sellers will list here.</p>
                </div>
            @else
                <div class="nv-table-wrap">
                    <table class="nv-table nv-table-compact">
                        <thead>
                            <tr><th class="is-num">#</th><th>Item</th><th class="is-num">Qty</th><th class="is-num">Amount</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($topItems as $item)
                                <tr>
                                    <td class="is-num">{{ $loop->iteration }}</td>
                                    <td>{{ $item['name'] }}</td>
                                    <td class="is-num">{{ rtrim(rtrim(number_format($item['qty'], 2), '0'), '.') }}</td>
                                    <td class="is-num"><strong>{{ $money($item['amount']) }}</strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        <x-card title="Low selling items" subtitle="What to take off the menu, or push.">
            @if ($lowItems->isEmpty())
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="arrow-down" /></span>
                    <strong>No items sold yet.</strong>
                    <p>The slow movers will list here.</p>
                </div>
            @else
                <div class="nv-table-wrap">
                    <table class="nv-table nv-table-compact">
                        <thead>
                            <tr><th class="is-num">#</th><th>Item</th><th class="is-num">Qty</th><th class="is-num">Amount</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($lowItems as $item)
                                <tr>
                                    <td class="is-num">{{ $loop->iteration }}</td>
                                    <td>{{ $item['name'] }}</td>
                                    <td class="is-num">{{ rtrim(rtrim(number_format($item['qty'], 2), '0'), '.') }}</td>
                                    <td class="is-num">{{ $money($item['amount']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
    </div>

    @if ($outletCount === 0)
        <div class="nv-mt">
            <x-alert tone="info" title="This screen is ready, the data is not">
                The Point Of Sale tables are in the database and this dashboard reads them, but nothing
                sells through the app yet — so every figure is honestly zero. Build <b>POS</b> (taking
                an order and billing it) and this screen fills itself in; nothing here has to change.
            </x-alert>
        </div>
    @endif
@endsection
