<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    <meta charset="utf-8" />
    {{-- viewport-fit=cover is what lets the phone bottom bar's
         env(safe-area-inset-bottom) padding (public/css/mobile-theme.css)
         actually read a non-zero value on an iPhone with a home indicator;
         without it iOS treats the safe area as outside the page entirely. --}}
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    <title>@yield('title', 'Dashboard') · {{ config('app.name') }}</title>

    {{--
        "Add to Home Screen" — what makes the icon on a phone's home screen
        say {{ config('app.name') }} instead of a browser bookmark, and open
        without the browser's own address bar around it.

        manifest.webmanifest is served by ManifestController rather than a
        static public/ file because its name and colours come from this
        installation's own config('app.name') and the mobile palette above,
        not from one hard-coded hotel's branding.

        Android reads the manifest's own icons; apple-touch-icon is repeated
        here too because iOS Safari has never read the manifest for that —
        it only ever looks at this link tag.
    --}}
    <link rel="manifest" href="{{ route('manifest') }}" />
    <meta name="theme-color" content="#fffbf5" />
    <link rel="apple-touch-icon" href="{{ asset('images/icons/apple-touch-icon.png') }}" />
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-status-bar-style" content="default" />
    <meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}" />

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

    {{--
        The mobile look's own two faces (public/css/mobile-theme.css) — same
        non-blocking load as the desktop pair above. Only ever applied below
        1024px width, so loading them unconditionally here costs a desktop
        session one small, cached, off-critical-path request and nothing
        else.
    --}}
    @php
        $mobileFonts = 'https://fonts.googleapis.com/css2'
            . '?family=Baloo+2:wght@500;600;700'
            . '&family=Nunito:wght@400;500;600;700;800'
            . '&display=swap';
    @endphp
    <link rel="stylesheet" href="{{ $mobileFonts }}" media="print" onload="this.media='all'" />
    <noscript><link rel="stylesheet" href="{{ $mobileFonts }}" /></noscript>

    @include('partials.assets')

    {{-- Always after partials.assets: theme.css/app.css must lose the
         cascade on any --nv-* token the two of them both set. --}}
    <link rel="stylesheet" href="{{ asset('css/mobile-theme.css') }}?v={{ file_exists(public_path('css/mobile-theme.css')) ? filemtime(public_path('css/mobile-theme.css')) : 1 }}" />

    @stack('styles')
</head>
<body class="nv-body">
    {{-- Computed once and hand-carried to every menu below (sidebar, mobile
         bottom bar / "More" sheet, and the topbar's Quick Actions) so a
         single page render queries sidebar_menu() one time no matter how
         many places draw it out. --}}
    @php $navMenu = sidebar_menu(); @endphp

    <div class="nv-app">
        @include('partials.sidebar', ['menu' => $navMenu])

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

    {{-- Phone-width bottom bar + its "More" sheet — hidden by CSS above
         1024px, so this costs a desktop screen nothing but a few bytes of
         markup it never displays. --}}
    @include('partials.mobile-nav', ['menu' => $navMenu])

    {{-- The topbar's search icon opens this. Sits at the body level like the
         sheet above rather than inside partials/topbar.blade.php itself, so
         it is never clipped by the topbar's own bounds. --}}
    @include('partials.quick-actions', ['menu' => $navMenu])

    <x-confirm-modal />

    <script src="{{ asset('js/quick-actions.js') }}?v={{ file_exists(public_path('js/quick-actions.js')) ? filemtime(public_path('js/quick-actions.js')) : time() }}" defer></script>

    @stack('scripts')
</body>
</html>
