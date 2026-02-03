@extends('shared.layouts.app')

@section('content')
<!-- Page Header Component -->
<x-page-header 
    title="Tambah Pengguna Baru" 
    icon="bi-person-plus"
    subtitle="Buat akun pengguna baru untuk akses sistem"
>
    <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</x-page-header>

<div class="row">
    <div class="col-lg-8 mx-auto">
        <x-card title="Informasi Pengguna Baru">
            <form method="POST" action="{{ route('users.store') }}" id="createUserForm">
                @csrf
                
                <div class="mb-4">
                    <label class="form-label fw-semibold">
                        <i class="bi bi-person"></i> Nama Lengkap
                    </label>
                    <input type="text" name="name" class="form-control" 
                           value="{{ old('name') }}" 
                           placeholder="Masukkan nama lengkap"
                           required>
                    @error('name')
                        <div class="text-danger small mt-1">
                            <i class="bi bi-exclamation-circle"></i> {{ $message }}
                        </div>
                    @enderror
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">
                        <i class="bi bi-envelope"></i> Alamat Email
                    </label>
                    <input type="email" name="email" class="form-control" 
                           value="{{ old('email') }}" 
                           placeholder="contoh@yamatogomu.com"
                           required>
                    @error('email')
                        <div class="text-danger small mt-1">
                            <i class="bi bi-exclamation-circle"></i> {{ $message }}
                        </div>
                    @enderror
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">
                        <i class="bi bi-shield-check"></i> Peran/Role
                    </label>
                    <select name="role" class="form-select" required>
                        <option value="" disabled selected>Pilih Peran Pengguna</option>
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
                            <option value="{{ $role }}" {{ old('role') === $role ? 'selected' : '' }}>
                                {{ $roleNames[$role] ?? ucfirst(str_replace('_', ' ', $role)) }}
                            </option>
                        @endforeach
                    </select>
                    @error('role')
                        <div class="text-danger small mt-1">
                            <i class="bi bi-exclamation-circle"></i> {{ $message }}
                        </div>
                    @enderror
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">
                        <i class="bi bi-lock"></i> Kata Sandi
                    </label>
                    <input type="password" name="password" class="form-control" 
                           placeholder="Minimal 8 karakter"
                           required>
                    @error('password')
                        <div class="text-danger small mt-1">
                            <i class="bi bi-exclamation-circle"></i> {{ $message }}
                        </div>
                    @enderror
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">
                        <i class="bi bi-lock-fill"></i> Konfirmasi Kata Sandi
                    </label>
                    <input type="password" name="password_confirmation" class="form-control" 
                           placeholder="Ketik ulang kata sandi"
                           required>
                </div>

                <div class="d-flex gap-2 justify-content-end">
                    <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle"></i> Batal
                    </a>
                    <x-button type="submit" variant="primary">
                        <i class="bi bi-check-circle"></i> Simpan Pengguna
                    </x-button>
                </div>
            </form>
        </x-card>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.getElementById('createUserForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const form = this;
        const name = form.querySelector('[name="name"]')?.value || '';
        const email = form.querySelector('[name="email"]')?.value || '';
        const role = form.querySelector('[name="role"] option:checked')?.text || '';
        
        WarehouseAlert.info({
            title: 'Konfirmasi Buat Pengguna',
            message: 'Pengguna baru akan ditambahkan ke sistem dengan data berikut:',
            details: {
                'Nama': name,
                'Email': email,
                'Peran': role
            },
            infoText: 'Pengguna dapat langsung login setelah akun dibuat.',
            confirmText: 'Buat Pengguna',
            onConfirm: () => {
                form.submit();
            }
        });
    });
</script>
@endsection
