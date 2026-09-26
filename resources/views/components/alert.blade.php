@props(['tone' => 'info', 'title' => null])

@php
    $icons = [
        'success' => 'check-circle',
        'warning' => 'alert',
        'danger' => 'x-circle',
        'info' => 'info',
    ];
@endphp

<div {{ $attributes->class(['nv-alert', 'is-' . $tone]) }}>
    <x-icon :name="$icons[$tone] ?? 'info'" />
    <div>
        @if ($title)
            <strong>{{ $title }}</strong>
        @endif
        <p>{{ $slot }}</p>
    </div>
</div>
