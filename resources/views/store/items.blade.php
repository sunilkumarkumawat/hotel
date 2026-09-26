@extends('layouts.app')

@section('title', 'Store Items')

@php
    $mayAdd = can_here('add');
    $mayEdit = can_here('edit');
    $mayDelete = can_here('delete');

    $money = fn ($n) => '₹' . number_format((float) $n, 2);
    $qty = fn ($n) => rtrim(rtrim(number_format((float) $n, 3), '0'), '.');

    $tab = fn (string $name) => request()->fullUrlWithQuery(['show' => $name, 'page' => null]);
@endphp

@section('content')
    <x-page-header
        title="Store Items"
        subtitle="What the store keeps. Quantities move through documents — never by typing over them."
        :crumbs="['Home' => url('/'), 'Store', 'Items']"
    >
        <x-slot:actions>
            @canView('store/categories')
                <a href="{{ route('store.categories') }}" class="nv-btn nv-btn-outline">
                    <x-icon name="grid" /> Categories
                </a>
            @endCanView
            @canView('store/stock')
                <a href="{{ route('store.stock') }}" class="nv-btn nv-btn-primary">
                    <x-icon name="chart" /> Stock sheet
                </a>
            @endCanView
        </x-slot:actions>
    </x-page-header>

    <div class="nv-grid nv-grid-4">
        <x-stat label="Items" :value="number_format($summary['items'])" icon="package" />
        <x-stat label="Stock value" :value="$money($summary['value'])" icon="wallet" tone="success"
                caption="At the moving average" />
        <x-stat label="At reorder level" :value="$summary['reorder']" icon="alert"
                :tone="$summary['reorder'] ? 'warning' : 'success'" />
        <x-stat label="Below zero" :value="$summary['negative']" icon="x-circle"
                :tone="$summary['negative'] ? 'danger' : 'success'"
                caption="A delivery note is late" />
    </div>

    @if ($errors->any())
        <div class="nv-mt"><x-alert tone="danger" title="Not saved">{{ $errors->first() }}</x-alert></div>
    @endif

    @if ($mayAdd && ! $editing)
        <form id="item-new" method="POST" action="{{ route('store.items.store') }}">
            @csrf
            <input type="hidden" name="status" value="1" />
            <input type="hidden" name="is_ingredient" value="0" />
        </form>
    @endif

    @if ($mayEdit && $editing)
        <form id="item-edit" method="POST" action="{{ route('store.items.update', $editing) }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="status" value="0" />
            <input type="hidden" name="is_ingredient" value="0" />
        </form>
    @endif

    <div class="nv-mt">
        <x-card flush>
            <form method="GET" class="nv-toolbar">
                <div class="nv-field-search">
                    <x-icon name="search" />
                    <input type="search" name="q" value="{{ $filters['q'] }}" class="nv-input"
                           placeholder="Item or code…" />
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

                <button type="submit" class="nv-btn nv-btn-primary"><x-icon name="search" /> Search</button>

                <span class="nv-toolbar-spacer"></span>

                <div class="nv-tabs">
                    <a href="{{ $tab('all') }}" @class(['nv-tab', 'is-active' => $view === 'all'])>All</a>
                    <a href="{{ $tab('reorder') }}" @class(['nv-tab', 'is-active' => $view === 'reorder'])>
                        To reorder
                    </a>
                    <a href="{{ $tab('short') }}" @class(['nv-tab', 'is-active' => $view === 'short'])>Below zero</a>
                </div>
            </form>

            <div class="nv-table-wrap">
                <table class="nv-table nv-setup-grid">
                    <thead>
                        <tr>
                            <th style="min-width:190px">Item</th>
                            <th style="width:96px">Code</th>
                            <th style="min-width:140px">Category</th>
                            <th style="width:76px">Unit</th>
                            <th class="is-end" style="width:104px">In stock</th>
                            <th class="is-end" style="width:104px">Avg rate</th>
                            <th class="is-end" style="width:104px">Value</th>
                            <th class="is-end" style="width:96px">Reorder at</th>
                            <th style="width:150px">&nbsp;</th>
                        </tr>
                    </thead>

                    <tbody>
                        @if ($mayAdd && ! $editing)
                            <tr class="is-new">
                                <td>
                                    <input form="item-new" name="name" class="nv-input" value="{{ old('name') }}"
                                           placeholder="Basmati Rice" required />
                                </td>
                                <td>
                                    <input form="item-new" name="code" class="nv-input" value="{{ old('code') }}"
                                           placeholder="Scan or type" title="Matched when this is scanned on a document" />
                                </td>
                                <td>
                                    <select form="item-new" name="store_category_id" class="nv-select">
                                        <option value="">No category</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}" @selected(old('store_category_id') == $category->id)>
                                                {{ $category->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <input form="item-new" name="unit" class="nv-input" value="{{ old('unit', 'kg') }}"
                                           maxlength="20" required />
                                </td>
                                <td>
                                    {{-- The opening balance is the one quantity a screen may set. --}}
                                    <input form="item-new" type="number" step="0.001" min="0" name="opening_qty"
                                           class="nv-input" value="{{ old('opening_qty', 0) }}" title="Opening quantity" />
                                </td>
                                <td>
                                    <input form="item-new" type="number" step="0.01" min="0" name="opening_rate"
                                           class="nv-input" value="{{ old('opening_rate', 0) }}" title="Opening rate" />
                                </td>
                                <td class="is-end"><span class="nv-muted">—</span></td>
                                <td>
                                    <input form="item-new" type="number" step="0.001" min="0" name="reorder_level"
                                           class="nv-input" value="{{ old('reorder_level', 0) }}" />
                                </td>
                                <td>
                                    <label class="nv-check-inline">
                                        <input form="item-new" type="checkbox" name="is_ingredient" value="1"
                                               @checked(old('is_ingredient', true)) />
                                        Recipe
                                    </label>
                                    <button form="item-new" type="submit" class="nv-btn nv-btn-sm nv-btn-primary">
                                        <x-icon name="plus" /> Add
                                    </button>
                                </td>
                            </tr>
                        @endif

                        @forelse ($items as $item)
                            @if ($editing === $item->id)
                                <tr class="is-editing">
                                    <td>
                                        <input form="item-edit" name="name" class="nv-input"
                                               value="{{ old('name', $item->name) }}" required />
                                    </td>
                                    <td>
                                        <input form="item-edit" name="code" class="nv-input"
                                               value="{{ old('code', $item->code) }}" placeholder="Scan or type"
                                               title="Matched when this is scanned on a document" />
                                    </td>
                                    <td>
                                        <select form="item-edit" name="store_category_id" class="nv-select">
                                            <option value="">No category</option>
                                            @foreach ($categories as $category)
                                                <option value="{{ $category->id }}" @selected(old('store_category_id', $item->store_category_id) == $category->id)>
                                                    {{ $category->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input form="item-edit" name="unit" class="nv-input"
                                               value="{{ old('unit', $item->unit) }}" required />
                                    </td>
                                    <td>
                                        <input form="item-edit" type="number" step="0.001" name="opening_qty"
                                               class="nv-input" value="{{ old('opening_qty', $item->opening_qty) }}"
                                               title="Opening quantity — changing this replays the ledger" />
                                    </td>
                                    <td>
                                        <input form="item-edit" type="number" step="0.01" min="0" name="opening_rate"
                                               class="nv-input" value="{{ old('opening_rate', $item->opening_rate) }}" />
                                    </td>
                                    <td class="is-end">{{ $money($item->value) }}</td>
                                    <td>
                                        <input form="item-edit" type="number" step="0.001" min="0" name="reorder_level"
                                               class="nv-input" value="{{ old('reorder_level', $item->reorder_level) }}" />
                                    </td>
                                    <td>
                                        <label class="nv-check-inline">
                                            <input form="item-edit" type="checkbox" name="status" value="1"
                                                   @checked(old('status', $item->status)) />
                                            Active
                                        </label>
                                        <div class="nv-row-actions">
                                            <a href="{{ route('store.items') }}" class="nv-btn nv-btn-sm nv-btn-ghost">Cancel</a>
                                            <button form="item-edit" type="submit" class="nv-btn nv-btn-sm nv-btn-primary">
                                                <x-icon name="check" /> Save
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @else
                                <tr @class(['is-off' => ! $item->isActive()])>
                                    <td>
                                        <strong>{{ $item->name }}</strong>
                                        @if ($item->isShort())
                                            <span class="nv-sub nv-st-short">Below zero — a delivery note is late</span>
                                        @elseif ($item->needsReorder())
                                            <span class="nv-sub nv-st-low">At or below the reorder level</span>
                                        @endif
                                    </td>
                                    <td>{{ $item->code ?: '—' }}</td>
                                    <td>{{ $item->category?->name ?: '—' }}</td>
                                    <td>{{ $item->unit }}</td>
                                    <td @class(['is-end', 'nv-st-qty', 'is-short' => $item->isShort(), 'is-low' => $item->needsReorder() && ! $item->isShort()])>
                                        {{ $qty($item->current_qty) }}
                                    </td>
                                    <td class="is-end">{{ $money($item->avg_rate) }}</td>
                                    <td class="is-end">{{ $money($item->value) }}</td>
                                    <td class="is-end">
                                        {{ (float) $item->reorder_level > 0 ? $qty($item->reorder_level) : '—' }}
                                    </td>
                                    <td>
                                        <div class="nv-row-actions">
                                            @canView('store/stock')
                                                <a href="{{ route('store.stock.ledger', $item) }}"
                                                   class="nv-btn nv-btn-sm nv-btn-ghost" title="Its movements">
                                                    <x-icon name="activity" />
                                                </a>
                                            @endCanView

                                            @if ($mayEdit)
                                                <a href="{{ route('store.items', array_merge(request()->query(), ['edit' => $item->id])) }}"
                                                   class="nv-btn nv-btn-sm nv-btn-ghost">
                                                    <x-icon name="pencil" />
                                                </a>
                                            @endif

                                            @if ($mayDelete)
                                                <form method="POST" action="{{ route('store.items.destroy', $item) }}"
                                                      data-confirm="Delete {{ $item->name }}? An item with movements is switched off instead."
                                                      data-confirm-title="Delete item">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="nv-btn nv-btn-sm nv-btn-ghost">
                                                        <x-icon name="trash" />
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr>
                                <td colspan="9">
                                    <div class="nv-empty">
                                        <span class="nv-empty-icon"><x-icon name="package" /></span>
                                        <strong>
                                            {{ $view === 'all' ? 'No items yet' : 'Nothing in this list' }}
                                        </strong>
                                        <p>
                                            @if ($view === 'reorder')
                                                Nothing is at its reorder level — or no levels have been set.
                                            @elseif ($view === 'short')
                                                No item is below zero. Every issue has stock behind it.
                                            @else
                                                Add the things the store keeps. Opening quantities go in here;
                                                everything after that moves through a document.
                                            @endif
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($items->hasPages())
                <div class="nv-card-foot">{{ $items->links() }}</div>
            @endif
        </x-card>
    </div>

    <div class="nv-mt">
        <x-alert tone="info" title="Why the stock column is not editable">
            Because the ledger has to be able to explain the balance. Everything after the opening quantity
            moves through a goods receipt, an issue, a wastage note or a stock adjustment — and each of those
            leaves a row saying when, how much and why. Typing over the number would break the one thing that
            makes a store auditable.
        </x-alert>
    </div>
@endsection
