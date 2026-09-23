{{--
    One cell of the "add several at once" grid.

    Everything a row posts is named `rows[<i>][<column>]`, so the server sees a
    list however many rows are on screen. `$key` is the dotted form of the same
    name — `rows.3.room_no` — which is what old() and the error bag use, and
    `$i` may be the literal `__i__` in the row the Add button clones.

    The controls point at the form with the HTML `form` attribute, because a
    <form> may not legally wrap table rows.
--}}

@php
    $type = $field['type'] ?? 'text';
    $input = 'rows[' . $i . '][' . $column . ']';
    $key = 'rows.' . $i . '.' . $column;
    $id = 'rows_' . preg_replace('/\W+/', '_', $i . '_' . $column);
    $invalid = $errors->has($key);
    $value = old($key);
@endphp

@if ($type === 'select')
    <select name="{{ $input }}" id="{{ $id }}" form="rows-new"
            @class(['nv-select', 'is-invalid' => $invalid])>
        <option value="">{{ $field['placeholder'] ?? 'Choose…' }}</option>

        @foreach ($choices as $optionKey => $label)
            <option value="{{ $optionKey }}" @selected((string) $value === (string) $optionKey)>{{ $label }}</option>
        @endforeach
    </select>

@elseif ($type === 'switch')
    {{-- The hidden 0 comes first, so an unticked box reads as a decision
         rather than as silence. --}}
    <input type="hidden" name="{{ $input }}" value="0" form="rows-new" />

    <label class="nv-check" for="{{ $id }}">
        <input type="checkbox" name="{{ $input }}" id="{{ $id }}" form="rows-new"
               value="1" @checked($value) />
        <span>Yes</span>
    </label>

@elseif ($type === 'textarea')
    <textarea name="{{ $input }}" id="{{ $id }}" form="rows-new" rows="2"
              placeholder="{{ $field['placeholder'] ?? '' }}"
              @class(['nv-input', 'is-invalid' => $invalid])>{{ $value }}</textarea>

@elseif ($type === 'money')
    <div class="nv-input-group">
        <span class="nv-input-addon">₹</span>
        <input type="number" name="{{ $input }}" id="{{ $id }}" form="rows-new"
               value="{{ $value }}" step="0.01" min="0" placeholder="0.00"
               @class(['nv-input', 'is-invalid' => $invalid]) />
    </div>

@elseif ($type === 'percent')
    <div class="nv-input-group">
        <input type="number" name="{{ $input }}" id="{{ $id }}" form="rows-new"
               value="{{ $value }}" step="0.01" min="0" max="100" placeholder="0"
               @class(['nv-input', 'is-invalid' => $invalid]) />
        <span class="nv-input-addon">%</span>
    </div>

@elseif ($type === 'number')
    <input type="number" name="{{ $input }}" id="{{ $id }}" form="rows-new"
           value="{{ $value }}" placeholder="{{ $field['placeholder'] ?? '' }}"
           @class(['nv-input', 'is-invalid' => $invalid]) />

@else
    <input type="text" name="{{ $input }}" id="{{ $id }}" form="rows-new"
           value="{{ $value }}" placeholder="{{ $field['placeholder'] ?? '' }}"
           @class(['nv-input', 'is-invalid' => $invalid]) />
@endif

@error($key)
    <span class="nv-error">{{ $message }}</span>
@enderror
