<?php

namespace App\Services;

use App\Models\RoleFeatureAccess;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Route;

class FeatureAccessService
{
    public function defaults(string $role): array
    {
        return array_keys(array_filter(config('feature_access.features'),
            fn (array $feature) => ! ($feature['admin_only'] ?? false) && in_array($role, array_merge($feature['roles'], app()->environment(['local','testing']) ? ($feature['debug_roles'] ?? []) : []), true)));
    }

    public function permissions(string $role): array
    {
        // Cache only in this HTTP request, so revocation applies on the next request.
        $key = 'feature_access.'.$role;
        if (! request()->attributes->has($key)) {
            $record = RoleFeatureAccess::where('role', $role)->first();
            $permissions = $record?->permissions ?? $this->defaults($role);
            if ($record && (int) ($record->permissions_version ?? 1) < GranularFeaturePermissions::VERSION) {
                $permissions = GranularFeaturePermissions::expand($role, $permissions);
            }
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
        if (isset($feature['allowed_roles']) && ! in_array($user->role, $feature['allowed_roles'], true)) {
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
        if (isset(config('feature_access.action_dispatch')[$route])) {
            return array_values(array_unique(config('feature_access.action_dispatch')[$route]['choices']));
        }
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
        if (in_array($route, ['export.delivery-plans.deletion-preview', 'export.delivery-plans.destroy',
            'export.fulfillment.cancel-form', 'export.fulfillment.cancel'], true) && ! $user->canDeleteExportDeliveryPlan()) {
            return false;
        }
        if (in_array($route, ['dashboard', 'profile.edit', 'profile.update'], true)) {
            return true;
        }
        $dispatch = config('feature_access.action_dispatch')[$route] ?? null;
        if ($dispatch && request()->route()?->getName() === $route) {
            $value = request()->input($dispatch['field']);
            if (is_string($value) && isset($dispatch['choices'][$value])) {
                return $this->allows($user, $dispatch['choices'][$value]);
            }
        }
        if ($route === 'delivery-assign.assign' && request()->route()?->getName() === $route && request()->filled('new_boxes')
            && !$this->allows($user, 'delivery_assign_new_box')) {
            return false;
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
                    $items[] = ['key' => $key, 'label' => 'Laporan Pengiriman', 'group' => 'Laporan', 'icon' => 'file-earmark-text', 'route' => 'reports.withdrawal'];
                }
            }
        }

        return $items;
    }

    /** Presentation hints only; middleware and service checks remain authoritative. */
    public function clientRules(User $user): array
    {
        $rules = [];
        foreach (Route::getRoutes() as $route) {
            if (!in_array('features', $route->gatherMiddleware(), true)) continue;
            $name = $route->getName();
            $rule = ['path' => $route->uri(), 'methods' => $route->methods(), 'where' => $route->wheres, 'allowed' => $this->allowsRoute($user, $name)];
            if ($dispatch = config('feature_access.action_dispatch')[$name] ?? null) {
                $rule['field'] = $dispatch['field'];
                $rule['choices'] = array_map(fn ($key) => $this->allows($user, $key), $dispatch['choices']);
                $rule['allowed'] = in_array(true, $rule['choices'], true);
            }
            $rules[] = $rule;
        }
        return ['root' => parse_url(url('/'), PHP_URL_PATH) ?: '', 'rules' => $rules,
            'features' => array_map(fn ($key) => $this->allows($user, $key), array_combine(array_keys(config('feature_access.features')), array_keys(config('feature_access.features'))))];
    }
}
