<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Rules\SafeCustomerPassword;
use App\Services\CustomerVerificationDelivery;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Database\Eloquent\Builder;
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
        Password::broker('users')->sendResetLink(['role' => 'customer', 'status' => 'active', 'deleted_at' => null, fn (Builder $query) => $query->whereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim($data['email']))])]);

        return back()->with('account_status', 'If an active customer account matches this email, a reset link has been requested.');
    }

    public function resetForm(Request $request, string $token): View
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);

        return view('pages.account.reset-password', ['token' => $token, 'email' => $data['email']]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255'], 'password' => ['bail', 'required', 'string', 'confirmed', 'max:256', PasswordRule::min(8), new SafeCustomerPassword]]);
        $credentials = $data;
        unset($credentials['email']);
        $credentials += ['role' => 'customer', 'status' => 'active', 'deleted_at' => null, fn (Builder $query) => $query->whereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim($data['email']))])];
        $status = Password::broker('users')->reset($credentials, function (User $user, string $password): void {
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

    public function resendVerification(Request $request, CustomerVerificationDelivery $delivery): RedirectResponse
    {
        if (! $request->user('web')->hasVerifiedEmail()) {
            $delivery->request($request->user('web'));
            $delivery->deliver($request->user('web')->id);
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
