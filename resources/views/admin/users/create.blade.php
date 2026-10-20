@extends('shared.layouts.app')

@section('title', 'Tambah User')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/user-management.css') }}">
@endsection

@section('content')
@php
    $roleNames = [
        'admin' => 'Administrator',
        'admin_warehouse' => 'Admin Warehouse',
        'warehouse_operator' => 'Operator Warehouse',
        'sales' => 'Sales',
        'ppc' => 'PPC',
        'supervisi' => 'Supervisi',
    ];
@endphp
<div class="user-page user-form-page">
    <a href="{{ route('users.index') }}" class="user-back-link">
        <i class="bi bi-arrow-left" aria-hidden="true"></i> Daftar user
    </a>
    <header class="user-page-header">
        <div>
            <span class="user-eyebrow">Administrasi / Akun baru</span>
            <h1>Tambah user</h1>
            <p>Siapkan identitas, peran, dan kata sandi awal untuk akun yang akan langsung aktif.</p>
        </div>
    </header>

    <div class="user-form-layout">
        <section class="card border-0 shadow-sm user-form-card" aria-labelledby="user-create-title">
            <div class="user-form-card-heading">
                <span class="user-form-icon"><i class="bi bi-person-plus" aria-hidden="true"></i></span>
                <div>
                    <h2 id="user-create-title">Informasi akun baru</h2>
                    <p>Semua kolom wajib diisi sebelum akun dibuat.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('users.store') }}" id="createUserForm" data-user-form="create">
                @csrf
                <div class="user-form-body">
                    @if($errors->any())
                        <div class="user-form-error-summary" role="alert">
                            <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                            <span>Periksa kembali kolom yang ditandai sebelum menyimpan.</span>
                        </div>
                    @endif

                    <div class="user-form-section-heading">
                        <span>01</span>
                        <div><h3>Identitas</h3><p>Nama dan email yang dipakai untuk mengenali akun.</p></div>
                    </div>
                    <div class="user-form-grid">
                        <div class="user-form-field">
                            <label for="user-name" class="form-label">Nama lengkap</label>
                            <input type="text" id="user-name" name="name" class="form-control @error('name') is-invalid @enderror"
                                value="{{ old('name') }}" maxlength="255" autocomplete="name" required
                                placeholder="Nama pengguna" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}"
                                aria-describedby="user-name-help @error('name') user-name-error @enderror">
                            <p id="user-name-help" class="user-form-help">Tampil pada daftar akun dan aktivitas pengguna.</p>
                            @error('name')<div id="user-name-error" class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                        </div>
                        <div class="user-form-field">
                            <label for="user-email" class="form-label">Alamat email</label>
                            <input type="email" id="user-email" name="email" class="form-control @error('email') is-invalid @enderror"
                                value="{{ old('email') }}" maxlength="255" autocomplete="email" required
                                placeholder="nama@perusahaan.com" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                                aria-describedby="user-email-help @error('email') user-email-error @enderror">
                            <p id="user-email-help" class="user-form-help">Digunakan untuk masuk; email harus unik.</p>
                            @error('email')<div id="user-email-error" class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="user-form-section-heading">
                        <span>02</span>
                        <div><h3>Akses</h3><p>Pilih peran awal sesuai tanggung jawab pengguna.</p></div>
                    </div>
                    <div class="user-form-field">
                        <label for="user-role" class="form-label">Peran pengguna</label>
                        <select id="user-role" name="role" class="form-select @error('role') is-invalid @enderror" required
                            aria-invalid="{{ $errors->has('role') ? 'true' : 'false' }}"
                            aria-describedby="user-role-help @error('role') user-role-error @enderror">
                            <option value="" disabled @selected(!old('role'))>Pilih peran</option>
                            @foreach($roles as $role)
                                <option value="{{ $role }}" @selected(old('role') === $role)>{{ $roleNames[$role] ?? ucfirst(str_replace('_', ' ', $role)) }}</option>
                            @endforeach
                        </select>
                        <p id="user-role-help" class="user-form-help">Akses fitur mengikuti pengaturan Hak Akses untuk peran ini.</p>
                        @error('role')<div id="user-role-error" class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                    </div>

                    <div class="user-form-section-heading">
                        <span>03</span>
                        <div><h3>Keamanan</h3><p>Buat kata sandi awal dan ketik ulang untuk konfirmasi.</p></div>
                    </div>
                    <div class="user-form-grid">
                        <div class="user-form-field">
                            <label for="user-password" class="form-label">Kata sandi</label>
                            <div class="user-password-input">
                                <input type="password" id="user-password" name="password"
                                    class="form-control @error('password') is-invalid @enderror" minlength="6" autocomplete="new-password" required
                                    aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                                    aria-describedby="user-password-help @error('password') user-password-error @enderror">
                                <button type="button" class="user-password-toggle" data-password-target="user-password" aria-controls="user-password" aria-pressed="false">Tampilkan</button>
                            </div>
                            <p id="user-password-help" class="user-form-help">Minimal 6 karakter sesuai aturan sistem.</p>
                            @error('password')<div id="user-password-error" class="invalid-feedback d-block" role="alert">{{ $message }}</div>@enderror
                        </div>
                        <div class="user-form-field">
                            <label for="user-password-confirmation" class="form-label">Konfirmasi kata sandi</label>
                            <div class="user-password-input">
                                <input type="password" id="user-password-confirmation" name="password_confirmation"
                                    class="form-control" autocomplete="new-password" required
                                    aria-describedby="user-password-confirmation-help user-password-match-error">
                                <button type="button" class="user-password-toggle" data-password-target="user-password-confirmation" aria-controls="user-password-confirmation" aria-pressed="false">Tampilkan</button>
                            </div>
                            <p id="user-password-confirmation-help" class="user-form-help">Harus sama dengan kata sandi di sebelahnya.</p>
                            <p id="user-password-match-error" class="user-form-match-error" role="alert" hidden></p>
                        </div>
                    </div>
                </div>
                <div class="user-form-actions">
                    <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Batal</a>
                    <button type="submit" class="btn btn-primary user-form-submit"><i class="bi bi-check-lg" aria-hidden="true"></i> Buat akun</button>
                </div>
            </form>
        </section>

        <aside class="user-form-aside" aria-labelledby="user-create-context-title">
            <span class="user-eyebrow">Setelah disimpan</span>
            <h2 id="user-create-context-title">Akun langsung aktif</h2>
            <p>Pengguna dapat masuk dengan email dan kata sandi yang dibuat. Fitur yang tersedia mengikuti perannya.</p>
            <div class="user-form-aside-note"><i class="bi bi-shield-check" aria-hidden="true"></i><span>Perubahan akun tercatat dalam Audit Trail.</span></div>
        </aside>
    </div>
</div>
@endsection

@section('scripts')
<script src="{{ asset('js/pages/user-management-forms.js') }}"></script>
@endsection
