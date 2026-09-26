@extends('layouts.guest')

@section('title', 'Not allowed')

@section('content')
    <div class="nv-error-page">
        <div>
            <p class="nv-error-code">403</p>
            <h1 style="margin-top:12px;font-size:24px;font-weight:700;letter-spacing:-.02em">
                You don't have access to this
            </h1>
            <p class="nv-muted" style="max-width:46ch;margin:10px auto 26px;font-size:14px">
                {{ $exception?->getMessage() ?: 'Ask an administrator to grant you permission for this screen.' }}
            </p>
            <a href="{{ url('/') }}" class="nv-btn nv-btn-primary nv-btn-lg">
                <x-icon name="home" /> Back to dashboard
            </a>
        </div>
    </div>
@endsection
