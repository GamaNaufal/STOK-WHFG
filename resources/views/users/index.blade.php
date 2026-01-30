@extends('shared.layouts.app')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <h1 class="h3 text-gray-800">Users Management</h1>
            <a href="{{ route('users.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Tambah User
            </a>
        </div>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">All Users</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Created At</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td><span class="badge bg-secondary">{{ $user->role }}</span></td>
                        <td>{{ $user->created_at->format('d M Y') }}</td>
                        <td>
                            <a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            <button type="button"
                                class="btn btn-sm btn-outline-danger js-delete-user"
                                data-delete-url="{{ route('users.destroy', $user) }}"
                                data-user-name="{{ $user->name }}">
                                Hapus
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
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
                    title: 'Hapus User ' + userName + '?',
                    text: 'Tindakan ini tidak dapat dibatalkan.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Hapus',
                    cancelButtonText: 'Batal',
                    confirmButtonColor: '#dc2626',
                    reverseButtons: true
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
