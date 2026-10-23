<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->query('q');
        $search = is_string($search) ? trim(mb_substr($search, 0, 100)) : '';
        $role = $request->query('role');
        $role = in_array($role, ['admin', 'admin_warehouse', 'warehouse_operator', 'sales', 'ppc', 'supervisi'], true)
            ? $role : '';
        $status = $request->query('status');
        $status = in_array($status, ['active', 'inactive'], true) ? $status : '';

        $users = User::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($match) use ($search) {
                    $match->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($role !== '', fn ($query) => $query->where('role', $role))
            ->when($status !== '', fn ($query) => $query->where('is_active', $status === 'active'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $totalUsers = User::count();
        $activeUsers = User::where('is_active', true)->count();

        return view('admin.users.index', compact('users', 'search', 'role', 'status', 'totalUsers', 'activeUsers'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $roles = [
            'admin',
            'admin_warehouse',
            'warehouse_operator',
            'sales',
            'ppc',
            'supervisi',
        ];

        return view('admin.users.create', compact('roles'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'role' => 'required|in:admin,admin_warehouse,warehouse_operator,sales,ppc,supervisi',
            'password' => 'required|string|min:6|confirmed',
        ]);

        DB::transaction(function () use ($validated, $request) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'role' => $validated['role'],
                'password' => Hash::make($validated['password']),
            ]);
            AuditService::record('user_management', 'created', 'User', $user->id, $request->user()->id,
                [], ['name' => $user->name, 'email' => $user->email, 'role' => $user->role, 'is_active' => (bool) $user->is_active],
                "Akun {$user->name} dibuat.");
        });

        return redirect()->route('users.index')->with('success', 'User berhasil ditambahkan.');
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        $roles = [
            'admin',
            'admin_warehouse',
            'warehouse_operator',
            'sales',
            'ppc',
            'supervisi',
        ];

        return view('admin.users.edit', compact('user', 'roles'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'role' => 'required|in:admin,admin_warehouse,warehouse_operator,sales,ppc,supervisi',
            'password' => 'nullable|string|min:6|confirmed',
            'is_active' => 'sometimes|boolean',
        ]);

        DB::transaction(function () use ($user, $validated, $request) {
            $before = ['name' => $user->name, 'email' => $user->email, 'role' => $user->role, 'is_active' => (bool) $user->is_active];
            $user->name = $validated['name'];
            $user->email = $validated['email'];
            $user->role = $validated['role'];
            if (array_key_exists('is_active', $validated)) {
                $user->is_active = (bool) $validated['is_active'];
            }
            $passwordChanged = ! empty($validated['password']);
            if ($passwordChanged) {
                $user->password = Hash::make($validated['password']);
            }
            $user->save();
            AuditService::record('user_management', 'updated', 'User', $user->id, $request->user()->id,
                $before, ['name' => $user->name, 'email' => $user->email, 'role' => $user->role,
                    'is_active' => (bool) $user->is_active, 'password_changed' => $passwordChanged],
                "Akun {$user->name} diperbarui.");
        });

        return redirect()->route('users.index')->with('success', 'User berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        if (auth()->id() === $user->id) {
            return redirect()->route('users.index')->with('error', 'Tidak bisa menghapus akun sendiri.');
        }

        DB::transaction(function () use ($user) {
            $wasActive = (bool) $user->is_active;
            $user->forceFill([
                'is_active' => false,
                'remember_token' => null,
            ])->save();

            DB::table('sessions')->where('user_id', $user->id)->delete();
            if ($wasActive) {
                AuditService::record('user_management', 'deactivated', 'User', $user->id, auth()->id(),
                    ['name' => $user->name, 'is_active' => true],
                    ['name' => $user->name, 'is_active' => false], "Akun {$user->name} dinonaktifkan.");
            }
        });

        return redirect()->route('users.index')->with('success', 'User berhasil dinonaktifkan. Histori aktivitas tetap tersimpan.');
    }
}
