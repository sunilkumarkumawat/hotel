<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <title>@yield('title', 'Sign in') · {{ config('app.name') }}</title>

    <script>
        (function () {
            try {
                var t = localStorage.getItem('nova.theme');
                if (!t) t = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                document.documentElement.setAttribute('data-theme', t);
            } catch (e) {}
        })();
    </script>

    @include('partials.assets')

    @stack('styles')
</head>
<body class="nv-body nv-body-signin">
    @yield('content')

    @stack('scripts')
</body>
</html>
