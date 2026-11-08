<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('web')->check()) {
            if ($request->routeIs('storefront.cart.store')) {
                $request->session()->put('url.intended', route('storefront.products.show', $request->route('slug')));

                return $request->expectsJson()
                    ? response()->json(['redirect' => route('login')], 401)
                    : redirect()->route('login');
            }
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Sign in to continue.'], 401);
            }

            return redirect()->guest(route('login'));
        }

        return $next($request);
    }
}
