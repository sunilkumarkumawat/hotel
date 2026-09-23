@extends('layouts.app')

@section('title', 'Recipes')

@php
    $money = fn ($n) => '₹' . number_format((float) $n, 2);

    /*
        Food cost as a share of the menu price. The thresholds are a rule of
        thumb rather than a law, and they are here so the column reads at a
        glance rather than having to be worked out per row.
    */
    $tone = fn (?float $pc) => match (true) {
        $pc === null => 'muted',
        $pc <= 30 => 'success',
        $pc <= 40 => 'info',
        $pc <= 50 => 'warning',
        default => 'danger',
    };
@endphp

@section('content')
    <x-page-header
        title="Recipes"
        subtitle="What a dish is made of, what it costs, and what is left over the menu price."
        :crumbs="['Home' => url('/'), 'Store', 'Recipes']"
    >
        <x-slot:actions>
            @canAdd('store/recipes')
                <a href="{{ route('store.recipes.create') }}" class="nv-btn nv-btn-primary">
                    <x-icon name="plus" /> New recipe
                </a>
            @endCanAdd
        </x-slot:actions>
    </x-page-header>

    @php
        $priced = $recipes->filter(fn ($r) => $r->food_cost !== null);
        $missing = $recipes->sum('missing');
    @endphp

    <div class="nv-grid nv-grid-4">
        <x-stat label="Recipes" :value="$recipes->count()" icon="file" />
        <x-stat label="Average food cost"
                :value="$priced->isEmpty() ? '—' : number_format($priced->avg('food_cost'), 1) . '%'"
                icon="chart" :tone="$tone($priced->isEmpty() ? null : (float) $priced->avg('food_cost'))"
                caption="Of the menu price" />
        <x-stat label="Not on the menu" :value="$recipes->whereNull('pos_item_id')->count()" icon="help"
                caption="No price to compare against" />
        <x-stat label="Unpriced ingredients" :value="$missing" icon="alert"
                :tone="$missing ? 'danger' : 'success'"
                caption="Never bought, so they cost nothing" />
    </div>

    @if ($missing > 0)
        <div class="nv-mt">
            <x-alert tone="warning" title="Some ingredients have never been bought">
                An ingredient the store has never received has no average rate, so it costs nothing — and
                every dish it is in looks cheaper than it is. Receive some stock against them, or set an
                opening rate on the item.
            </x-alert>
        </div>
    @endif

    <div class="nv-mt">
        <x-card flush>
            <form method="GET" class="nv-toolbar">
                <div class="nv-field-search">
                    <x-icon name="search" />
                    <input type="search" name="q" value="{{ $term }}" class="nv-input" placeholder="Dish…" />
                </div>
                <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="search" /> Search</button>
                @if ($term)
                    <a href="{{ route('store.recipes') }}" class="nv-btn nv-btn-ghost">Reset</a>
                @endif
            </form>

            @if ($recipes->isEmpty())
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="file" /></span>
                    <strong>No recipes yet</strong>
                    <p>
                        A recipe ties a menu item to the store items it is made of. Write one and the food
                        cost appears beside the price it sells at.
                    </p>
                </div>
            @else
                <div class="nv-table-wrap">
                    <table class="nv-table">
                        <thead>
                            <tr>
                                <th>Dish</th>
                                <th>On the menu as</th>
                                <th class="is-end">Makes</th>
                                <th class="is-end">Cost a portion</th>
                                <th class="is-end">Sells at</th>
                                <th class="is-end">Food cost</th>
                                <th class="is-end">Margin</th>
                                <th class="is-end">&nbsp;</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recipes as $recipe)
                                <tr @class(['is-off' => ! $recipe->isActive()])>
                                    <td>
                                        <strong>{{ $recipe->name }}</strong>
                                        <span class="nv-sub">
                                            {{ $recipe->lines->count() }}
                                            {{ \Illuminate\Support\Str::plural('ingredient', $recipe->lines->count()) }}
                                            @if ($recipe->missing > 0)
                                                · <span class="nv-st-short">{{ $recipe->missing }} unpriced</span>
                                            @endif
                                        </span>
                                    </td>
                                    <td>{{ $recipe->menuItem?->name ?: '—' }}</td>
                                    <td class="is-end">
                                        {{ rtrim(rtrim(number_format((float) $recipe->yield_qty, 3), '0'), '.') }}
                                        {{ $recipe->yield_unit }}
                                    </td>
                                    <td class="is-end">{{ $money($recipe->cost) }}</td>
                                    <td class="is-end">{{ $recipe->price > 0 ? $money($recipe->price) : '—' }}</td>
                                    <td class="is-end">
                                        @if ($recipe->food_cost === null)
                                            <span class="nv-muted">—</span>
                                        @else
                                            <span class="nv-st-pc is-{{ $tone($recipe->food_cost) }}">
                                                {{ number_format($recipe->food_cost, 1) }}%
                                            </span>
                                        @endif
                                    </td>
                                    <td class="is-end">
                                        {{ $recipe->price > 0 ? $money($recipe->price - $recipe->cost) : '—' }}
                                    </td>
                                    <td class="is-end">
                                        <a href="{{ route('store.recipes.edit', $recipe) }}"
                                           class="nv-btn nv-btn-sm nv-btn-ghost">
                                            <x-icon name="pencil" /> Open
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
    </div>

    <div class="nv-mt">
        <x-alert tone="info" title="Costed from the average, not the last price">
            One expensive delivery should not make the menu look unprofitable for a week. Every ingredient is
            priced at the store's moving average, which is the same figure the stock is valued at.
        </x-alert>
    </div>
@endsection
