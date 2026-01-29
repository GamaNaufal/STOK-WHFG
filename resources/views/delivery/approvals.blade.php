@extends('shared.layouts.app')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <h1 class="h3 text-gray-800">
            <i class="bi bi-clipboard-check"></i> Pending Approvals
        </h1>
    </div>
</div>

<div class="container-fluid py-2">
    @if($pendingOrders->isEmpty())
        <div class="text-center py-5 bg-white shadow rounded">
            <i class="bi bi-check-circle display-1 text-success"></i>
            <p class="mt-3 lead">All Caught Up!</p>
            <p class="text-muted">No pending orders to review.</p>
        </div>
    @else
        <div class="row">
            @foreach($pendingOrders as $order)
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card shadow-sm border-warning h-100">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <strong class="text-truncate" style="max-width: 70%;">{{ $order->customer_name }}</strong>
                        <span class="badge bg-warning text-dark">Pending</span>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between">
                                <span class="text-muted small">Sales:</span>
                                <span class="fw-bold small">{{ $order->salesUser->name ?? 'Unknown' }}</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted small">Delivery Date:</span>
                                <span class="fw-bold small">{{ $order->delivery_date->format('d M Y') }}</span>
                            </div>
                        </div>
    
                        @if($order->notes)
                        <div class="alert alert-secondary py-2 px-2 mb-3">
                            <i class="bi bi-chat-quote me-1"></i> <small class="fst-italic">{{ $order->notes }}</small>
                        </div>
                        @endif

                        <!-- ITEMS & STOCK INFO -->
                        @if($order->items->count() > 0)
                        <div class="mb-3">
                            <label class="form-label fw-bold small mb-2">
                                <i class="bi bi-box-seam"></i> Items & Stock Availability
                            </label>
                            <div class="table-responsive" style="font-size: 0.85rem;">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="text-center">Part</th>
                                            <th class="text-center">Required</th>
                                            <th class="text-center">Can Fulfill</th>
                                            <th class="text-center">Available</th>
                                            <th class="text-center">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($order->items as $item)
                                        <tr>
                                            <td class="fw-bold">{{ $item->part_number }}</td>
                                            <td class="text-center">{{ $item->quantity }}</td>
                                            <td class="text-center fw-bold">
                                                <span class="@if($item->stock_warning) text-danger @else text-success @endif">
                                                    {{ $item->display_fulfilled ?? 0 }}
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                {{ $item->available_total ?? 0 }}
                                            </td>
                                            <td class="text-center">
                                                @if($item->stock_warning)
                                                    <span class="badge bg-danger">⚠ Low</span>
                                                @else
                                                    <span class="badge bg-success">✓ OK</span>
                                                @endif
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @endif
    
                        <div class="d-grid gap-2 mt-auto">
                            <button class="btn btn-success" onclick="openStatusModal({{ $order->id }}, 'approved')">
                                <i class="bi bi-check-lg"></i> Approve
                            </button>
                            <div class="btn-group w-100">
                                <button class="btn btn-outline-warning w-50" onclick="openStatusModal({{ $order->id }}, 'correction')">
                                    <i class="bi bi-pencil"></i> Correction
                                </button>
                                <button class="btn btn-outline-danger w-50" onclick="openStatusModal({{ $order->id }}, 'rejected')">
                                    <i class="bi bi-x-lg"></i> Reject
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    @endif

    <div class="mt-5">
        <h5 class="mb-3"><i class="bi bi-clock-history"></i> Approval History</h5>
        <div class="card shadow-sm">
            <div class="card-body">
                @if($historyOrders->isEmpty())
                    <div class="text-muted">Belum ada riwayat approval.</div>
                @else
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>Updated At</th>
                                    <th>Customer</th>
                                    <th>Sales</th>
                                    <th>Status</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($historyOrders as $order)
                                    <tr>
                                        <td>{{ $order->updated_at->format('d M Y H:i') }}</td>
                                        <td>{{ $order->customer_name }}</td>
                                        <td>{{ $order->salesUser->name ?? 'Unknown' }}</td>
                                        <td>
                                            @if($order->status == 'approved')
                                                <span class="badge bg-primary">Approved</span>
                                            @elseif($order->status == 'rejected')
                                                <span class="badge bg-danger">Rejected</span>
                                            @elseif($order->status == 'correction')
                                                <span class="badge bg-warning text-dark">Correction</span>
                                            @elseif($order->status == 'completed')
                                                <span class="badge bg-success">Completed</span>
                                            @elseif($order->status == 'processing')
                                                <span class="badge bg-info">Processing</span>
                                            @else
                                                <span class="badge bg-secondary">{{ $order->status }}</span>
                                            @endif
                                        </td>
                                        <td class="small text-muted" style="max-width: 320px;">
                                            {{ $order->notes ?? '-' }}
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
                        <label class="form-label">Notes / Reason</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Enter reason for correction/rejection..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Confirm Action</button>
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

        // Set Form Action
        form.action = `/delivery-stock/${orderId}/status`;
        statusInput.value = status;

        // UI Updates based on status
        if (status === 'approved') {
            title.innerText = 'Approve Delivery';
            title.className = 'modal-title text-success';
            confirmationText.innerText = 'Checking schedule impact...';
            noteSection.style.display = 'none';
            impactWarning.classList.add('d-none');
            impactWarning.innerHTML = '';

            fetch(`/delivery-stock/${orderId}/approval-impact`)
                .then((response) => response.json())
                .then((data) => {
                    const impacted = data.impacted || [];
                    if (impacted.length === 0) {
                        confirmationText.innerText = 'Are you sure you want to approve this delivery schedule?';
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

                    confirmationText.innerText = 'Approval ini akan menggeser jadwal dan membuat order berikut tidak terpenuhi:';
                    impactWarning.innerHTML = `<ul class="mb-0">${itemsHtml}</ul>`;
                    impactWarning.classList.remove('d-none');
                })
                .catch(() => {
                    confirmationText.innerText = 'Are you sure you want to approve this delivery schedule?';
                    impactWarning.classList.add('d-none');
                    impactWarning.innerHTML = '';
                });
        } else if (status === 'correction') {
            title.innerText = 'Request Correction';
            title.className = 'modal-title text-warning';
            confirmationText.innerText = 'Please specify what needs to be corrected:';
            noteSection.style.display = 'block';
            noteSection.querySelector('textarea').required = true;
            impactWarning.classList.add('d-none');
            impactWarning.innerHTML = '';
        } else if (status === 'rejected') {
            title.innerText = 'Reject Order';
            title.className = 'modal-title text-danger';
            confirmationText.innerText = 'Are you sure you want to reject this order? Please provide a reason.';
            noteSection.style.display = 'block';
            noteSection.querySelector('textarea').required = true;
            impactWarning.classList.add('d-none');
            impactWarning.innerHTML = '';
        }

        modal.show();
    }
</script>
@endsection