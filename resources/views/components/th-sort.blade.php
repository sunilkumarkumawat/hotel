@props(['field', 'sort' => null, 'direction' => 'desc'])

@php
    $active = $sort === $field;
    $next = $active && $direction === 'asc' ? 'desc' : 'asc';
@endphp

<a href="{{ request()->fullUrlWithQuery(['sort' => $field, 'direction' => $next, 'page' => null]) }}"
   @class(['nv-th-sort', 'is-sorted' => $active])>
    {{ $slot }}
    <x-icon :name="$active && $direction === 'asc' ? 'arrow-up' : 'arrow-down'" />
</a>
