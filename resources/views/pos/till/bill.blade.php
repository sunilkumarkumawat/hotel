@extends('layouts.app')

@section('title', 'Bill · ' . ($order->where_label ?: $order->order_no))

@php
    use App\Models\Pos\PosOrder;

    $editable = $order->isOpen();
    $canAdd = can_do('point-of-sale/pos', 'add') && $editable;
    $canEdit = can_do('point-of-sale/pos', 'edit');
    $canDelete = can_do('point-of-sale/pos', 'delete');

    $headings = $tree->get('headings', collect());
    $subs = $tree->get('subs', collect());

    // Tapping a heading shows its sub-headings underneath, so a long menu is
    // walked into rather than scrolled through.
    $openHeading = $chosen
        ? ($headings->firstWhere('id', $chosen)?->id ?? $subs->firstWhere('id', $chosen)?->parent_id)
        : null;

    $pending = $lines->where('kot_no', 0);
    $keep = array_filter(['category' => $chosen, 'q' => $term ?: null]);
@endphp

@section('content')
    <x-page-header
        :title="$order->where_label"
        :subtitle="$order->order_no . ' · ' . (PosOrder::TYPES[$order->order_type] ?? $order->order_type)
            . ' · opened ' . $order->opened_at?->format('d M, h:i A')"
        :crumbs="['Home' => url('/'), 'POS' => route('point-of-sale.pos', ['outlet' => $order->outlet_id]), 'Bill']"
    >
        <x-slot:actions>
            <a href="{{ route('point-of-sale.pos', ['outlet' => $order->outlet_id]) }}" class="nv-btn nv-btn-outline">
                <x-icon name="chevron-left" /> Floor
            </a>
        </x-slot:actions>
    </x-page-header>

    @include('pos.till.partials.nav', ['current' => 'floor'])

    @if (session('status'))
        <div class="nv-mt"><x-alert tone="success">{{ session('status') }}</x-alert></div>
    @endif

    @if (session('error'))
        <div class="nv-mt"><x-alert tone="danger" title="Not done">{{ session('error') }}</x-alert></div>
    @endif

    @if (! $editable)
        <div class="nv-mt">
            <x-alert tone="warning" title="This order is {{ strtolower(PosOrder::STATUSES[$order->status]) }}">
                Nothing more can be added to it. Reprint the bill below, or take payment if it is still owed.
            </x-alert>
        </div>
    @endif

    <div class="nv-till">

        {{-- ══ The menu ══════════════════════════════════════════════════ --}}
        <div class="nv-till-menu">
            <div class="nv-till-find">
                <form method="GET" class="nv-field-search">
                    <x-icon name="search" />
                    <input type="search" name="q" value="{{ $term }}" class="nv-input"
                           placeholder="Search the menu…" aria-label="Search the menu" />
                    @if ($chosen)
                        <input type="hidden" name="category" value="{{ $chosen }}" />
                    @endif
                </form>

                @if ($term)
                    <a href="{{ route('point-of-sale.pos.order', [$order->id] + array_filter(['category' => $chosen])) }}"
                       class="nv-btn nv-btn-ghost nv-btn-sm">Clear search</a>
                @endif
            </div>

            {{-- Categories, one at a time, with All as the way back out. --}}
            <div class="nv-cats" role="tablist" aria-label="Menu categories">
                <a href="{{ route('point-of-sale.pos.order', [$order->id] + array_filter(['q' => $term ?: null])) }}"
                   @class(['nv-cat-chip', 'is-active' => ! $chosen])>
                    <x-icon name="grid" /> All
                </a>

                @foreach ($headings as $heading)
                    <a href="{{ route('point-of-sale.pos.order', [$order->id, 'category' => $heading->id] + array_filter(['q' => $term ?: null])) }}"
                       @class(['nv-cat-chip', 'is-active' => $chosen === $heading->id])>
                        {{ $heading->name }}
                        <i>{{ $heading->items_count }}</i>
                    </a>
                @endforeach
            </div>

            @php $children = $openHeading ? $subs->where('parent_id', $openHeading) : collect(); @endphp

            @if ($children->isNotEmpty())
                <div class="nv-cats is-subs">
                    <a href="{{ route('point-of-sale.pos.order', [$order->id, 'category' => $openHeading] + array_filter(['q' => $term ?: null])) }}"
                       @class(['nv-cat-chip is-sub', 'is-active' => $chosen === $openHeading])>
                        Everything in {{ $headings->firstWhere('id', $openHeading)?->name }}
                    </a>

                    @foreach ($children as $sub)
                        <a href="{{ route('point-of-sale.pos.order', [$order->id, 'category' => $sub->id] + array_filter(['q' => $term ?: null])) }}"
                           @class(['nv-cat-chip is-sub', 'is-active' => $chosen === $sub->id])>
                            {{ $sub->name }}
                            <i>{{ $sub->items_count }}</i>
                        </a>
                    @endforeach
                </div>
            @endif

            {{-- The items themselves. --}}
            @if ($items->isEmpty())
                <div class="nv-card nv-mt">
                    <div class="nv-card-body">
                        <div class="nv-empty">
                            <span class="nv-empty-icon"><x-icon name="bag" /></span>
                            <strong>Nothing to sell here yet</strong>
                            <p>
                                {{ $term
                                    ? 'Nothing on the menu matches that.'
                                    : 'Add what this outlet sells under Setup → Items.' }}
                            </p>
                            @canView('point-of-sale/setup/items')
                                @unless ($term)
                                    <a href="{{ route('point-of-sale.setup.items') }}" class="nv-btn nv-btn-primary">
                                        <x-icon name="plus" /> Add items
                                    </a>
                                @endunless
                            @endCanView
                        </div>
                    </div>
                </div>
            @else
                <div class="nv-items">
                    @foreach ($items as $item)
                        @if ($item->has_modifiers)
                            {{--
                                An item with modifier groups cannot be added by
                                one tap — the till has to ask its questions
                                first — so the button opens that item's own
                                modal instead of posting straight away. See
                                partials.item-modifiers-modal, included once
                                per such item just below the settle/cancel/
                                shift modals.
                            --}}
                            @if ($canAdd)
                                <a href="#item-mods-{{ $item->id }}"
                                   @class(['nv-item', 'is-veg' => $item->is_veg, 'is-nonveg' => ! $item->is_veg])>
                                    <span class="nv-item-dot" aria-hidden="true"></span>
                                    <span class="nv-item-name">{{ $item->name }}</span>
                                    @if ($item->code)
                                        <span class="nv-item-code">{{ $item->code }}</span>
                                    @endif
                                    <span class="nv-item-price">₹ {{ number_format((float) $item->sell_price, 2) }}+</span>
                                </a>
                            @else
                                <span @class(['nv-item', 'is-off', 'is-veg' => $item->is_veg, 'is-nonveg' => ! $item->is_veg])
                                      aria-disabled="true">
                                    <span class="nv-item-dot" aria-hidden="true"></span>
                                    <span class="nv-item-name">{{ $item->name }}</span>
                                    @if ($item->code)
                                        <span class="nv-item-code">{{ $item->code }}</span>
                                    @endif
                                    <span class="nv-item-price">₹ {{ number_format((float) $item->sell_price, 2) }}+</span>
                                </span>
                            @endif
                        @else
                            <form method="POST" action="{{ route('point-of-sale.pos.order.item', $order->id) }}"
                                  class="nv-item-form">
                                @csrf
                                <input type="hidden" name="item_id" value="{{ $item->id }}" />
                                @foreach ($keep as $key => $value)
                                    <input type="hidden" name="{{ $key }}" value="{{ $value }}" />
                                @endforeach

                                <button type="submit" @class(['nv-item', 'is-veg' => $item->is_veg, 'is-nonveg' => ! $item->is_veg])
                                        @disabled(! $canAdd)>
                                    <span class="nv-item-dot" aria-hidden="true"></span>
                                    <span class="nv-item-name">{{ $item->name }}</span>
                                    @if ($item->code)
                                        <span class="nv-item-code">{{ $item->code }}</span>
                                    @endif
                                    <span class="nv-item-price">₹ {{ number_format((float) $item->sell_price, 2) }}</span>
                                </button>
                            </form>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ══ The bill ══════════════════════════════════════════════════ --}}
        <aside class="nv-till-bill" aria-label="This order">
            <form method="POST" action="{{ route('point-of-sale.pos.order.header', $order->id) }}"
                  id="order-header" class="nv-bill-head">
                @csrf
                @method('PUT')

                <div class="nv-bill-head-grid">
                    <label class="nv-bill-field">
                        <span>Steward</span>
                        <select name="pos_steward_id" class="nv-select nv-input-sm" @disabled(! $canEdit || ! $editable)>
                            <option value="">—</option>
                            @foreach ($stewards as $id => $name)
                                <option value="{{ $id }}" @selected($order->pos_steward_id == $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="nv-bill-field">
                        <span>Covers</span>
                        <input type="number" name="pax" value="{{ $order->pax }}" min="1" max="250"
                               class="nv-input nv-input-sm" @disabled(! $canEdit || ! $editable) />
                    </label>

                    @if ($ratePlans->isNotEmpty())
                        <label class="nv-bill-field">
                            <span>Price list</span>
                            <select name="pos_rate_plan_id" class="nv-select nv-input-sm" @disabled(! $canEdit || ! $editable)>
                                <option value="">Everyday</option>
                                @foreach ($ratePlans as $id => $name)
                                    <option value="{{ $id }}" @selected($order->pos_rate_plan_id == $id)>{{ $name }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif

                    {{--
                        Tax, chosen per bill. A new order opens on "No Tax" and
                        stays there until somebody picks something — the till
                        does not reach for the branch default any more.
                        Lines already sent to the kitchen keep the tax they were
                        quoted at; only unsent lines follow a change here.
                    --}}
                    <label class="nv-bill-field">
                        <span>Tax</span>
                        <select name="tax_choice" class="nv-select nv-input-sm" @disabled(! $canEdit || ! $editable)>
                            @foreach ($taxChoices as $key => $label)
                                <option value="{{ $key }}"
                                        @selected((string) $key === (string) \App\Support\PosTill::normaliseTaxChoice($order->tax_choice))>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="nv-bill-field">
                        <span>Discount %</span>
                        <input type="number" name="discount_percent" value="{{ (float) $order->discount_percent }}"
                               min="0" max="100" step="0.01" class="nv-input nv-input-sm"
                               @disabled(! $canEdit || ! $editable) />
                    </label>

                    <label class="nv-bill-field is-wide">
                        <span>Guest</span>
                        <input type="text" name="guest_name" value="{{ $order->guest_name }}" maxlength="150"
                               class="nv-input nv-input-sm" placeholder="Name on the bill"
                               @disabled(! $canEdit || ! $editable) />
                    </label>
                </div>

                @if ($canEdit && $editable)
                    <button type="submit" class="nv-btn nv-btn-ghost nv-btn-sm nv-bill-save">
                        <x-icon name="check" /> Save details
                    </button>
                @endif
            </form>

            {{-- ── The lines ──────────────────────────────────────────── --}}
            <div class="nv-bill-lines">
                @forelse ($lines as $line)
                    @php $sent = $line->kot_no > 0; @endphp

                    <div @class(['nv-bill-line', 'is-sent' => $sent, 'is-nc' => $line->is_nc])>
                        <div class="nv-bill-line-top">
                            <span class="nv-bill-line-name">
                                {{ $line->item_name }}
                                @if ($line->is_nc)
                                    <x-badge tone="warning">NC</x-badge>
                                @endif
                            </span>

                            <span class="nv-bill-line-amount">₹ {{ number_format((float) $line->total_amount, 2) }}</span>
                        </div>

                        <div class="nv-bill-line-foot">
                            @if ($canEdit && $editable)
                                <form method="POST"
                                      action="{{ route('point-of-sale.pos.order.item.update', [$order->id, $line->id]) }}"
                                      class="nv-qty">
                                    @csrf
                                    @method('PUT')

                                    {{--
                                        The buttons post a STEP and the box posts a quantity, under
                                        two different names. Three controls all called `qty` looks
                                        tidier and does not work: the browser sends the button's
                                        value and the box's, PHP keeps the last one, and the minus
                                        button silently does nothing.
                                    --}}
                                    <button type="submit" name="step" value="-1"
                                            class="nv-qty-btn" aria-label="One less {{ $line->item_name }}">−</button>

                                    <input type="number" name="qty" value="{{ rtrim(rtrim(number_format((float) $line->qty, 2, '.', ''), '0'), '.') }}"
                                           step="0.5" min="0.01" max="999" class="nv-qty-input"
                                           aria-label="Quantity of {{ $line->item_name }}" />

                                    <button type="submit" name="step" value="1"
                                            class="nv-qty-btn" aria-label="One more {{ $line->item_name }}">+</button>
                                </form>
                            @else
                                <span class="nv-bill-line-qty">
                                    × {{ rtrim(rtrim(number_format((float) $line->qty, 2, '.', ''), '0'), '.') }}
                                </span>
                            @endif

                            <span class="nv-bill-line-rate">@ ₹{{ number_format((float) $line->price, 2) }}</span>

                            @if ($sent)
                                <span class="nv-bill-line-kot" title="Went to the kitchen on KOT {{ $line->kot_no }}">
                                    KOT {{ $line->kot_no }}
                                </span>
                            @else
                                <span class="nv-bill-line-kot is-new">Not sent</span>
                            @endif

                            @if ($canDelete && $editable)
                                <form method="POST"
                                      action="{{ route('point-of-sale.pos.order.item.destroy', [$order->id, $line->id]) }}"
                                      @if ($sent)
                                          data-confirm="{{ $line->item_name }} has already gone to the kitchen. Taking it off now is recorded against this till."
                                          data-confirm-title="Remove a cooked item?"
                                          data-confirm-action="Remove"
                                      @endif>
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="nv-icon-btn is-danger" aria-label="Remove {{ $line->item_name }}">
                                        <x-icon name="trash" />
                                    </button>
                                </form>
                            @endif
                        </div>

                        @if ($line->has_modifiers && $line->modifiers->isNotEmpty())
                            <p class="nv-bill-line-note">
                                <x-icon name="plus" />
                                {{ $line->modifiers->map(fn ($m) => $m->name . ((float) $m->price > 0
                                    ? ' (+₹' . rtrim(rtrim(number_format((float) $m->price, 2), '0'), '.') . ')'
                                    : ''))->implode(', ') }}
                            </p>
                        @endif

                        @if ($line->remark)
                            <p class="nv-bill-line-note"><x-icon name="info" /> {{ $line->remark }}</p>
                        @endif
                    </div>
                @empty
                    <div class="nv-empty is-tight">
                        <span class="nv-empty-icon"><x-icon name="inbox" /></span>
                        <strong>Nothing on this order yet</strong>
                        <p>Tap something on the menu to start.</p>
                    </div>
                @endforelse
            </div>

            {{-- ── What it comes to ───────────────────────────────────── --}}
            <div class="nv-bill-total">
                <div class="nv-bill-row"><span>Sub total</span><b>₹ {{ number_format((float) $order->sub_total, 2) }}</b></div>

                @if ((float) $order->discount_total > 0)
                    <div class="nv-bill-row is-off">
                        <span>Discount{{ (float) $order->discount_percent ? ' (' . rtrim(rtrim(number_format((float) $order->discount_percent, 2), '0'), '.') . '%)' : '' }}</span>
                        <b>− ₹ {{ number_format((float) $order->discount_total, 2) }}</b>
                    </div>
                @endif

                @if ((float) $order->service_charge > 0)
                    <div class="nv-bill-row"><span>Service charge</span><b>₹ {{ number_format((float) $order->service_charge, 2) }}</b></div>
                @endif

                <div class="nv-bill-row"><span>Tax</span><b>₹ {{ number_format((float) $order->tax_total, 2) }}</b></div>

                @if ((float) $order->round_off != 0)
                    <div class="nv-bill-row"><span>Round off</span><b>₹ {{ number_format((float) $order->round_off, 2) }}</b></div>
                @endif

                <div class="nv-bill-row is-net">
                    <span>Net payable</span>
                    <b>₹ {{ number_format((float) $order->net_amount, 2) }}</b>
                </div>

                @if ($invoice)
                    <div class="nv-bill-row is-note">
                        <span>Bill {{ $invoice->invoice_no }}</span>
                        <b>{{ $invoice->balance() > 0 ? '₹ ' . number_format($invoice->balance(), 2) . ' owing' : 'Paid' }}</b>
                    </div>
                @endif
            </div>

            {{-- ── What to do next ────────────────────────────────────── --}}
            <div class="nv-bill-acts">
                @if ($canAdd)
                    <form method="POST" action="{{ route('point-of-sale.pos.order.kot', $order->id) }}">
                        @csrf
                        <button type="submit" class="nv-btn nv-btn-outline" @disabled($pending->isEmpty())>
                            <x-icon name="bell" />
                            {{ $pending->isEmpty() ? 'Kitchen is up to date' : 'Send ' . $pending->count() . ' to kitchen' }}
                        </button>
                    </form>
                @endif

                @if ($canEdit && $order->status !== 'cancelled')
                    <form method="POST" action="{{ route('point-of-sale.pos.order.bill', $order->id) }}">
                        @csrf
                        <button type="submit" class="nv-btn nv-btn-outline" @disabled($lines->isEmpty())>
                            <x-icon name="file" /> {{ $invoice ? 'Reprint bill' : 'Print bill' }}
                        </button>
                    </form>

                    @if ($lines->isNotEmpty() && ! $order->isSettled())
                        <a href="#settle" class="nv-btn nv-btn-primary">
                            <x-icon name="credit-card" /> Settle
                        </a>
                    @else
                        <span class="nv-btn nv-btn-primary is-off" aria-disabled="true">
                            <x-icon name="credit-card" /> Settle
                        </span>
                    @endif
                @endif

                @if ($order->kot_count)
                    <a href="{{ route('point-of-sale.pos.order.kot.print', [$order->id, $order->kot_count]) }}"
                       class="nv-btn nv-btn-ghost nv-btn-sm" target="_blank">
                        <x-icon name="file" /> KOT {{ $order->kot_count }}
                    </a>
                @endif

                @if ($canEdit && $editable && $order->pos_table_id && $tables->isNotEmpty())
                    <a href="#shift" class="nv-btn nv-btn-ghost nv-btn-sm">
                        <x-icon name="arrow-right" /> Move table
                    </a>
                @endif

                @if ($canDelete && ! $order->isSettled() && $order->status !== 'cancelled')
                    <a href="#cancel" class="nv-btn nv-btn-ghost nv-btn-sm is-danger">
                        <x-icon name="x-circle" /> Cancel order
                    </a>
                @endif
            </div>
        </aside>
    </div>

    @include('pos.till.partials.settle-modal')
    @include('pos.till.partials.cancel-modal')
    @include('pos.till.partials.shift-modal')

    @foreach ($items->where('has_modifiers', true) as $item)
        @include('pos.till.partials.item-modifiers-modal', ['item' => $item])
    @endforeach
@endsection

@push('scripts')
    <script src="{{ asset('js/pos-till.js') }}?v={{ filemtime(public_path('js/pos-till.js')) }}" defer></script>

    @if (session('print'))
        {{-- Printing the bill opens the sheet in its own tab, so the till is
             still on the order when the cashier turns back to it. --}}
        <script>window.open(@json(session('print') . '?auto=1'), '_blank');</script>
    @endif
@endpush
