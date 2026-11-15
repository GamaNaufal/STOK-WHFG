<?php

namespace App\Services;

class GranularFeaturePermissions
{
    public const VERSION = 2;

    /** Expand only legacy grants. Version 2 selections must never inherit actions again. */
    public static function expand(string $role, array $permissions): array
    {
        $features = config('feature_access.features');
        $expanded = $permissions;
        foreach ($features as $key => $feature) {
            if (isset($feature['allowed_roles']) && !in_array($role, $feature['allowed_roles'], true)) continue;
            $sources = $feature['legacy_sources'] ?? (isset($feature['legacy_parent']) ? [$feature['legacy_parent']] : []);
            // This is evaluated against the original pre-action configuration grants.
            if (array_intersect($sources, $permissions) || in_array($role, $feature['legacy_always_roles'] ?? [], true)) {
                $expanded[] = $key;
            }
        }
        $expanded = array_values(array_unique($expanded));
        $allows = function (string $key, array $trail = []) use (&$allows, $features, $expanded, $role): bool {
            $feature = $features[$key] ?? null;
            if (!$feature || isset($trail[$key]) || !in_array($key, $expanded, true)
                || isset($feature['allowed_roles']) && !in_array($role, $feature['allowed_roles'], true)) return false;
            $trail[$key] = true;
            foreach ($feature['requires'] ?? [] as $required) if (!$allows($required, $trail)) return false;
            return true;
        };
        return array_values(array_filter($expanded, fn ($key) => in_array($key, $permissions, true) || $allows($key)));
    }
}
