@props(['name' => null, 'value' => null])

@php
    $content = $value ?? trim($slot->toHtml());
@endphp

<textarea
    @if ($name) name="{{ $name }}" id="{{ $name }}" @endif
    {{ $attributes->class(['nv-textarea', 'is-invalid' => $name && $errors->has($name)]) }}
>{{ $name ? old($name, $content) : $content }}</textarea>
