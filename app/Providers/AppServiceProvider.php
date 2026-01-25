<?php

namespace App\Providers;

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
        \App\Models\StockLocation::observe(\App\Observers\StockLocationObserver::class);

        // Share pending count for PPC sidebar badge
        \Illuminate\Support\Facades\View::composer('shared.layouts.app', function ($view) {
            if (auth()->check() && (auth()->user()->role === 'ppc' || auth()->user()->role === 'admin')) {
                $count = \App\Models\DeliveryOrder::where('status', 'pending')->count();
                $view->with('pendingDeliveryCount', $count);
            }
        });
    }
}
