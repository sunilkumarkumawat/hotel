@props(['name' => null, 'options' => [], 'selected' => null, 'placeholder' => null])

@php
    $selected = $name ? old($name, $selected) : $selected;

    // A plain list (['Male', 'Female']) uses its labels as values; a map
    // ([1 => 'India'], from pluck('name', 'id')) keeps its keys.
    $options = $options instanceof \Illuminate\Support\Collection ? $options->all() : (array) $options;
    $isList = array_is_list($options);
@endphp

<select
    @if ($name) name="{{ $name }}" id="{{ $name }}" @endif
    {{ $attributes->class(['nv-select', 'is-invalid' => $name && $errors->has($name)]) }}
>
    @if ($placeholder)
        <option value="">{{ $placeholder }}</option>
    @endif

    @foreach ($options as $key => $label)
        @php $value = $isList ? $label : $key; @endphp
        <option value="{{ $value }}" @selected((string) $value === (string) $selected)>{{ $label }}</option>
    @endforeach

    {{ $slot }}
</select>
