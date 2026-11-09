<?php

namespace App\Providers;

use App\Services\SystemNotifications;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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
        View::composer('layouts.workspace', function ($view): void {
            if (! auth()->check()) {
                return;
            }
            $notifications = app(SystemNotifications::class);
            $summary = $view->getData()['notificationSummary'] ?? $notifications->summary();
            $view->with('alertCount', $view->getData()['alertCount'] ?? ($summary['inventory'] ?? 0));
            $view->with('notificationSummary', $summary)->with('notificationCount', $summary['all'])
                ->with('notificationPreview', $notifications->decorate($notifications->query()->limit(5)->get()));
        });
    }
}
