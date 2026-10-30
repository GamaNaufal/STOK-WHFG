<?php

namespace App\Services;

use App\Models\RoleFeatureAccess;
use App\Models\User;
use Illuminate\Support\Str;

class FeatureAccessService
{
    public function defaults(string $role): array
    {
        return array_keys(array_filter(config('feature_access.features'),
            fn (array $feature) => ! ($feature['admin_only'] ?? false) && in_array($role, $feature['roles'], true)));
    }

    public function permissions(string $role): array
    {
        // Cache only in this HTTP request, so revocation applies on the next request.
        $key = 'feature_access.'.$role;
        if (! request()->attributes->has($key)) {
            $permissions = RoleFeatureAccess::where('role', $role)->first()?->permissions ?? $this->defaults($role);
            request()->attributes->set($key, $permissions);
        }

        return request()->attributes->get($key);
    }

    public function allows(?User $user, string $key): bool
    {
        $feature = config('feature_access.features')[$key] ?? null;
        if (! $user || ! $user->is_active || ! $feature) {
            return false;
        }
        if ($user->role === 'admin') {
            return true;
        }
        if (($feature['admin_only'] ?? false) || ! array_key_exists($user->role, config('feature_access.roles'))) {
            return false;
        }
        if (! in_array($key, $this->permissions($user->role), true)) {
            return false;
        }
        foreach ($feature['requires'] ?? [] as $dependency) {
            if (! $this->allows($user, $dependency)) {
                return false;
            }
        }

        return true;
    }

    public function routePermissions(?string $route): array
    {
        if (isset(config('feature_access.lookups')[$route])) {
            return config('feature_access.lookups')[$route];
        }
        foreach (config('feature_access.features') as $key => $feature) {
            if (Str::is($feature['routes'], $route ?? '')) {
                return [$key];
            }
        }

        return [];
    }

    public function allowsRoute(?User $user, ?string $route): bool
    {
        if (! $user || ! $user->is_active) {
            return false;
        }
        if (in_array($route, ['dashboard', 'profile.edit', 'profile.update'], true)) {
            return true;
        }
        foreach ($this->routePermissions($route) as $permission) {
            if ($this->allows($user, $permission)) {
                return true;
            }
        }

        return false;
    }

    public function navigation(User $user): array
    {
        $items = [];
        foreach (config('feature_access.features') as $key => $feature) {
            if (isset($feature['route']) && $this->allows($user, $key)) {
                $items[] = $feature + ['key' => $key];
                if ($key === 'reports') {
                    $items[] = ['key' => $key, 'label' => 'Laporan Pengambilan', 'group' => 'Laporan', 'icon' => 'file-earmark-text', 'route' => 'reports.withdrawal'];
                }
                if ($key === 'export_stock_input') {
                    $items[] = ['key' => $key, 'label' => 'Stok Ekspor', 'group' => 'Pengiriman Ekspor', 'icon' => 'box-seam', 'route' => 'export.stock.index'];
                }
            }
        }

        return $items;
    }
}
