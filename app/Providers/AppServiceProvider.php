<?php

namespace App\Providers;

use App\Models\DeliveryIssue;
use App\Models\DeliveryOrder;
use App\Models\StockLocation;
use App\Observers\StockLocationObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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
        if (app()->environment('testing')) {
            Model::preventLazyLoading(true);
        } elseif (! app()->isProduction()) {
            Model::preventLazyLoading(false);
            Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation): void {
                $request = request();
                $route = $request?->route();

                Log::warning('Lazy loading detected', [
                    'model' => $model::class,
                    'model_id' => $model->getKey(),
                    'relation' => $relation,
                    'url' => $request?->fullUrl(),
                    'method' => $request?->method(),
                    'route_name' => $route?->getName(),
                    'route_uri' => $route?->uri(),
                    'user_id' => Auth::id(),
                ]);
            });
        }

        StockLocation::observe(StockLocationObserver::class);

        // Share notification counts for sidebar badges
        View::composer('shared.layouts.app', function ($view) {
            $avatarInitials = 'UU';
            $globalSearchFeatures = [];

            if (Auth::check()) {
                $displayName = Auth::user()?->name ?? 'User';
                $nameParts = preg_split('/\s+/', trim($displayName));
                $firstInitial = isset($nameParts[0]) ? strtoupper(substr($nameParts[0], 0, 1)) : 'U';
                $lastInitial = isset($nameParts[count($nameParts) - 1]) ? strtoupper(substr($nameParts[count($nameParts) - 1], 0, 1)) : $firstInitial;
                $avatarInitials = $firstInitial.$lastInitial;

                $featureNavigation = app(\App\Services\FeatureAccessService::class)->navigation(Auth::user());
                $view->with('featureNavigation', $featureNavigation);
                $globalSearchFeatures = array_merge([
                    ['label' => 'Dashboard', 'group' => 'General', 'icon' => 'house', 'route' => route('dashboard'), 'keywords' => 'dashboard beranda'],
                ], array_map(fn ($item) => [
                    'label' => $item['label'], 'group' => $item['group'], 'icon' => $item['icon'],
                    'route' => route($item['route']), 'keywords' => $item['label'].' '.$item['group'],
                ], $featureNavigation));
                $cacheTtlSeconds = 30;

                // Delivery count for Warehouse Operator
                if (Auth::user()->canAccess('delivery_schedule')) {
                    $deliveryCount = Cache::remember('sidebar_delivery_count', $cacheTtlSeconds, function () {
                        return DeliveryOrder::where('status', 'approved')->count();
                    });
                    $view->with('pendingDeliveryCount', $deliveryCount);
                }

                // Pending Approval count for PPC
                if (Auth::user()->canAccess('delivery_approval')) {
                    $approvalCount = Cache::remember('sidebar_approval_count', $cacheTtlSeconds, function () {
                        return DeliveryOrder::where('status', 'pending')->count();
                    });
                    $view->with('pendingApprovalCount', $approvalCount);
                }

                // Scan Issues count for Admin Warehouse
                if (Auth::user()->canAccess('delivery_issues')) {
                    $scanIssueCount = Cache::remember('sidebar_scan_issue_count_v2', $cacheTtlSeconds, function () {
                        return DeliveryIssue::where('status', 'pending')->count() + \App\Models\ExportPickingIssue::where('status', 'pending')->count();
                    });
                    $view->with('pendingScanIssueCount', $scanIssueCount);
                }
            }

            $view->with('avatarInitials', $avatarInitials);
            $view->with('globalSearchFeatures', $globalSearchFeatures);
        });
    }
}
