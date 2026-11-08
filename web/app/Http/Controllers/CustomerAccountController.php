<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRegisterRequest;
use App\Models\User;
use App\Rules\SafeCustomerPassword;
use App\Services\CustomerVerificationDelivery;
use App\Services\StorefrontCart;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CustomerAccountController extends Controller
{
    public function loginForm(): View|RedirectResponse
    {
        return Auth::guard('web')->check() ? redirect()->route('account.profile') : view('pages.account.login');
    }

    public function registerForm(): View|RedirectResponse
    {
        return Auth::guard('web')->check() ? redirect()->route('account.profile') : view('pages.account.register');
    }

    public function login(Request $request, StorefrontCart $cart): RedirectResponse|JsonResponse
    {
        if (Auth::guard('web')->check()) {
            return $this->authenticatedResponse($request);
        }
        $data = $request->validate(['email' => ['required', 'email', 'max:255'], 'password' => ['required', 'string', 'max:256'], 'remember' => ['sometimes', 'boolean']]);
        $email = strtolower(trim($data['email']));
        $key = 'customer-login:'.hash('sha256', $email.'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.']);
        }
        if (! Auth::guard('web')->attempt(['password' => $data['password'], 'role' => 'customer', 'status' => 'active', 'deleted_at' => null, fn (Builder $query) => $query->whereRaw('LOWER(TRIM(email)) = ?', [$email])], $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'The provided credentials are incorrect or the account is unavailable.']);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->session()->put('customer_password_hash', Auth::guard('web')->user()->password);
        $cart->mergeGuest($request);

        return $this->authenticatedResponse($request);
    }

    public function register(CustomerRegisterRequest $request, StorefrontCart $cart, CustomerVerificationDelivery $delivery): RedirectResponse|JsonResponse
    {
        if (Auth::guard('web')->check()) {
            return $this->authenticatedResponse($request);
        }
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);
        try {
            $user = DB::transaction(function () use ($data, $delivery): User {
                $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'], 'phone' => $data['phone'] ?? null, 'role' => 'customer', 'status' => 'active']);
                $delivery->request($user);

                return $user;
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (in_array($exception->index, ['users_email_normalized_unique', 'users_email_unique'], true) || in_array('email', $exception->columns, true)) {
                throw ValidationException::withMessages(['email' => 'This email is already registered. Sign in or reset your password.']);
            }
            if ($exception->index === 'users_phone_unique' || in_array('phone', $exception->columns, true)) {
                throw ValidationException::withMessages(['phone' => 'This phone number is already registered.']);
            }
            throw $exception;
        }
        $delivery->deliver($user->id);
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put('customer_password_hash', Auth::guard('web')->user()->password);
        $cart->mergeGuest($request);

        return $this->authenticatedResponse($request, 201);
    }

    private function authenticatedResponse(Request $request, int $status = 200): RedirectResponse|JsonResponse
    {
        $redirect = redirect()->intended(route('account.profile'));

        return $request->expectsJson() ? response()->json(['redirect' => $redirect->getTargetUrl()], $status) : $redirect;
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function profile(Request $request): View
    {
        return view('pages.account.profile', ['customer' => $request->user('web')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user('web');
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'phone' => ['nullable', 'string', 'max:50', Rule::unique('users', 'phone')->ignore($user->id)], 'role' => ['prohibited'], 'status' => ['prohibited'], 'admin_permissions' => ['prohibited']]);
        $user->update($data);

        return back()->with('account_status', 'Profile updated.');
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate(['current_password' => ['required', 'current_password:web'], 'password' => ['bail', 'required', 'string', 'confirmed', 'max:256', Password::min(8), new SafeCustomerPassword]]);
        $request->user('web')->forceFill(['password' => Hash::make($data['password']), 'remember_token' => Str::random(60)])->save();
        $request->session()->regenerate();
        $request->session()->put('customer_password_hash', $request->user('web')->password);

        return back()->with('account_status', 'Password updated.');
    }
}
