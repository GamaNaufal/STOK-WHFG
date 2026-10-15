@extends('shared.layouts.app')
@section('title', 'Hak Akses — Admin Utama')
@section('content')
<div class="d-flex align-items-center gap-3 mb-4">
    <i class="bi bi-shield-lock fs-2 text-primary"></i>
    <div>
        <h1 class="h3 mb-1">Hak Akses</h1>
        <p class="text-muted mb-0">Atur fitur yang dapat digunakan oleh setiap role.</p>
    </div>
</div>
<div class="alert alert-info">
    Admin Utama selalu memiliki akses penuh. Kelola User, Hak Akses, dan Lock Management hanya tersedia untuk Admin Utama.
    Dashboard dan profil tetap tersedia untuk seluruh pengguna aktif.
</div>
<form method="GET" action="{{ route('access.index') }}" class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex flex-wrap align-items-end gap-3">
        <div>
            <label for="selectedRole" class="form-label fw-semibold">Pilih role</label>
            <select id="selectedRole" name="role" class="form-select">
                @foreach($roles as $key => $label)
                    <option value="{{ $key }}" @selected($selectedRole === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn btn-outline-primary" type="submit">Tampilkan Akses</button>
    </div>
</form>
@if($errors->any())
    <div class="alert alert-danger" role="alert">
        @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
    </div>
@endif
<form id="featureAccessForm" method="POST" action="{{ route('access.update') }}">
    @php($checkedPermissions = is_array(old('permissions', $selected)) ? old('permissions', $selected) : [])
    @csrf
    @method('PUT')
    <input type="hidden" name="role" value="{{ $selectedRole }}">
    {{-- Explicitly submit an empty selection when every checkbox is off. --}}
    <input type="hidden" name="permissions" value="">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h2 class="h5 mb-1">Akses {{ $roles[$selectedRole] }}</h2>
            <span class="text-muted small">Perubahan berlaku untuk semua akun dengan role ini, termasuk akun yang sedang login.</span>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="selectAllFeatures">Pilih Semua</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="clearAllFeatures">Kosongkan</button>
        </div>
    </div>
    @foreach(collect($features)->groupBy('group', preserveKeys: true) as $group => $items)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white py-3 fw-semibold">{{ $group }}</div>
            <div class="card-body row g-3">
                @foreach($items as $key => $feature)
                    <div class="col-md-6 col-xl-4">
                        <div class="form-check">
                            <input class="form-check-input feature-checkbox" type="checkbox" id="feature-{{ $key }}"
                                   name="permissions[]" value="{{ $key }}"
                                   data-requires='@json($feature["requires"] ?? [])'
                                   @checked(in_array($key, $checkedPermissions, true))
                                   @disabled($feature['admin_only'] ?? false)>
                            <label class="form-check-label" for="feature-{{ $key }}">{{ $feature['label'] }}</label>
                            @if($feature['admin_only'] ?? false)
                                <div class="small text-muted"><i class="bi bi-lock"></i> Khusus Admin Utama</div>
                            @elseif(!empty($feature['requires']))
                                <div class="small text-muted">Memerlukan: {{ collect($feature['requires'])->map(fn($dependency) => $features[$dependency]['label'])->join(', ') }}</div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
    <div class="d-flex align-items-center gap-3 mb-4">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i> Simpan Hak Akses</button>
        <span id="accessChangeStatus" class="small text-muted" role="status">Belum ada perubahan.</span>
    </div>
</form>
@endsection
@section('scripts')
<script src="{{ asset('js/pages/feature-access.js') }}"></script>
@endsection
