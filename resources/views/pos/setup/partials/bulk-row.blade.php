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
        {{-- Chosen while the row is still being typed — it uploads together
             with everything else when the row saves. The span only stands in
             for the <form> the "what is already there" version below wraps
             its own input in — this cell posts through the big rows-new form
             instead — so it reuses that same hide-the-raw-input styling. --}}
        <td>
            <span class="nv-item-photo-form">
                <label for="rows-new_{{ $i }}_photo" class="nv-item-photo" title="Add a photo" data-item-photo-label>
                    <x-icon name="upload" />
                </label>
                <input type="file" name="{{ $prefix }}[photo]" id="rows-new_{{ $i }}_photo"
                       accept="image/png,image/jpeg,image/webp" form="rows-new" data-item-photo-input />
            </span>
            @error('rows.' . $i . '.photo')
                <span class="nv-error">{{ $message }}</span>
            @enderror
        </td>
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
