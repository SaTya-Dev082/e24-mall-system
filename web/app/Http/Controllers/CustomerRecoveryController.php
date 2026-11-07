<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class CustomerRecoveryController extends Controller
{
    public function forgot(): View
    {
        return view('pages.account.forgot-password');
    }

    public function sendReset(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        Password::broker('users')->sendResetLink(['email' => strtolower(trim($data['email'])), 'role' => 'customer', 'status' => 'active', 'deleted_at' => null]);

        return back()->with('account_status', 'If an active customer account matches this email, a reset link has been requested.');
    }

    public function resetForm(Request $request, string $token): View
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);

        return view('pages.account.reset-password', ['token' => $token, 'email' => $data['email']]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255'], 'password' => ['required', 'string', 'confirmed', 'max:128', PasswordRule::min(12)]]);
        $status = Password::broker('users')->reset($data + ['role' => 'customer', 'status' => 'active', 'deleted_at' => null], function (User $user, string $password): void {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });
        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => 'This reset link is invalid or expired. Request another link.']);
        }

        return redirect()->route('login')->with('account_status', 'Password reset. Sign in with your new password.');
    }

    public function verificationNotice(): View
    {
        return view('pages.account.verify-email');
    }

    public function resendVerification(Request $request): RedirectResponse
    {
        if (! $request->user('web')->hasVerifiedEmail()) {
            $request->user('web')->sendEmailVerificationNotification();
        }

        return back()->with('account_status', 'Verification email requested.');
    }

    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = $request->user('web');
        abort_unless($user->id === $id && hash_equals(sha1($user->getEmailForVerification()), $hash), 403);
        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect()->intended(route('account.profile'))->with('account_status', 'Email verified.');
    }
}
