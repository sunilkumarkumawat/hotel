<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    <title>@yield('title', 'Dashboard') · {{ config('app.name') }}</title>

    <script>
        (function () {
            try {
                var t = localStorage.getItem('nova.theme');
                if (!t) t = 'dark';
                document.documentElement.setAttribute('data-theme', t);
                if (localStorage.getItem('nova.sidebar') === 'collapsed') {
                    document.documentElement.setAttribute('data-sidebar', 'collapsed');
                }
            } catch (e) {}
        })();
    </script>

    {{--
        The two faces of the theme, pulled from Google Fonts.

        Loaded by the browser rather than by the server, so a hotel running this
        on a local XAMPP box still gets them as long as the desk has internet.
        When it does not, `--nv-font` falls through to Segoe UI and the display
        face to Palatino: different letters, same layout, nothing broken — which
        is why the fallback stacks are ordered by metric similarity rather than
        by preference.

        `media=print` + `onload` keeps the stylesheet off the critical path, so
        a slow font never holds up the screen; the <noscript> copy covers the
        case where that trick cannot run.
    --}}
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    @php
        $novaFonts = 'https://fonts.googleapis.com/css2'
            . '?family=Fraunces:opsz,wght@9..144,500;9..144,600'
            . '&family=Plus+Jakarta+Sans:wght@400;500;600;700'
            . '&display=swap';
    @endphp
    <link rel="stylesheet" href="{{ $novaFonts }}" media="print" onload="this.media='all'" />
    <noscript><link rel="stylesheet" href="{{ $novaFonts }}" /></noscript>

    @include('partials.assets')

    @stack('styles')
</head>
<body class="nv-body">
    <div class="nv-app">
        @include('partials.sidebar')

        <div class="nv-overlay" data-close="sidebar"></div>

        <div class="nv-shell">
            @include('partials.topbar')

            <main class="nv-main">
                @if (session('status'))
                    <div style="margin-bottom:18px">
                        <x-alert tone="success" title="Done">{{ session('status') }}</x-alert>
                    </div>
                @endif

                @if (session('error'))
                    <div style="margin-bottom:18px">
                        <x-alert tone="danger" title="Not allowed">{{ session('error') }}</x-alert>
                    </div>
                @endif

                {{-- It saved, but something it asked for did not happen — a
                     charge that could not come off a bill that is already paid.
                     Not an error: the work was done, and one thing needs a
                     human to look at it. --}}
                @if (session('warning'))
                    <div style="margin-bottom:18px">
                        <x-alert tone="warning" title="Saved, with one thing to know">{{ session('warning') }}</x-alert>
                    </div>
                @endif

                {{-- Neither good news nor bad — "pick a booking first" is just
                     the screen telling you where to start. --}}
                @if (session('info'))
                    <div style="margin-bottom:18px">
                        <x-alert tone="info" title="Nothing to show yet">{{ session('info') }}</x-alert>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <x-confirm-modal />

    @stack('scripts')
</body>
</html>
