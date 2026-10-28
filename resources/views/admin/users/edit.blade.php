@extends('shared.layouts.app')

@section('title', 'Edit User')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/user-management.css') }}">
@endsection

@section('content')
@php
    $roleNames = [
        'admin' => 'Administrator',
        'admin_warehouse' => 'Admin Warehouse',
        'warehouse_operator' => 'Operator Warehouse',
        'export_operator' => 'Operator Export',
        'sales' => 'Sales',
        'ppc' => 'PPC',
        'supervisi' => 'Supervisi',
    ];
    $selectedStatus = (string) old('is_active', $user->is_active ? '1' : '0');
@endphp
<div class="user-page user-form-page">
    <a href="{{ route('users.index') }}" class="user-back-link">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> Daftar user
    </a>
    <header class="user-page-header">
        <div>
            <span class="user-eyebrow">Administrasi / Perbarui akun</span>
            <h1>Edit user</h1>
            <p>Perbarui identitas dan akses akun {{ $user->name }}. Kata sandi hanya berubah bila diisi.</p>
        </div>
    </header>

    <div class="user-form-layout">
        <section class="card border-0 shadow-sm user-form-card" aria-labelledby="user-edit-title">
            <div class="user-form-card-heading">
                <span class="user-form-icon"><i class="bi bi-person-gear" aria-hidden="true"></i></span>
                <div>
                    <h2 id="user-edit-title">Informasi akun</h2>
                    <p>Periksa kembali perubahan peran dan status sebelum menyimpan.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('users.update', $user) }}" id="editUserForm" data-user-form="edit"
                data-original-role="{{ $user->role }}" data-original-status="{{ $user->is_active ? '1' : '0' }}">
                @csrf
                @method('PUT')
                <div class="user-form-body">
                    @if($errors->any())
                        <div class="user-form-error-summary" role="alert">
                            <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                            <span>Periksa kembali kolom yang ditandai sebelum menyimpan.</span>
                        </div>
                    @endif

                    <div class="user-form-section-heading">
                        <span>01</span>
                        <div><h3>Identitas</h3><p>Data yang tampil di direktori dan riwayat aktivitas.</p></div>
                    </div>
                    <div class="user-form-grid">
                        <div class="user-form-field">
                            <label for="user-name" class="form-label">Nama lengkap</label>
                            <input type="text" id="user-name" name="name" class="form-control @error('name') is-invalid @enderror"
                                value="{{ old('name', $user->name) }}" maxlength="255" autocomplete="name" required
                                aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}"
                                aria-describedby="user-name-help @error('name') user-name-error @enderror">
                            <p id="user-name-help" class="user-form-help">Tampil pada daftar akun dan aktivitas berikutnya.</p>
                            @error('name')<div id="user-name-error" class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                        </div>
                        <div class="user-form-field">
                            <label for="user-email" class="form-label">Alamat email</label>
                            <input type="email" id="user-email" name="email" class="form-control @error('email') is-invalid @enderror"
                                value="{{ old('email', $user->email) }}" maxlength="255" autocomplete="email" required
                                aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                                aria-describedby="user-email-help @error('email') user-email-error @enderror">
                            <p id="user-email-help" class="user-form-help">Digunakan untuk masuk; email harus unik.</p>
                            @error('email')<div id="user-email-error" class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="user-form-section-heading">
                        <span>02</span>
                        <div><h3>Akses akun</h3><p>Perubahan peran dan status memengaruhi akses pengguna.</p></div>
                    </div>
                    <div class="user-form-grid">
                        <div class="user-form-field">
                            <label for="user-role" class="form-label">Peran pengguna</label>
                            <select id="user-role" name="role" class="form-select @error('role') is-invalid @enderror" required
                                aria-invalid="{{ $errors->has('role') ? 'true' : 'false' }}"
                                aria-describedby="user-role-help @error('role') user-role-error @enderror">
                                @foreach($roles as $role)
                                    <option value="{{ $role }}" @selected(old('role', $user->role) === $role)>{{ $roleNames[$role] ?? ucfirst(str_replace('_', ' ', $role)) }}</option>
                                @endforeach
                            </select>
                            <p id="user-role-help" class="user-form-help">Akses fitur mengikuti pengaturan Hak Akses untuk peran ini.</p>
                            @error('role')<div id="user-role-error" class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                        </div>
                        <div class="user-form-field">
                            <label for="user-status" class="form-label">Status akun</label>
                            <select id="user-status" name="is_active" class="form-select @error('is_active') is-invalid @enderror" required
                                aria-invalid="{{ $errors->has('is_active') ? 'true' : 'false' }}"
                                aria-describedby="user-status-help @error('is_active') user-status-error @enderror">
                                <option value="1" @selected($selectedStatus === '1')>Aktif</option>
                                <option value="0" @selected($selectedStatus === '0')>Nonaktif</option>
                            </select>
                            <p id="user-status-help" class="user-form-help">Nonaktif membatasi login; riwayat aktivitas tetap disimpan.</p>
                            @error('is_active')<div id="user-status-error" class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="user-form-section-heading">
                        <span>03</span>
                        <div><h3>Kata sandi</h3><p>Lewati bagian ini bila kata sandi tetap sama.</p></div>
                    </div>
                    <div class="user-form-grid">
                        <div class="user-form-field">
                            <label for="user-password" class="form-label">Kata sandi baru <span class="user-optional">Opsional</span></label>
                            <div class="user-password-input">
                                <input type="password" id="user-password" name="password"
                                    class="form-control @error('password') is-invalid @enderror" minlength="6" autocomplete="new-password"
                                    aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                                    aria-describedby="user-password-help @error('password') user-password-error @enderror">
                                <button type="button" class="user-password-toggle" data-password-target="user-password" aria-controls="user-password" aria-pressed="false">Tampilkan</button>
                            </div>
                            <p id="user-password-help" class="user-form-help">Kosongkan jika tidak diubah; minimal 6 karakter jika diisi.</p>
                            @error('password')<div id="user-password-error" class="invalid-feedback d-block" role="alert">{{ $message }}</div>@enderror
                        </div>
                        <div class="user-form-field">
                            <label for="user-password-confirmation" class="form-label">Konfirmasi kata sandi baru</label>
                            <div class="user-password-input">
                                <input type="password" id="user-password-confirmation" name="password_confirmation"
                                    class="form-control" autocomplete="new-password" aria-describedby="user-password-confirmation-help user-password-match-error">
                                <button type="button" class="user-password-toggle" data-password-target="user-password-confirmation" aria-controls="user-password-confirmation" aria-pressed="false">Tampilkan</button>
                            </div>
                            <p id="user-password-confirmation-help" class="user-form-help">Wajib diisi jika membuat kata sandi baru.</p>
                            <p id="user-password-match-error" class="user-form-match-error" role="alert" hidden></p>
                        </div>
                    </div>
                </div>
                <div class="user-form-actions">
                    <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Batal</a>
                    <button type="submit" class="btn btn-primary user-form-submit"><i class="bi bi-check-lg" aria-hidden="true"></i> Simpan perubahan</button>
                </div>
            </form>
        </section>

        <aside class="user-form-aside" aria-labelledby="user-edit-context-title">
            <span class="user-eyebrow">Akun saat ini</span>
            <h2 id="user-edit-context-title">{{ $user->name }}</h2>
            <p class="user-form-aside-email">{{ $user->email }}</p>
            <span class="user-status {{ $user->is_active ? 'is-active' : 'is-inactive' }}">
                <span class="user-status-dot" aria-hidden="true"></span>
                {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
            </span>
            <div class="user-form-aside-note"><i class="bi bi-info-circle" aria-hidden="true"></i><span>Status dan peran baru berlaku setelah perubahan disimpan. Riwayat akun tetap tersimpan.</span></div>
        </aside>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('js/pages/user-management-forms.js') }}"></script>
@endsection
