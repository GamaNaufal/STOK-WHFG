@extends('shared.layouts.app')

@section('title', 'Expired Box')

@section('content')
<x-page-header 
    title="Expired Box Management"
    icon="clock-history"
    subtitle="Warning tetap dapat dikirim. Handle mengaktifkan box selama 1 bulan; setelah itu kembali expired."
/>



<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <x-card>
            <x-slot name="header">
                <i class="bi bi-exclamation-triangle text-warning"></i> Warning (9-12 Bulan)
            </x-slot>
            
            <div class="table-responsive">
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
                        @forelse($warningBoxes as $box)
                            @php
                                $storedAt = $box->stored_at ? \Illuminate\Support\Carbon::parse($box->stored_at) : null;
                                $age = $storedAt ? $storedAt->diffInMonths(now()) : 0;
                            @endphp
                            <tr>
                                <td class="fw-bold text-primary">{{ $box->box_number }}</td>
                                <td>{{ $box->part_number }}</td>
                                <td>{{ $box->pallet_number ?? '-' }}</td>
                                <td><span class="badge bg-light text-dark border">{{ $box->warehouse_location ?? '-' }}</span></td>
                                <td><span class="badge bg-warning text-dark">{{ $age }} bulan</span></td>
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
    <div class="col-lg-6">
        <x-card>
            <x-slot name="header">
                <i class="bi bi-exclamation-octagon text-danger"></i> Expired / Masa Aktif Habis
            </x-slot>
            
            <div class="table-responsive">
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
                                $storedAt = $box->stored_at ? \Illuminate\Support\Carbon::parse($box->stored_at) : null;
                                $age = $storedAt ? $storedAt->diffInMonths(now()) : 0;
                            @endphp
                            <tr>
                                <td class="fw-bold text-primary">{{ $box->box_number }}</td>
                                <td>{{ $box->part_number }}</td>
                                <td>{{ $box->pallet_number ?? '-' }}</td>
                                <td><span class="badge bg-light text-dark border">{{ $box->warehouse_location ?? '-' }}</span></td>
                                <td><span class="badge bg-danger">{{ $age }} bulan</span></td>
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
            <div class="table-responsive">
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
                                <td><span class="badge bg-info text-dark">{{ $row->age_months }} bulan</span></td>
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
                    message: `Anda akan menandai box <strong style="color: #DC2626;">${boxNumber}</strong> (${age} bulan) aktif kembali selama satu bulan kalender.`,
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
