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
    
                        <div class="alert alert-info">
                            <small><i class="bi bi-info-circle"></i> Items from this delivery order are now managed through the picking session system.</small>
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
        // Items management has been moved to the picking session system
    });
</script>
@endsection
