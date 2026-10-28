@extends('shared.layouts.app')

@section('title', 'User Management')

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
    $filtersApplied = $search !== '' || $role !== '' || $status !== '';
@endphp
<div class="user-page">
    <header class="user-page-header">
        <div>
            <span class="user-eyebrow">Administrasi / Akun</span>
            <h1>User Management</h1>
            <p>Kelola akun, peran, dan akses masuk pengguna. Aktivitas akun tetap tersimpan saat akun dinonaktifkan.</p>
        </div>
        <a href="{{ route('users.create') }}" class="btn btn-primary user-primary-action">
            <i class="bi bi-person-plus" aria-hidden="true"></i> Tambah user
        </a>
    </header>

    <div class="user-overview" aria-label="Ringkasan akun">
        <div class="user-overview-item">
            <span>Total akun</span>
            <strong>{{ number_format($totalUsers, 0, ',', '.') }}</strong>
        </div>
        <div class="user-overview-item">
            <span>Aktif</span>
            <strong>{{ number_format($activeUsers, 0, ',', '.') }}</strong>
        </div>
        <div class="user-overview-item">
            <span>Nonaktif</span>
            <strong>{{ number_format($totalUsers - $activeUsers, 0, ',', '.') }}</strong>
        </div>
    </div>

    <section class="user-filter-panel" aria-labelledby="user-filter-title">
        <div class="user-filter-intro">
            <h2 id="user-filter-title">Temukan akun</h2>
            <p>Pencarian nama atau email mencakup seluruh akun, termasuk yang nonaktif.</p>
        </div>
        <form method="GET" action="{{ route('users.index') }}" class="user-filter-form">
            <div class="user-filter-field user-filter-search">
                <label for="user-search">Nama atau email</label>
                <input type="search" id="user-search" name="q" class="form-control" value="{{ $search }}"
                    maxlength="100" placeholder="Cari pengguna" autocomplete="off">
            </div>
            <div class="user-filter-field">
                <label for="user-role">Peran</label>
                <select id="user-role" name="role" class="form-select">
                    <option value="">Semua peran</option>
                    @foreach($roleNames as $value => $label)
                        <option value="{{ $value }}" @selected($role === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="user-filter-field">
                <label for="user-status">Status</label>
                <select id="user-status" name="status" class="form-select">
                    <option value="">Semua status</option>
                    <option value="active" @selected($status === 'active')>Aktif</option>
                    <option value="inactive" @selected($status === 'inactive')>Nonaktif</option>
                </select>
            </div>
            <div class="user-filter-actions">
                <button type="submit" class="btn btn-primary"><i class="bi bi-search" aria-hidden="true"></i> Tampilkan</button>
                @if($filtersApplied)
                    <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Reset</a>
                @endif
            </div>
        </form>
    </section>

    <section class="card border-0 shadow-sm user-results" aria-labelledby="user-results-title">
        <div class="user-results-heading">
            <div>
                <span class="user-eyebrow">Direktori akun</span>
                <h2 id="user-results-title">Daftar user</h2>
                <p>{{ $filtersApplied ? 'Hasil sesuai pencarian dan filter yang diterapkan.' : 'Urutan akun terbaru, termasuk yang nonaktif.' }}</p>
            </div>
            <span class="user-count">{{ number_format($users->total(), 0, ',', '.') }} akun</span>
        </div>
        <div class="table-responsive user-table-scroll" tabindex="0" role="region" aria-label="Daftar user, geser untuk melihat semua kolom">
            <table class="table table-hover align-middle user-table">
                <thead>
                    <tr>
                        <th scope="col">Pengguna</th>
                        <th scope="col">Peran</th>
                        <th scope="col">Status akun</th>
                        <th scope="col">Dibuat</th>
                        <th scope="col">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr>
                            <th scope="row" class="user-identity-cell">
                                <span class="user-name">{{ $user->name }}</span>
                                <span class="user-email">{{ $user->email }}</span>
                                @if(auth()->id() === $user->id)
                                    <span class="user-self-label">Akun Anda</span>
                                @endif
                            </th>
                            <td><span class="user-role">{{ $roleNames[$user->role] ?? ucfirst(str_replace('_', ' ', $user->role)) }}</span></td>
                            <td>
                                <span class="user-status {{ $user->is_active ? 'is-active' : 'is-inactive' }}">
                                    <span class="user-status-dot" aria-hidden="true"></span>
                                    {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="user-date">
                                <time datetime="{{ $user->created_at->toDateString() }}">{{ $user->created_at->format('d M Y') }}</time>
                            </td>
                            <td>
                                <div class="user-row-actions">
                                    <a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-outline-primary"
                                        aria-label="Edit akun {{ $user->name }}">
                                        <i class="bi bi-pencil" aria-hidden="true"></i> Edit
                                    </a>
                                    @if($user->is_active && auth()->id() !== $user->id)
                                        <button type="button" class="btn btn-sm btn-outline-danger js-deactivate-user"
                                            data-deactivate-url="{{ route('users.destroy', $user) }}"
                                            data-user-name="{{ $user->name }}"
                                            aria-label="Nonaktifkan akun {{ $user->name }}">
                                            <i class="bi bi-person-x" aria-hidden="true"></i> Nonaktifkan
                                        </button>
                                    @elseif(! $user->is_active)
                                        <span class="user-action-note">Aktifkan lewat Edit</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="user-table-empty">
                                <i class="bi {{ $filtersApplied ? 'bi-search' : 'bi-people' }}" aria-hidden="true"></i>
                                @if($users->total() > 0)
                                    <strong>Halaman ini kosong</strong>
                                    <span>Akun tersedia di halaman sebelumnya.</span>
                                    <a href="{{ route('users.index', array_filter(['q' => $search, 'role' => $role, 'status' => $status])) }}">Kembali ke halaman pertama</a>
                                @elseif($filtersApplied)
                                    <strong>Tidak ada akun yang cocok</strong>
                                    <span>Coba nama, email, peran, atau status lain.</span>
                                    <a href="{{ route('users.index') }}">Reset pencarian dan filter</a>
                                @else
                                    <strong>Belum ada user</strong>
                                    <span>Tambahkan akun pertama untuk mulai mengelola akses.</span>
                                    <a href="{{ route('users.create') }}">Tambah user</a>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($users->count() > 0)
            <div class="user-results-footer">
                <span>Menampilkan {{ $users->firstItem() }}–{{ $users->lastItem() }} dari {{ $users->total() }} akun</span>
                @if($users->hasPages())
                    {{ $users->onEachSide(1)->links('pagination::bootstrap-5') }}
                @endif
            </div>
        @endif
    </section>
</div>
@endsection

@section('scripts')
<script>
    document.querySelectorAll('.js-deactivate-user').forEach((button) => {
        button.addEventListener('click', () => {
            const name = document.createElement('span');
            name.textContent = button.dataset.userName || 'pengguna ini';

            WarehouseAlert.confirm({
                title: 'Nonaktifkan akun?',
                message: `Akun <strong>${name.innerHTML}</strong> tidak dapat masuk setelah dinonaktifkan.`,
                warningItems: [
                    'Sesi login akun akan dihentikan.',
                    'Riwayat aktivitas tetap tersimpan.',
                    'Akun dapat diaktifkan kembali melalui halaman Edit.'
                ],
                confirmText: 'Ya, nonaktifkan',
                onConfirm: () => {
                    button.disabled = true;
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = button.dataset.deactivateUrl;

                    const token = document.createElement('input');
                    token.type = 'hidden';
                    token.name = '_token';
                    token.value = @json(csrf_token());
                    form.appendChild(token);

                    const method = document.createElement('input');
                    method.type = 'hidden';
                    method.name = '_method';
                    method.value = 'DELETE';
                    form.appendChild(method);

                    document.body.appendChild(form);
                    form.submit();
                }
            });
        });
    });
</script>
@endsection
