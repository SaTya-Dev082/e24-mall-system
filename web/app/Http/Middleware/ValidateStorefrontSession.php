<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ValidateStorefrontSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $sessionUser = Auth::guard('web')->user();
        $user = $sessionUser?->fresh();
        $passwordChanged = $user && $request->session()->has('customer_password_hash') && ! hash_equals($request->session()->get('customer_password_hash'), $user->password);
        if (($sessionUser && ! $user) || $passwordChanged || ($user && ($user->status !== 'active' || $user->role !== 'customer' || $user->deleted_at !== null))) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your customer session has ended. Please sign in again.'], 401);
            }

            return redirect()->route('login')->withErrors(['email' => 'This customer account is not available.']);
        }

        if ($user) {
            Auth::guard('web')->setUser($user);
            $request->session()->put('customer_password_hash', $user->password);
        }

        $response = $next($request);
        if ($request->routeIs('login*', 'register*', 'password.*', 'account.*', 'verification.*', 'storefront.notifications.*')) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
