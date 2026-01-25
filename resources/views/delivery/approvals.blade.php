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
                        
                        <div class="bg-light p-2 border rounded mb-3" style="max-height: 150px; overflow-y: auto;">
                            <ul class="mb-0 small ps-3">
                                @foreach($order->items as $item)
                                    <li>{{ $item->part_number }} - <strong>Qty: {{ $item->quantity }}</strong></li>
                                @endforeach
                            </ul>
                        </div>
    
                        @if($order->notes)
                        <div class="alert alert-secondary py-2 px-2 mb-3">
                            <i class="bi bi-chat-quote me-1"></i> <small class="fst-italic">{{ $order->notes }}</small>
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
        const title = document.getElementById('statusModalLabel');

        // Set Form Action
        form.action = `/delivery-stock/${orderId}/status`;
        statusInput.value = status;

        // UI Updates based on status
        if (status === 'approved') {
            title.innerText = 'Approve Delivery';
            title.className = 'modal-title text-success';
            confirmationText.innerText = 'Are you sure you want to approve this delivery schedule?';
            noteSection.style.display = 'none';
        } else if (status === 'correction') {
            title.innerText = 'Request Correction';
            title.className = 'modal-title text-warning';
            confirmationText.innerText = 'Please specify what needs to be corrected:';
            noteSection.style.display = 'block';
            noteSection.querySelector('textarea').required = true;
        } else if (status === 'rejected') {
            title.innerText = 'Reject Order';
            title.className = 'modal-title text-danger';
            confirmationText.innerText = 'Are you sure you want to reject this order? Please provide a reason.';
            noteSection.style.display = 'block';
            noteSection.querySelector('textarea').required = true;
        }

        modal.show();
    }
</script>
@endsection