@extends('shared.layouts.app')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <h1 class="h3 text-gray-800">
            <i class="bi bi-truck"></i> Delivery Schedule
        </h1>
    </div>
</div>

@if(isset($completedOrders) && $completedOrders->count() > 0)
<div class="card shadow mb-4">
    <div class="card-header py-3 bg-white">
        <h6 class="m-0 font-weight-bold text-primary">Completed Orders (Redo 5 Hari)</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Completed At</th>
                        <th>Redo Until</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($completedOrders as $completion)
                        <tr>
                            <td>#{{ $completion->order->id }}</td>
                            <td>{{ $completion->order->customer_name }}</td>
                            <td>{{ is_object($completion->completed_at) ? $completion->completed_at->format('d M Y H:i') : \Carbon\Carbon::parse($completion->completed_at)->format('d M Y H:i') }}</td>
                            <td>{{ is_object($completion->redo_until) ? $completion->redo_until->format('d M Y H:i') : \Carbon\Carbon::parse($completion->redo_until)->format('d M Y H:i') }}</td>
                            <td class="text-end">
                                @if(Auth::user()->role === 'admin_warehouse' || Auth::user()->role === 'admin')
                                    <form method="POST" action="{{ route('delivery.pick.redo', $completion->id) }}" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-warning" {{ $completion->completion_status !== 'completed' || now()->greaterThan($completion->redo_until) ? 'disabled' : '' }}>
                                            <i class="bi bi-arrow-counterclockwise"></i> Redo
                                        </button>
                                    </form>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

<div class="container-fluid py-4">
    <!-- Buttons Moved to Sidebar -->

    <!-- Main Schedule Table (Visible to All) -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-white">
            <h6 class="m-0 font-weight-bold text-primary">Approved Delivery Schedule</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th style="width: 15%">Delivery Date</th>
                            <th style="width: 20%">Customer</th>
                            <th>Order Details (Part No. & Qty)</th>
                            <th style="width: 12%">Due Time</th>
                            @if(in_array(Auth::user()->role, ['warehouse_operator', 'admin', 'admin_warehouse'], true))
                            <th style="width: 15%" class="text-end">Action</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($approvedOrders as $order)
                        <tr>
                            <td class="fw-bold">{{ $order->delivery_date->format('d M Y') }}</td>
                            <td>{{ $order->customer_name }}</td>
                            <td>
                                <ul class="mb-0 list-unstyled">
                                    @foreach($order->items as $item)
                                        <li class="d-flex justify-content-between border-bottom py-1">
                                            <span>Part: <strong>{{ $item->part_number }}</strong></span>
                                            <span class="{{ ($item->is_fulfillable ?? false) ? 'text-success' : 'text-danger fw-bold' }}">
                                                Qty: {{ $item->display_fulfilled ?? 0 }} / {{ $item->quantity }}
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                            </td>
                            <td>
                                @if($order->days_remaining < 0)
                                    <span class="badge bg-danger">Overdue {{ abs($order->days_remaining) }} hari</span>
                                @elseif($order->days_remaining === 0)
                                    <span class="badge bg-warning text-dark">H-0 (Hari ini)</span>
                                @else
                                    <span class="badge bg-success">H-{{ $order->days_remaining }}</span>
                                @endif
                            </td>
                            @if(in_array(Auth::user()->role, ['warehouse_operator', 'admin', 'admin_warehouse'], true))
                            <td class="text-end">
                                <div class="d-flex gap-2 justify-content-end">
                                    @if($order->status === 'completed')
                                        <span class="badge bg-success"><i class="bi bi-check"></i> Selesai</span>
                                    @elseif($order->has_sufficient_stock)
                                        <button class="btn btn-sm btn-dark" onclick="openFulfillModal({{ $order->id }})">
                                            <i class="bi bi-box-seam"></i> Process
                                        </button>
                                    @else
                                        <button class="btn btn-sm btn-secondary" disabled>
                                            <i class="bi bi-x-circle"></i> Stock Kurang
                                        </button>
                                    @endif
                                    @if(Auth::user()->role === 'admin')
                                        <form method="POST" action="{{ route('delivery.destroy', $order->id) }}" onsubmit="return confirm('Hapus jadwal ini?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i> Hapus
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                            @endif
                        </tr>
                        @empty
                        <tr>
                            <td colspan="{{ (in_array(Auth::user()->role, ['warehouse_operator', 'admin', 'admin_warehouse'], true)) ? 5 : 4 }}" class="text-center text-muted py-5">
                                <h5>No scheduled deliveries yet.</h5>
                                <p>Once PPC approves an order, it will appear here.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@if(isset($historyRows) && $historyRows->count() > 0)
