@extends('shared.layouts.app')

@section('title', 'Box Not Full - Warehouse FG Yamato')

@section('styles')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .select2-container .select2-selection--single {
            height: 38px;
            padding: 4px 8px;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 28px;
        }

        @media (max-width: 992px) {
            .card-body {
                padding: 20px !important;
            }
        }

        @media (max-width: 768px) {
            .row.g-4 > [class*="col-"] {
                margin-bottom: 1rem;
            }
        }
    </style>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%);
                        color: white;
                        padding: 30px 24px;
                        border-radius: 12px;
                        box-shadow: 0 8px 24px rgba(12, 119, 121, 0.15);">
                <h1 class="h4" style="margin: 0 0 8px 0; font-weight: 700;">
                    <i class="bi bi-exclamation-circle"></i> Input Box Not Full
                </h1>
                <p style="margin: 0; opacity: 0.95; font-size: 14px;">
                    Input 1 box not full, pilih delivery dan lokasi/pallet tujuan.
                </p>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card shadow" style="border: none; border-radius: 12px; overflow: hidden;">
                <div class="card-body" style="padding: 28px;">
                    <form method="POST" action="{{ route('box-not-full.store') }}">
                        @csrf

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">ID Box (Scan)</label>
                                <input type="text" name="box_number" class="form-control" placeholder="Scan/ketik ID Box" required autofocus>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">No Part</label>
                                <select name="part_number" id="partNumberSelect" class="form-select" required>
                                    <option value="">Pilih No Part</option>
                                    @foreach($partNumbers as $part)
                                        <option value="{{ $part->part_number }}" data-fixed="{{ $part->qty_box }}">
                                            {{ $part->part_number }} (Fixed: {{ $part->qty_box }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold">PCS Aktual</label>
                                <input type="number" name="pcs_quantity" class="form-control" min="1" required>
                                <small class="text-muted">Wajib lebih kecil dari fixed qty.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Delivery Wajib</label>
                                <select name="delivery_order_id" class="form-select" required>
                                    <option value="">Pilih jadwal delivery</option>
                                    @foreach($deliveryOrders as $order)
                                        <option value="{{ $order->id }}">
                                            #{{ $order->id }} - {{ $order->customer_name }} ({{ $order->delivery_date->format('d M Y') }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold">Tipe Permintaan</label>
                                <select name="request_type" class="form-select" required>
                                    <option value="supplement">Pelengkap (tidak ubah qty order)</option>
                                    <option value="additional">Tambahan Order (tambah qty)</option>
                                </select>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-bold">Alasan Not Full</label>
                                <textarea name="reason" class="form-control" rows="2" required></textarea>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="mb-3">
                            <label class="form-label fw-bold">Masukkan ke</label>
                            <div class="d-flex gap-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="target_type" id="targetPallet" value="pallet" checked>
                                    <label class="form-check-label" for="targetPallet">Pallet Existing</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="target_type" id="targetLocation" value="location">
                                    <label class="form-check-label" for="targetLocation">Lokasi Baru</label>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3" id="palletTargetSection">
                            <div class="col-md-12">
                                <label class="form-label fw-bold">Pilih Pallet</label>
                                <select name="target_pallet_id" class="form-select">
                                    <option value="">Pilih pallet</option>
                                    @foreach($pallets as $pallet)
                                        <option value="{{ $pallet->id }}">
                                            {{ $pallet->pallet_number }} ({{ $pallet->stockLocation?->warehouse_location ?? 'Unknown' }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="row g-3" id="locationTargetSection" style="display:none;">
                            <div class="col-md-12">
                                <label class="form-label fw-bold">Pilih Lokasi</label>
                                <select name="target_location_id" class="form-select">
                                    <option value="">Pilih lokasi kosong</option>
                                    @foreach($locations as $location)
                                        <option value="{{ $location->id }}">
                                            {{ $location->code }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end mt-4">
                            <button type="submit" class="btn" style="background: #0C7779; color: white;">
                                <i class="bi bi-send"></i> Kirim ke Supervisi
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card shadow-sm border-0">
                <div class="card-header" style="background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
                    <strong><i class="bi bi-clock-history"></i> Riwayat Box Not Full</strong>
                </div>
                <div class="card-body" style="max-height: 520px; overflow-y: auto;">
                    @if($historyRequests->isEmpty())
                        <div class="text-muted">Belum ada riwayat.</div>
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach($historyRequests as $history)
                                <li class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <strong>{{ $history->box_number }}</strong>
                                        <small class="text-muted">{{ $history->updated_at->format('d M Y H:i') }}</small>
                                    </div>
                                    <div class="small">Part: {{ $history->part_number }} ({{ $history->pcs_quantity }} PCS)</div>
                                    <div class="small">Tipe: {{ $history->request_type === 'additional' ? 'Tambahan' : 'Pelengkap' }}</div>
                                    <div class="small">
                                        Delivery: #{{ $history->delivery_order_id }}
                                        @if($history->deliveryOrder)
                                            - {{ $history->deliveryOrder->customer_name }} ({{ $history->deliveryOrder->delivery_date->format('d M Y') }})
                                        @endif
                                    </div>
                                    <div class="small">
                                        Status:
                                        @if($history->status === 'approved')
                                            <span class="badge bg-success">Approved</span>
                                        @else
                                            <span class="badge bg-danger">Rejected</span>
                                        @endif
                                    </div>
                                    <div class="small text-muted">Approver: {{ $history->approver?->name ?? '-' }}</div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    const partSelect = document.getElementById('partNumberSelect');

    window.jQuery(function() {
        window.jQuery(partSelect).select2({
            width: '100%',
            placeholder: 'Pilih No Part',
            allowClear: true,
            minimumResultsForSearch: 0
        });
    });

    const targetPallet = document.getElementById('targetPallet');
    const targetLocation = document.getElementById('targetLocation');
    const palletSection = document.getElementById('palletTargetSection');
    const locationSection = document.getElementById('locationTargetSection');

    const toggleTarget = () => {
        if (targetLocation.checked) {
            palletSection.style.display = 'none';
            locationSection.style.display = 'block';
        } else {
            palletSection.style.display = 'block';
            locationSection.style.display = 'none';
        }
    };

    targetPallet.addEventListener('change', toggleTarget);
    targetLocation.addEventListener('change', toggleTarget);
    toggleTarget();

    // Add SweetAlert2 confirmation before form submission
    document.querySelector('form').addEventListener('submit', function(e) {
        e.preventDefault();
        const form = this;
        const partNumber = document.getElementById('partNumberSelect').value;
        const boxQty = document.getElementById('boxQty').value;
        const targetType = document.getElementById('targetLocation').checked ? 'Lokasi' : 'Pallet';
        
        if (!partNumber || !boxQty) {
            WarehouseAlert.error({
                title: 'Data Tidak Lengkap',
                message: 'Harap isi semua field yang diperlukan'
            });
            return;
        }

        WarehouseAlert.info({
            title: 'Konfirmasi Kirim Request',
            message: 'Request box not full akan dikirim ke <strong style="color: #0C7779;">Supervisor</strong> untuk approval.',
            details: {
                'Part Number': partNumber,
                'Jumlah Box': `${boxQty} box`,
                'Tujuan': targetType
            },
            infoText: 'Pastikan data sudah benar sebelum mengirim.',
            confirmText: 'Kirim Request',
            onConfirm: () => {
                form.submit();
            }
        });
    });
</script>
@endsection
