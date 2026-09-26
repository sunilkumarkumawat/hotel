@props(['name' => '?', 'size' => null, 'color' => null])

@php
    $initials = collect(preg_split('/\s+/', trim($name)))
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_substr($part, 0, 1))
        ->implode('');

    // Stable colour per name when none is given.
    $palette = ['', 'c2', 'c3', 'c4', 'c5', 'c6'];
    $color ??= $palette[crc32($name) % count($palette)];
@endphp

<span {{ $attributes->class([
    'nv-avatar',
    'nv-avatar-' . $size => $size,
    'nv-avatar-' . $color => $color,
]) }}>{{ $initials ?: '?' }}</span>
