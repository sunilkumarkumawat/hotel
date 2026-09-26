{{--
    Stylesheet and script for the public hotel website only — a small,
    self-contained pair (resources/css/site.css, resources/js/site.js),
    never the ~12,000-line admin theme.css a guest has no reason to
    download. See resources/css/site.css's own header comment for why this
    is a separate file rather than reusing partials.assets.

    Same filemtime cache-busting trick as partials.assets, so a fresh
    deploy is never masked by a browser's cached copy.
--}}
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
@php
    $siteFonts = 'https://fonts.googleapis.com/css2'
        . '?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700'
        . '&family=Plus+Jakarta+Sans:wght@400;500;600;700;800'
        . '&display=swap';
    $siteCss = public_path('css/site.css');
    $siteJs = public_path('js/site.js');
@endphp
<link rel="stylesheet" href="{{ $siteFonts }}" media="print" onload="this.media='all'" />
<noscript><link rel="stylesheet" href="{{ $siteFonts }}" /></noscript>

<link rel="stylesheet" href="{{ asset('css/site.css') }}?v={{ file_exists($siteCss) ? filemtime($siteCss) : 1 }}" />
<script src="{{ asset('js/site.js') }}?v={{ file_exists($siteJs) ? filemtime($siteJs) : 1 }}" defer></script>
