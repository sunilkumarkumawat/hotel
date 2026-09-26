@extends('layouts.public')

@section('title', ($outlet?->name ? $outlet->name . ' — ' : '') . 'Table ' . $table->name)

@php
    $headings = $tree->get('headings', collect())->sortBy('name');
@endphp

@section('content')
<div class="nv-om">
    <div class="nv-om-card">
        <div class="nv-fb-head">
            <p class="nv-fb-hotel">{{ $hotel?->legal_name ?: ($hotel?->branch_name ?: config('app.name')) }}</p>
            <h1>{{ $outlet?->name ?: 'Menu' }} · Table {{ $table->name }}</h1>
            <p>Pick what you'd like. We'll bring it out as soon as the kitchen confirms.</p>
        </div>

        <form method="POST"
              action="{{ route('guest-order.store', ['table' => $table->id, 'code' => $code]) }}"
              id="om-form" class="nv-om-body">
            @csrf

            @if (session('error'))
                <p class="nv-fb-error">{{ session('error') }}</p>
            @endif

            <div class="nv-om-search">
                <x-icon name="search" />
                <input type="search" id="om-search" placeholder="Search the menu…" autocomplete="off" />
            </div>

            @if ($headings->isNotEmpty())
                <div class="nv-om-tabs" id="om-tabs">
                    <button type="button" class="nv-om-tab is-active" data-cat="">All</button>
                    @foreach ($headings as $heading)
                        <button type="button" class="nv-om-tab" data-cat="{{ $heading->id }}">{{ $heading->name }}</button>
                    @endforeach
                </div>
            @endif

            <div class="nv-om-list" id="om-list">
                @forelse ($items as $item)
                    <div class="nv-om-item" data-name="{{ strtolower($item->name) }}"
                         data-cat="{{ $item->pos_menu_category_id }}" data-price="{{ (float) $item->sell_price }}">
                        <span class="nv-om-photo">
                            @if ($item->photoUrl())
                                <img src="{{ $item->photoUrl() }}" alt="" loading="lazy" />
                            @else
                                <x-icon name="bag" />
                            @endif
                        </span>

                        <span class="nv-om-info">
                            <span class="nv-om-name">
                                <i class="nv-veg-dot {{ $item->is_veg ? 'is-veg' : 'is-nonveg' }}"
                                   title="{{ $item->is_veg ? 'Veg' : 'Non-Veg' }}"></i>
                                {{ $item->name }}
                            </span>
                            <span class="nv-om-price">₹ {{ number_format((float) $item->sell_price, 2) }}</span>
                        </span>

                        <span class="nv-om-qty" data-qty>
                            <button type="button" class="nv-om-step" data-step="-1" aria-label="One less">−</button>
                            <input type="number" name="items[{{ $item->id }}]" value="{{ old('items.' . $item->id, 0) }}" min="0" max="99"
                                   inputmode="numeric" class="nv-om-qty-input" aria-label="Quantity — {{ $item->name }}" />
                            <button type="button" class="nv-om-step" data-step="1" aria-label="One more">+</button>
                        </span>
                    </div>
                @empty
                    <div class="nv-empty">
                        <span class="nv-empty-icon"><x-icon name="bag" /></span>
                        <strong>Nothing on the menu yet</strong>
                        <p>Ask a staff member — they can take your order the usual way.</p>
                    </div>
                @endforelse

                <p class="nv-om-no-match" id="om-no-match" hidden>Nothing matches that search.</p>
            </div>

            @if ($items->isNotEmpty())
                <div class="nv-om-note">
                    <label for="om-note">Anything we should know? <span>(optional)</span></label>
                    <textarea id="om-note" name="note" rows="2" maxlength="255"
                              placeholder="e.g. less spicy, no onions">{{ old('note') }}</textarea>
                </div>

                <div class="nv-om-note">
                    <label for="om-contact">Want an update on this order? <span>(optional)</span></label>
                    <input type="text" id="om-contact" name="contact" maxlength="100"
                           value="{{ old('contact') }}"
                           placeholder="WhatsApp number or email" />
                </div>
            @endif
        </form>
    </div>

    @if ($items->isNotEmpty())
        <div class="nv-om-cart" id="om-cart">
            <span class="nv-om-cart-info">
                <span id="om-cart-empty">Choose items to send your order</span>
                <span id="om-cart-filled" hidden><b id="om-cart-count">0</b> item(s) · <b id="om-cart-total">₹ 0.00</b></span>
            </span>
            <button type="submit" form="om-form" class="nv-fb-send nv-om-send" id="om-send" disabled>
                Send order
            </button>
        </div>
    @endif

    <p class="nv-fb-foot">
        {{ $hotel?->branch_name }}{{ $hotel?->mobile_number ? ' · ' . $hotel->mobile_number : '' }}
    </p>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/guest-order.js') }}?v={{ file_exists(public_path('js/guest-order.js')) ? filemtime(public_path('js/guest-order.js')) : time() }}" defer></script>
@endpush
