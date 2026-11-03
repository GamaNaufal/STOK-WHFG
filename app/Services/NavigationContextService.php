<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

/** Presentation only: links are always drawn from FeatureAccessService's allowed navigation. */
class NavigationContextService
{
    public function build(User $user, ?string $routeName): array
    {
        $access = app(FeatureAccessService::class);
        $items = $access->navigation($user);
        $order = array_flip(['Warehouse', 'Delivery', 'Pengiriman Ekspor', 'Master Data', 'Master Data Ekspor', 'Laporan', 'Administrasi']);
        usort($items, fn ($a, $b) => ($order[$a['group']] ?? 99) <=> ($order[$b['group']] ?? 99));
        $activeRoute = $this->activeRoute($items, $routeName, $access);
        foreach ($items as &$item) {
            $item['active'] = $item['route'] === $activeRoute;
            $item['short_label'] = match ($item['route']) {
                'export.locations.index' => 'Lokasi',
                'part-settings.index' => 'Nomor Part',
                default => str_contains($item['group'], 'Ekspor') ? Str::replaceEnd(' Ekspor', '', $item['label']) : $item['label'],
            };
            $item['group_label'] = match ($item['group']) {
                'Warehouse' => 'Gudang', 'Delivery' => 'Pengiriman', default => $item['group'],
            };
            $scope = str_starts_with($item['key'], 'export_') ? 'ekspor export' : match ($item['key']) {
                'not_full_request', 'not_full_approval', 'delivery_issues', 'reports', 'audit', 'parts' => 'domestik lokal ekspor export',
                'stock_input', 'stock_view', 'merge_pallet', 'move_pallet', 'expired_box', 'delivery_schedule',
                'delivery_order', 'delivery_approval', 'delivery_assign', 'delivery_pick', 'locations' => 'domestik lokal',
                default => '',
            };
            $item['keywords'] = $item['label'].' '.$item['group'].' '.$item['group_label'].' '.$scope.' '.match ($item['key']) {
                'export_stock_input', 'stock_input' => 'input penerimaan stok stock box transfer',
                'parts' => 'master nomor no part part number kapasitas pcs box',
                'export_locations', 'locations' => 'master lokasi location tempat',
                'export_pallet_plans', 'merge_pallet', 'move_pallet' => 'palet pallet rencana planning',
                'export_delivery_plans', 'delivery_schedule', 'delivery_order' => 'rencana jadwal pengiriman delivery',
                'not_full_request', 'not_full_approval' => 'box not full nf pengajuan persetujuan approval',
                'reports' => 'laporan report excel ekspor export unduh',
                'stock_view' => 'stock stok lokal domestik',
                default => '',
            };
        }
        unset($item);

        $selected = collect($items)->firstWhere('active', true);
        $breadcrumbs = [];
        if ($selected) {
            $group = str_contains($selected['group'], 'Ekspor') ? 'Ekspor' : $selected['group_label'];
            $breadcrumbs[] = ['label' => $group, 'url' => null];
            $action = $routeName === $selected['route'] ? null : $this->action($routeName);
            $breadcrumbs[] = ['label' => $selected['short_label'], 'url' => $action ? route($selected['route']) : null];
            if ($action) {
                $breadcrumbs[] = ['label' => $action, 'url' => null];
            }
        } elseif (Str::is('profile.*', $routeName ?? '')) {
            $breadcrumbs = [['label' => 'Akun', 'url' => null], ['label' => 'Profil Saya', 'url' => null]];
        } else {
            $breadcrumbs = [['label' => $routeName === 'dashboard' ? 'Dashboard' : 'Warehouse FG', 'url' => null]];
        }

        return [
            'items' => $items, 'breadcrumbs' => $breadcrumbs,
            'role_label' => match ($user->role) {
                'admin' => 'Admin Utama', 'export_operator' => 'Operator Ekspor',
                default => config('feature_access.roles.'.$user->role, ucfirst(str_replace('_', ' ', $user->role))),
            },
        ];
    }

    private function activeRoute(array $items, ?string $route, FeatureAccessService $access): ?string
    {
        $available = array_column($items, 'route');
        if (in_array($route, $available, true)) {
            return $route;
        }
        // Shared permissions may expose multiple destinations; select by page, never by key alone.
        $preferred = match (true) {
            Str::is(['export.receiving.picking.*', 'export.picking.*'], $route ?? '') => 'export.picking.index',
            Str::is('export.stock.*', $route ?? '') => 'export.stock.index',
            Str::is('export.receiving.*', $route ?? '') => 'export.receiving.index',
            Str::is('reports.withdrawal*', $route ?? '') => 'reports.withdrawal',
            Str::is('reports.*', $route ?? '') => 'reports.stock-input',
            default => null,
        };
        if (in_array($preferred, $available, true)) {
            return $preferred;
        }
        foreach ($access->routePermissions($route) as $key) {
            $candidate = collect($items)->firstWhere('key', $key);
            if ($candidate) {
                return $candidate['route'];
            }
            foreach (config('feature_access.features.'.$key.'.requires', []) as $parent) {
                $candidate = collect($items)->firstWhere('key', $parent);
                if ($candidate) {
                    return $candidate['route'];
                }
            }
        }

        return null;
    }

    private function action(?string $route): string
    {
        return match (true) {
            Str::is('*.create', $route ?? '') => 'Tambah',
            Str::is(['*.edit', '*.update'], $route ?? '') => 'Edit',
            Str::is(['*.print*', '*.pdf', '*.legacy-print'], $route ?? '') => 'Cetak',
            Str::is('*.ready-form', $route ?? '') => 'Konfirmasi pallet',
            Str::is('*.revise', $route ?? '') => 'Revisi',
            Str::is('*.cancel-form', $route ?? '') => 'Pembatalan',
            Str::is(['*.verify', '*.scan'], $route ?? '') => 'Verifikasi',
            default => 'Detail',
        };
    }
}
