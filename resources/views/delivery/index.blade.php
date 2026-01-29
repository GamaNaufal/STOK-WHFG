@extends('shared.layouts.app')

@section('styles')
    <style>
        .delivery-page {
            background: linear-gradient(135deg, #f5f7fa 0%, #e8eef5 100%);
            min-height: 100vh;
        }

        .page-header {
            margin-bottom: 3rem;
            padding-top: 1.5rem;
            padding-bottom: 0.5rem;
        }

        .page-header h1 {
            color: #1f2937;
            font-weight: 700;
            margin-bottom: 0.5rem;
            font-size: 1.875rem;
        }

        .page-header p {
            color: #6b7280;
            font-size: 0.95rem;
            margin: 0;
        }

        /* Section Cards */
        .section-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            border: none;
            margin-bottom: 2.5rem;
            overflow: hidden;
        }

        .section-header {
            background: linear-gradient(135deg, #0C7779 0%, #005461 100%);
            color: white;
            padding: 1.5rem 1.75rem;
            display: flex;
            align-items: center;
            gap: 0.875rem;
            border-bottom: 3px solid rgba(255, 255, 255, 0.1);
        }

        .section-header h5 {
            margin: 0;
            font-weight: 700;
            font-size: 1.15rem;
            letter-spacing: 0.3px;
        }

        .section-header i {
            font-size: 1.4rem;
            opacity: 0.95;
        }

        /* Tables */
        .modern-table {
            margin-bottom: 0;
        }

        .modern-table thead {
            background: #f0f8f7;
            border-bottom: 2px solid #b3e5db;
        }

        .modern-table thead th {
            color: #0C7779;
            font-weight: 700;
            padding: 0.85rem 1rem;
            border: none;
            white-space: nowrap;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.8px;
        }

        .modern-table tbody tr {
            border-bottom: 1px solid #e9ecef;
            transition: background 0.2s ease;
        }

        .modern-table tbody tr:hover {
            background: #f8fffe;
        }

        .modern-table tbody td {
            padding: 0.6rem 0.85rem;
            vertical-align: middle;
            font-size: 0.9rem;
        }

        .modern-table tbody td:first-child {
            font-weight: 700;
            color: #1f2937;
            width: 10%;
        }

        .modern-table tbody td:nth-child(2) {
            font-weight: 600;
            color: #374151;
            width: 12%;
        }

        .modern-table tbody td:nth-child(3) {
            width: 40%;
        }

        .modern-table tbody td:nth-child(4) {
            width: 15%;
            text-align: center;
        }

        .modern-table tbody td:nth-child(5) {
            text-align: left;
            padding-left: 1.5rem;
            width: 23%;
        }

        .modern-table .badge {
            padding: 0.5rem 0.875rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        /* Badges */
        .badge-overdue {
            background: #fee2e2;
            color: #991b1b;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .badge-today {
            background: #fef3c7;
            color: #92400e;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .badge-pending {
            background: #dcfce7;
            color: #166534;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .badge-status {
            background: #dbeafe;
            color: #1e40af;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .part-item {
            display: flex;
            justify-content: space-between;
            padding: 0.4rem 0;
            border-bottom: 1px solid #e9ecef;
            font-size: 0.85rem;
            align-items: center;
        }

        .part-item:last-child {
            border-bottom: none;
        }

        .part-name {
            font-weight: 700;
            color: #1f2937;
            flex: 1;
        }

        .part-qty {
            font-weight: 700;
            min-width: 80px;
            text-align: right;
        }

        .part-qty.ok {
            color: #059669;
        }

        .part-qty.warning {
            color: #dc2626;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-start;
            align-items: center;
            flex-wrap: nowrap;
        }

        .btn-process {
            background: linear-gradient(135deg, #0C7779 0%, #005461 100%);
            color: white;
            border: none;
            padding: 0.4rem 0.8rem;
            border-radius: 5px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            flex: 1;
            justify-content: center;
        }

        .btn-process:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(12, 119, 121, 0.3);
            color: white;
        }

        .btn-insufficient {
            background: #f3f4f6;
            color: #9ca3af;
            border: 1px solid #e5e7eb;
            padding: 0.4rem 0.8rem;
            border-radius: 5px;
            font-size: 0.75rem;
            font-weight: 600;
            cursor: not-allowed;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            flex: 1;
            justify-content: center;
        }

        .btn-completed {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
            padding: 0.4rem 0.8rem;
            border-radius: 5px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            flex: 1;
            justify-content: center;
        }

        .btn-delete {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
            padding: 0.4rem 0.5rem;
            border-radius: 5px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s ease;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 30px;
            height: 30px;
            flex-shrink: 0;
        }

        .btn-delete:hover {
            background: #dc2626;
            color: white;
            border-color: #dc2626;
        }

        .btn-redo {
            background: #fbbf24;
            color: #92400e;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-redo:hover:not(:disabled) {
            background: #f59e0b;
            color: white;
        }

        .btn-redo:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* Empty State */
        .empty-state {
            padding: 3rem 2rem;
            text-align: center;
            color: #9ca3af;
        }

        .empty-state i {
            font-size: 3rem;
            color: #10b981;
            margin-bottom: 1rem;
            display: block;
        }

        .empty-state h5 {
            color: #1f2937;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
    </style>
@endsection

@section('content')
<div class="page-header">
    <h1>
        <i class="bi bi-truck" style="color: #0C7779;"></i> Delivery Schedule
    </h1>
    <p class="mb-0">Kelola jadwal pengiriman dan status pesanan delivery</p>
</div>

<div class="container-fluid">
    @if(isset($completedOrders) && $completedOrders->count() > 0)
    <div class="section-card">
        <div class="section-header">
            <i class="bi bi-clock-history"></i>
            <h5>Pesanan Selesai (Redo 5 Hari)</h5>
        </div>
        <div class="table-responsive">
            <table class="table modern-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Selesai Pada</th>
                        <th>Redo Hingga</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($completedOrders as $completion)
                        <tr>
                            <td><strong>#{{ $completion->order->id }}</strong></td>
                            <td>{{ $completion->order->customer_name }}</td>
                            <td>{{ is_object($completion->completed_at) ? $completion->completed_at->format('d M Y H:i') : \Carbon\Carbon::parse($completion->completed_at)->format('d M Y H:i') }}</td>
                            <td>{{ is_object($completion->redo_until) ? $completion->redo_until->format('d M Y H:i') : \Carbon\Carbon::parse($completion->redo_until)->format('d M Y H:i') }}</td>
                            <td class="text-end">
                                @if(Auth::user()->role === 'admin_warehouse' || Auth::user()->role === 'admin')
                                    <form method="POST" action="{{ route('delivery.pick.redo', $completion->id) }}" class="d-inline">
                                        @csrf
                                        <button class="btn-redo" {{ $completion->completion_status !== 'completed' || now()->greaterThan($completion->redo_until) ? 'disabled' : '' }}>
                                            <i class="bi bi-arrow-counterclockwise"></i> Redo
                                        </button>
                                    </form>
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

    <!-- Main Schedule Table (Visible to All) -->
    <div class="section-card">
        <div class="section-header">
            <i class="bi bi-calendar-check"></i>
            <h5>Jadwal Delivery Terdaftar</h5>
        </div>
        <div class="table-responsive">
            <table class="table modern-table">
                <thead>
                    <tr>
                        <th style="width: 12%">Tanggal</th>
                        <th style="width: 18%">Customer</th>
                        <th>Detail Pesanan (Part No. & Qty)</th>
                        <th style="width: 10%">Status</th>
                        @if(in_array(Auth::user()->role, ['warehouse_operator', 'admin', 'admin_warehouse'], true))
                        <th style="width: 18%" class="text-end">Action</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($approvedOrders as $order)
                    <tr>
                        <td>
                            <strong>{{ $order->delivery_date->format('d M Y') }}</strong>
                        </td>
                        <td>
                            {{ $order->customer_name }}
                        </td>
                        <td>
                            @foreach($order->items as $item)
                                <div class="part-item">
                                    <span class="part-name">{{ $item->part_number }}</span>
                                    <span class="part-qty {{ ($item->is_fulfillable ?? false) ? 'ok' : 'warning' }}">
                                        {{ $item->display_fulfilled ?? 0 }} / {{ $item->quantity }}
                                    </span>
                                </div>
                            @endforeach
                        </td>
                        <td>
                            @if($order->days_remaining < 0)
                                <span class="badge badge-overdue">
                                    <i class="bi bi-exclamation-triangle"></i> Overdue
                                </span>
                            @elseif($order->days_remaining === 0)
                                <span class="badge badge-today">
                                    <i class="bi bi-calendar-event"></i> H-0
                                </span>
                            @else
                                <span class="badge badge-pending">
                                    <i class="bi bi-clock"></i> H-{{ $order->days_remaining }}
                                </span>
                            @endif
                        </td>
                        @if(in_array(Auth::user()->role, ['warehouse_operator', 'admin', 'admin_warehouse'], true))
                        <td class="text-end">
                            <div class="action-buttons">
                                @if($order->status === 'completed')
                                    <span class="btn-completed">
                                        <i class="bi bi-check-circle"></i> Selesai
                                    </span>
                                @elseif($order->has_sufficient_stock)
                                    <button class="btn-process" onclick="openFulfillModal({{ $order->id }})">
                                        <i class="bi bi-box-seam"></i> Process
                                    </button>
                                @else
                                    <button class="btn-insufficient" disabled>
                                        <i class="bi bi-x-circle"></i> Stock Kurang
                                    </button>
                                @endif
                                @if(Auth::user()->role === 'admin')
                                    <form method="POST" action="{{ route('delivery.destroy', $order->id) }}" onsubmit="return confirm('Hapus jadwal ini?');" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn-delete" type="submit">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ (in_array(Auth::user()->role, ['warehouse_operator', 'admin', 'admin_warehouse'], true)) ? 5 : 4 }}" class="text-center py-5">
                            <div class="empty-state">
                                <i class="bi bi-inbox"></i>
                                <h5>Tidak ada jadwal pengiriman</h5>
                                <p>Pesanan akan muncul di sini setelah disetujui oleh PPC</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

@if(isset($historyRows) && $historyRows->count() > 0)
    <div class="section-card">
        <div class="section-header">
            <i class="bi bi-archive"></i>
            <h5>Riwayat Pesanan</h5>
        </div>
        <div class="table-responsive">
            <table class="table modern-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Detail Pesanan (Part No. & Qty)</th>
                        <th>Selesai Pada</th>
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
                                    @foreach($order->items as $item)
                                        <div class="part-item">
                                            <span class="part-name">{{ $item->part_number }}</span>
                                            <span>{{ $item->quantity }}</span>
                                        </div>
                                    @endforeach
                                @else
                                    -
                                @endif
                            </td>
                            <td>{{ is_object($history->completed_at) ? $history->completed_at->format('d M Y H:i') : \Carbon\Carbon::parse($history->completed_at)->format('d M Y H:i') }}</td>
                            <td>
                                @if($history->status_label === 'Deleted')
                                    <span class="badge" style="background: #fee2e2; color: #991b1b;">
                                        <i class="bi bi-x-circle"></i> Dihapus
                                    </span>
                                @elseif($history->status_label === 'Redone')
                                    <span class="badge" style="background: #fef3c7; color: #92400e;">
                                        <i class="bi bi-arrow-counterclockwise"></i> Diulang
                                    </span>
                                @else
                                    <span class="badge" style="background: #e5e7eb; color: #6b7280;">
                                        <i class="bi bi-clock"></i> Kadaluarsa
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
</div>

<!-- Picklist Modal -->
<div class="modal fade" id="picklistModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);">
            <div class="modal-header" style="background: linear-gradient(135deg, #0C7779 0%, #005461 100%); color: white; border: none; padding: 1.5rem;">
                <h5 class="modal-title"><i class="bi bi-file-earmark-pdf"></i> Dokumen Pengambilan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding: 1.5rem;">
                <p>Silakan download atau print pick list sebelum scan.</p>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #e9ecef; padding: 1.5rem;">
                <a href="#" class="btn" id="btnDownloadPdf" target="_blank" style="background: #f0f8f7; color: #0C7779; border: 1px solid #b3e5db; padding: 0.5rem 1rem; border-radius: 6px; font-weight: 600; text-decoration: none;">
                    <i class="bi bi-download"></i> Download PDF
                </a>
                <a href="#" class="btn" id="btnPrintPdf" target="_blank" style="background: #f0f8f7; color: #0C7779; border: 1px solid #b3e5db; padding: 0.5rem 1rem; border-radius: 6px; font-weight: 600; text-decoration: none;">
                    <i class="bi bi-printer"></i> Print
                </a>
                <a href="#" class="btn" id="btnStartScan" style="background: linear-gradient(135deg, #0C7779 0%, #005461 100%); color: white; border: none; padding: 0.5rem 1rem; border-radius: 6px; font-weight: 600; text-decoration: none;">
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
        <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);">
            <div class="modal-header" style="background: linear-gradient(135deg, #0C7779 0%, #005461 100%); color: white; border: none; padding: 1.5rem;">
                <h5 class="modal-title"><i class="bi bi-box-seam"></i> Rekomendasi Pengambilan (FIFO)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="padding: 1.5rem;">
                <div id="fulfillInfo" class="mb-3"></div>
                <div id="fulfillItems"></div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #e9ecef; padding: 1.5rem;">
                <button type="button" class="btn" id="btnProcessAll" style="background: linear-gradient(135deg, #0C7779 0%, #005461 100%); color: white; border: none; padding: 0.5rem 1.25rem; border-radius: 6px; font-weight: 600; cursor: pointer;">
                    <i class="bi bi-box-seam"></i> Proses Pengambilan
                </button>
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
