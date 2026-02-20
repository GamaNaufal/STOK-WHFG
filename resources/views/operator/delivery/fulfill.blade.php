@extends('shared.layouts.app')

@section('title', 'Penuhi Pesanan Pengiriman')

@section('content')
<!-- Page Header Component -->
<x-page-header 
    title="Penuhi Pesanan #{{ $order->id }}" 
    icon="bi-box-seam"
    subtitle="Pelanggan: {{ $order->customer_name }} | Tanggal: {{ $order->delivery_date->format('d M Y') }}"
>
    <a href="{{ route('delivery.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
    </a>
</x-page-header>

<div class="row">
    <div class="col-12">
        <x-card title="Barang untuk Diambil">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Nomor Part</th>
                            <th class="text-center">Dibutuhkan</th>
                            <th class="text-center">Terpenuhi</th>
                            <th class="text-center">Sisa</th>
                            <th class="text-end pe-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="5" class="ps-3 text-muted text-center py-4">
                                <i class="bi bi-info-circle me-2"></i>
                                <small>Barang dilacak melalui sesi pengambilan. Gunakan sistem pengambilan untuk memenuhi pesanan ini.</small>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Withdrawal Modal --}}
<div class="modal fade" id="withdrawModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Withdraw Stock</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <p>Withdrawing Part: <strong id="modalPartNumber"></strong></p>
                    <div class="d-flex align-items-center gap-2">
                        <label class="mb-0">Quantity Needed:</label>
                        <input type="number" id="modalQtyInput" class="form-control form-control-sm" style="width: 140px;" min="1">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btnRecalc">
                            <i class="bi bi-arrow-repeat"></i> Recalc FIFO
                        </button>
                    </div>
                </div>
                
                <div id="loading" class="text-center py-4" style="display:none;">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 text-muted">Calculating FIFO locations...</p>
                </div>

                <div id="fifo-recommendation" style="display:none;">
                    <h6 class="fw-bold border-bottom pb-2">FIFO Recommendation</h6>
                    <table class="table table-sm table-bordered">
                        <thead class="bg-light">
                            <tr>
                                <th>Location</th>
                                <th>Pallet</th>
                                <th>Date In</th>
                                <th>Available</th>
                                <th width="100">Take</th>
                            </tr>
                        </thead>
                        <tbody id="fifoTableBody"></tbody>
                    </table>
                    <div class="alert alert-info small">
                        <i class="bi bi-info-circle"></i> System automatically selects oldest stock.
                    </div>
                </div>

                <div id="error-msg" class="alert alert-danger" style="display:none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <x-button type="button" variant="primary" id="btnConfirmWithdraw">Konfirmasi Pengambilan</x-button>
            </div>
        </div>
    </div>
</div>

<script>
    let currentPart = '';
    let currentQty = 0;
    let currentitemId = 0;

    const modal = new bootstrap.Modal(document.getElementById('withdrawModal'));
    
    function openWithdrawModal(part, qty, itemId) {
        currentPart = part;
        currentQty = qty;
        currentitemId = itemId;

        document.getElementById('modalPartNumber').textContent = part;
        document.getElementById('modalQtyInput').value = qty;
        document.getElementById('fifo-recommendation').style.display = 'none';
        document.getElementById('error-msg').style.display = 'none';
        
        modal.show();
        loadFifoPreview();
    }

    function loadFifoPreview() {
        document.getElementById('loading').style.display = 'block';
        const qtyValue = parseInt(document.getElementById('modalQtyInput').value, 10) || 0;
        currentQty = qtyValue;
        
        fetch('{{ route("stock-withdrawal.preview") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                part_number: currentPart,
                pcs_quantity: currentQty
            })
        })
        .then(res => res.json())
        .then(data => {
            document.getElementById('loading').style.display = 'none';
            if(data.success) {
                renderFifoTable(data.locations);
                document.getElementById('fifo-recommendation').style.display = 'block';
                document.getElementById('btnConfirmWithdraw').disabled = false;
            } else {
                showError(data.message);
                document.getElementById('btnConfirmWithdraw').disabled = true;
            }
        })
        .catch(err => {
            document.getElementById('loading').style.display = 'none';
            showError("Network Error");
        });
    }

    function renderFifoTable(locations) {
        const tbody = document.getElementById('fifoTableBody');
        tbody.innerHTML = '';
        locations.forEach(loc => {
            tbody.innerHTML += `
                <tr>
                    <td>${loc.location}</td>
                    <td>${loc.pallet}</td>
                    <td>${loc.date_in}</td>
                    <td>${loc.available}</td>
                    <td class="fw-bold text-primary">${loc.take}</td>
                </tr>
            `;
        });
    }

    function showError(msg) {
        const el = document.getElementById('error-msg');
        el.textContent = msg;
        el.style.display = 'block';
    }

    document.getElementById('btnConfirmWithdraw').addEventListener('click', function() {
        // Reuse existing StockWithdrawal logic, but we might need to update the Order Item too.
        // We will send 'delivery_order_item_id' in the payload if the controller supports it,
        // OR we modify the controller to accept it.
        
        fetch('{{ route("stock-withdrawal.confirm") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                part_number: currentPart,
                pcs_quantity: currentQty,
                delivery_order_item_id: currentitemId, // Passing this to link it
                notes: 'Order Fulfillment'
            })
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                showToast('Withdrawal Successful', 'success');
                location.reload();
            } else {
                showToast('Error: ' + data.message, 'danger');
            }
        })
        .catch(err => showToast('Error connecting to server', 'danger'));
    });

    document.getElementById('btnRecalc').addEventListener('click', function() {
        loadFifoPreview();
    });
</script>
@endsection
