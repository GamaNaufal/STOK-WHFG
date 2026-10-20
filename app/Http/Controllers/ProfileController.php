<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function edit()
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            abort(403);
        }

        return view('profile.edit', compact('user'));
    }

    public function update(Request $request)
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'current_password' => 'nullable|string',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        if (!empty($validated['password'])) {
            if (empty($validated['current_password']) || !Hash::check($validated['current_password'], $user->password)) {
                return back()->withErrors(['current_password' => 'Password saat ini tidak sesuai.'])->withInput();
            }
        }

        DB::transaction(function () use ($user, $validated) {
            $before = ['name' => $user->name, 'email' => $user->email];
            $user->name = $validated['name'];
            $user->email = $validated['email'];
            $passwordChanged = ! empty($validated['password']);
            if ($passwordChanged) {
                $user->password = Hash::make($validated['password']);
            }
            $user->save();
            AuditService::record('profile', 'updated', 'User', $user->id, $user->id,
                $before, ['name' => $user->name, 'email' => $user->email, 'password_changed' => $passwordChanged],
                'Profil pengguna diperbarui.');
        });

        return redirect()->route('profile.edit')->with('success', 'Profil berhasil diperbarui.');
    }
}
