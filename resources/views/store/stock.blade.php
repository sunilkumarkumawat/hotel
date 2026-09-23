@extends('layouts.app')

@section('title', 'Stock Sheet')

@php
    $money = fn ($n) => '₹' . number_format((float) $n, 2);
    $qty = fn ($n) => rtrim(rtrim(number_format((float) $n, 3), '0'), '.');

    // Grouped the way a storekeeper walks the store, with a value per shelf.
    $byCategory = $items->groupBy(fn ($i) => $i->category?->name ?: 'Uncategorised')->sortKeys();

    $issued = $consumption->where('kind', 'issue');
    $wasted = $consumption->where('kind', 'wastage');
@endphp

@section('content')
    <x-page-header
        title="Stock Sheet"
        subtitle="What is on the shelf, at what it cost."
        :crumbs="['Home' => url('/'), 'Store', 'Stock']"
    >
        <x-slot:actions>
            <a href="{{ route('store.stock.export') }}" class="nv-btn nv-btn-outline">
                <x-icon name="download" /> Export CSV
            </a>
            @canView('store/items')
                <a href="{{ route('store.items') }}" class="nv-btn nv-btn-primary">
                    <x-icon name="package" /> Items
                </a>
            @endCanView
        </x-slot:actions>
    </x-page-header>

    <div class="nv-grid nv-grid-4">
        <x-stat label="Stock value" :value="$money($summary['value'])" icon="wallet" tone="success"
                caption="At the moving average" />
        <x-stat label="Items" :value="number_format($summary['items'])" icon="package" />
        <x-stat label="To reorder" :value="$summary['reorder']" icon="alert"
                :tone="$summary['reorder'] ? 'warning' : 'success'" />
        <x-stat label="Issued this period" :value="$money($issued->sum('value'))" icon="arrow-right" tone="info"
                :caption="$wasted->sum('value') > 0 ? 'plus ' . $money($wasted->sum('value')) . ' wasted' : 'nothing wasted'" />
    </div>

    {{-- ── The two lists that are jobs, not information ──────────────────── --}}
    @if ($short->isNotEmpty())
        <div class="nv-mt">
            <x-alert tone="danger" title="{{ $short->count() }} {{ \Illuminate\Support\Str::plural('item', $short->count()) }} below zero">
                Stock was issued before the delivery note was entered. The books are not wrong — the
                paperwork is late. These are the fastest way to find it:
                <strong>{{ $short->take(6)->pluck('name')->join(', ') }}</strong>{{ $short->count() > 6 ? ' and others' : '' }}.
            </x-alert>
        </div>
    @endif

    <div class="nv-grid nv-grid-2 nv-mt">
        <x-card title="To reorder" subtitle="At or below the level somebody set, shortest first." :flush="true">
            @if ($reorder->isEmpty())
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="check-circle" /></span>
                    <strong>Nothing to order</strong>
                    <p>Every item with a reorder level is above it.</p>
                </div>
            @else
                <div class="nv-table-wrap">
                    <table class="nv-table nv-table-compact">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th class="is-end">Have</th>
                                <th class="is-end">Want</th>
                                <th class="is-end">Last paid</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($reorder as $row)
                                <tr>
                                    <td>
                                        <strong>{{ $row->name }}</strong>
                                        <span class="nv-sub">{{ $row->category ?: 'Uncategorised' }}</span>
                                    </td>
                                    <td @class(['is-end', 'nv-st-qty', 'is-short' => (float) $row->current_qty < 0, 'is-low' => (float) $row->current_qty >= 0])>
                                        {{ $qty($row->current_qty) }} {{ $row->unit }}
                                    </td>
                                    <td class="is-end">{{ $qty($row->reorder_level) }}</td>
                                    <td class="is-end">{{ $money($row->last_rate ?: $row->avg_rate) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        <x-card title="Consumption"
                :subtitle="\Carbon\CarbonImmutable::parse($from)->format('d M') . ' – ' . \Carbon\CarbonImmutable::parse($to)->format('d M Y')">
            <form method="GET" class="nv-toolbar">
                <x-field label="From" name="from"><x-input type="date" name="from" :value="$from" /></x-field>
                <x-field label="To" name="to"><x-input type="date" name="to" :value="$to" /></x-field>
                <button type="submit" class="nv-btn nv-btn-sm nv-btn-primary"><x-icon name="search" /> Show</button>
            </form>

            @if ($consumption->isEmpty())
                <p class="nv-muted nv-mt">Nothing was issued in this period.</p>
            @else
                <div class="nv-crm-facts nv-mt">
                    @foreach ($departments as $key => $label)
                        @php
                            $out = $consumption->where('department', $key);
                        @endphp

                        @if ($out->isNotEmpty())
                            <div>
                                <span>
                                    {{ $label }}
                                    @if ($out->where('kind', 'wastage')->isNotEmpty())
                                        <small class="nv-st-wasted">
                                            incl. {{ $money($out->where('kind', 'wastage')->sum('value')) }} wasted
                                        </small>
                                    @endif
                                </span>
                                <b>{{ $money($out->sum('value')) }}</b>
                            </div>
                        @endif
                    @endforeach
                </div>

                <p class="nv-help nv-mt">
                    Issues at the average rate — what was consumed, not what was bought. That is the figure a
                    food cost percentage is built on.
                </p>
            @endif
        </x-card>
    </div>

    {{-- ── Recipes say vs. what actually left the store ────────────────────── --}}
    <div class="nv-mt">
        <x-card title="Expected vs. actual"
                subtitle="What recipes say the kitchen should have used from what sold, against what was actually issued or wasted."
                :flush="true">
            @if ($variance->isEmpty())
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="activity" /></span>
                    <strong>Nothing to compare yet</strong>
                    <p>
                        This needs sales sent to the kitchen in this period, and recipes linking the menu items
                        sold to the store items they use. Nothing matched either — link a recipe under
                        <a href="{{ route('store.recipes') }}">Recipes</a> to start seeing a comparison here.
                    </p>
                </div>
            @else
                <div class="nv-table-wrap">
                    <table class="nv-table nv-table-compact">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th class="is-end">Recipes expected</th>
                                <th class="is-end">Actually left the store</th>
                                <th class="is-end">Gap</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($variance as $row)
                                <tr>
                                    <td>{{ $row->name }}</td>
                                    <td class="is-end">{{ $qty($row->expected_qty) }} {{ $row->unit }}</td>
                                    <td class="is-end">{{ $qty($row->actual_qty) }} {{ $row->unit }}</td>
                                    <td @class([
                                        'is-end', 'nv-st-qty',
                                        'is-short' => $row->variance_qty > 0.0005,
                                        'is-low' => $row->variance_qty < -0.0005,
                                    ])>
                                        {{ $row->variance_qty > 0 ? '+' : '' }}{{ $qty($row->variance_qty) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <p class="nv-help nv-mt">
                    A gap is not an accusation. More left the store than recipes account for, or less — either
                    can simply mean stock was issued in bulk ahead of when it was actually cooked. Over a day
                    it is normal; over a longer period it is worth a look, starting with the biggest gaps
                    above.
                </p>
            @endif
        </x-card>
    </div>

    {{-- ── The sheet itself ──────────────────────────────────────────────── --}}
    <div class="nv-mt">
        <x-card flush>
            <form method="GET" class="nv-toolbar">
                <input type="hidden" name="from" value="{{ $from }}" />
                <input type="hidden" name="to" value="{{ $to }}" />

                <div class="nv-field-search">
                    <x-icon name="search" />
                    <input type="search" name="q" value="{{ $filters['q'] }}" class="nv-input" placeholder="Item…" />
                </div>

                <x-field label="Category" name="category">
                    <select name="category" class="nv-select">
                        <option value="">Every category</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected($filters['category'] === $category->id)>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </x-field>

                <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="search" /> Show</button>
            </form>

            @if ($items->isEmpty())
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="package" /></span>
                    <strong>Nothing in the store</strong>
                    <p>Add items first, then receive some stock against them.</p>
                </div>
            @else
                <div class="nv-table-wrap">
                    <table class="nv-table nv-table-compact">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Unit</th>
                                <th class="is-end">In stock</th>
                                <th class="is-end">Average rate</th>
                                <th class="is-end">Value</th>
                                <th class="is-end">Reorder at</th>
                                <th class="is-end">&nbsp;</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($byCategory as $name => $group)
                                <tr class="nv-rt-group">
                                    <td colspan="4">
                                        <strong>{{ $name }}</strong>
                                        <span class="nv-sub">{{ $group->count() }}
                                            {{ \Illuminate\Support\Str::plural('item', $group->count()) }}</span>
                                    </td>
                                    <td class="is-end">
                                        <strong>{{ $money($group->sum(fn ($i) => $i->value)) }}</strong>
                                    </td>
                                    <td colspan="2"></td>
                                </tr>

                                @foreach ($group as $item)
                                    <tr>
                                        <td>{{ $item->name }}{{ $item->code ? ' · ' . $item->code : '' }}</td>
                                        <td>{{ $item->unit }}</td>
                                        <td @class(['is-end', 'nv-st-qty', 'is-short' => $item->isShort(), 'is-low' => $item->needsReorder() && ! $item->isShort()])>
                                            {{ $qty($item->current_qty) }}
                                        </td>
                                        <td class="is-end">{{ $money($item->avg_rate) }}</td>
                                        <td class="is-end">{{ $money($item->value) }}</td>
                                        <td class="is-end">
                                            {{ (float) $item->reorder_level > 0 ? $qty($item->reorder_level) : '—' }}
                                        </td>
                                        <td class="is-end">
                                            <a href="{{ route('store.stock.ledger', $item) }}"
                                               class="nv-btn nv-btn-sm nv-btn-ghost" title="Its movements">
                                                <x-icon name="activity" />
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
    </div>

    <div class="nv-mt">
        <x-alert tone="info" title="How stock is valued">
            A moving weighted average: held × old average, plus received × paid, over the new total. It moves
            only on a receipt — issuing stock cannot change what the stock still on the shelf cost. The
            figures here are a cache of running the ledger, and any item's page can rebuild them from it.
        </x-alert>
    </div>
@endsection
