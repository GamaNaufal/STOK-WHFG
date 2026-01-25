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
                            <th style="width: 10%">Status</th>
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
                                            <span class="{{ $item->fulfilled_quantity >= $item->quantity ? 'text-success' : 'fw-bold' }}">
                                                Qty: {{ $item->fulfilled_quantity }} / {{ $item->quantity }}
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>
                                @if($order->notes)
                                    <div class="small text-muted mt-1 fst-italic">Note: {{ $order->notes }}</div>
                                @endif
                            </td>
                            <td>
                                @if($order->status == 'completed')
                                    <span class="badge bg-success">Completed</span>
                                @else
                                    <span class="badge bg-primary">Approved</span>
                                @endif
                            </td>
                            @if(Auth::user()->role === 'warehouse_operator' || Auth::user()->role === 'admin')
                            <td class="text-end">
                                <a href="{{ route('delivery.fulfill', $order->id) }}" class="btn btn-sm btn-dark">
                                    <i class="bi bi-box-seam"></i> Process
                                </a>
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
