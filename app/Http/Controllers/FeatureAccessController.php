<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\RoleFeatureAccess;
use App\Services\FeatureAccessService;
use App\Services\GranularFeaturePermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FeatureAccessController extends Controller
{
    public function index(Request $request, FeatureAccessService $access)
    {
        $roles = config('feature_access.roles');
        $role = $request->query('role', array_key_first($roles));
        abort_unless(is_string($role) && array_key_exists($role, $roles), 404);

        return view('admin.access.index', [
            'roles' => $roles,
            'selectedRole' => $role,
            'features' => $this->featuresForRole($role),
            'selected' => $access->permissions($role),
        ]);
    }

    private function featuresForRole(mixed $role): array
    {
        $all = config('feature_access.features');
        $ordered = [];
        foreach ($all as $key => $feature) {
            if (isset($feature['parent'])) continue;
            $ordered[$key] = $feature;
            foreach ($all as $childKey => $child) if (($child['parent'] ?? null) === $key) $ordered[$childKey] = $child;
        }
        return array_filter($ordered, fn ($feature) => ! isset($feature['allowed_roles'])
            || in_array($role, $feature['allowed_roles'], true));
    }

    public function update(Request $request, FeatureAccessService $access)
    {
        // The hidden form field represents an explicitly empty selection.
        if ($request->exists('permissions') && $request->input('permissions') === null) {
            $request->merge(['permissions' => []]);
        }
        $features = $this->featuresForRole($request->input('role'));
        $editable = array_keys(array_filter($features, fn ($feature) => ! ($feature['admin_only'] ?? false)));
        $validated = $request->validate([
            'role' => ['required', Rule::in(array_keys(config('feature_access.roles')))],
            'permissions' => ['present', 'array'],
            'permissions_version' => ['sometimes', 'integer', 'in:2'],
            'permissions.*' => ['string', 'distinct', Rule::in($editable)],
        ]);
        $permissions = array_values($validated['permissions']);
        if (!$request->has('permissions_version')) {
            $permissions = GranularFeaturePermissions::expand($validated['role'], $permissions);
        }
        foreach ($permissions as $permission) {
            foreach ($features[$permission]['requires'] ?? [] as $dependency) {
                if (! in_array($dependency, $permissions, true)) {
                    throw ValidationException::withMessages([
                        'permissions' => $features[$permission]['label'].' memerlukan akses '.$features[$dependency]['label'].'.',
                    ]);
                }
            }
        }

        DB::transaction(function () use ($request, $access, $validated, $permissions) {
            $role = $validated['role'];
            $record = RoleFeatureAccess::firstOrCreate(['role' => $role], ['permissions' => $access->defaults($role)]);
            $record = RoleFeatureAccess::whereKey($record->id)->lockForUpdate()->firstOrFail();
            $previous = $record->permissions;
            $record->update(['permissions' => $permissions, 'permissions_version' => GranularFeaturePermissions::VERSION]);
            // Fail the transaction if its audit record cannot be saved.
            AuditLog::create([
                'type' => 'role_feature_access', 'action' => 'updated',
                'model' => 'RoleFeatureAccess', 'model_id' => $record->id,
                'old_values' => json_encode(['role' => $role, 'permissions' => $previous]),
                'new_values' => json_encode(['role' => $role, 'permissions' => $permissions]),
                'description' => 'Hak akses role '.config('feature_access.roles')[$role].' diperbarui.',
                'user_id' => $request->user()->id, 'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        });
        $request->attributes->remove('feature_access.'.$validated['role']);

        return redirect()->route('access.index', ['role' => $validated['role']])
            ->with('success', 'Hak akses berhasil disimpan dan berlaku pada request berikutnya untuk seluruh pengguna role ini.');
    }
}
