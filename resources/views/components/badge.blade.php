@props(['tone' => null, 'plain' => false])

<span {{ $attributes->class(['nv-badge', 'is-' . $tone => $tone, 'is-plain' => $plain]) }}>{{ $slot }}</span>
