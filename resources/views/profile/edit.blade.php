@extends('shared.layouts.app')

@section('title', 'Edit Profil')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <!-- Page Header Component -->
            <x-page-header 
                title="Edit Profil" 
                icon="bi-person-gear"
                subtitle="Perbarui informasi pribadi dan kata sandi Anda"
            />
            
            <x-card title="Informasi Profil">
                @if ($errors->any())
                    <x-alert type="danger" icon="bi-exclamation-triangle">
                        <ul class="mb-0">
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

                    <form method="POST" action="{{ route('profile.update') }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="profile-name" class="form-label fw-semibold">Nama Lengkap</label>
                            <input type="text" name="name" id="profile-name" autocomplete="name" class="form-control" value="{{ old('name', $user->name) }}" required>
                        </div>

                        <div class="mb-3">
                            <label for="profile-email" class="form-label fw-semibold">Alamat Email</label>
                            <input type="email" name="email" id="profile-email" autocomplete="email" class="form-control" value="{{ old('email', $user->email) }}" required>
                        </div>

                        <hr class="my-4">
                        <h6 class="fw-semibold mb-3 text-muted">Ganti Kata Sandi</h6>

                        <div class="mb-3">
                            <label for="profile-current_password" class="form-label fw-semibold">Kata Sandi Saat Ini</label>
                            <input type="password" name="current_password" id="profile-current_password" autocomplete="current-password" class="form-control" placeholder="Wajib diisi jika ingin mengganti kata sandi">
                        </div>

                        <div class="mb-3">
                            <label for="profile-password" class="form-label fw-semibold">Kata Sandi Baru</label>
                            <input type="password" name="password" id="profile-password" autocomplete="new-password" class="form-control" placeholder="Kosongkan jika tidak ingin mengganti">
                        </div>

                        <div class="mb-3">
                            <label for="profile-password_confirmation" class="form-label fw-semibold">Konfirmasi Kata Sandi Baru</label>
                            <input type="password" name="password_confirmation" id="profile-password_confirmation" autocomplete="new-password" class="form-control" placeholder="Ulangi kata sandi baru">
                        </div>

                        <div class="d-flex gap-2 justify-content-end">
                            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">Batal</a>
                            <x-button type="submit" variant="primary">Simpan Perubahan</x-button>
                        </div>

                    </form>
                </x-card>
        </div>
    </div>
</div>
@endsection
