@extends('shared.layouts.app')

@section('title', 'Expired Box')

@section('styles')
<style>
    .warning-stock-note {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 12px 16px;
        margin-bottom: 16px;
        background: #fffbeb;
        border: 1px solid #fde68a;
        border-radius: 8px;
        color: #78350f;
        font-size: .875rem;
        line-height: 1.6;
    }
    .warning-stock-note .badge { flex-shrink: 0; margin-top: 3px; }
    .warning-stock-table { min-width: 640px; table-layout: fixed; }
    .warning-stock-table th,
    .warning-stock-table td {
        padding: 12px !important;
        vertical-align: middle;
        white-space: normal !important;
        overflow-wrap: anywhere;
        line-height: 1.5;
    }
    .warning-stock-table .badge {
        max-width: 100%;
        white-space: normal;
        overflow-wrap: anywhere;
        line-height: 1.5;
    }
    @media (max-width: 575.98px) {
        .warning-stock-note { flex-direction: column; gap: 6px; }
    }
</style>
@endsection

@section('content')
<x-page-header 
    title="Expired Box Management"
    icon="clock-history"
    subtitle="Box warning tetap dapat masuk FIFO dan tidak dapat di-Handle. Handle hanya untuk expired box, berlaku selama 1 bulan."
/>



<div class="row g-4 mb-4">
    <div class="col-12 col-xxl-6">
        <x-card title="Warning (9-12 Bulan)" icon="exclamation-triangle">
            <div class="warning-stock-note">
                <span class="badge bg-warning text-dark">Warning</span>
                <div>Box belum expired, tidak perlu Handle. Tetap dapat masuk FIFO.</div>
            </div>
            <div class="table-responsive" tabindex="0" role="region" aria-label="Daftar box warning, geser tabel untuk melihat semua kolom">
                <table class="table table-sm table-striped align-middle mb-0 warning-stock-table">
                    <colgroup>
                        <col style="width: 17%;">
                        <col style="width: 25%;">
                        <col style="width: 20%;">
                        <col style="width: 17%;">
                        <col style="width: 21%;">
                    </colgroup>
                    <thead class="bg-light">
                        <tr>
                            <th class="fw-semibold">Box</th>
                            <th class="fw-semibold">Part</th>
                            <th class="fw-semibold">Pallet</th>
                            <th class="fw-semibold">Lokasi</th>
                            <th class="fw-semibold">Umur</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($warningBoxes as $box)
                            @php
                                $age = \App\Support\BoxAgeFormatter::format($box->stored_at);
                            @endphp
                            <tr>
                                <td class="fw-bold text-primary">{{ $box->box_number }}</td>
                                <td>{{ $box->part_number }}</td>
                                <td>{{ $box->pallet_number ?? '-' }}</td>
                                <td><span class="badge bg-light text-dark border">{{ $box->warehouse_location ?? '-' }}</span></td>
                                <td><span class="badge bg-warning text-dark">{{ $age }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-4">
                                    <x-empty-state 
                                        message="Tidak ada box dengan status warning" 
                                        icon="check-circle" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
    <div class="col-12 col-xxl-6">
        <x-card title="Expired / Masa Aktif Habis" icon="exclamation-octagon">
            
            <div class="table-responsive" tabindex="0" role="region" aria-label="Tabel data, geser untuk melihat kolom lainnya">
                <table class="table table-sm table-striped align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="fw-semibold">Box</th>
                            <th class="fw-semibold">Part</th>
                            <th class="fw-semibold">Pallet</th>
                            <th class="fw-semibold">Lokasi</th>
                            <th class="fw-semibold">Umur</th>
                            <th class="fw-semibold"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($expiredBoxes as $box)
                            @php
                                $age = \App\Support\BoxAgeFormatter::format($box->stored_at);
                            @endphp
                            <tr>
                                <td class="fw-bold text-primary">{{ $box->box_number }}</td>
                                <td>{{ $box->part_number }}</td>
                                <td>{{ $box->pallet_number ?? '-' }}</td>
                                <td><span class="badge bg-light text-dark border">{{ $box->warehouse_location ?? '-' }}</span></td>
                                <td><span class="badge bg-danger">{{ $age }}</span></td>
                                <td class="text-end">
                                    @if(!$box->warehouse_location || $box->warehouse_location === 'Unknown')
                                        <select class="form-select form-select-sm mb-2 js-handle-location" data-box-id="{{ $box->id }}" aria-label="Lokasi penyimpanan box {{ $box->box_number }}">
                                            <option value="">Pilih lokasi kosong untuk pallet</option>
                                            @foreach($availableLocations as $location)
                                                <option value="{{ $location->id }}">{{ $location->code }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                    <x-button 
                                        type="button" 
                                        size="sm" 
                                        variant="success"
                                        class="js-handle-btn"
                                        data-box-id="{{ $box->id }}"
                                        data-box-number="{{ $box->box_number }}"
                                        data-age="{{ $age }}"
                                        title="Aktifkan selama 1 bulan">
                                        <i class="bi bi-check-circle"></i> Handle
                                    </x-button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-4">
                                    <x-empty-state 
                                        message="Tidak ada box yang sudah expired" 
                                        icon="check-circle" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
</div>

<!-- History Section -->
<div class="row mt-4">
    <div class="col-12">
        <x-card>
            <x-slot name="header">
                <i class="bi bi-clock-history"></i> Riwayat Handle / Aktivasi
            </x-slot>
            <div class="table-responsive" tabindex="0" role="region" aria-label="Tabel data, geser untuk melihat kolom lainnya">
                <table class="table table-sm table-striped align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="fw-semibold">Box</th>
                            <th class="fw-semibold">Part</th>
                            <th class="fw-semibold">Pallet</th>
                            <th class="fw-semibold">Lokasi</th>
                            <th class="fw-semibold">Stored At</th>
                            <th class="fw-semibold">Umur</th>
                            <th class="fw-semibold">Handled By</th>
                            <th class="fw-semibold">Handled At</th>
                            <th class="fw-semibold">Aktif Sampai</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($handledHistory as $row)
                            <tr>
                                <td class="fw-bold text-primary">{{ $row->box_number }}</td>
                                <td>{{ $row->part_number }}</td>
                                <td>{{ $row->pallet_number ?? '-' }}</td>
                                <td><span class="badge bg-light text-dark border">{{ $row->warehouse_location ?? '-' }}</span></td>
                                <td><span class="badge bg-secondary">{{ optional($row->stored_at)->format('d M Y') ?? '-' }}</span></td>
                                <td><span class="badge bg-info text-dark">{{ \App\Support\BoxAgeFormatter::format($row->stored_at, $row->handled_at ?? $row->created_at) }}</span></td>
                                <td>{{ $row->handler?->name ?? '-' }}</td>
                                <td><span class="badge bg-success">{{ optional($row->handled_at)->format('d M Y H:i') ?? '-' }}</span></td>
                                <td>{{ $row->reactivated_until?->format('d M Y H:i') ?? 'Penanganan lama' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="p-4">
                                    <x-empty-state 
                                        message="Belum ada history penanganan box expired" 
                                        icon="clock-history" />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            @if(method_exists($handledHistory, 'links'))
                <x-slot name="footer">
                    <x-pagination :paginator="$handledHistory" />
                </x-slot>
            @endif
        </x-card>
    </div>
</div>

@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle all expired box buttons
        document.querySelectorAll('.js-handle-btn').forEach(button => {
            button.addEventListener('click', function() {
                const boxId = this.getAttribute('data-box-id');
                const boxNumber = this.getAttribute('data-box-number');
                const age = this.getAttribute('data-age');
                const locationSelect = document.querySelector(`.js-handle-location[data-box-id="${boxId}"]`);
                if (locationSelect && !locationSelect.value) {
                    showToast('Pilih lokasi penyimpanan kosong untuk pallet terlebih dahulu.', 'warning');
                    locationSelect.focus();
                    return;
                }
                
                WarehouseAlert.confirm({
                    title: 'Aktifkan Box Selama 1 Bulan?',
                    message: `Anda akan menandai box <strong style="color: #DC2626;">${boxNumber}</strong> (${age}) aktif kembali selama satu bulan kalender.`,
                    warningItems: [
                        'Box dapat masuk FIFO selama masa aktif dan tetap mengikuti tanggal penyimpanan aslinya.',
                        'Setelah satu bulan, box kembali expired dan diblokir dari FIFO sampai Handle berikutnya.',
                        'Pastikan kondisi box layak digunakan dan lokasi penyimpanannya sesuai.'
                    ],
                    confirmText: 'Ya, Aktifkan 1 Bulan',
                    confirmColor: '#DC2626',
                    onConfirm: () => {
                        // Submit form to handle the expired box
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = `{{ url('expired-box') }}/${boxId}/handle`;
                        
                        const csrfToken = document.createElement('input');
                        csrfToken.type = 'hidden';
                        csrfToken.name = '_token';
                        csrfToken.value = '{{ csrf_token() }}';
                        
                        form.appendChild(csrfToken);
                        if (locationSelect) {
                            const locationInput = document.createElement('input');
                            locationInput.type = 'hidden';
                            locationInput.name = 'location_id';
                            locationInput.value = locationSelect.value;
                            form.appendChild(locationInput);
                        }
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            });
        });
    });
</script>
@endsection
