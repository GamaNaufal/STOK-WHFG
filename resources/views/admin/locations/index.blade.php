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
            <div class="p-3">
                {{ $locations->links() }}
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

                Swal.fire({
                    title: 'Hapus Lokasi ' + code,
                    text: status === 'terisi'
                        ? 'Lokasi ini masih terisi pallet. Menghapus akan melepaskan data lokasi terkait.'
                        : 'Lokasi ini kosong dan akan dihapus.',
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
