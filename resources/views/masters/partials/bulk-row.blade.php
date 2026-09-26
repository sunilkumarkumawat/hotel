{{--
    One row of the master bulk grid. `$i` is the row number, or the literal
    `__i__` in the copy the Add button clones.
--}}

@php
    $rowHasError = $errors->has('rows.' . $i . '.*');
@endphp

<tr @class(['is-new', 'is-invalid' => $rowHasError]) data-bulk-row>
    @foreach ($config['fields'] as $column => $field)
        <td>
            @include('masters.partials.bulk-cell', [
                'column' => $column,
                'field' => $field,
                'i' => $i,
                'choices' => $options[$column] ?? [],
            ])
        </td>
    @endforeach

    <td>
        <input type="hidden" name="rows[{{ $i }}][status]" value="0" form="rows-new" />

        <label class="nv-check" for="rows_{{ $i }}_status">
            <input type="checkbox" name="rows[{{ $i }}][status]" id="rows_{{ $i }}_status"
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
