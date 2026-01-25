@extends('shared.layouts.app')

@section('title', 'Master No Part - Warehouse FG Yamato')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h4 mb-0"><i class="bi bi-list-check"></i> Master No Part & Qty</h1>
        <a href="{{ route('part-settings.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Tambah No Part
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>No Part</th>
                            <th>Qty Box (Tetap)</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($parts as $part)
                            <tr>
                                <td>{{ $part->part_number }}</td>
                                <td>{{ $part->qty_box }}</td>
                                <td class="text-end">
                                    <a href="{{ route('part-settings.edit', $part) }}" class="btn btn-sm btn-outline-primary">
                                        Edit
                                    </a>
                                    <form action="{{ route('part-settings.destroy', $part) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus data ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted">Belum ada data.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $parts->links() }}
        </div>
    </div>
</div>
@endsection
