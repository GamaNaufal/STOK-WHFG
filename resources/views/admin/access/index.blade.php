@extends('shared.layouts.app')

@section('title', 'Hak Akses — Admin Utama')

@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/access-management.css') }}">
@endsection

@section('content')
@php
    $checkedPermissions = is_array(old('permissions', $selected)) ? old('permissions', $selected) : [];
    $editableFeatures = collect($features)->reject(fn ($feature) => $feature['admin_only'] ?? false);
    $selectedCount = $editableFeatures->keys()->intersect($checkedPermissions)->count();
    $groupIcons = [
        'Warehouse' => 'boxes',
        'Delivery' => 'truck',
        'Master Data' => 'collection',
        'Laporan' => 'bar-chart',
        'Administrasi' => 'shield-lock',
    ];
@endphp
<div class="access-page">
    <header class="access-page-header">
        <span class="access-eyebrow">Administrasi / Kebijakan fitur</span>
        <h1>Hak Akses</h1>
        <p>Atur fitur yang tersedia untuk setiap peran. Perubahan berlaku untuk semua akun dengan peran tersebut pada permintaan berikutnya.</p>
    </header>

    <section class="access-role-panel" aria-labelledby="access-role-title">
        <div class="access-role-intro">
            <span class="access-panel-icon"><i class="bi bi-people" aria-hidden="true"></i></span>
            <div>
                <h2 id="access-role-title">Pilih peran</h2>
                <p>Periksa satu peran pada satu waktu. Berpindah peran akan meninggalkan perubahan yang belum disimpan.</p>
            </div>
        </div>
        <form method="GET" action="{{ route('access.index') }}" class="access-role-form">
            <label for="selectedRole" class="visually-hidden">Peran yang ditampilkan</label>
            <select id="selectedRole" name="role" class="form-select">
                @foreach($roles as $key => $label)
                    <option value="{{ $key }}" @selected($selectedRole === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="btn btn-outline-primary" type="submit">Tampilkan akses</button>
        </form>
    </section>

    <div class="access-policy-note" role="note">
        <i class="bi bi-shield-check" aria-hidden="true"></i>
        <p><strong>Akses tetap:</strong> Admin Utama selalu memiliki akses penuh. Kelola User, Hak Akses, dan Lock Management khusus Admin Utama. Dashboard dan profil tersedia untuk seluruh pengguna aktif.</p>
    </div>

    @if($errors->any())
        <div class="access-error-summary" role="alert">
            <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
            <div>
                <strong>Hak akses belum tersimpan</strong>
                @foreach($errors->all() as $error)<span>{{ $error }}</span>@endforeach
            </div>
        </div>
    @endif

    <form id="featureAccessForm" method="POST" action="{{ route('access.update') }}" data-role-label="{{ $roles[$selectedRole] }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="role" value="{{ $selectedRole }}">
        {{-- Explicitly submit an empty selection when every checkbox is off. --}}
        <input type="hidden" name="permissions" value="">

        <div class="access-editor-heading">
            <div>
                <span class="access-eyebrow">Peran terpilih</span>
                <h2>Akses {{ $roles[$selectedRole] }}</h2>
                <p>Pilih fitur yang boleh digunakan. Izin turunan membutuhkan izin induknya.</p>
            </div>
            <span class="access-selected-count" id="accessSelectedCount" aria-live="polite">{{ $selectedCount }} dari {{ $editableFeatures->count() }} fitur dipilih</span>
        </div>

        <div class="access-bulk-toolbar">
            <span><i class="bi bi-check2-square" aria-hidden="true"></i> Pilihan untuk {{ $roles[$selectedRole] }}</span>
            <div class="access-bulk-actions">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="selectAllFeatures">Pilih semua</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="clearAllFeatures">Kosongkan</button>
            </div>
        </div>

        @foreach(collect($features)->groupBy('group', preserveKeys: true) as $group => $items)
            @php
                $groupEditable = $items->reject(fn ($feature) => $feature['admin_only'] ?? false);
                $groupSelected = $groupEditable->keys()->intersect($checkedPermissions)->count();
            @endphp
            <section class="access-group" data-access-group aria-labelledby="access-group-{{ $loop->index }}">
                <div class="access-group-heading">
                    <div class="access-group-title">
                        <span class="access-group-icon"><i class="bi bi-{{ $groupIcons[$group] ?? 'grid' }}" aria-hidden="true"></i></span>
                        <div>
                            <h3 id="access-group-{{ $loop->index }}">{{ $group }}</h3>
                            <p>{{ $groupEditable->isEmpty() ? 'Fitur khusus Admin Utama.' : 'Pilih izin sesuai pekerjaan peran ini.' }}</p>
                        </div>
                    </div>
                    <span class="access-group-count" data-group-count data-group-total="{{ $groupEditable->count() }}">{{ $groupEditable->isEmpty() ? 'Khusus admin' : $groupSelected.' dari '.$groupEditable->count().' dipilih' }}</span>
                </div>
                <div class="access-feature-grid">
                    @foreach($items as $key => $feature)
                        @php($locked = (bool) ($feature['admin_only'] ?? false))
                        <label class="access-feature {{ $locked ? 'is-locked' : '' }}" for="feature-{{ $key }}">
                            <input class="form-check-input feature-checkbox" type="checkbox" id="feature-{{ $key }}"
                                name="permissions[]" value="{{ $key }}"
                                data-requires='@json($feature["requires"] ?? [])'
                                @checked(in_array($key, $checkedPermissions, true))
                                @disabled($locked)>
                            <span class="access-feature-body">
                                <span class="access-feature-label">{{ $feature['label'] }}</span>
                                @if($locked)
                                    <span class="access-feature-note"><i class="bi bi-lock" aria-hidden="true"></i> Khusus Admin Utama</span>
                                @elseif(!empty($feature['requires']))
                                    <span class="access-feature-note">Memerlukan: {{ collect($feature['requires'])->map(fn ($dependency) => $features[$dependency]['label'])->join(', ') }}</span>
                                @endif
                            </span>
                        </label>
                    @endforeach
                </div>
            </section>
        @endforeach

        <div class="access-save-bar">
            <div>
                <strong id="accessChangeStatus" role="status" aria-live="polite">Belum ada perubahan.</strong>
                <span>Daftar yang dipilih akan menggantikan pengaturan tersimpan untuk peran ini.</span>
            </div>
            <button type="submit" class="btn btn-primary" id="saveFeatureAccess">
                <i class="bi bi-check2-circle" aria-hidden="true"></i> Simpan Hak Akses
            </button>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script src="{{ asset('js/pages/feature-access.js') }}"></script>
@endsection
