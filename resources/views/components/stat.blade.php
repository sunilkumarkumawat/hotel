@props([
    'label',
    'value',
    'icon' => 'chart',
    'tone' => 'primary',
    'delta' => null,
    'caption' => null,
])

@php
    $up = $delta !== null && ! str_starts_with((string) $delta, '-');
@endphp

<div {{ $attributes->class(['nv-stat']) }}>
    <div class="nv-stat-top">
        <div>
            <p class="nv-stat-label">{{ $label }}</p>
            <p class="nv-stat-value">{{ $value }}</p>
        </div>

        <span @class(['nv-stat-icon', 'is-' . $tone => $tone !== 'primary'])>
            <x-icon :name="$icon" />
        </span>
    </div>

    @if ($delta !== null || $caption)
        <div class="nv-stat-foot">
            @if ($delta !== null)
                <span @class(['nv-delta', 'is-up' => $up, 'is-down' => ! $up])>
                    <x-icon :name="$up ? 'arrow-up' : 'arrow-down'" />
                    {{ ltrim((string) $delta, '-') }}
                </span>
            @endif

            @if ($caption)
                <span>{{ $caption }}</span>
            @endif
        </div>
    @endif
</div>
