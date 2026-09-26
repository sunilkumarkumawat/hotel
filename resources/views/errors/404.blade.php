@extends('layouts.guest')

@section('title', 'Page not found')

@section('content')
    <div class="nv-error-page">
        <div>
            <p class="nv-error-code">404</p>
            <h1 style="margin-top:12px;font-size:24px;font-weight:700;letter-spacing:-.02em">
                We couldn't find that page
            </h1>
            <p class="nv-muted" style="max-width:44ch;margin:10px auto 26px;font-size:14px">
                The link may be broken, or the page may have been moved.
            </p>
            <a href="{{ url('/') }}" class="nv-btn nv-btn-primary nv-btn-lg">
                <x-icon name="home" /> Back to dashboard
            </a>
        </div>
    </div>
@endsection
