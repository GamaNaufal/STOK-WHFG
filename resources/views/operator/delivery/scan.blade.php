@extends('shared.layouts.app')

@section('title', 'Scan Box Pengambilan')

@section('content')
<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h3 text-gray-800">
                <i class="bi bi-upc-scan"></i> Scan Box Pengambilan
            </h1>
            <p class="text-muted">Order #{{ $order->id }} | {{ $order->customer_name }} | {{ $order->delivery_date->format('d M Y') }}</p>
        </div>
        <div class="d-flex gap-2 flex-wrap justify-content-end">
            <a href="{{ route('delivery.pick.print-preview', [$order->id, $session->id]) }}" target="_blank" class="btn btn-outline-primary">
                <i class="bi bi-printer"></i> Print Keterangan Pengambilan
            </a>
            <a href="{{ route('delivery.pick.verification') }}" class="btn btn-outline-info">
                <i class="bi bi-arrow-left-circle"></i> Kembali ke Picking Verification
            </a>
            <a href="{{ route('delivery.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Kembali ke Delivery
            </a>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-5 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light fw-bold">Scan ID Box</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">ID Box</label>
                    <input type="text" id="scanInput" class="form-control" placeholder="Scan / input ID Box" autofocus>
                </div>
                <button class="btn btn-primary" id="btnScan">
                    <i class="bi bi-check2-circle"></i> Submit Scan
                </button>
                <div id="scanMessage" class="mt-3"></div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mt-4">
            <div class="card-header bg-light fw-bold">Status</div>
            <div class="card-body">
                <p class="mb-2">Status Session: <strong id="sessionStatus">{{ strtoupper($session->status) }}</strong></p>
                <p class="mb-2">Sisa Box: <strong id="remainingCount">{{ $session->items->where('status', 'pending')->count() }}</strong></p>
                <button class="btn btn-success" id="btnComplete" {{ ($session->status === 'blocked' || $session->items->where('status','pending')->count() > 0) ? 'disabled' : '' }}>
                    <i class="bi bi-check2"></i> Selesaikan Pengiriman
                </button>
                <button class="btn btn-outline-danger mt-2" id="btnCancelScan">
                    <i class="bi bi-x-circle"></i> Batalkan Scan
                </button>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-light fw-bold">Daftar Box Wajib</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-3">ID Box</th>
                                <th>Part</th>
                                <th>PCS</th>
                                <th>Lokasi</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="requiredBoxes">
                            @foreach($session->items as $item)
                                <tr data-box-id="{{ $item->box_id }}">
                                    <td class="ps-3 fw-bold">{{ $item->box->box_number }}</td>
                                    <td>{{ $item->part_number }}</td>
                                    <td>{{ $item->pcs_quantity }}</td>
                                    <td>
                                        {{ optional(optional($item->box->pallets->first())->stockLocation)->warehouse_location ?? 'Unknown' }}
                                    </td>
                                    <td>
                                        @if($item->status === 'scanned')
                                            <span class="badge bg-success">Scanned</span>
                                        @else
                                            <span class="badge bg-secondary">Pending</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if($session->issues->where('status', 'pending')->count() > 0)
            <div class="alert alert-warning mt-3">
                <i class="bi bi-exclamation-triangle"></i> Ada masalah scan. Menunggu approval admin warehouse.
            </div>
        @endif
    </div>
</div>
@endsection

@section('scripts')
<script>
    const scanInput = document.getElementById('scanInput');
    const btnScan = document.getElementById('btnScan');
    const scanMessage = document.getElementById('scanMessage');
    const remainingCount = document.getElementById('remainingCount');
    const btnComplete = document.getElementById('btnComplete');
    const btnCancelScan = document.getElementById('btnCancelScan');
    const sessionStatus = document.getElementById('sessionStatus');

    function markRowScanned(boxId) {
        const row = document.querySelector(`tr[data-box-id="${boxId}"]`);
        if (!row) return;

        const statusCell = row.querySelector('td:last-child');
        if (statusCell) {
            statusCell.innerHTML = '<span class="badge bg-success">Scanned</span>';
        }

        row.classList.add('table-success');
    }

    function showMessage(message, type = 'success') {
        scanMessage.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
    }

    function submitScan() {
        const boxNumber = scanInput.value.trim();
        if (!boxNumber) {
            showMessage('ID Box wajib diisi.', 'danger');
            return;
        }

        // Untuk barcode scanner hardware: setelah Enter/submit, bersihkan input
        scanInput.value = '';
        scanInput.focus();

        fetch("{{ route('delivery.pick.scan.submit', $session->id) }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ box_number: boxNumber })
        })
        .then(async res => {
            const data = await res.json();
            if (data.success) {
                showMessage(data.message, 'success');
                remainingCount.textContent = data.remaining;
                if (data.box_id) {
                    markRowScanned(data.box_id);
                }
                if (data.remaining === 0) {
                    btnComplete.disabled = false;
                }
            } else {
                showMessage(data.message || 'Scan gagal.', 'danger');

                if (res.status === 423) {
                    sessionStatus.textContent = 'BLOCKED';
                }

                if (res.status === 409) {
                    sessionStatus.textContent = 'CANCELLED';
                    setTimeout(() => {
                        window.location.href = "{{ route('delivery.index') }}";
                    }, 900);
                }
            }
        })
        .catch(() => showMessage('Gagal koneksi.', 'danger'));
    }

    // Handle Enter key in input
    scanInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            submitScan();
        }
    });

    btnScan.addEventListener('click', submitScan);

    btnComplete.addEventListener('click', function () {
        fetch("{{ route('delivery.pick.complete', $session->id) }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('Pengiriman selesai.', 'success');
                window.location.href = "{{ route('delivery.index') }}";
            } else {
                showToast(data.message || 'Tidak bisa menyelesaikan.', 'danger');
            }
        })
        .catch(() => showToast('Gagal koneksi.', 'danger'));
    });

    btnCancelScan.addEventListener('click', function () {
        const confirmed = confirm('Batalkan proses scan ini? Box yang terkunci akan dilepas.');
        if (!confirmed) {
            return;
        }

        fetch("{{ route('delivery.pick.cancel', $session->id) }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast(data.message || 'Proses scan dibatalkan.', 'success');
                window.location.href = "{{ route('delivery.index') }}";
            } else {
                showToast(data.message || 'Gagal membatalkan scan.', 'danger');
            }
        })
        .catch(() => showToast('Gagal koneksi.', 'danger'));
    });
</script>
@endsection