@extends('shared.layouts.app')

@section('content')
<!-- Page Header Component -->
<x-page-header 
    title="Edit Pengguna" 
    icon="bi-person-gear"
    subtitle="Perbarui informasi dan hak akses pengguna"
/>

<x-card title="Informasi Pengguna">
    <form method="POST" action="{{ route('users.update', $user) }}">
        @csrf
        @method('PUT')
        
        <div class="mb-3">
            <label class="form-label fw-semibold">Nama Lengkap</label>
            <input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
            @error('name')
                <div class="text-danger small">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold">Alamat Email</label>
            <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required>
            @error('email')
                <div class="text-danger small">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold">Peran/Role</label>
            <select name="role" class="form-select" required>
                @foreach($roles as $role)
                    @php
                        $roleNames = [
                            'admin' => 'Administrator',
                            'admin_warehouse' => 'Admin Warehouse',
                            'warehouse_operator' => 'Operator Warehouse', 
                            'supervisi' => 'Supervisi',
                            'sales' => 'Sales',
                            'ppc' => 'PPC'
                        ];
                    @endphp
                    <option value="{{ $role }}" {{ old('role', $user->role) === $role ? 'selected' : '' }}>
                        {{ $roleNames[$role] ?? ucfirst(str_replace('_', ' ', $role)) }}
                    </option>
                @endforeach
            </select>
            @error('role')
                <div class="text-danger small">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold">Kata Sandi Baru (Kosongkan jika tidak diubah)</label>
            <input type="password" name="password" class="form-control" placeholder="Masukkan kata sandi baru">
            @error('password')
                <div class="text-danger small">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold">Konfirmasi Kata Sandi</label>
            <input type="password" name="password_confirmation" class="form-control" placeholder="Ulangi kata sandi baru">
        </div>

        <div class="d-flex gap-2 justify-content-end">
            <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Batal</a>
            <x-button type="submit" variant="primary">Simpan Perubahan</x-button>
        </div>
    </form>
</x-card>
@endsection
