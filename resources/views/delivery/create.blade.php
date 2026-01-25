@extends('shared.layouts.app')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <h1 class="h3 text-gray-800">
            <i class="bi bi-cart-plus"></i> Sales Input & History
        </h1>
    </div>
</div>

<div class="container-fluid py-2">
    <div class="row">
        <!-- New Request Form -->
        <div class="col-md-5 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 bg-primary text-white">
                    <h6 class="m-0 font-weight-bold">➕ New Delivery Request</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('delivery.store') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Customer Name</label>
                            <input type="text" name="customer_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Delivery Date</label>
                            <input type="date" name="delivery_date" class="form-control" required>
                        </div>
    
                        <div class="mb-3">
                            <label class="form-label fw-bold d-flex justify-content-between">
                                Items
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="add-item-btn">
                                    <i class="bi bi-plus"></i> Add
                                </button>
                            </label>
                            <div id="items-container">
                                <div class="row g-2 mb-2 item-row">
                                    <div class="col-7">
                                        <input type="text" name="items[0][part_number]" class="form-control form-control-sm" placeholder="Part Number" required>
                                    </div>
                                    <div class="col-3">
                                        <input type="number" name="items[0][quantity]" class="form-control form-control-sm" placeholder="Qty" min="1" required>
                                    </div>
                                    <div class="col-2">
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-item w-100"><i class="bi bi-trash"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
    
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
    
                        <button type="submit" class="btn btn-primary w-100">Submit Request</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- History -->
        <div class="col-md-7 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 bg-white">
                    <h6 class="m-0 font-weight-bold text-primary">📋 My Request History</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Status</th>
                                    <th>Items</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($myOrders as $order)
                                <tr>
                                    <td>{{ $order->delivery_date->format('d/m/Y') }}</td>
                                    <td>{{ $order->customer_name }}</td>
                                    <td>
                                        @if($order->status == 'pending') <span class="badge bg-warning text-dark">Pending</span>
                                        @elseif($order->status == 'approved') <span class="badge bg-primary">Approved</span>
                                        @elseif($order->status == 'correction') <span class="badge bg-danger">Correction Needed</span>
                                        @elseif($order->status == 'completed') <span class="badge bg-success">Completed</span>
                                        @else <span class="badge bg-secondary">{{ $order->status }}</span> 
                                        @endif

                                        @if($order->status == 'correction')
                                            <div class="alert alert-danger py-1 px-2 small mt-1 mb-0">
                                                <strong>Note:</strong> {{ $order->notes }}
                                            </div>
                                            <div class="mt-2">
                                                <a href="{{ route('delivery.edit', $order->id) }}" class="btn btn-sm btn-warning">
                                                    <i class="bi bi-pencil"></i> Edit Koreksi
                                                </a>
                                            </div>
                                        @endif
                                    </td>
                                    <td class="small">
                                        <ul class="mb-0 ps-3">
                                        @foreach($order->items as $item)
                                            <li>{{ $item->part_number }} ({{ $item->quantity }})</li>
                                        @endforeach
                                        </ul>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted">No requests found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        let itemIndex = 1;
        const container = document.getElementById('items-container');
        const addBtn = document.getElementById('add-item-btn');

        // Function to update remove buttons state
        function updateRemoveButtons() {
            const rows = container.getElementsByClassName('item-row');
            if (rows.length === 1) {
                rows[0].querySelector('.remove-item').disabled = true;
            } else {
                Array.from(rows).forEach(row => {
                    row.querySelector('.remove-item').disabled = false;
                });
            }
        }

        // Add item
        addBtn.addEventListener('click', function() {
            const row = document.createElement('div');
            row.className = 'row g-2 mb-2 item-row';
            row.innerHTML = `
                <div class="col-7">
                    <input type="text" name="items[${itemIndex}][part_number]" class="form-control form-control-sm" placeholder="Part Number" required>
                </div>
                <div class="col-3">
                    <input type="number" name="items[${itemIndex}][quantity]" class="form-control form-control-sm" placeholder="Qty" min="1" required>
                </div>
                <div class="col-2">
                    <button type="button" class="btn btn-sm btn-outline-danger remove-item w-100"><i class="bi bi-trash"></i></button>
                </div>
            `;
            container.appendChild(row);
            itemIndex++;
            updateRemoveButtons();
        });

        // Remove item
        container.addEventListener('click', function(e) {
            if(e.target.closest('.remove-item')) {
                const row = e.target.closest('.item-row');
                // Only remove if it's not the last one, or ensure at least one remains
                if (container.getElementsByClassName('item-row').length > 1) {
                    row.remove();
                    updateRemoveButtons();
                }
            }
        });

        // Initial check
        updateRemoveButtons();
    });
</script>
@endsection