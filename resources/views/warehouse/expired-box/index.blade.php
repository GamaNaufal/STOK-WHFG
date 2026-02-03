@extends('shared.layouts.app')

@section('title', 'Expired Box')

@section('content')
<x-page-header 
    title="Expired Box Management"
    icon="clock-history"
    subtitle="Box berumur 9-12 bulan (warning) dan 12+ bulan (expired)"
/>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

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
                                    <x-button 
                                        type="button" 
                                        size="sm" 
                                        variant="danger"
                                        class="js-handle-btn"
                                        data-box-id="{{ $box->id }}"
                                        data-box-number="{{ $box->box_number }}"
                                        data-age="{{ $age }}"
                                        title="Tandai sudah ditangani">
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
                <i class="bi bi-exclamation-octagon text-danger"></i> Expired (>=12 Bulan)
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
                                    <x-button 
                                        type="button" 
                                        size="sm" 
                                        variant="danger"
                                        class="js-handle-btn"
                                        data-box-id="{{ $box->id }}"
                                        data-box-number="{{ $box->box_number }}"
                                        data-age="{{ $age }}"
                                        title="Tandai sudah ditangani">
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
                <i class="bi bi-clock-history"></i> History Handled
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
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="p-4">
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
                
                WarehouseAlert.confirm({
                    title: 'Konfirmasi Handle Expired Box',
                    message: `Anda akan menandai box <strong style="color: #DC2626;">${boxNumber}</strong> (${age} bulan) sudah ditangani.`,
                    warningItems: [
                        'Box akan <strong>dihapus dari stok</strong>',
                        'Aksi ini <strong>tidak dapat dibatalkan</strong>',
                        'Pastikan box sudah benar-benar ditangani'
                    ],
                    confirmText: 'Ya, Handle!',
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
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            });
        });
    });
</script>
    </div>
</div>
@endsection
