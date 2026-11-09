<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || $request->user()->role !== 'admin' || $request->user()->status !== 'active' || $request->user()->deleted_at !== null) {
            return response()->json([
                'message' => 'This action requires an admin account.',
            ], 403);
        }

        $section = $request->segment(4);
        $module = match ($section) {
            'manage' => config('admin_resources.'.$request->segment(5).'.module', 'administration'),
            'inventory' => 'inventory', 'orders' => 'orders', 'customers', 'customer-notifications' => 'customers',
            'payments', 'refunds' => 'payments', 'staff', 'store-settings' => 'administration',
            default => 'catalog',
        };
        abort_unless($request->user()->canManage($module), 403, 'Your account does not have permission for this section.');

        return $next($request);
    }
}
