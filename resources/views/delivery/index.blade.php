@extends('shared.layouts.app')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <h1 class="h3 text-gray-800">
            <i class="bi bi-truck"></i> Delivery Schedule
        </h1>
    </div>
</div>

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
                            @if(Auth::user()->role === 'warehouse_operator' || Auth::user()->role === 'admin')
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
                            @if(Auth::user()->role === 'warehouse_operator' || Auth::user()->role === 'admin')
                            <td class="text-end">
                                @if($order->has_sufficient_stock)
                                    <button class="btn btn-sm btn-dark" onclick="openFulfillModal({{ $order->id }})">
                                        <i class="bi bi-box-seam"></i> Process
                                    </button>
                                @else
                                    <button class="btn btn-sm btn-secondary" disabled>
                                        <i class="bi bi-x-circle"></i> Stock Kurang
                                    </button>
                                @endif
                            </td>
                            @endif
                        </tr>
                        @empty
                        <tr>
                            <td colspan="{{ (Auth::user()->role === 'warehouse_operator' || Auth::user()->role === 'admin') ? 5 : 4 }}" class="text-center text-muted py-5">
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

    function openFulfillModal(orderId) {
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

        const requests = pendingProcessItems.map(item => {
            return fetch('{{ route("stock-withdrawal.confirm") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    part_number: item.part_number,
                    pcs_quantity: item.remaining,
                    delivery_order_item_id: item.id,
                    notes: 'Order Fulfillment'
                })
            }).then(res => res.json());
        });

        Promise.all(requests)
            .then(results => {
                const failed = results.find(r => !r.success);
                if (failed) {
                    alert(failed.message || 'Gagal proses.');
                    return;
                }
                location.reload();
            })
            .catch(() => alert('Gagal proses.'));
    });
</script>
@endsection
