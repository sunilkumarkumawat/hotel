<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <title>@yield('title', 'Hotel') · {{ config('app.name') }}</title>

    {{--
        A page a guest opens on their phone, from a link, with no account.

        It deliberately does NOT follow the browser's dark-mode preference the
        way the signed-in app does. This is the hotel's own page, printed in
        the hotel's own colours, and a guest who happens to have dark mode on
        should not be handed a different-looking hotel.
    --}}
    @include('partials.assets')

    @stack('styles')
</head>
<body class="nv-body nv-body-public">
    @yield('content')

    @stack('scripts')
</body>
</html>
