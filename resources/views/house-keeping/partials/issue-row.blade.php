{{--
    One line of the laundry grid.

    Rendered by the server for the lines that already exist (so the screen
    works, and survives a failed save, with no JavaScript at all), and cloned
    by laundry.js for every line added after that. `$i` is null in the template
    copy — the browser fills the names in when it clones it.

    @param int|null $i     row index, or null for the clonable template
    @param array    $line  what to put in the cells
    @param mixed    $items the item list
--}}

@php
    $field = fn (string $name) => $i === null ? '' : "lines[{$i}][{$name}]";
    $val = fn (string $name, $default = '0') => $line[$name] ?? $default;

    $amount = number_format(
        max(0, (float) $val('std_qty', 0)) * max(0, (float) $val('std_rate', 0))
        + max(0, (float) $val('exp_qty', 0)) * max(0, (float) $val('exp_rate', 0)),
        2,
        '.',
        ''
    );
@endphp

<tr class="nv-issue-row">
    <td>
        <select class="nv-select" data-cell="hk_item_id" @if ($i !== null) name="{{ $field('hk_item_id') }}" @endif>
            <option value="">Select Item</option>
            @foreach ($items as $item)
                <option value="{{ $item->id }}"
                        data-std="{{ (float) $item->std_rate }}"
                        data-exp="{{ (float) $item->exp_rate }}"
                        @selected((string) $val('hk_item_id', '') === (string) $item->id)>{{ $item->name }} ({{ $item->unit }})</option>
            @endforeach
        </select>
    </td>

    <td class="is-num">
        <input type="text" class="nv-input is-readonly" data-cell="prev_qty"
               value="{{ $val('prev_qty', '0') }}" readonly tabindex="-1" />
    </td>

    @foreach (['std_qty', 'exp_qty', 'rewash_qty'] as $name)
        <td class="is-num">
            <input type="number" class="nv-input" data-cell="{{ $name }}"
                   @if ($i !== null) name="{{ $field($name) }}" @endif
                   value="{{ $val($name) }}" min="0" step="1" />
        </td>
    @endforeach

    @foreach (['std_rate', 'exp_rate'] as $name)
        <td class="is-num">
            <input type="number" class="nv-input" data-cell="{{ $name }}"
                   @if ($i !== null) name="{{ $field($name) }}" @endif
                   value="{{ $val($name) }}" min="0" step="0.01" />
        </td>
    @endforeach

    <td class="is-num">
        <input type="text" class="nv-input is-readonly" data-cell="amount"
               value="{{ $amount }}" readonly tabindex="-1" />
    </td>

    <td>
        <div class="nv-row-actions">
            <button type="button" class="nv-btn nv-btn-ghost nv-btn-sm" data-row-remove aria-label="Remove line">
                <x-icon name="trash" />
            </button>
            <button type="button" class="nv-btn nv-btn-ghost nv-btn-sm" data-row-add aria-label="Add line">
                <x-icon name="plus" />
            </button>
        </div>
    </td>
</tr>
