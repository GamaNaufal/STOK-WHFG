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

        // Share notification counts for sidebar badges
        \Illuminate\Support\Facades\View::composer('shared.layouts.app', function ($view) {
            if (auth()->check()) {
                $userRole = auth()->user()->role;
                $cacheTtlSeconds = 30;
                
                // Delivery count for Warehouse Operator
                if ($userRole === 'warehouse_operator') {
                    $deliveryCount = \Illuminate\Support\Facades\Cache::remember('sidebar_delivery_count', $cacheTtlSeconds, function () {
                        return \App\Models\DeliveryOrder::where('status', 'approved')->count();
                    });
                    $view->with('pendingDeliveryCount', $deliveryCount);
                }
                
                // Pending Approval count for PPC
                if ($userRole === 'ppc') {
                    $approvalCount = \Illuminate\Support\Facades\Cache::remember('sidebar_approval_count', $cacheTtlSeconds, function () {
                        return \App\Models\DeliveryOrder::where('status', 'pending')->count();
                    });
                    $view->with('pendingDeliveryCount', $approvalCount);
                }
                
                // Scan Issues count for Admin Warehouse
                if ($userRole === 'admin_warehouse' || $userRole === 'admin') {
                    $scanIssueCount = \Illuminate\Support\Facades\Cache::remember('sidebar_scan_issue_count', $cacheTtlSeconds, function () {
                        return \App\Models\DeliveryIssue::where('status', 'pending')->count();
                    });
                    $view->with('pendingScanIssueCount', $scanIssueCount);
                }
            }
        });
    }
}
