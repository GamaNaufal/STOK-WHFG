@extends('shared.layouts.app')

@section('content')
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
                            <i class="bi bi-people-fill"></i> Users Management
                        </h1>
                        <p style="margin: 0; opacity: 0.95; font-size: 15px;">
                            Kelola data pengguna dan hak akses sistem
                        </p>
                    </div>
                    <a href="{{ route('users.create') }}" 
                       class="btn" 
                       style="background-color: white; color: #0C7779; border: none; padding: 10px 24px; font-weight: 600; border-radius: 8px; transition: all 0.3s ease;" 
                       onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.15)'" 
                       onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                        <i class="bi bi-plus-circle"></i> Tambah User
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Users Table Card -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0" style="border-left: 4px solid #0C7779; border-radius: 12px; overflow: hidden;">
                <div class="card-header text-white" style="background-color: #0C7779; border: none; padding: 15px 20px;">
                    <h6 class="m-0 fw-bold"><i class="bi bi-list-ul"></i> Daftar Semua User</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 15px;">
                            <thead style="background-color: #f8f9fa; border-bottom: 2px solid #e9ecef;">
                                <tr>
                                    <th class="ps-3" style="padding: 15px;">Name</th>
                                    <th style="padding: 15px;">Email</th>
                                    <th style="padding: 15px;">Role</th>
                                    <th style="padding: 15px;">Created At</th>
                                    <th class="text-center" style="padding: 15px; width: 180px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($users as $user)
                                <tr style="border-bottom: 1px solid #f3f4f6;">
                                    <td class="ps-3 fw-bold" style="padding: 15px; color: #1f2937;">{{ $user->name }}</td>
                                    <td style="padding: 15px; color: #6b7280;">
                                        <i class="bi bi-envelope"></i> {{ $user->email }}
                                    </td>
                                    <td style="padding: 15px;">
                                        <span class="badge" style="background-color: #0C7779; color: white; padding: 6px 12px; border-radius: 6px; font-weight: 500;">
                                            {{ ucfirst(str_replace('_', ' ', $user->role)) }}
                                        </span>
                                    </td>
                                    <td style="padding: 15px; color: #6b7280;">
                                        <i class="bi bi-calendar"></i> {{ $user->created_at->format('d M Y') }}
                                    </td>
                                    <td class="text-center" style="padding: 15px;">
                                        <a href="{{ route('users.edit', $user) }}" 
                                           class="btn btn-sm" 
                                           style="background-color: #f0f9ff; color: #0C7779; border: 1px solid #bae6fd; padding: 6px 12px; border-radius: 6px; margin-right: 5px; transition: all 0.2s;"
                                           onmouseover="this.style.backgroundColor='#e0f2fe'"
                                           onmouseout="this.style.backgroundColor='#f0f9ff'">
                                            <i class="bi bi-pencil"></i> Edit
                                        </a>
                                        <button type="button"
                                            class="btn btn-sm js-delete-user" 
                                            style="background-color: #fee2e2; color: #dc2626; border: 1px solid #fecaca; padding: 6px 12px; border-radius: 6px; transition: all 0.2s;"
                                            onmouseover="this.style.backgroundColor='#fecaca'"
                                            onmouseout="this.style.backgroundColor='#fee2e2'"
                                            data-delete-url="{{ route('users.destroy', $user) }}"
                                            data-user-name="{{ $user->name }}">
                                            <i class="bi bi-trash"></i> Hapus
                                        </button>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center py-5" style="color: #9ca3af;">
                                        <i class="bi bi-inbox display-4 d-block mb-3" style="opacity: 0.3;"></i>
                                        <p class="mb-0">Belum ada data user</p>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    (function () {
        const buttons = document.querySelectorAll('.js-delete-user');
        buttons.forEach((btn) => {
            btn.addEventListener('click', () => {
                const deleteUrl = btn.getAttribute('data-delete-url') || '#';
                const userName = btn.getAttribute('data-user-name') || 'user';

                Swal.fire({
                    title: '<strong>Hapus User?</strong>',
                    html: `
                        <div style="text-align: left; padding: 10px;">
                            <p style="margin-bottom: 15px;">Anda akan menghapus user <strong style="color: #dc2626;">${userName}</strong> dari sistem.</p>
                            
                            <div style="background: #fee2e2; padding: 12px; border-radius: 8px; border-left: 4px solid #dc2626; margin-bottom: 15px;">
                                <i class="bi bi-exclamation-triangle" style="color: #dc2626;"></i> 
                                <strong style="color: #991b1b;">Perhatian:</strong>
                                <ul style="margin: 8px 0 0 0; padding-left: 20px; color: #991b1b;">
                                    <li>User tidak akan bisa login lagi</li>
                                    <li>Semua data aktivitas tetap tersimpan</li>
                                    <li>Tindakan ini <strong>tidak dapat dibatalkan</strong></li>
                                </ul>
                            </div>
                        </div>
                    `,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: '<i class="bi bi-trash"></i> Ya, Hapus User',
                    cancelButtonText: '<i class="bi bi-x-circle"></i> Batal',
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#6b7280',
                    reverseButtons: true,
                    width: '550px',
                    customClass: {
                        confirmButton: 'btn btn-lg',
                        cancelButton: 'btn btn-lg'
                    }
                }).then((result) => {
                    if (!result.isConfirmed) return;

                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = deleteUrl;
                    form.innerHTML = `
                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                        <input type="hidden" name="_method" value="DELETE">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                });
            });
        });
    })();
</script>
@endsection
