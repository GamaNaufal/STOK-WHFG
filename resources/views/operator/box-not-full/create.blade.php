@extends('shared.layouts.app')

@section('title', 'Box Not Full - Warehouse FG Yamato')

@section('styles')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('css/not-full.css') }}">
@endsection

@section('content')
<div class="container-fluid not-full-page">
    @include('shared.not-full-feedback')
    @if($errors->any())
        <div class="alert alert-dismissible fade show shadow-sm" role="alert" style="background: #FEF2F2; border: none; border-left: 4px solid #EF4444; color: #991B1B; border-radius: 8px;">
            <strong><i class="bi bi-exclamation-triangle"></i> Gagal mengirim request:</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup pesan"></button>
        </div>
    @endif

    <div class="row mb-4">
        <div class="col-12">
            <div class="app-page-banner" style="background: linear-gradient(135deg, #0C7779 0%, #249E94 100%);
                        color: white;
                        padding: 30px 24px;
                        border-radius: 12px;
                        box-shadow: 0 8px 24px rgba(12, 119, 121, 0.15);">
                <h1 class="h4" style="margin: 0 0 8px 0; font-weight: 700;">
                    <i class="bi bi-exclamation-circle"></i> Request Box Not Full
                </h1>
                <p style="margin: 0; opacity: 0.95; font-size: 14px;">
                    Ajukan 1 box not full untuk approval Supervisi, lalu pilih delivery dan lokasi/pallet tujuan.
                </p>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="card shadow" style="border: none; border-radius: 12px; overflow: hidden;">
                <div class="card-body" style="padding: 28px;">
                    <form id="notFullRequestForm" method="POST" action="{{ route('box-not-full.store') }}">
                        <div id="notFullFormError" class="alert alert-danger d-none" role="alert" tabindex="-1"></div>
                        @csrf

                        <h2 class="not-full-section-title">Detail box dan delivery</h2>
                        <p class="text-muted">Stok dibuat setelah request disetujui. Semua field wajib diisi.</p>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="boxNumber" class="form-label fw-bold">ID Box (Scan)</label>
                                <input type="text" id="boxNumber" name="box_number" class="form-control" placeholder="Scan/ketik ID Box" value="{{ old('box_number') }}" minlength="8" maxlength="8" inputmode="numeric" pattern="[0-9]{8}" required autofocus aria-describedby="boxNumberHint">
                            <small id="boxNumberHint" class="text-muted">Gunakan ID box 8 angka yang belum pernah dipakai.</small>
                            </div>
                            <div class="col-md-6">
                                <label id="partNumberLabel" for="partNumberSelect" class="form-label fw-bold">No Part</label>
                                <select name="part_number" id="partNumberSelect" class="form-select" required>
                                    <option value="">Pilih No Part</option>
                                    @foreach($partNumbers as $part)
                                        <option value="{{ $part->part_number }}" data-fixed="{{ $part->qty_box }}" {{ old('part_number') === $part->part_number ? 'selected' : '' }}>
                                            {{ $part->part_number }} (Fixed: {{ $part->qty_box }})
                                        </option>
                                    @endforeach
                                </select>
                                @if($partNumbers->isEmpty())
                                    <small class="text-muted">Master part belum tersedia. Hubungi pengelola master data sebelum mengajukan request.</small>
                                @endif
                            </div>

                            <div class="col-md-6">
                                <label for="pcsQuantityInput" class="form-label fw-bold">PCS Aktual</label>
                                <input type="number" aria-describedby="pcsHint" id="pcsQuantityInput" name="pcs_quantity" class="form-control" min="1" value="{{ old('pcs_quantity') }}" required>
                                <small id="pcsHint" class="text-muted" role="status">Pilih part untuk melihat kapasitas standar box.</small>
                            </div>
                            <div class="col-md-6">
                                <label for="deliveryOrder" class="form-label fw-bold">Delivery Wajib</label>
                                <select id="deliveryOrder" name="delivery_order_id" class="form-select" required>
                                    <option value="">Pilih jadwal delivery</option>
                                    @foreach($deliveryOrders as $order)
                                        <option value="{{ $order->id }}" {{ (string) old('delivery_order_id') === (string) $order->id ? 'selected' : '' }}>
                                            #{{ $order->id }} - {{ $order->customer_name }} ({{ $order->delivery_date->format('d M Y') }})
                                        </option>
                                    @endforeach
                                </select>
                                @if($deliveryOrders->isEmpty())
                                    <small class="text-muted">Belum ada delivery aktif yang dapat dipilih.</small>
                                @endif
                            </div>

                            <div class="col-md-6">
                                <label for="requestType" class="form-label fw-bold">Tipe Permintaan</label>
                                <select id="requestType" name="request_type" class="form-select" required>
                                    <option value="supplement" {{ old('request_type', 'supplement') === 'supplement' ? 'selected' : '' }}>Pelengkap (tidak ubah qty order)</option>
                                    <option value="additional" {{ old('request_type') === 'additional' ? 'selected' : '' }}>Tambahan Order (tambah qty)</option>
                                </select>
                            </div>

                            <div class="col-md-12">
                                <label for="notFullReason" class="form-label fw-bold">Alasan Not Full</label>
                                <textarea id="notFullReason" name="reason" class="form-control" rows="3" minlength="3" required aria-describedby="notFullReasonHint">{{ old('reason') }}</textarea>
                                <small id="notFullReasonHint" class="text-muted">Jelaskan alasan box tidak penuh, minimal 3 karakter.</small>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="mb-3">
                            <h2 class="not-full-section-title">Tujuan penyimpanan</h2>
                            <div class="d-flex gap-3 flex-wrap" role="group" aria-label="Tujuan penyimpanan">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="target_type" id="targetPallet" value="pallet" {{ old('target_type', 'pallet') === 'pallet' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="targetPallet">Pallet Existing</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="target_type" id="targetLocation" value="location" {{ old('target_type') === 'location' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="targetLocation">Pallet Baru di Lokasi Kosong</label>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3" id="palletTargetSection">
                            <div class="col-md-12">
                                <label for="targetPalletId" class="form-label fw-bold">Pilih Pallet</label>
                                <select id="targetPalletId" name="target_pallet_id" class="form-select" required>
                                    <option value="">Pilih pallet</option>
                                    @foreach($pallets as $pallet)
                                        <option value="{{ $pallet->id }}" {{ (string) old('target_pallet_id') === (string) $pallet->id ? 'selected' : '' }}>
                                            {{ $pallet->pallet_number }} ({{ $pallet->stockLocation?->warehouse_location ?? 'Unknown' }})
                                        </option>
                                    @endforeach
                                </select>
                                @if($pallets->isEmpty())
                                    <small class="text-muted">Belum ada pallet dengan lokasi aktif. Pilih pallet baru di lokasi kosong jika tersedia.</small>
                                @endif
                            </div>
                        </div>

                        <div class="row g-3" id="locationTargetSection" style="display:none;">
                            <div class="col-md-12">
                                <label for="targetLocationId" class="form-label fw-bold">Pilih Lokasi</label>
                                <select id="targetLocationId" name="target_location_id" class="form-select">
                                    <option value="">Pilih lokasi kosong</option>
                                    @foreach($locations as $location)
                                        <option value="{{ $location->id }}" {{ (string) old('target_location_id') === (string) $location->id ? 'selected' : '' }}>
                                            {{ $location->code }}
                                        </option>
                                    @endforeach
                                </select>
                                @if($locations->isEmpty())
                                    <small class="text-muted">Tidak ada lokasi kosong. Pilih pallet existing atau hubungi pengelola gudang.</small>
                                @endif
                            </div>
                        </div>

                        <p class="not-full-note mt-4">Request pending menahan proses delivery terkait sampai disetujui atau ditolak.</p>
                        <div class="d-flex justify-content-end mt-4">
                            <button type="submit" class="btn btn-primary">
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
                    <h2 class="not-full-section-title mb-0">Riwayat Box Not Full</h2>
                </div>
                <div class="card-body" style="max-height: 520px; overflow-y: auto;">
                    @if($historyRequests->isEmpty())
                        <div class="text-muted">Belum ada request yang disetujui atau ditolak.</div>
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach($historyRequests as $history)
                                <li class="list-group-item">
                                    <div class="d-flex justify-content-between gap-2 flex-wrap">
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
                                            <span class="badge bg-success">Disetujui</span>
                                        @else
                                            <span class="badge bg-danger">Ditolak</span>
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
<script src="{{ asset('js/pages/not-full.js') }}"></script>
@endsection
