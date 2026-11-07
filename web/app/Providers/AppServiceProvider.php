<?php

namespace App\Providers;

use App\Services\StorefrontCart;
use Illuminate\Support\Facades\Auth;
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
        \Illuminate\Support\Facades\View::composer('layouts.storefront', function (View $view): void {
            $view->with('cartCount', app(StorefrontCart::class)->count(request()));
            $view->with('customer', Auth::guard('web')->user());
        });
    }
}
