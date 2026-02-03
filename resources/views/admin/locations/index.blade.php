@extends('shared.layouts.app')

@section('title', 'Kelola Lokasi Penyimpanan')

@section('content')
<div class="row mb-4">
    <div class="col-12 text-center text-md-start">
        <h1 class="h3 mb-2 text-gray-800">
            <i class="bi bi-geo-alt"></i> Kelola Lokasi Penyimpanan
        </h1>
        <p class="text-muted">Daftar lokasi rak penyimpanan (Master Location)</p>
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card border-0 shadow-sm" style="border-radius: 12px;">
            <div style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%); color: white; padding: 16px; border-radius: 12px 12px 0 0;">
                <h6 class="m-0 fw-bold"><i class="bi bi-plus-circle"></i> Tambah Lokasi Baru</h6>
            </div>
            <div class="card-body">
                <form action="{{ route('locations.store') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label for="code" class="form-label fw-bold">Kode Lokasi</label>
                        <input type="text" class="form-control" id="code" name="code" placeholder="Contoh: A-1-1" required>
                        <div class="form-text">Format bebas tapi disarankan konsisten (Rak-Baris-Posisi).</div>
                        @error('code')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary w-100" style="background-color: #0C7779; border: none;">
                        <i class="bi bi-save"></i> Simpan
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead style="background-color: #f8f9fa;">
                        <tr>
                            <th class="ps-4">Kode Lokasi</th>
                            <th class="text-center">Status</th>
                            <th>Terisi Oleh (Pallet)</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($locations as $location)
                        <tr>
                            <td class="ps-4 fw-bold">{{ $location->code }}</td>
                            <td class="text-center">
                                @php
                                    $isOccupied = $location->is_occupied && $location->currentPallet;
                                @endphp
                                @if($isOccupied)
                                    <span class="badge bg-danger">Terisi</span>
                                @else
                                    <span class="badge bg-success">Kosong</span>
                                @endif
                            </td>
                            <td>
                                {{ $location->currentPallet ? $location->currentPallet->pallet_number : '-' }}
                            </td>
                            <td class="text-center">
                                <div class="d-inline-flex gap-1">
                                    <a href="{{ route('locations.edit', $location->id) }}" class="btn btn-sm btn-outline-primary border-0">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button type="button"
                                        class="btn btn-sm btn-outline-danger border-0 js-delete-location"
                                        data-delete-url="{{ route('locations.destroy', $location->id) }}"
                                        data-location-code="{{ $location->code }}"
                                        data-location-status="{{ $isOccupied ? 'terisi' : 'kosong' }}">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center py-4 text-muted">Belum ada data lokasi.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-4" style="background-color: #f8f9fa; border-top: 1px solid #e5e7eb;">
                <nav aria-label="Pagination Navigation" class="d-flex justify-content-between align-items-center">
                    <!-- Previous Button -->
                    <div>
                        @if ($locations->onFirstPage())
                            <button class="btn btn-sm" style="background-color: #e5e7eb; color: #9ca3af; border: none; padding: 8px 16px; border-radius: 8px; cursor: not-allowed;">
                                <i class="bi bi-chevron-left"></i> Previous
                            </button>
                        @else
                            <a href="{{ $locations->previousPageUrl() }}" class="btn btn-sm" style="background-color: #0C7779; color: white; border: none; padding: 8px 16px; border-radius: 8px; transition: all 0.3s ease;" onmouseover="this.style.backgroundColor='#249E94'" onmouseout="this.style.backgroundColor='#0C7779'">
                                <i class="bi bi-chevron-left"></i> Previous
                            </a>
                        @endif
                    </div>

                    <!-- Page Info -->
                    <div class="text-center">
                        <small class="text-muted d-block" style="font-size: 13px;">
                            <strong>Halaman {{ $locations->currentPage() }}</strong> dari <strong>{{ $locations->lastPage() }}</strong>
                        </small>
                        <small class="text-muted d-block" style="font-size: 12px;">
                            Menampilkan <strong>{{ $locations->count() }}</strong> dari <strong>{{ $locations->total() }}</strong> hasil
                        </small>
                    </div>

                    <!-- Next Button -->
                    <div>
                        @if ($locations->hasMorePages())
                            <a href="{{ $locations->nextPageUrl() }}" class="btn btn-sm" style="background-color: #0C7779; color: white; border: none; padding: 8px 16px; border-radius: 8px; transition: all 0.3s ease;" onmouseover="this.style.backgroundColor='#249E94'" onmouseout="this.style.backgroundColor='#0C7779'">
                                Next <i class="bi bi-chevron-right"></i>
                            </a>
                        @else
                            <button class="btn btn-sm" style="background-color: #e5e7eb; color: #9ca3af; border: none; padding: 8px 16px; border-radius: 8px; cursor: not-allowed;">
                                Next <i class="bi bi-chevron-right"></i>
                            </button>
                        @endif
                    </div>
                </nav>

                <!-- Optional: Simple Pagination Links with Better Styling -->
                <div class="mt-3 d-flex justify-content-center gap-2">
                    @foreach ($locations->getUrlRange(1, $locations->lastPage()) as $page => $url)
                        @if ($page == $locations->currentPage())
                            <span class="btn btn-sm" style="background-color: #0C7779; color: white; border: none; min-width: 40px; padding: 6px 10px; border-radius: 6px;">
                                {{ $page }}
                            </span>
                        @else
                            <a href="{{ $url }}" class="btn btn-sm" style="background-color: #f3f4f6; color: #0C7779; border: 1px solid #e5e7eb; min-width: 40px; padding: 6px 10px; border-radius: 6px; transition: all 0.3s ease;" onmouseover="this.style.backgroundColor='#e5e7eb'" onmouseout="this.style.backgroundColor='#f3f4f6'">
                                {{ $page }}
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    (function () {
        const buttons = document.querySelectorAll('.js-delete-location');
        buttons.forEach((btn) => {
            btn.addEventListener('click', () => {
                const deleteUrl = btn.getAttribute('data-delete-url') || '#';
                const code = btn.getAttribute('data-location-code') || '-';
                const status = btn.getAttribute('data-location-status') || 'kosong';

                const warningMessage = status === 'terisi'
                    ? `Lokasi <strong>${code}</strong> masih terisi pallet. Menghapus akan melepaskan data lokasi terkait.`
                    : `Lokasi <strong>${code}</strong> kosong dan akan dihapus.`;
                    
                const warnings = status === 'terisi' 
                    ? ['Lokasi masih <strong>terisi pallet</strong>', 'Data lokasi akan <strong>dilepaskan</strong>']
                    : ['Tindakan ini <strong>tidak dapat dibatalkan</strong>'];

                WarehouseAlert.delete({
                    title: 'Hapus Lokasi?',
                    itemName: `lokasi ${code}`,
                    warningItems: warnings,
                    confirmText: 'Ya, Hapus',
                    onConfirm: () => {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = deleteUrl;
                        form.innerHTML = `
                            <input type="hidden" name="_token" value="{{ csrf_token() }}">
                            <input type="hidden" name="_method" value="DELETE">
                        `;
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            });
        });
    })();
</script>
@endsection
