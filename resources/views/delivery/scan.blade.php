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
        <a href="{{ route('delivery.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
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
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="requiredBoxes">
                            @foreach($session->items as $item)
                                <tr>
                                    <td class="ps-3 fw-bold">{{ $item->box->box_number }}</td>
                                    <td>{{ $item->part_number }}</td>
                                    <td>{{ $item->pcs_quantity }}</td>
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
    const sessionStatus = document.getElementById('sessionStatus');

    function showMessage(message, type = 'success') {
        scanMessage.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
    }

    // Handle Enter key in input
    scanInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            btnScan.click();
        }
    });

    btnScan.addEventListener('click', function () {
        const boxNumber = scanInput.value.trim();
        if (!boxNumber) {
            showMessage('ID Box wajib diisi.', 'danger');
            return;
        }

        fetch('{{ route('delivery.pick.scan.submit', $session->id) }}', {
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
                if (data.remaining === 0) {
                    btnComplete.disabled = false;
                }
                location.reload();
            } else {
                showMessage(data.message || 'Scan gagal.', 'danger');
                if (res.status === 423) {
                    sessionStatus.textContent = 'BLOCKED';
                }
            }
        })
        .catch(() => showMessage('Gagal koneksi.', 'danger'));
    });

    btnComplete.addEventListener('click', function () {
        fetch('{{ route('delivery.pick.complete', $session->id) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('Pengiriman selesai.');
                window.location.href = '{{ route('delivery.index') }}';
            } else {
                alert(data.message || 'Tidak bisa menyelesaikan.');
            }
        })
        .catch(() => alert('Gagal koneksi.'));
    });
</script>
@endsection