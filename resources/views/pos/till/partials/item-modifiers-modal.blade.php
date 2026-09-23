{{--
    Picking modifiers for one item — Size, Toppings, whatever groups Setup →
    Items assigned it. One of these renders per item that has any, right after
    the settle/cancel/shift modals; the item's own button on the menu grid
    (see bill.blade.php) opens it instead of adding straight away.

    Every input is named modifiers[<group id>][] whether the group is single-
    or multiple-select — a radio and a checkbox scoped to the same group name
    behave correctly either way, and it keeps two different groups on the same
    item from ever fighting over one shared name. The server re-derives which
    group each id belongs to itself; see PosTill::resolveModifiers().
--}}

<div class="nv-modal-backdrop" id="item-mods-{{ $item->id }}" data-modal="item-mods-{{ $item->id }}">
    <div class="nv-modal" role="dialog" aria-modal="true" aria-labelledby="item-mods-{{ $item->id }}-title">
        <div class="nv-modal-head">
            <strong id="item-mods-{{ $item->id }}-title">{{ $item->name }}</strong>
            <a href="#" class="nv-icon-btn" data-modal-close aria-label="Close">
                <x-icon name="x" />
            </a>
        </div>

        <form method="POST" action="{{ route('point-of-sale.pos.order.item', $order->id) }}">
            @csrf
            <input type="hidden" name="item_id" value="{{ $item->id }}" />
            @foreach ($keep as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}" />
            @endforeach

            @foreach ($item->modifierGroups->filter(fn ($g) => $g->modifiers->isNotEmpty()) as $group)
                <div class="nv-field">
                    <label>
                        {{ $group->name }}{{ $group->is_required ? ' (required)' : '' }}
                        @if ($group->selection_type === 'multiple' && $group->max_select)
                            <span class="nv-muted">— up to {{ $group->max_select }}</span>
                        @endif
                    </label>

                    <div class="nv-check-grid">
                        @foreach ($group->modifiers as $modifier)
                            <label class="nv-check">
                                <input type="{{ $group->selection_type === 'single' ? 'radio' : 'checkbox' }}"
                                       name="modifiers[{{ $group->id }}][]"
                                       value="{{ $modifier->id }}"
                                       @if ($group->is_required && $group->selection_type === 'single') required @endif />
                                <span>
                                    {{ $modifier->name }}
                                    @if ((float) $modifier->price > 0)
                                        <b>+₹{{ rtrim(rtrim(number_format((float) $modifier->price, 2), '0'), '.') }}</b>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <x-field label="Note" name="remark">
                <x-input name="remark" maxlength="120" placeholder="No onion, well done…" />
            </x-field>

            <div class="nv-actions" style="justify-content:flex-end;margin-top:16px">
                <a href="#" class="nv-btn nv-btn-ghost" data-modal-close>Cancel</a>
                <button type="submit" class="nv-btn nv-btn-primary">
                    <x-icon name="plus" /> Add to order
                </button>
            </div>
        </form>
    </div>
</div>
