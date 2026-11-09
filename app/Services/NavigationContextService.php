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
        $mergeRoutes = $this->mergeRoutes($user);
        $mergeAnchor = $mergeRoutes['domestic'] ?? $mergeRoutes['export'] ?? null;
        $mergeRoute = Str::is('export.merge.*', $routeName ?? '')
            ? ($mergeRoutes['export'] ?? $mergeAnchor)
            : $mergeAnchor;
        $items = array_filter($items, fn ($item) => ! in_array($item['key'], ['merge_pallet', 'export_merge_pallet'], true)
            || $item['route'] === $mergeAnchor);
        foreach ($items as &$item) {
            if ($item['route'] === $mergeAnchor) {
                $item['route'] = $mergeRoute;
                $item['key'] = $mergeRoute === 'export.merge.index' ? 'export_merge_pallet' : 'merge_pallet';
                $item['group'] = 'Warehouse';
                $item['label'] = 'Merge Pallet';
                $item['icon'] = 'intersect';
            }
        }
        unset($item);

        $moveRoutes = $this->moveRoutes($user);
        $moveAnchor = $moveRoutes['domestic'] ?? $moveRoutes['export'] ?? null;
        $moveRoute = Str::is('export.move-pallet.*', $routeName ?? '') ? ($moveRoutes['export'] ?? $moveAnchor) : $moveAnchor;
        $items = array_filter($items, fn ($item) => ! in_array($item['key'], ['move_pallet', 'export_move_pallet'], true)
            || $item['route'] === $moveAnchor);
        foreach ($items as &$item) {
            if ($item['route'] === $moveAnchor) {
                $item['route'] = $moveRoute;
                $item['key'] = $moveRoute === 'export.move-pallet.index' ? 'export_move_pallet' : 'move_pallet';
                $item['label'] = 'Pemindahan Pallet';
            }
        }
        unset($item);

        $expiredKeys = ['expired_box' => 'expired-box.index', 'export_expired_box' => 'export.expired-box.index'];
        $expiredRoutes = array_filter($expiredKeys, fn ($route, $key) => $user->canAccess($key), ARRAY_FILTER_USE_BOTH);
        $expiredAnchor = reset($expiredRoutes) ?: null;
        $expiredRoute = Str::is('export.expired-box.*', $routeName ?? '') ? ($expiredRoutes['export_expired_box'] ?? $expiredAnchor) : $expiredAnchor;
        $items = array_filter($items, fn ($item) => ! isset($expiredKeys[$item['key']]) || $item['route'] === $expiredAnchor);
        foreach ($items as &$item) {
            if ($item['route'] === $expiredAnchor) {
                $item['route'] = $expiredRoute;
                $item['key'] = $expiredRoute === 'export.expired-box.index' ? 'export_expired_box' : 'expired_box';
                $item['label'] = 'Expired Box dan Handle';
            }
        }
        unset($item);

        $order = array_flip(['Warehouse', 'Delivery', 'Pengiriman Ekspor', 'Master Data', 'Master Data Ekspor', 'Laporan', 'Administrasi']);
        usort($items, fn ($a, $b) => ($order[$a['group']] ?? 99) <=> ($order[$b['group']] ?? 99));
        $activeRoute = $this->activeRoute($items, $routeName, $access);
        foreach ($items as &$item) {
            $item['active'] = $item['route'] === $activeRoute;
            $item['label'] = match ($item['route']) {
                'locations.index' => 'Lokasi Lokal',
                'export.locations.index' => 'Lokasi Ekspor',
                default => $item['label'],
            };
            $item['short_label'] = match ($item['route']) {
                'part-settings.index' => 'Nomor Part',
                'export.locations.index', 'export.fulfillment.index' => $item['label'],
                default => str_contains($item['group'], 'Ekspor') ? Str::replaceEnd(' Ekspor', '', $item['label']) : $item['label'],
            };
            $item['group_label'] = match ($item['group']) {
                'Warehouse' => 'Warehouse', 'Delivery' => 'Pengiriman Lokal', default => $item['group'],
            };
            $scope = str_starts_with($item['key'], 'export_') ? 'ekspor export' : match ($item['key']) {
                'not_full_request', 'not_full_approval', 'delivery_issues', 'delivery_announcements', 'reports', 'audit', 'parts' => 'lokal ekspor export',
                'stock_input', 'stock_view', 'merge_pallet', 'move_pallet', 'expired_box', 'delivery_schedule',
                'delivery_order', 'delivery_approval', 'delivery_assign', 'delivery_pick', 'locations' => 'lokal',
                default => '',
            };
            $item['keywords'] = $item['label'].' '.$item['group'].' '.$item['group_label'].' '.$scope.' '.match ($item['key']) {
                'delivery_announcements' => 'pengumuman instruksi keterangan delivery customer part kuota',
                'export_stock_manage' => 'stok stock ekspor kelola edit hapus riwayat box pallet',
                'export_stock_input', 'stock_input' => 'input penerimaan stok stock box transfer',
                'stock_opname_local', 'stock_opname_export' => 'stock stok opname cek cocok pemeriksaan history inventaris',
                'parts' => 'master nomor no part part number kapasitas pcs box',
                'export_locations' => 'master lokasi location tempat',
                'locations' => 'master lokasi location tempat kelola lokasi',
                'merge_pallet', 'export_merge_pallet' => 'gabung palet pallet'.(count($mergeRoutes) === 2 ? ' lokal ekspor export' : ''),
                'export_pallet_plans' => 'palet pallet rencana planning',
                'export_fulfillment' => 'jadwal kalender tenggat pemenuhan permintaan',
                'move_pallet', 'export_move_pallet' => 'palet pallet pindah lokasi'.(count($moveRoutes) === 2 ? ' lokal ekspor export' : ''),
                'export_delivery_plans', 'delivery_schedule', 'delivery_order' => 'rencana jadwal pengiriman delivery',
                'not_full_request', 'not_full_approval' => 'box not full nf pengajuan persetujuan approval',
                'reports' => 'laporan report excel ekspor export unduh',
                'stock_view' => 'stock stok lokal',
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

    public function mergeRoutes(User $user): array
    {
        $routes = [];
        if ($user->canAccess('merge_pallet')) {
            $routes['domestic'] = 'merge-pallet.index';
        }
        if (in_array($user->role, ['admin', ...config('feature_access.features.export_merge_pallet.roles')], true)
            && $user->canAccess('export_merge_pallet')) {
            $routes['export'] = 'export.merge.index';
        }

        return $routes;
    }

    public function moveRoutes(User $user): array
    {
        $routes = [];
        if ($user->canAccess('move_pallet')) {
            $routes['domestic'] = 'move-pallet.index';
        }
        if ($user->canAccess('export_move_pallet')) {
            $routes['export'] = 'export.move-pallet.index';
        }

        return $routes;
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
