<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role !== 'admin' || $request->user()?->status !== 'active' || $request->user()?->trashed()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Your account does not have access to this dashboard.']);
        }
        $name = (string) $request->route()?->getName();
        $prefix = explode('.', $name)[0];
        $module = match ($prefix) {
            'management' => config('admin_resources.'.$request->route('resource').'.module', 'administration'),
            'products','variants','images' => 'catalog', 'inventory' => 'inventory', 'orders' => 'orders',
            'customers','customer-announcements' => 'customers', 'payments','refunds' => 'payments', 'reports','dashboard' => 'reports',
            'staff','audit','store-settings' => 'administration', default => null,
        };
        abort_if($module && ! $request->user()->canManage($module), 403, 'Your account does not have access to this section.');
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