<div class="card shadow mb-4">
    <div class="card-header py-3 bg-white">
        <h6 class="m-0 font-weight-bold text-primary">History Orders</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Order Details (Part No. & Qty)</th>
                        <th>Completed At</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($historyRows as $history)
                        @php
                            $order = $history->order;
                        @endphp
                        <tr>
                            <td>{{ $order ? '#' . $order->id : '-' }}</td>
                            <td>{{ $order?->customer_name ?? '-' }}</td>
                            <td>
                                @if($order)
                                    <ul class="mb-0 list-unstyled">
                                        @foreach($order->items as $item)
                                            <li class="d-flex justify-content-between border-bottom py-1">
                                                <span>Part: <strong>{{ $item->part_number }}</strong></span>
                                                <span>Qty: {{ $item->quantity }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    -
                                @endif
                            </td>
                            <td>{{ is_object($history->completed_at) ? $history->completed_at->format('d M Y H:i') : \Carbon\Carbon::parse($history->completed_at)->format('d M Y H:i') }}</td>
                            <td>
                                @if($history->status_label === 'Deleted')
                                    <span class="badge bg-danger">Deleted</span>
                                @elseif($history->status_label === 'Redone')
                                    <span class="badge bg-warning text-dark">Redone</span>
                                @else
                                    <span class="badge bg-secondary">Expired</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

<!-- Picklist Modal -->
<div class="modal fade" id="picklistModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="bi bi-file-earmark-pdf"></i> Dokumen Pengambilan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Silakan download atau print pick list sebelum scan.</p>
            </div>
            <div class="modal-footer">
                <a href="#" class="btn btn-outline-primary" id="btnDownloadPdf" target="_blank">
                    <i class="bi bi-download"></i> Download PDF
                </a>
                <a href="#" class="btn btn-outline-secondary" id="btnPrintPdf" target="_blank">
                    <i class="bi bi-printer"></i> Print
                </a>
                <a href="#" class="btn btn-success" id="btnStartScan">
                    <i class="bi bi-upc-scan"></i> Mulai Scan
                </a>
            </div>
        </div>
    </div>
</div>






@endsection

<!-- Fulfill Modal -->
<div class="modal fade" id="fulfillModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="bi bi-box-seam"></i> Rekomendasi Pengambilan (FIFO)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="fulfillInfo" class="mb-3"></div>
                <div id="fulfillItems"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-dark" id="btnProcessAll">Proses Pengambilan</button>
            </div>
        </div>
    </div>
</div>

@section('scripts')
<script>
    const fulfillModal = new bootstrap.Modal(document.getElementById('fulfillModal'));
    const picklistModal = new bootstrap.Modal(document.getElementById('picklistModal'));
    let currentOrderId = null;

    function openFulfillModal(orderId) {
        currentOrderId = orderId;
        document.getElementById('fulfillItems').innerHTML = '';
        document.getElementById('fulfillInfo').innerHTML = 'Loading...';
        fulfillModal.show();

        fetch(`/delivery-stock/${orderId}/fulfill-data`)
            .then(res => res.json())
            .then(data => {
                document.getElementById('fulfillInfo').innerHTML = `
                    <div class="small text-muted">Order #${data.order_id} | ${data.customer_name} | ${data.delivery_date}</div>
                `;
                renderFulfillItems(data.items);
            })
            .catch(() => {
                document.getElementById('fulfillInfo').innerHTML = '<div class="text-danger">Gagal memuat data.</div>';
            });
    }

    let pendingProcessItems = [];

    function renderFulfillItems(items) {
        const container = document.getElementById('fulfillItems');
        container.innerHTML = '';
        pendingProcessItems = [];

        items.forEach(item => {
            if (item.remaining <= 0) {
                return;
            }

            pendingProcessItems.push(item);

            const section = document.createElement('div');
            section.className = 'card mb-3';
            section.innerHTML = `
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <div>
                        <strong>Part: ${item.part_number}</strong>
                        <span class="ms-2 text-muted">Remaining: ${item.remaining}</span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="small text-muted">Qty Ambil:</span>
                        <span class="fw-bold">${item.remaining}</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="fifo-loading text-muted">Menghitung FIFO...</div>
                    <div class="fifo-table" style="display:none;">
                        <table class="table table-sm table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>ID Box</th>
                                    <th>Pallet</th>
                                    <th>Location</th>
                                    <th>Date In</th>
                                    <th>Available</th>
                                    <th>Take</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                        <div class="alert alert-info small mb-0">
                            <i class="bi bi-info-circle"></i> Rekomendasi FIFO mengikuti sistem (tidak dapat diubah).
                        </div>
                    </div>
                    <div class="fifo-error alert alert-danger mt-2" style="display:none;"></div>
                </div>
            `;

            container.appendChild(section);

            const loadingEl = section.querySelector('.fifo-loading');
            const tableWrap = section.querySelector('.fifo-table');
            const tableBody = section.querySelector('tbody');
            const errorEl = section.querySelector('.fifo-error');

            function loadFifo() {
                const qty = item.remaining;
                loadingEl.style.display = 'block';
                tableWrap.style.display = 'none';
                errorEl.style.display = 'none';

                fetch('{{ route("stock-withdrawal.preview") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        part_number: item.part_number,
                        pcs_quantity: qty
                    })
                })
                .then(res => res.json())
                .then(data => {
                    loadingEl.style.display = 'none';
                    if (data.success) {
                        tableBody.innerHTML = '';
                        data.locations.forEach(loc => {
                            tableBody.innerHTML += `
                                <tr>
                                    <td>${loc.box_number}</td>
                                    <td>${loc.pallet_number}</td>
                                    <td>${loc.warehouse_location}</td>
                                    <td>${loc.stored_date}</td>
                                    <td>${loc.available_pcs}</td>
                                    <td class="fw-bold text-primary">${loc.will_take_pcs}</td>
                                </tr>
                            `;
                        });
                        tableWrap.style.display = 'block';
                    } else {
                        errorEl.textContent = data.message || 'Tidak dapat menghitung FIFO.';
                        errorEl.style.display = 'block';
                    }
                })
                .catch(() => {
                    loadingEl.style.display = 'none';
                    errorEl.textContent = 'Gagal memuat FIFO.';
                    errorEl.style.display = 'block';
                });
            }

            loadFifo();
        });
    }

    document.getElementById('btnProcessAll').addEventListener('click', function() {
        if (pendingProcessItems.length === 0) {
            alert('Tidak ada item untuk diproses.');
            return;
        }

        fetch(`/delivery-stock/${currentOrderId}/start-pick`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.session_id) {
                // Set download button
                document.getElementById('btnDownloadPdf').href = data.pdf_url;
                document.getElementById('btnDownloadPdf').download = 'picklist-order-' + currentOrderId + '.pdf';
                
                // Set print button - open PDF and trigger print immediately
                const printBtn = document.getElementById('btnPrintPdf');
                printBtn.onclick = function(e) {
                    e.preventDefault();
                    const printWindow = window.open(data.pdf_url, '_blank');
                    // Trigger print dialog saat PDF selesai load
                    printWindow.onload = function() {
                        printWindow.print();
                    };
                };
                
                document.getElementById('btnStartScan').href = data.scan_url;
                fulfillModal.hide();
                picklistModal.show();
            } else {
                alert(data.message || 'Gagal membuat pick session.');
            }
        })
        .catch(() => alert('Gagal proses.'));
    });
</script>
@endsection
