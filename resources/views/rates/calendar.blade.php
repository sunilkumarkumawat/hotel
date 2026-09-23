@extends('layouts.app')

@section('title', 'Rate Calendar')

@php
    use App\Support\Rates;

    /*
        The grid answers one question a rate sheet never can: what am I actually
        charging on the 24th? It runs the same engine the booking screen runs,
        one cell at a time, and prints what comes back — including the cells
        where nothing was loaded and the base rent is standing in.
    */
    $money = fn ($n) => '₹' . number_format((float) $n, 0);

    $loaded = collect($types)
        ->flatMap(fn ($t) => array_column($t['cells'], 'source'))
        ->countBy();

    $missing = (int) ($loaded[Rates::FROM_BASE] ?? 0) + (int) ($loaded[Rates::FROM_NONE] ?? 0);
    $total = (int) $loaded->sum();
@endphp

@section('content')
    <x-page-header
        title="Rate Calendar"
        subtitle="What every room type sells for, every night."
        :crumbs="['Home' => url('/'), 'Rate Management', 'Rate Calendar']"
    >
        <x-slot:actions>
            @canView('rates/rules')
                <a href="{{ route('rates.rules', ['plan' => $plan?->id]) }}" class="nv-btn nv-btn-outline">
                    <x-icon name="pencil" /> Edit these rates
                </a>
            @endCanView
        </x-slot:actions>
    </x-page-header>

    {{-- ── Which plan, from when, how far ────────────────────────────────── --}}
    <div class="nv-mt">
        <x-card>
            <form method="GET" class="nv-toolbar">
                <x-field label="Rate plan" name="plan">
                    <select name="plan" class="nv-select">
                        @foreach ($plans as $p)
                            <option value="{{ $p->id }}" @selected((int) $p->id === (int) $plan?->id)>
                                {{ $p->name }}{{ $p->is_default ? ' (default)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </x-field>

                <x-field label="From" name="from">
                    <x-input type="date" name="from" :value="$from" />
                </x-field>

                <x-field label="Showing" name="nights">
                    <select name="nights" class="nv-select">
                        @foreach ($spans as $value => $label)
                            <option value="{{ $value }}" @selected((int) $value === (int) $nights)>{{ $label }}</option>
                        @endforeach
                    </select>
                </x-field>

                <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="search" /> Show</button>

                <span class="nv-toolbar-spacer"></span>

                <a href="{{ route('rates.calendar', ['from' => $prev, 'nights' => $nights, 'plan' => $plan?->id]) }}"
                   class="nv-btn nv-btn-ghost"><x-icon name="chevron-left" /> Earlier</a>
                <a href="{{ route('rates.calendar', ['from' => $today, 'nights' => $nights, 'plan' => $plan?->id]) }}"
                   class="nv-btn nv-btn-ghost">Today</a>
                <a href="{{ route('rates.calendar', ['from' => $next, 'nights' => $nights, 'plan' => $plan?->id]) }}"
                   class="nv-btn nv-btn-ghost">Later <x-icon name="chevron-right" /></a>
            </form>
        </x-card>
    </div>

    @if (! $plan)
        <div class="nv-mt">
            <x-alert tone="warning" title="No rate plan yet">
                There is nothing to draw until a plan exists.
                @canView('rates/plans')
                    <a href="{{ route('rates.plans') }}">Set one up</a> — call it Rack Rate and tick Default.
                @endCanView
            </x-alert>
        </div>
    @elseif ($missing > 0)
        <div class="nv-mt">
            <x-alert tone="{{ $missing === $total ? 'danger' : 'warning' }}"
                     title="{{ $missing === $total ? 'Nothing is priced yet' : 'Some nights are not priced' }}">
                {{ $missing }} of {{ $total }} cells fall back to the room type's base rent — they are the pale
                ones below. A booking on one of those nights is sold at the base rent, whatever the season says.
            </x-alert>
        </div>
    @endif

    {{-- ── The grid ──────────────────────────────────────────────────────── --}}
    <div class="nv-mt">
        <x-card flush>
            <div class="nv-table-wrap nv-rg-wrap">
                <table class="nv-table nv-rg">
                    <thead>
                        <tr>
                            <th class="nv-rg-head">Room type</th>
                            @foreach ($days as $day)
                                <th @class(['nv-rg-day', 'is-weekend' => $day['weekend'], 'is-today' => $day['date'] === $today])
                                    @if ($day['season']) title="{{ $day['season'] }}" @endif>
                                    <span class="nv-rg-dow">{{ $day['day'] }}</span>
                                    <span class="nv-rg-date">{{ $day['label'] }}</span>
                                    @if ($day['season'])
                                        <span class="nv-rg-season is-{{ $day['colour'] ?: 'plum' }}"></span>
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($types as $type)
                            <tr>
                                <th class="nv-rg-head">
                                    {{ $type['name'] }}
                                    <span class="nv-sub">base {{ $money($type['base']) }}</span>
                                </th>

                                @foreach ($type['cells'] as $cell)
                                    <td @class([
                                        'nv-rg-cell',
                                        'is-weekend' => $cell['weekend'],
                                        'is-fallback' => $cell['source'] !== Rates::FROM_RULE,
                                        'is-closed' => $cell['stop_sell'],
                                    ]) title="{{ $cell['source'] === Rates::FROM_RULE ? 'From the rate plan' : 'No rate loaded — base rent' }}">
                                        @if ($cell['stop_sell'])
                                            <span class="nv-rg-x"><x-icon name="x" /></span>
                                        @else
                                            {{ $money($cell['amount']) }}
                                            @if ($cell['min_stay'] > 0)
                                                <span class="nv-rg-min">{{ $cell['min_stay'] }}n</span>
                                            @endif
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($days) + 1 }}">
                                    <div class="nv-empty">
                                        <span class="nv-empty-icon"><x-icon name="layers" /></span>
                                        <strong>No room types</strong>
                                        <p>Add room types under Masters before pricing them.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>

    {{-- ── What the shading means, and what is running ───────────────────── --}}
    <div class="nv-grid nv-grid-2 nv-mt">
        <x-card title="Reading the grid">
{{--
                Each chip carries a number, not just a colour. Two shades of grey
                side by side are indistinguishable on a projector and to a good
                share of readers; the same number set plain and set pale-italic
                is the difference the grid actually uses.
            --}}
            <div class="nv-rg-key">
                <span><i class="nv-rg-chip">4,000</i> Priced by the rate plan</span>
                <span><i class="nv-rg-chip is-fallback">3,500</i> No rate loaded — the room type's base rent</span>
                <span><i class="nv-rg-chip is-closed"><x-icon name="x" /></i> Stop sell — not for sale that night</span>
                <span><i class="nv-rg-chip is-weekend">5,200</i> Friday and Saturday</span>
                <span><i class="nv-rg-chip is-min">3n</i> A minimum stay applies</span>
            </div>
        </x-card>

        <x-card title="Seasons in view" :subtitle="$seasons->isEmpty() ? null : $seasons->count() . ' covering these nights'">
            @if ($seasons->isEmpty())
                <p class="nv-muted">No season covers this stretch. Every night is priced by an all-year row,
                    a dated row, or the base rent.</p>
            @else
                <div class="nv-rg-seasons">
                    @foreach ($seasons as $season)
                        <div class="nv-rg-season-row">
                            <span class="nv-season-dot is-{{ $season->colour ?: 'plum' }}"></span>
                            <strong>{{ $season->name }}</strong>
                            <span>{{ $season->from_date->format('d M') }} – {{ $season->to_date->format('d M Y') }}</span>
                            @if ($season->priority > 0)
                                <x-badge tone="info">priority {{ $season->priority }}</x-badge>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </x-card>
    </div>
@endsection
