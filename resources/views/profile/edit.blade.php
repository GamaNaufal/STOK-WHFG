@extends('shared.layouts.app')

@section('title', 'Profil Saya')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/profile.css') }}">
@endsection

@section('content')
<div class="profile-page">
    <header class="profile-header">
        <span class="profile-eyebrow">Pengaturan akun</span>
        <h1>Profil saya</h1>
        <p>Perbarui identitas akun dan, bila perlu, ganti kata sandi Anda.</p>
    </header>

    <div class="profile-layout">
        <div class="profile-panel" aria-labelledby="profile-form-title">
            <div class="profile-panel-heading">
                <span class="profile-panel-icon" aria-hidden="true"><i class="bi bi-person-gear"></i></span>
                <div>
                    <h2 id="profile-form-title">Pengaturan profil</h2>
                    <p>Perubahan disimpan untuk akun yang sedang Anda gunakan.</p>
                </div>
            </div>

            @if ($errors->any() || session('success'))
                <div class="profile-feedback">
                    @if ($errors->any())
                        <x-alert type="danger" icon="bi-exclamation-triangle">
                            <strong>Periksa kembali isian yang ditandai.</strong>
                            <ul class="mb-0 mt-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </x-alert>
                    @endif
                    @if (session('success'))
                        <x-alert type="success" icon="bi-check-circle">
                            {{ session('success') }}
                        </x-alert>
                    @endif
                </div>
            @endif

            <form method="POST" action="{{ route('profile.update') }}">
                @csrf
                @method('PUT')

                <section class="profile-section" aria-labelledby="profile-identity-title">
                    <div class="profile-section-heading">
                        <span class="profile-section-number" aria-hidden="true">01</span>
                        <div>
                            <h3 id="profile-identity-title">Informasi akun</h3>
                            <p>Nama dan email yang digunakan untuk mengenali akun Anda.</p>
                        </div>
                    </div>
                    <div class="profile-field-grid">
                        <div class="profile-field">
                            <label for="profile-name" class="form-label">Nama lengkap</label>
                            <input type="text" name="name" id="profile-name" autocomplete="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}" @error('name') aria-invalid="true" aria-describedby="profile-name-error" @enderror required>
                            @error('name')<div id="profile-name-error" class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="profile-field">
                            <label for="profile-email" class="form-label">Alamat email</label>
                            <input type="email" name="email" id="profile-email" autocomplete="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}" @error('email') aria-invalid="true" aria-describedby="profile-email-error" @enderror required>
                            @error('email')<div id="profile-email-error" class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </section>

                <section class="profile-section profile-section-security" aria-labelledby="profile-password-title">
                    <div class="profile-section-heading">
                        <span class="profile-section-number" aria-hidden="true">02</span>
                        <div>
                            <h3 id="profile-password-title">Ganti kata sandi <span class="profile-optional">Opsional</span></h3>
                            <p>Biarkan ketiga kolom kosong jika tidak ingin mengganti kata sandi.</p>
                        </div>
                    </div>
                    <div class="profile-field profile-field-current">
                        <label for="profile-current_password" class="form-label">Kata sandi saat ini</label>
                        <input type="password" name="current_password" id="profile-current_password" autocomplete="current-password" class="form-control @error('current_password') is-invalid @enderror" @error('current_password') aria-invalid="true" aria-describedby="profile-current-password-error" @enderror>
                        @error('current_password')<div id="profile-current-password-error" class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="profile-field-grid">
                        <div class="profile-field">
                            <label for="profile-password" class="form-label">Kata sandi baru</label>
                            <input type="password" name="password" id="profile-password" autocomplete="new-password" class="form-control @error('password') is-invalid @enderror" aria-describedby="profile-password-help @error('password') profile-password-error @enderror" @error('password') aria-invalid="true" @enderror>
                            <p id="profile-password-help" class="profile-field-help">Minimal 8 karakter.</p>
                            @error('password')<div id="profile-password-error" class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="profile-field">
                            <label for="profile-password_confirmation" class="form-label">Konfirmasi kata sandi baru</label>
                            <input type="password" name="password_confirmation" id="profile-password_confirmation" autocomplete="new-password" class="form-control @error('password_confirmation') is-invalid @enderror" @error('password_confirmation') aria-invalid="true" aria-describedby="profile-password-confirmation-error" @enderror>
                            @error('password_confirmation')<div id="profile-password-confirmation-error" class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </section>

                <div class="profile-actions">
                    <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">Batal</a>
                    <button type="submit" class="btn btn-primary">Simpan perubahan</button>
                </div>
            </form>
        </div>

        <aside class="profile-aside" aria-label="Panduan perubahan profil">
            <div class="profile-aside-label"><i class="bi bi-shield-check" aria-hidden="true"></i> Keamanan akun</div>
            <h2>Ubah hanya yang diperlukan</h2>
            <p>Nama dan email dapat diperbarui tanpa mengganti kata sandi.</p>
            <div class="profile-aside-divider"></div>
            <p>Untuk mengganti kata sandi, masukkan kata sandi saat ini, lalu isi dan konfirmasi kata sandi baru.</p>
        </aside>
    </div>
</div>
@endsection
