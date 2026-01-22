@extends('shared.layouts.app')

@section('title', 'Kelola Box QR - Warehouse FG Yamato')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h2">
                    <i class="bi bi-boxes"></i> Kelola Box QR
                </h1>
                <p class="text-muted">Daftar box yang sudah dibuat dengan kode QR unik</p>
            </div>
            <a href="{{ route('boxes.create') }}" class="btn btn-lg" style="background: #0C7779; color: white; border: none;">
                <i class="bi bi-plus-circle"></i> Buat Box Baru
            </a>
        </div>
    </div>
</div>

@if ($message = Session::get('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle"></i> {{ $message }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header" style="background: #0C7779; color: white;">
                <i class="bi bi-table"></i> Daftar Box
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr style="background: #f8f9fa;">
                            <th>No Box</th>
                            <th>Part Number</th>
                            <th>Jumlah PCS</th>
                            <th>Dibuat oleh</th>
                            <th>Tanggal Dibuat</th>
                            <th style="width: 150px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($boxes as $box)
                            <tr>
                                <td>
                                    <strong>{{ $box->box_number }}</strong>
                                </td>
                                <td>{{ $box->part_number }}</td>
                                <td><span class="badge bg-info">{{ $box->pcs_quantity }} PCS</span></td>
                                <td>{{ $box->user->name }}</td>
                                <td>{{ $box->created_at->format('d/m/Y H:i') }}</td>
                                <td>
                                    <a href="{{ route('boxes.show', $box) }}" class="btn btn-sm btn-info" title="Lihat QR">
                                        <i class="bi bi-qr-code"></i>
                                    </a>
                                    <form action="{{ route('boxes.destroy', $box) }}" method="POST" style="display: inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger" title="Hapus" 
                                                onclick="return confirm('Yakin ingin menghapus box ini?')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox"></i><br>
                                    Belum ada box yang dibuat
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-top">
                {{ $boxes->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
