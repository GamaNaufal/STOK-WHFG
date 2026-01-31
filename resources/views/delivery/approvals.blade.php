@extends('shared.layouts.app')

@section('styles')
    <style>
        .approvals-page {
            background: linear-gradient(135deg, #f5f7fa 0%, #e8eef5 100%);
            min-height: 100vh;
        }

        .page-header {
            margin-bottom: 2.5rem;
            padding-top: 1rem;
        }

        .page-header h1 {
            color: #1f2937;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: #6b7280;
            font-size: 0.95rem;
        }

        /* Approval Card Styling */
        .approval-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            border: none;
            overflow: hidden;
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .approval-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }

        .approval-card .card-header {
            background: linear-gradient(135deg, #0C7779 0%, #005461 100%);
            color: white;
            padding: 1.25rem;
            border: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .approval-card .card-header .customer-name {
            font-weight: 600;
            font-size: 1.05rem;
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .approval-card .card-header .badge {
            background: #fbbf24 !important;
            color: #92400e !important;
            padding: 0.5rem 0.875rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-left: 1rem;
            flex-shrink: 0;
        }

        .approval-card .card-body {
            padding: 1.5rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .order-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #e5e9f0;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            color: #9ca3af;
            font-size: 0.85rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }

        .info-value {
            color: #1f2937;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .notes-box {
            background: #fef3c7;
            border: 1px solid #fcd34d;
            border-radius: 8px;
            padding: 0.875rem;
            margin-bottom: 1.5rem;
        }

        .notes-box small {
            color: #92400e;
            font-size: 0.85rem;
            line-height: 1.5;
        }

        /* Items & Stock Table */
        .items-section {
            margin-bottom: 1.5rem;
        }

        .items-section h6 {
            color: #0C7779;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .items-table {
            font-size: 0.85rem;
            border-collapse: collapse;
        }

        .items-table thead {
            background: #f0f8f7;
            border-bottom: 2px solid #b3e5db;
        }

        .items-table thead th {
            color: #0C7779;
            font-weight: 600;
            padding: 0.75rem 0.5rem;
            text-align: center;
            border: none;
        }

        .items-table tbody td {
            padding: 0.75rem 0.5rem;
            text-align: center;
            border-bottom: 1px solid #e9ecef;
        }

        .items-table tbody td:first-child {
            text-align: left;
            font-weight: 600;
            color: #1f2937;
        }

        .items-table .status-ok {
            color: #059669;
            font-weight: 600;
        }

        .items-table .status-warning {
            color: #dc2626;
            font-weight: 600;
        }

        .items-table .badge-ok {
            background: #dcfce7;
            color: #166534;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .items-table .badge-warning {
            background: #fee2e2;
            color: #991b1b;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-top: auto;
        }

        .btn-approve {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: white;
            border: none;
            padding: 0.75rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-approve:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
            color: white;
        }

        .btn-correction {
            background: #fbbf24;
            color: #92400e;
            border: none;
            padding: 0.75rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            flex: 1;
        }

        .btn-correction:hover {
            background: #f59e0b;
            color: white;
            transform: translateY(-2px);
        }

        .btn-reject {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
            padding: 0.75rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            flex: 1;
        }

        .btn-reject:hover {
            background: #dc2626;
            color: white;
            border-color: #dc2626;
            transform: translateY(-2px);
        }

        .btn-group-horizontal {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }

        /* Empty State */
        .empty-state {
            background: white;
            border-radius: 12px;
            padding: 3rem 2rem;
            text-align: center;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }

        .empty-state i {
            font-size: 4rem;
            color: #10b981;
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            color: #1f2937;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: #9ca3af;
        }

        /* History Section */
        .history-section {
            margin-top: 3rem;
        }

        .history-header {
            color: #1f2937;
            font-weight: 700;
            font-size: 1.25rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .history-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            border: none;
            overflow: hidden;
        }

        .history-table {
            margin-bottom: 0;
        }

        .history-table thead {
            background: #f0f8f7;
            border-bottom: 2px solid #b3e5db;
        }

        .history-table thead th {
            color: #0C7779;
            font-weight: 600;
            padding: 1rem 0.75rem;
            border: none;
        }

        .history-table tbody tr {
            border-bottom: 1px solid #e9ecef;
            transition: background 0.2s ease;
        }

        .history-table tbody tr:hover {
            background: #f8fffe;
        }

        .history-table tbody td {
            padding: 1rem 0.75rem;
            vertical-align: middle;
        }

        .empty-history {
            padding: 2rem;
            text-align: center;
            color: #9ca3af;
        }

        /* Modal Styling */
        .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
        }

        .modal-header {
            border-bottom: 1px solid #e9ecef;
            padding: 1.5rem;
        }

        .modal-title {
            font-weight: 700;
            font-size: 1.25rem;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            border-top: 1px solid #e9ecef;
            padding: 1.5rem;
        }
    </style>
@endsection

@section('content')
<div class="page-header">
    <h1>
        <i class="bi bi-clipboard-check" style="color: #0C7779;"></i> Pending Approvals
    </h1>
    <p class="mb-0">Review dan setujui pesanan delivery yang menunggu persetujuan</p>
</div>

<div class="container-fluid">
    @if($pendingOrders->isEmpty())
        <div class="empty-state">
            <i class="bi bi-check-circle"></i>
            <h3>Tidak Ada Pesanan Menunggu</h3>
            <p>Semua pesanan telah diproses. Kerja bagus!</p>
        </div>
    @else
        <div class="row g-4">
            @foreach($pendingOrders as $order)
            <div class="col-md-6 col-xl-4">
                <div class="approval-card">
                    <div class="card-header">
                        <span class="customer-name">{{ $order->customer_name }}</span>
                        <span class="badge">
                            <i class="bi bi-hourglass-split"></i> Pending
                        </span>
                    </div>
                    <div class="card-body">
                        <!-- Order Info -->
                        <div class="order-info">
                            <div class="info-item">
                                <span class="info-label">Sales</span>
                                <span class="info-value">{{ $order->salesUser->name ?? 'Unknown' }}</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Delivery Date</span>
                                <span class="info-value">{{ $order->delivery_date->format('d M Y') }}</span>
                            </div>
                        </div>

                        <!-- Notes -->
                        @if($order->notes)
                        <div class="notes-box">
                            <i class="bi bi-chat-quote"></i>
                            <small>{{ $order->notes }}</small>
                        </div>
                        @endif

                        <!-- Items & Stock Table -->
                        @if($order->items->count() > 0)
                        <div class="items-section">
                            <h6>
                                <i class="bi bi-box-seam"></i> Items & Stock
                            </h6>
                            <div style="overflow-x: auto;">
                                <table class="items-table" style="width: 100%;">
                                    <thead>
                                        <tr>
                                            <th style="text-align: left;">Part</th>
                                            <th>Required</th>
                                            <th>Available</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($order->items as $item)
                                        <tr>
                                            <td style="text-align: left;">{{ $item->part_number }}</td>
                                            <td>{{ $item->quantity }}</td>
                                            <td class="@if($item->stock_warning) status-warning @else status-ok @endif">
                                                {{ $item->available_stock ?? 0 }}
                                            </td>
                                            <td>
                                                @if($item->stock_warning)
                                                    <span class="badge-warning">⚠ Low</span>
                                                @else
                                                    <span class="badge-ok">✓ OK</span>
                                                @endif
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @endif

                        <!-- Action Buttons -->
                        <div class="action-buttons">
                            <button class="btn-approve" onclick="openStatusModal({{ $order->id }}, 'approved')">
                                <i class="bi bi-check-circle"></i> Setujui
                            </button>
                            <div class="btn-group-horizontal">
                                <button class="btn-correction" onclick="openStatusModal({{ $order->id }}, 'correction')">
                                    <i class="bi bi-pencil"></i> Koreksi
                                </button>
                                <button class="btn-reject" onclick="openStatusModal({{ $order->id }}, 'rejected')">
                                    <i class="bi bi-x-circle"></i> Tolak
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    @endif

    <!-- History Section -->
    <div class="history-section">
        <h4 class="history-header">
            <i class="bi bi-clock-history"></i> Riwayat Persetujuan
        </h4>
        <div class="history-card">
            @if($historyOrders->isEmpty())
                <div class="empty-history">
                    <i class="bi bi-inbox" style="font-size: 2rem; display: block; margin-bottom: 0.5rem;"></i>
                    Belum ada riwayat persetujuan
                </div>
            @else
                <div class="table-responsive">
                    <table class="table history-table">
                        <thead>
                            <tr>
                                <th style="width: 15%;">Tanggal</th>
                                <th style="width: 20%;">Pelanggan</th>
                                <th style="width: 15%;">Sales</th>
                                <th style="width: 15%;">Status</th>
                                <th style="width: 35%;">Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($historyOrders as $order)
                                <tr>
                                    <td>{{ $order->updated_at->format('d M Y H:i') }}</td>
                                    <td><strong>{{ $order->customer_name }}</strong></td>
                                    <td>{{ $order->salesUser->name ?? 'Unknown' }}</td>
                                    <td>
                                        @if($order->status == 'approved')
                                            <span class="badge" style="background: #dcfce7; color: #166534;">
                                                <i class="bi bi-check2-circle"></i> Disetujui
                                            </span>
                                        @elseif($order->status == 'rejected')
                                            <span class="badge" style="background: #fee2e2; color: #991b1b;">
                                                <i class="bi bi-x-circle"></i> Ditolak
                                            </span>
                                        @elseif($order->status == 'correction')
                                            <span class="badge" style="background: #fef3c7; color: #92400e;">
                                                <i class="bi bi-pencil-square"></i> Koreksi
                                            </span>
                                        @elseif($order->status == 'completed')
                                            <span class="badge" style="background: #e0f2fe; color: #0369a1;">
                                                <i class="bi bi-check-all"></i> Selesai
                                            </span>
                                        @elseif($order->status == 'processing')
                                            <span class="badge" style="background: #dbeafe; color: #1e40af;">
                                                <i class="bi bi-arrow-repeat"></i> Proses
                                            </span>
                                        @else
                                            <span class="badge" style="background: #f3f4f6; color: #6b7280;">{{ ucfirst($order->status) }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <small class="text-muted">{{ $order->notes ? Str::limit($order->notes, 50) : '-' }}</small>
                                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#history-detail-{{ $order->id }}">
                                                <i class="bi bi-eye"></i> Detail
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr class="collapse" id="history-detail-{{ $order->id }}">
                                    <td colspan="5" class="bg-light">
                                        <div class="p-3">
                                            <div class="fw-bold mb-2">Detail Item</div>
                                            <ul class="mb-0">
                                                @foreach($order->items as $item)
                                                    <li>{{ $item->part_number }} - {{ $item->quantity }} PCS</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Status Update Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="statusForm" method="POST">
            @csrf
            <input type="hidden" name="status" id="modalStatusInput">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="statusModalLabel">Update Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="statusConfirmationText"></p>
                    <div id="impactWarning" class="alert alert-warning d-none"></div>
                    
                    <div class="mb-3" id="noteSection">
                        <label class="form-label">Catatan / Alasan</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Masukkan alasan atau catatan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Konfirmasi</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    function openStatusModal(orderId, status) {
        const modal = new bootstrap.Modal(document.getElementById('statusModal'));
        const form = document.getElementById('statusForm');
        const statusInput = document.getElementById('modalStatusInput');
        const noteSection = document.getElementById('noteSection');
        const confirmationText = document.getElementById('statusConfirmationText');
        const impactWarning = document.getElementById('impactWarning');
        const title = document.getElementById('statusModalLabel');

        form.action = `/delivery-stock/${orderId}/status`;
        statusInput.value = status;

        if (status === 'approved') {
            title.innerText = 'Setujui Pesanan Delivery';
            title.className = 'modal-title text-success';
            confirmationText.innerText = 'Mengecek dampak jadwal...';
            noteSection.style.display = 'none';
            impactWarning.classList.add('d-none');
            impactWarning.innerHTML = '';

            fetch(`/delivery-stock/${orderId}/approval-impact`)
                .then((response) => response.json())
                .then((data) => {
                    const impacted = data.impacted || [];
                    if (impacted.length === 0) {
                        confirmationText.innerText = 'Yakin ingin menyetujui pesanan delivery ini?';
                        impactWarning.classList.add('d-none');
                        impactWarning.innerHTML = '';
                        return;
                    }

                    const itemsHtml = impacted.map((item) => {
                        const parts = (item.short_parts || []).map((part) => {
                            return `${part.part_number} (${part.can_fulfill}/${part.required})`;
                        }).join(', ');
                        return `<li>#${item.id} - ${item.customer_name} (${item.delivery_date ?? '-'})<br><small>Parts: ${parts}</small></li>`;
                    }).join('');

                    confirmationText.innerText = 'Persetujuan ini akan menggeser jadwal dan membuat pesanan berikut tidak terpenuhi:';
                    impactWarning.innerHTML = `<ul class="mb-0">${itemsHtml}</ul>`;
                    impactWarning.classList.remove('d-none');
                })
                .catch(() => {
                    confirmationText.innerText = 'Yakin ingin menyetujui pesanan delivery ini?';
                    impactWarning.classList.add('d-none');
                    impactWarning.innerHTML = '';
                });
        } else if (status === 'correction') {
            title.innerText = 'Minta Koreksi';
            title.className = 'modal-title text-warning';
            confirmationText.innerText = 'Sebutkan apa yang perlu dikoreksi:';
            noteSection.style.display = 'block';
            noteSection.querySelector('textarea').required = true;
            impactWarning.classList.add('d-none');
            impactWarning.innerHTML = '';
        } else if (status === 'rejected') {
            title.innerText = 'Tolak Pesanan';
            title.className = 'modal-title text-danger';
            confirmationText.innerText = 'Yakin ingin menolak pesanan ini? Berikan alasan penolakan.';
            noteSection.style.display = 'block';
            noteSection.querySelector('textarea').required = true;
            impactWarning.classList.add('d-none');
            impactWarning.innerHTML = '';
        }

        modal.show();
    }
</script>
@endsection