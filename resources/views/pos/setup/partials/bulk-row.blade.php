{{--
    One blank row in a batch.

    `$i` is the row's index — a number for a row on screen, and the literal
    `__i__` for the copy the "Add another row" button clones, which the script
    swaps for the next number. Everything a row posts is named `rows[<i>][…]`,
    so the server sees a list rather than a single item however many there are.

    The row carries its own Active box because each thing being added is its own
    thing: a menu typed in one go can perfectly well have two items live and one
    not ready yet.
--}}

@php
    $prefix = 'rows[' . $i . ']';
    $rowHasError = $errors->has('rows.' . $i . '.*') || $errors->has('rows.' . $i);
@endphp

<tr @class(['is-new', 'is-invalid' => $rowHasError]) data-bulk-row>
    @if ($photos ?? false)
        {{-- A photo is added after the row exists and has an id — see the
             "what is already there" loop in list.blade.php — so a row still
             being typed just holds the column's place. --}}
        <td class="nv-muted nv-item-photo-pending" title="Save this row first, then add a photo">—</td>
    @endif
    @foreach ($fields as $name => $field)
        <td>
            @include('pos.setup.partials.cell', [
                'field' => $field,
                'name' => $name,
                'value' => ($field['type'] ?? 'text') === 'checkboxes' ? [] : null,
                'form' => 'rows-new',
                'prefix' => $prefix,
                'key' => 'rows.' . $i . '.' . $name,
            ])
        </td>
    @endforeach

    <td>
        {{-- The hidden 0 comes first, so an unticked box reads as a decision
             rather than as silence. --}}
        <input type="hidden" name="{{ $prefix }}[status]" value="0" form="rows-new" />

        <label class="nv-check" for="rows-new_{{ $i }}_status">
            <input type="checkbox" name="{{ $prefix }}[status]" id="rows-new_{{ $i }}_status"
                   form="rows-new" value="1" @checked(old('rows.' . $i . '.status', true)) />
            <span>Active</span>
        </label>
    </td>

    <td class="is-end">
        <div class="nv-row-actions">
            {{-- Hidden until the script that makes it work has run. A button
                 that does nothing is worse than no button. --}}
            <button type="button" class="nv-btn nv-btn-ghost nv-btn-sm" data-row-remove hidden>
                <x-icon name="trash" /> Remove
            </button>
        </div>
    </td>
</tr>
