@extends('shared.layouts.app')

@section('title', 'Input Pallet - Warehouse FG Yamato')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h2">
                    <i class="bi bi-plus-circle"></i> Input Pallet Baru
                </h1>
                <p class="text-muted">Departemen Packing - Input data pallet baru ke sistem</p>
            </div>
            <a href="{{ route('pallet-input.create') }}" class="btn btn-lg" style="background-color: #0C7779; color: white; border: none;">
                <i class="bi bi-plus-lg"></i> Tambah Pallet Baru
            </a>
        </div>
    </div>
</div>

<!-- Daftar Pallet -->
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm border-0" style="border-left: 4px solid #0C7779;">
            <div class="card-header text-white" style="background-color: #0C7779;">
                <i class="bi bi-list"></i> Daftar Pallet
            </div>
            <div class="card-body p-0">
                @if($pallets->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead style="background-color: #f5f7fa; color: #0C7779;">
                                <tr>
                                    <th>No Pallet</th>
                                    <th>Items (Parts)</th>
                                    <th>Total Box</th>
                                    <th>Total PCS</th>
                                    <th>Dibuat</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($pallets as $pallet)
                                    <tr>
                                        <td>
                                            <strong>{{ $pallet->pallet_number }}</strong>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-1">
                                                @foreach($pallet->items as $item)
                                                    <span class="badge" style="background-color: #e0f5f3; color: #0C7779;">{{ $item->part_number }}</span>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td>{{ (int) ceil($pallet->items->sum('box_quantity')) }} Box</td>
                                        <td>{{ $pallet->items->sum('pcs_quantity') }} PCS</td>
                                        <td>
                                            <small class="text-muted">
                                                {{ $pallet->created_at->format('d/m/Y H:i') }}
                                            </small>
                                        </td>
                                        <td class="text-center">
                                            <a href="{{ route('pallet-input.edit', $pallet->id) }}" 
                                               class="btn btn-sm" style="background-color: #249E94; color: white; border: none;">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                            <form method="POST" action="{{ route('pallet-input.destroy', $pallet->id) }}" 
                                                  style="display: inline;" onsubmit="return confirm('Yakin hapus?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm" style="background-color: #e74c3c; color: white; border: none;">
                                                    <i class="bi bi-trash"></i> Hapus
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="p-3">
                        {{ $pallets->links() }}
                    </div>
                @else
                    <div class="p-5 text-center">
                        <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i>
                        <h5 class="mt-3 text-muted">Belum ada pallet yang diinput</h5>
                        <a href="{{ route('pallet-input.create') }}" class="btn mt-3" style="background-color: #0C7779; color: white; border: none;">
                            <i class="bi bi-plus-lg"></i> Tambah Pallet Pertama
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
