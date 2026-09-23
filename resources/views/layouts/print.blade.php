<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <title>@yield('title', 'Print') · {{ config('app.name') }}</title>

    {{--
        A printed document is not a themed screen: it is black ink on white
        paper. So this layout deliberately does NOT load theme.css — a dark
        theme would come out of the printer as a page of solid ink, and the
        app's grid and card styles are no use on A4.
    --}}
    <link rel="stylesheet" href="{{ asset('css/print.css') }}?v={{ filemtime(public_path('css/print.css')) }}" />

    @stack('styles')
</head>
<body class="pr-body">
    {{-- Screen only: the bar disappears the moment the page is printed. --}}
    <div class="pr-bar">
        <div class="pr-bar-in">
            <a href="{{ $back ?? url()->previous() }}" class="pr-btn">← Back</a>

            <span class="pr-bar-title">@yield('title', 'Print')</span>

            <button type="button" class="pr-btn pr-btn-primary" onclick="window.print()">Print</button>
        </div>

        {{-- Checkout lands straight on the bill, so its confirmation has to be
             shown here or the desk never sees that anything happened. --}}
        @if (session('status') || session('error'))
            <p @class(['pr-flash', 'is-error' => session('error')])>
                {{ session('error') ?: session('status') }}
            </p>
        @endif
    </div>

    <div class="pr-sheet">
        @yield('content')
    </div>

    <script>
        // ?auto=1 comes from the "Print" buttons in the app, so one click on a
        // list opens the card and raises the print dialog.
        if (new URLSearchParams(location.search).get('auto') === '1') {
            window.addEventListener('load', function () { setTimeout(window.print, 250); });
        }
    </script>
</body>
</html>
