@extends('layouts.app')

@section('title', $recipe?->name ?: 'New recipe')

@php
    $money = fn ($n) => '₹' . number_format((float) $n, 2);

    $rows = old('lines', $recipe
        ? $recipe->lines->map(fn ($l) => [
            'store_item_id' => $l->store_item_id,
            'qty' => (float) $l->qty,
            'remark' => $l->remark,
        ])->all()
        : [[], [], []]);

    $boot = [
        'items' => $items->map(fn ($i) => [
            'id' => (int) $i->id,
            'name' => $i->name,
            'unit' => $i->unit,
            'rate' => (float) $i->avg_rate,
        ])->values()->all(),
    ];
@endphp

@section('content')
    <x-page-header
        :title="$recipe?->name ?: 'New recipe'"
        subtitle="Write it the way a chef writes it — for however many portions it actually makes."
        :crumbs="['Home' => url('/'), 'Store', 'Recipes' => route('store.recipes'), $recipe?->name ?: 'New']"
    >
        <x-slot:actions>
            <a href="{{ route('store.recipes') }}" class="nv-btn nv-btn-outline">
                <x-icon name="chevron-left" /> All recipes
            </a>

            @if ($recipe && can_here('delete'))
                <form method="POST" action="{{ route('store.recipes.destroy', $recipe) }}"
                      data-confirm="Delete {{ $recipe->name }}?" data-confirm-title="Delete recipe">
                    @csrf @method('DELETE')
                    <button type="submit" class="nv-btn nv-btn-ghost"><x-icon name="trash" /> Delete</button>
                </form>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($errors->any())
        <div class="nv-mt">
            <x-alert tone="danger" title="Please fix {{ $errors->count() }} thing(s)">{{ $errors->first() }}</x-alert>
        </div>
    @endif

    @if ($costing && $costing['missing'] > 0)
        <div class="nv-mt">
            <x-alert tone="warning" title="{{ $costing['missing'] }} {{ \Illuminate\Support\Str::plural('ingredient', $costing['missing']) }} have no cost">
                They have never been received into the store, so they are priced at nothing and this dish
                looks cheaper than it is.
            </x-alert>
        </div>
    @endif

    <form method="POST"
          action="{{ $recipe ? route('store.recipes.update', $recipe) : route('store.recipes.store') }}"
          data-recipe>
        @csrf
        @if ($recipe) @method('PUT') @endif

        <div class="nv-grid nv-grid-main nv-mt">
            <div class="nv-stack">
                <x-card title="The dish">
                    <div class="nv-form-grid">
                        <x-field label="Name" name="name" required>
                            <x-input name="name" :value="old('name', $recipe?->name)" required
                                     placeholder="Hyderabadi Biryani" />
                        </x-field>

                        <x-field label="On the menu as" name="pos_item_id"
                                 help="Optional — but without it there is no price to compare the cost to.">
                            <select name="pos_item_id" class="nv-select">
                                <option value="">Not on the menu</option>
                                @foreach ($menuItems as $menu)
                                    <option value="{{ $menu->id }}" @selected(old('pos_item_id', $recipe?->pos_item_id) == $menu->id)>
                                        {{ $menu->name }} — ₹{{ number_format((float) $menu->price, 2) }}
                                    </option>
                                @endforeach
                            </select>
                        </x-field>

                        <x-field label="This recipe makes" name="yield_qty" required
                                 help="Write the recipe as you cook it; the cost is worked out per portion.">
                            <x-input type="number" step="0.001" min="0.001" name="yield_qty"
                                     :value="old('yield_qty', $recipe?->yield_qty ?: 1)" required />
                        </x-field>

                        <x-field label="Of" name="yield_unit" required>
                            <x-input name="yield_unit" :value="old('yield_unit', $recipe?->yield_unit ?: 'portion')"
                                     required />
                        </x-field>

                        <x-field label="Method" name="method" :wide="true">
                            <x-textarea name="method" :value="old('method', $recipe?->method)" rows="4"
                                        placeholder="Optional — the kitchen's own notes." />
                        </x-field>
                    </div>
                </x-card>

                <x-card title="Ingredients" :flush="true">
                    <div class="nv-table-wrap">
                        <table class="nv-table nv-st-lines" data-lines>
                            <thead>
                                <tr>
                                    <th style="min-width:230px">Ingredient</th>
                                    <th style="width:130px" class="is-end">Quantity</th>
                                    <th style="min-width:150px">Note</th>
                                    <th style="width:120px" class="is-end">Cost</th>
                                    <th style="width:44px">&nbsp;</th>
                                </tr>
                            </thead>

                            <tbody data-line-body>
                                @foreach ($rows as $i => $row)
                                    <tr data-line>
                                        <td>
                                            <select name="lines[{{ $i }}][store_item_id]" class="nv-select" data-item>
                                                <option value="">Choose…</option>
                                                @foreach ($items as $item)
                                                    <option value="{{ $item->id }}"
                                                            @selected(($row['store_item_id'] ?? null) == $item->id)>
                                                        {{ $item->name }} ({{ $item->unit }})
                                                    </option>
                                                @endforeach
                                            </select>
                                            <span class="nv-st-stock" data-stock></span>
                                        </td>
                                        <td>
                                            <input type="number" step="0.0001" min="0" class="nv-input is-num"
                                                   data-qty name="lines[{{ $i }}][qty]"
                                                   value="{{ $row['qty'] ?? '' }}" />
                                        </td>
                                        <td>
                                            <input type="text" class="nv-input" maxlength="255"
                                                   name="lines[{{ $i }}][remark]" value="{{ $row['remark'] ?? '' }}"
                                                   placeholder="chopped fine" />
                                        </td>
                                        <td class="is-end"><span data-amount>0.00</span></td>
                                        <td>
                                            <button type="button" class="nv-btn nv-btn-sm nv-btn-ghost nv-hidden"
                                                    data-line-remove><x-icon name="x" /></button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <template data-line-template>
                        <tr data-line>
                            <td>
                                <select name="lines[__i__][store_item_id]" class="nv-select" data-item>
                                    <option value="">Choose…</option>
                                    @foreach ($items as $item)
                                        <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->unit }})</option>
                                    @endforeach
                                </select>
                                <span class="nv-st-stock" data-stock></span>
                            </td>
                            <td><input type="number" step="0.0001" min="0" class="nv-input is-num" data-qty name="lines[__i__][qty]" /></td>
                            <td><input type="text" class="nv-input" maxlength="255" name="lines[__i__][remark]" /></td>
                            <td class="is-end"><span data-amount>0.00</span></td>
                            <td>
                                <button type="button" class="nv-btn nv-btn-sm nv-btn-ghost" data-line-remove>
                                    <x-icon name="x" />
                                </button>
                            </td>
                        </tr>
                    </template>

                    <x-slot:footer>
                        <div class="nv-st-foot">
                            <button type="button" class="nv-btn nv-btn-sm nv-btn-outline" data-line-add>
                                <x-icon name="plus" /> Add an ingredient
                            </button>

                            <div class="nv-st-totals">
                                <div class="is-net"><span>Whole recipe</span><b data-net>0.00</b></div>
                            </div>
                        </div>
                    </x-slot:footer>
                </x-card>
            </div>

            <div class="nv-stack">
                @if ($costing)
                    <x-card title="Costing" :subtitle="'At today\'s average rates'">
                        <div class="nv-crm-facts">
                            <div><span>Whole recipe</span><b>{{ $money($costing['total']) }}</b></div>
                            <div>
                                <span>Per {{ $recipe->yield_unit }}</span>
                                <b>{{ $money($costing['per_portion']) }}</b>
                            </div>

                            @if ($recipe->menuItem)
                                <div><span>Sells at</span><b>{{ $money($recipe->menuItem->price) }}</b></div>
                                <div>
                                    <span>Food cost</span>
                                    <b>
                                        {{ (float) $recipe->menuItem->price > 0
                                            ? number_format($costing['per_portion'] / (float) $recipe->menuItem->price * 100, 1) . '%'
                                            : '—' }}
                                    </b>
                                </div>
                                <div>
                                    <span>Margin</span>
                                    <b>{{ $money((float) $recipe->menuItem->price - $costing['per_portion']) }}</b>
                                </div>
                            @endif
                        </div>

                        @unless ($recipe->menuItem)
                            <p class="nv-help nv-mt">
                                Tie this to a menu item and the food cost and margin appear here.
                            </p>
                        @endunless
                    </x-card>
                @endif

                <x-card title="Status">
                    <label class="nv-check">
                        <input type="checkbox" name="status" value="1"
                               @checked(old('status', $recipe ? $recipe->status : 1)) />
                        <span>Active</span>
                    </label>

                    <p class="nv-help nv-mt">
                        An inactive recipe keeps its history but drops out of the costing averages.
                    </p>
                </x-card>
            </div>
        </div>

        <div class="nv-actions" style="justify-content:flex-end;margin-top:22px">
            <a href="{{ route('store.recipes') }}" class="nv-btn nv-btn-outline">Cancel</a>
            <button type="submit" class="nv-btn nv-btn-primary">
                <x-icon name="check" /> {{ $recipe ? 'Save changes' : 'Save the recipe' }}
            </button>
        </div>
    </form>
@endsection

@push('scripts')
    <script>window.storeDoc = @json($boot + ['kind' => 'recipe', 'signed' => false]);</script>
    <script src="{{ asset('js/store-lines.js') }}?v={{ filemtime(public_path('js/store-lines.js')) }}"></script>
@endpush
