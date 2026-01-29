@extends('shared.layouts.app')

@section('title', 'Kelola Box')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="mb-1">Kelola Box</h4>
        <p class="text-muted mb-0">Daftar box yang terdaftar di sistem</p>
    </div>
    <a href="{{ route('boxes.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Tambah Box
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Box Number</th>
                        <th>Part Number</th>
                        <th>PCS</th>
                        <th>Operator</th>
                        <th>Tanggal</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($boxes as $box)
                        <tr>
                            <td>{{ $loop->iteration + ($boxes->currentPage() - 1) * $boxes->perPage() }}</td>
                            <td>{{ $box->box_number }}</td>
                            <td>{{ $box->part_number }}</td>
                            <td>{{ (int) $box->pcs_quantity }}</td>
                            <td>{{ $box->user?->name ?? 'System' }}</td>
                            <td>{{ $box->created_at?->format('d M Y H:i') }}</td>
                            <td class="text-end">
                                <a href="{{ route('boxes.show', $box) }}" class="btn btn-sm btn-outline-primary">Detail</a>
                                <form action="{{ route('boxes.destroy', $box) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus box ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted">Belum ada data box.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $boxes->links() }}
        </div>
    </div>
</div>
@endsection
