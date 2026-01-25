@extends('shared.layouts.app')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <h1 class="h3 text-gray-800">
            <i class="bi bi-pencil-square"></i> Edit Request (Correction)
        </h1>
    </div>
</div>

<div class="container-fluid py-2">
    <div class="row">
        <div class="col-md-8 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3 bg-warning text-dark">
                    <h6 class="m-0 font-weight-bold">✏️ Perbaiki Delivery Request</h6>
                </div>
                <div class="card-body">
                    <form action="{{ route('delivery.update', $order->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="mb-3">
                            <label class="form-label">Customer Name</label>
                            <input type="text" name="customer_name" class="form-control" value="{{ old('customer_name', $order->customer_name) }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Delivery Date</label>
                            <input type="date" name="delivery_date" class="form-control" value="{{ old('delivery_date', $order->delivery_date->format('Y-m-d')) }}" required>
                        </div>
    
                        <div class="mb-3">
                            <label class="form-label fw-bold d-flex justify-content-between">
                                Items
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="add-item-btn">
                                    <i class="bi bi-plus"></i> Add
                                </button>
                            </label>
                            <div id="items-container">
                                @foreach($order->items as $index => $item)
                                    <div class="row g-2 mb-2 item-row">
                                        <div class="col-7">
                                            <input type="text" name="items[{{ $index }}][part_number]" class="form-control form-control-sm" placeholder="Part Number" value="{{ $item->part_number }}" required>
                                        </div>
                                        <div class="col-3">
                                            <input type="number" name="items[{{ $index }}][quantity]" class="form-control form-control-sm" placeholder="Qty" min="1" value="{{ $item->quantity }}" required>
                                        </div>
                                        <div class="col-2">
                                            <button type="button" class="btn btn-sm btn-outline-danger remove-item w-100"><i class="bi bi-trash"></i></button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
    
                        <div class="mb-3">
                            <label class="form-label">Notes (optional)</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
    
                        <button type="submit" class="btn btn-warning w-100">Submit Correction</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        let itemIndex = {{ $order->items->count() }};
        const container = document.getElementById('items-container');
        const addBtn = document.getElementById('add-item-btn');

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

        container.addEventListener('click', function(e) {
            if(e.target.closest('.remove-item')) {
                const row = e.target.closest('.item-row');
                if (container.getElementsByClassName('item-row').length > 1) {
                    row.remove();
                    updateRemoveButtons();
                }
            }
        });

        updateRemoveButtons();
    });
</script>
@endsection
