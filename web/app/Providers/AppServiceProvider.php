<?php

namespace App\Providers;

use App\Services\StorefrontCart;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('customer-login', fn (Request $request) => Limit::perMinute(60)->by('customer-login-ip:'.$request->ip()));
        RateLimiter::for('customer-api-login', fn (Request $request) => [
            Limit::perMinute(60)->by('customer-api-login-ip:'.$request->ip()),
            Limit::perMinute(10)->by('customer-api-login-identity:'.hash('sha256', (is_string($request->input('email')) ? strtolower(trim($request->input('email'))) : '').'|'.$request->ip())),
        ]);
        RateLimiter::for('customer-register', fn (Request $request) => [
            Limit::perMinute(20)->by('customer-register-minute:'.$request->ip()),
            Limit::perHour(100)->by('customer-register-hour:'.$request->ip()),
        ]);
        \Illuminate\Support\Facades\View::composer('layouts.storefront', function (View $view): void {
            $view->with('cartCount', app(StorefrontCart::class)->count(request()));
            $view->with('customer', Auth::guard('web')->user());
            $view->with('customerUnreadCount', Auth::guard('web')->user()?->unreadNotifications()->count() ?? 0);
        });
    }
}
