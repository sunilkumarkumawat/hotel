{{--
    Moving an order to another table, from the bill screen.

    The same dialog as the one on the floor, and the same endpoint — only the
    list of destinations is built differently, because here the order is already
    known and the controller has handed over the free tables in its outlet.
--}}

@canEdit('point-of-sale/pos')
    <div class="nv-modal-backdrop" id="shift" data-modal="shift">
        <div class="nv-modal" role="dialog" aria-modal="true" aria-labelledby="shift-title">
            <div class="nv-modal-head">
                <strong id="shift-title">Move <span data-shift-name>{{ $order->table_no }}</span></strong>
                <a href="#" class="nv-icon-btn" data-modal-close aria-label="Close">
                    <x-icon name="x" />
                </a>
            </div>

            <form method="POST" action="{{ route('point-of-sale.pos.order.shift', $order->id) }}" data-shift-form>
                @csrf

                <x-field label="Move to" name="pos_table_id" required>
                    <select name="pos_table_id" id="pos_table_id" class="nv-select" required>
                        <option value="">Choose a free table</option>
                        @foreach ($tables->groupBy(fn ($table) => $table->group?->name ?: 'Other') as $section => $group)
                            <optgroup label="{{ $section }}">
                                @foreach ($group as $table)
                                    <option value="{{ $table->id }}">
                                        {{ $table->name }}@if ($table->capacity) · {{ $table->capacity }} covers @endif
                                    </option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </x-field>

                <p class="nv-help">
                    Only free tables are listed. Merging two running bills is a different decision and
                    is not made by accident here.
                </p>

                <div class="nv-actions" style="justify-content:flex-end;margin-top:16px">
                    <a href="#" class="nv-btn nv-btn-ghost" data-modal-close>Cancel</a>
                    <button type="submit" class="nv-btn nv-btn-primary">
                        <x-icon name="arrow-right" /> Move
                    </button>
                </div>
            </form>
        </div>
    </div>
@endCanEdit
