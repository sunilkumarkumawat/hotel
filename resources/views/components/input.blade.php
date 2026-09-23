@props(['name' => null, 'type' => 'text', 'value' => null])

<input
    type="{{ $type }}"
    @if ($name) name="{{ $name }}" id="{{ $name }}" @endif
    value="{{ $name ? old($name, $value) : $value }}"
    {{ $attributes->class(['nv-input', 'is-invalid' => $name && $errors->has($name)]) }}
/>
