<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <meta name="description" content="@yield('meta_description', 'Book direct and save — real-time room availability and instant confirmation.')" />

    <title>@yield('title', 'Hotel')</title>

    {{--
        The guest-facing website. Like layouts/public.blade.php, this always
        forces the light theme regardless of the browser's colour-scheme
        preference — a guest browsing rooms should see the hotel's own
        colours, not whatever mode their phone happens to be in.
    --}}
    @include('partials.site-assets')

    @stack('styles')
</head>
<body class="nv-site @yield('body_class')">
    @yield('content')

    @stack('scripts')
</body>
</html>
