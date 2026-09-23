@extends('layouts.guest')

@section('title', 'Sign in')

@section('content')
    {{--
        The drifting shapes. Six empty spans with no meaning of their own —
        aria-hidden, so a screen reader is not told about six blank boxes.
    --}}
    <div class="nv-signin">
        <div class="nv-sky" aria-hidden="true">
            <span></span><span></span><span></span>
            <span></span><span></span><span></span>
        </div>

        <form class="nv-signin-card" method="POST" action="{{ route('loginIn') }}" data-signin>
            @csrf

            <span class="nv-signin-mark"><x-icon name="sparkles" :size="23" /></span>

            <h1>Welcome Back</h1>
            <p>Sign in to continue</p>

            @if (session('status'))
                <p class="nv-signin-note is-good">{{ session('status') }}</p>
            @endif

            {{--
                One place for every reason a sign-in can fail — wrong password,
                a deactivated account, too many attempts. The script writes into
                this same box, so a failure looks the same whether the page
                reloaded or not.
            --}}
            <p @class(['nv-signin-note', 'is-bad', 'nv-hidden' => ! $errors->has('username')])
               data-signin-error role="alert">
                {{ $errors->first('username') ?: $errors->first('password') }}
            </p>

            <div class="nv-signin-field">
                <input type="text" name="username" id="username" placeholder=" "
                       value="{{ old('username') }}" autocomplete="username" autofocus required />
                <label for="username">Username</label>
                <span class="nv-signin-icon"><x-icon name="user" /></span>
            </div>

            <div class="nv-signin-field">
                <input type="password" name="password" id="password" placeholder=" "
                       autocomplete="current-password" required />
                <label for="password">Password</label>
                <span class="nv-signin-icon"><x-icon name="lock" /></span>

                {{-- Hidden until the script that makes it work has run: a button
                     that does nothing is worse than no button. --}}
                <button type="button" class="nv-signin-eye" data-signin-eye
                        aria-label="Show password" hidden>
                    <x-icon name="eye" />
                </button>
            </div>

            <div class="nv-signin-row">
                <label>
                    <input type="checkbox" name="remember" value="1" @checked(old('remember')) />
                    <span>Remember me</span>
                </label>

                <span>Forgot it? Ask an administrator.</span>
            </div>

            <button type="submit" class="nv-signin-go" data-signin-go data-state="idle">
                <span><i class="nv-signin-spin"></i><span data-signin-label>Sign in</span></span>
            </button>
        </form>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/signin.js') }}?v={{ file_exists(public_path('js/signin.js')) ? filemtime(public_path('js/signin.js')) : time() }}" defer></script>
@endpush
