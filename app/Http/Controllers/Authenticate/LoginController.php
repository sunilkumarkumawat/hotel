<?php

namespace App\Http\Controllers\Authenticate;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Support\Audit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function index(): View
    {
        return view('auth.login');
    }

    public function loginIn(Request $request): RedirectResponse
    {
        $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $this->ensureIsNotRateLimited($request);

        if (! Auth::attempt($request->only('username', 'password'), $request->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey($request));

            Audit::note('login_failed', 'Sign-in refused for "' . $request->string('username') . '"', [
                'area' => 'access',
                'user_name' => 'Not signed in',
                'subject_label' => $request->string('username')->limit(60)->toString(),
            ]);

            throw ValidationException::withMessages([
                'username' => 'These credentials do not match our records.',
            ]);
        }

        $user = Auth::user();

        if (! $user->isActive()) {
            Audit::note('login_failed', 'Deactivated account tried to sign in: ' . $user->username, [
                'area' => 'access',
                'user_id' => $user->user_id,
                'user_name' => $user->name,
                'subject_label' => $user->username,
                'branch_id' => $user->branch_id,
            ]);

            Auth::logout();

            throw ValidationException::withMessages([
                'username' => 'This account has been deactivated. Please contact an administrator.',
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        $request->session()->regenerate();

        session(['branch_id' => $user->branch_id, 'active_branch_id' => $user->branch_id]);

        Helper::flushAccess();

        Audit::note('login', $user->name . ' signed in', [
            'area' => 'access',
            'branch_id' => $user->branch_id,
            'subject_label' => $user->username,
        ]);

        return redirect()->intended('/')->with('status', 'Welcome back, ' . $user->name . '.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Audit::note('logout', ($request->user()?->name ?: 'Somebody') . ' signed out', [
            'area' => 'access',
            'branch_id' => $request->user()?->branch_id,
            'subject_label' => $request->user()?->username,
        ]);

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'You have been signed out.');
    }

    private function ensureIsNotRateLimited(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'username' => "Too many login attempts. Please try again in {$seconds} seconds.",
        ]);
    }

    private function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower($request->string('username')) . '|' . $request->ip());
    }
}
