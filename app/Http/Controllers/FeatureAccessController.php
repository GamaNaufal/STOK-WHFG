<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\RoleFeatureAccess;
use App\Services\FeatureAccessService;
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
            'features' => config('feature_access.features'),
            'selected' => $access->permissions($role),
        ]);
    }

    public function update(Request $request, FeatureAccessService $access)
    {
        // The hidden form field represents an explicitly empty selection.
        if ($request->exists('permissions') && $request->input('permissions') === null) {
            $request->merge(['permissions' => []]);
        }
        $features = config('feature_access.features');
        $editable = array_keys(array_filter($features, fn ($feature) => ! ($feature['admin_only'] ?? false)));
        $validated = $request->validate([
            'role' => ['required', Rule::in(array_keys(config('feature_access.roles')))],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in($editable)],
        ]);
        $permissions = array_values($validated['permissions']);
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
            $record->update(['permissions' => $permissions]);
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
