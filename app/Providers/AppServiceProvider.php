<?php

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    private function buildGlobalSearchFeatures(?string $role): array
    {
        $features = [[
            'label' => 'Dashboard',
            'group' => 'General',
            'icon' => 'house',
            'route' => route('dashboard'),
            'keywords' => 'dashboard home beranda',
        ]];

        if ($role === 'warehouse_operator') {
            $features = array_merge($features, [
                ['label' => 'Input Stok', 'group' => 'Warehouse', 'icon' => 'plus-circle', 'route' => route('stock-input.index'), 'keywords' => 'input stok inbound warehouse'],
                ['label' => 'Delivery', 'group' => 'Warehouse', 'icon' => 'truck', 'route' => route('delivery.index'), 'keywords' => 'delivery schedule pengiriman'],
                ['label' => 'Picking Verification', 'group' => 'Warehouse', 'icon' => 'upc-scan', 'route' => route('delivery.pick.verification'), 'keywords' => 'picking verification scan'],
                ['label' => 'Merge Palet', 'group' => 'Warehouse', 'icon' => 'box-seam', 'route' => route('merge-pallet.index'), 'keywords' => 'merge pallet palet gabung'],
                ['label' => 'Lihat Stok', 'group' => 'Warehouse', 'icon' => 'eye', 'route' => route('stock-view.index'), 'keywords' => 'stok stock view'],
            ]);
        }

        if ($role === 'sales') {
            $features[] = ['label' => 'Sales Input', 'group' => 'Sales', 'icon' => 'cart-plus', 'route' => route('delivery.create'), 'keywords' => 'sales input order delivery'];
        }

        if ($role === 'ppc') {
            $features = array_merge($features, [
                ['label' => 'Pending Approval', 'group' => 'PPC', 'icon' => 'clipboard-check', 'route' => route('delivery.approvals'), 'keywords' => 'approval pending ppc'],
                ['label' => 'Delivery Schedule', 'group' => 'PPC', 'icon' => 'truck', 'route' => route('delivery.index'), 'keywords' => 'delivery schedule pengiriman'],
                ['label' => 'Lihat Stok', 'group' => 'PPC', 'icon' => 'eye', 'route' => route('stock-view.index'), 'keywords' => 'stok stock view'],
            ]);
        }

        if ($role === 'supervisi') {
            $features = array_merge($features, [
                ['label' => 'Lihat Stok', 'group' => 'Supervisi', 'icon' => 'eye', 'route' => route('stock-view.index'), 'keywords' => 'stok stock view'],
                ['label' => 'Approval Box Not Full', 'group' => 'Supervisi', 'icon' => 'clipboard-check', 'route' => route('box-not-full.approvals'), 'keywords' => 'approval box not full'],
                ['label' => 'Expired Box', 'group' => 'Supervisi', 'icon' => 'exclamation-triangle', 'route' => route('expired-box.index'), 'keywords' => 'expired box kadaluarsa'],
                ['label' => 'Laporan Input Stok', 'group' => 'Laporan', 'icon' => 'file-earmark-bar-graph', 'route' => route('reports.stock-input'), 'keywords' => 'laporan report input stok'],
                ['label' => 'Laporan Pengambilan', 'group' => 'Laporan', 'icon' => 'file-earmark-text', 'route' => route('reports.withdrawal'), 'keywords' => 'laporan report pengambilan withdrawal'],
                ['label' => 'Audit Trail', 'group' => 'Laporan', 'icon' => 'shield-check', 'route' => route('audit.index'), 'keywords' => 'audit trail log'],
            ]);
        }

        if ($role === 'admin_warehouse') {
            $features = array_merge($features, [
                ['label' => 'Kelola Lokasi', 'group' => 'Admin Warehouse', 'icon' => 'geo-alt', 'route' => route('locations.index'), 'keywords' => 'lokasi location'],
                ['label' => 'Box Not Full', 'group' => 'Admin Warehouse', 'icon' => 'exclamation-circle', 'route' => route('box-not-full.create'), 'keywords' => 'box not full request'],
                ['label' => 'Scan Issues', 'group' => 'Admin Warehouse', 'icon' => 'bell', 'route' => route('delivery.pick.issues'), 'keywords' => 'scan issue delivery'],
                ['label' => 'Picking Verification', 'group' => 'Admin Warehouse', 'icon' => 'upc-scan', 'route' => route('delivery.pick.verification'), 'keywords' => 'picking verification scan'],
                ['label' => 'Master No Part', 'group' => 'Admin Warehouse', 'icon' => 'list-check', 'route' => route('part-settings.index'), 'keywords' => 'master part part number'],
                ['label' => 'Lihat Stok', 'group' => 'Admin Warehouse', 'icon' => 'eye', 'route' => route('stock-view.index'), 'keywords' => 'stok stock view'],
            ]);
        }

        if ($role === 'admin') {
            $features = array_merge($features, [
                ['label' => 'Input Stok', 'group' => 'Warehouse', 'icon' => 'plus-circle', 'route' => route('stock-input.index'), 'keywords' => 'input stok inbound warehouse'],
                ['label' => 'Box Not Full', 'group' => 'Warehouse', 'icon' => 'exclamation-circle', 'route' => route('box-not-full.create'), 'keywords' => 'box not full request'],
                ['label' => 'Approval Box Not Full', 'group' => 'Warehouse', 'icon' => 'clipboard-check', 'route' => route('box-not-full.approvals'), 'keywords' => 'approval box not full'],
                ['label' => 'Merge Palet', 'group' => 'Warehouse', 'icon' => 'box-seam', 'route' => route('merge-pallet.index'), 'keywords' => 'merge pallet palet gabung'],
                ['label' => 'Lihat Stok', 'group' => 'Warehouse', 'icon' => 'eye', 'route' => route('stock-view.index'), 'keywords' => 'stok stock view'],
                ['label' => 'Expired Box', 'group' => 'Warehouse', 'icon' => 'exclamation-triangle', 'route' => route('expired-box.index'), 'keywords' => 'expired box kadaluarsa'],
                ['label' => 'Delivery', 'group' => 'Delivery', 'icon' => 'truck', 'route' => route('delivery.index'), 'keywords' => 'delivery schedule pengiriman'],
                ['label' => 'Picking Verification', 'group' => 'Delivery', 'icon' => 'upc-scan', 'route' => route('delivery.pick.verification'), 'keywords' => 'picking verification scan'],
                ['label' => 'Sales Input', 'group' => 'Delivery', 'icon' => 'cart-plus', 'route' => route('delivery.create'), 'keywords' => 'sales input order delivery'],
                ['label' => 'Pending Approval', 'group' => 'Delivery', 'icon' => 'clipboard-check', 'route' => route('delivery.approvals'), 'keywords' => 'approval pending ppc'],
                ['label' => 'Scan Issues', 'group' => 'Delivery', 'icon' => 'bell', 'route' => route('delivery.pick.issues'), 'keywords' => 'scan issue delivery'],
                ['label' => 'Lokasi', 'group' => 'Master Data', 'icon' => 'geo-alt', 'route' => route('locations.index'), 'keywords' => 'lokasi location'],
                ['label' => 'Master No Part', 'group' => 'Master Data', 'icon' => 'list-check', 'route' => route('part-settings.index'), 'keywords' => 'master part part number'],
                ['label' => 'Kelola User', 'group' => 'Master Data', 'icon' => 'people', 'route' => route('users.index'), 'keywords' => 'users user management'],
                ['label' => 'Laporan Input Stok', 'group' => 'Laporan', 'icon' => 'file-earmark-bar-graph', 'route' => route('reports.stock-input'), 'keywords' => 'laporan report input stok'],
                ['label' => 'Laporan Pengambilan', 'group' => 'Laporan', 'icon' => 'file-earmark-text', 'route' => route('reports.withdrawal'), 'keywords' => 'laporan report pengambilan withdrawal'],
                ['label' => 'Audit Trail', 'group' => 'Laporan', 'icon' => 'shield-check', 'route' => route('audit.index'), 'keywords' => 'audit trail log'],
            ]);
        }

        return $features;
    }

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
            $avatarInitials = 'UU';
            $globalSearchFeatures = [];

            if (Auth::check()) {
                $displayName = Auth::user()?->name ?? 'User';
                $nameParts = preg_split('/\s+/', trim($displayName));
                $firstInitial = isset($nameParts[0]) ? strtoupper(substr($nameParts[0], 0, 1)) : 'U';
                $lastInitial = isset($nameParts[count($nameParts) - 1]) ? strtoupper(substr($nameParts[count($nameParts) - 1], 0, 1)) : $firstInitial;
                $avatarInitials = $firstInitial . $lastInitial;

                $userRole = Auth::user()?->role;
                $globalSearchFeatures = $this->buildGlobalSearchFeatures($userRole);
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

            $view->with('avatarInitials', $avatarInitials);
            $view->with('globalSearchFeatures', $globalSearchFeatures);
        });
    }
}
