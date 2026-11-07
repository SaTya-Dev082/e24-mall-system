<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdminLoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(AdminLoginRequest $request): RedirectResponse
    {
        $key = 'admin-login:'.hash('sha256', Str::lower($request->string('email')->trim()->toString()).'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => ['Too many sign-in attempts. Try again in '.RateLimiter::availableIn($key).' seconds.']]);
        }
        $credentials = $request->validated();
        $credentials['email'] = trim($credentials['email']);
        if (! Auth::guard('web')->attemptWhen($credentials, fn ($user) => $user->role === 'admin' && $user->status === 'active')) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => ['Unable to sign in with these credentials.']]);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->to($request->user()->workspaceHome());
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'You have been signed out.');
    }
}
