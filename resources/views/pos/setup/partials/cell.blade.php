{{--
    One editable cell in a Setup list.

    `$form` is the id of the <form> element that lives outside the table — a
    form cannot legally wrap table rows, so every control points back at it with
    the HTML `form` attribute instead.

    `$prefix` is set only on the bulk-entry rows, where twenty rows are saved at
    once and each field has to arrive as `rows[3][name]` rather than as `name`.
    `$key` is the dotted form of the same thing — `rows.3.name` — which is what
    old() and the error bag use. Everywhere else both are absent and a field is
    simply called what it is called.

    Ids are prefixed with the form id so the add row and an open edit row can
    both carry a field called `name` without colliding.
--}}

@php
    $type = $field['type'] ?? 'text';

    $prefix = $prefix ?? null;
    $key = $key ?? $name;
    $input = $prefix ? $prefix . '[' . $name . ']' : $name;

    // A dotted key is not a legal id, and two rows must not share one.
    $id = $form . '_' . preg_replace('/\W+/', '_', $key);
    $invalid = $errors->has($key);
@endphp

@if ($type === 'select')
    <select name="{{ $input }}" id="{{ $id }}" form="{{ $form }}"
            @class(['nv-select', 'is-invalid' => $invalid])>
        @if (isset($field['placeholder']))
            <option value="">{{ $field['placeholder'] }}</option>
        @endif

        @foreach ($field['options'] as $optionKey => $label)
            <option value="{{ $optionKey }}" @selected((string) old($key, $value) === (string) $optionKey)>{{ $label }}</option>
        @endforeach
    </select>

@elseif ($type === 'checkbox')
    {{-- An unticked box posts nothing, so a plain checkbox cannot say "no".
         The hidden 0 sits earlier in the document than the box, and a later
         value of the same name wins — so ticked sends 1 and unticked sends 0. --}}
    <input type="hidden" name="{{ $input }}" value="0" form="{{ $form }}" />

    <label class="nv-check" for="{{ $id }}">
        <input type="checkbox" name="{{ $input }}" id="{{ $id }}" form="{{ $form }}" value="1"
               @checked(old($key, $value)) />
        <span>{{ $field['help'] ?? 'Yes' }}</span>
    </label>

@elseif ($type === 'money')
    <div class="nv-input-group">
        <span class="nv-input-addon">₹</span>
        <input type="number" name="{{ $input }}" id="{{ $id }}" form="{{ $form }}"
               value="{{ old($key, $value) }}" step="0.01" min="0"
               placeholder="{{ $field['placeholder'] ?? '0.00' }}"
               @class(['nv-input', 'is-invalid' => $invalid]) />
    </div>

@elseif ($type === 'prices')
    {{--
        One small box per price list, so an item can be ₹180 à la carte and
        ₹120 in Happy Hours without a screen of its own. Leave a box empty and
        that plan simply charges the everyday price — which is what lets a new
        plan be switched on mid-service without pricing the whole menu first.
    --}}
    @php $current = (array) old($key, $value ?: []); @endphp

    <div class="nv-plan-prices">
        @forelse ($field['options'] as $optionKey => $label)
            <label class="nv-plan-price" for="{{ $id }}_{{ $optionKey }}">
                <span>{{ $label }}</span>
                <input type="number" name="{{ $input }}[{{ $optionKey }}]" id="{{ $id }}_{{ $optionKey }}"
                       form="{{ $form }}" step="0.01" min="0" placeholder="—"
                       value="{{ $current[$optionKey] ?? '' }}" class="nv-input nv-input-xs" />
            </label>
        @empty
            <span class="nv-muted">No rate plans yet.</span>
        @endforelse
    </div>

@elseif ($type === 'checkboxes')
    @php $picked = array_map('strval', (array) old($key, $value ?: [])); @endphp

    <div class="nv-check-grid">
        @forelse ($field['options'] as $optionKey => $label)
            <label class="nv-check" for="{{ $id }}_{{ $optionKey }}">
                <input type="checkbox" name="{{ $input }}[]" id="{{ $id }}_{{ $optionKey }}" form="{{ $form }}"
                       value="{{ $optionKey }}" @checked(in_array((string) $optionKey, $picked, true)) />
                <span>{{ $label }}</span>
            </label>
        @empty
            <span class="nv-muted">No outlets yet — add one first.</span>
        @endforelse
    </div>

@else
    <input type="text" name="{{ $input }}" id="{{ $id }}" form="{{ $form }}"
           value="{{ old($key, $value) }}"
           placeholder="{{ $field['placeholder'] ?? '' }}"
           @class(['nv-input', 'is-invalid' => $invalid]) />
@endif

@error($key)
    <span class="nv-error">{{ $message }}</span>
@enderror
