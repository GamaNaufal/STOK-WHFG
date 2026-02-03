@extends('shared.layouts.app')

@section('content')
<<<<<<< Updated upstream
<div class="container-fluid">
    <!-- Modern Gradient Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%); 
                        color: white; 
                        padding: 40px 30px; 
                        border-radius: 12px; 
                        box-shadow: 0 8px 24px rgba(12, 119, 121, 0.15);">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h2" style="margin: 0 0 10px 0; font-weight: 700;">
                            <i class="bi bi-person-plus-fill"></i> Tambah User Baru
                        </h1>
                        <p style="margin: 0; opacity: 0.95; font-size: 15px;">
                            Buat akun pengguna baru untuk akses ke sistem
                        </p>
                    </div>
                    <a href="{{ route('users.index') }}" 
                       class="btn" 
                       style="background-color: white; color: #0C7779; border: none; padding: 10px 24px; font-weight: 600; border-radius: 8px; transition: all 0.3s ease;" 
                       onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.15)'" 
                       onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                        <i class="bi bi-arrow-left"></i> Kembali
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Card -->
    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card shadow-sm border-0" style="border-left: 4px solid #0C7779; border-radius: 12px;">
                <div class="card-body" style="padding: 30px;">
                    <form method="POST" action="{{ route('users.store') }}" id="createUserForm">
                        @csrf
                        <div class="mb-4">
                            <label class="form-label fw-bold" style="color: #0C7779;">
                                <i class="bi bi-person"></i> Nama Lengkap
                            </label>
                            <input type="text" name="name" class="form-control" 
                                   style="padding: 12px; border-radius: 8px; border: 1px solid #d1d5db;" 
                                   value="{{ old('name') }}" 
                                   placeholder="Masukkan nama lengkap"
                                   required>
                            @error('name')
                                <div class="text-danger small mt-1"><i class="bi bi-exclamation-circle"></i> {{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold" style="color: #0C7779;">
                                <i class="bi bi-envelope"></i> Email
                            </label>
                            <input type="email" name="email" class="form-control" 
                                   style="padding: 12px; border-radius: 8px; border: 1px solid #d1d5db;" 
                                   value="{{ old('email') }}" 
                                   placeholder="contoh@yamatogomu.com"
                                   required>
                            @error('email')
                                <div class="text-danger small mt-1"><i class="bi bi-exclamation-circle"></i> {{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold" style="color: #0C7779;">
                                <i class="bi bi-shield-check"></i> Role / Hak Akses
                            </label>
                            <select name="role" class="form-select" 
                                    style="padding: 12px; border-radius: 8px; border: 1px solid #d1d5db;" 
                                    required>
                                <option value="" disabled selected>Pilih Role Pengguna</option>
                                @foreach($roles as $role)
                                    <option value="{{ $role }}" {{ old('role') === $role ? 'selected' : '' }}>
                                        {{ ucfirst(str_replace('_', ' ', $role)) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('role')
                                <div class="text-danger small mt-1"><i class="bi bi-exclamation-circle"></i> {{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold" style="color: #0C7779;">
                                <i class="bi bi-lock"></i> Password
                            </label>
                            <input type="password" name="password" class="form-control" 
                                   style="padding: 12px; border-radius: 8px; border: 1px solid #d1d5db;" 
                                   placeholder="Minimal 8 karakter"
                                   required>
                            @error('password')
                                <div class="text-danger small mt-1"><i class="bi bi-exclamation-circle"></i> {{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold" style="color: #0C7779;">
                                <i class="bi bi-lock-fill"></i> Konfirmasi Password
                            </label>
                            <input type="password" name="password_confirmation" class="form-control" 
                                   style="padding: 12px; border-radius: 8px; border: 1px solid #d1d5db;" 
                                   placeholder="Ketik ulang password"
                                   required>
                        </div>

                        <div class="d-flex gap-2 mt-4">
                            <a href="{{ route('users.index') }}" 
                               class="btn btn-lg" 
                               style="background-color: #f3f4f6; color: #6b7280; border: none; padding: 10px 24px; font-weight: 600; border-radius: 8px;">
                                <i class="bi bi-x-circle"></i> Batal
                            </a>
                            <button type="submit" 
                                    class="btn btn-lg flex-grow-1" 
                                    style="background-color: #0C7779; color: white; border: none; padding: 10px 24px; font-weight: 600; border-radius: 8px; transition: all 0.3s ease;"
                                    onmouseover="this.style.backgroundColor='#094d4f'"
                                    onmouseout="this.style.backgroundColor='#0C7779'">
                                <i class="bi bi-check-circle"></i> Simpan User
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
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
        const role = form.querySelector('[name="role"]')?.value || '';
        
        Swal.fire({
            title: '<strong>Konfirmasi Buat User</strong>',
            html: `
                <div style="text-align: left; padding: 10px;">
                    <p style="margin-bottom: 15px;">User baru akan ditambahkan ke sistem dengan data berikut:</p>
                    
                    <div style="background: #f0f9ff; padding: 12px; border-radius: 8px; border-left: 4px solid #0C7779; margin-bottom: 15px;">
                        <strong style="color: #0C7779;">Data User:</strong>
                        <ul style="margin: 8px 0 0 0; padding-left: 20px; color: #1e40af;">
                            <li><strong>Nama:</strong> ${name}</li>
                            <li><strong>Email:</strong> ${email}</li>
                            <li><strong>Role:</strong> ${role.replace(/_/g, ' ')}</li>
                        </ul>
                    </div>
                    
                    <p style="margin: 0; font-size: 14px; color: #6b7280;">
                        <i class="bi bi-info-circle"></i> User dapat langsung login setelah dibuat.
                    </p>
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="bi bi-check-circle"></i> Buat User',
            cancelButtonText: '<i class="bi bi-x-circle"></i> Batal',
            confirmButtonColor: '#0C7779',
            cancelButtonColor: '#6b7280',
            reverseButtons: true,
            width: '550px'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });
</script>
@endsection
=======
<div class="row mb-4">
    <div class="col-12">
        <h1 class="h3 text-gray-800">Tambah User</h1>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-body">
        <form method="POST" action="{{ route('users.store') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">Nama</label>
                <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                @error('name')
                    <div class="text-danger small">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                @error('email')
                    <div class="text-danger small">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label class="form-label">Role</label>
                <select name="role" class="form-select" required>
                    <option value="" disabled selected>Pilih Role</option>
                    @foreach($roles as $role)
                        <option value="{{ $role }}" {{ old('role') === $role ? 'selected' : '' }}>
                            {{ ucfirst(str_replace('_', ' ', $role)) }}
                        </option>
                    @endforeach
                </select>
                @error('role')
                    <div class="text-danger small">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
                @error('password')
                    <div class="text-danger small">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label class="form-label">Konfirmasi Password</label>
                <input type="password" name="password_confirmation" class="form-control" required>
            </div>

            <div class="d-flex gap-2">
                <a href="{{ route('users.index') }}" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>
@endsection
>>>>>>> Stashed changes
