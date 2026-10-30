@extends('shared.layouts.app')

@section('styles')
    <link href="{{ asset('vendor/select2-4.1.0-rc.0/dist/css/select2.min.css') }}" rel="stylesheet" />
    <style>
        .delivery-page {
            background: linear-gradient(135deg, #f5f7fa 0%, #e8eef5 100%);
            min-height: 100vh;
        }
        
        .form-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            border: none;
            overflow: hidden;
        }
        
        .form-card .card-header {
            background: linear-gradient(135deg, #0C7779 0%, #005461 100%);
            color: white;
            padding: 1.5rem;
            border: none;
        }
        
        .form-card .card-header h6 {
            font-weight: 600;
            font-size: 1.1rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-card .card-body {
            padding: 2rem;
        }
        
        .history-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            border: none;
        }
        
        .history-card .card-header {
            background-color: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
            padding: 1.5rem;
        }
        
        .history-card .card-header h6 {
            color: #0C7779;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-label {
            font-weight: 500;
            color: #1f2937;
            margin-bottom: 0.75rem;
        }
        
        .form-control, .form-select {
            border: 1.5px solid #e5e9f0;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #0C7779;
            box-shadow: 0 0 0 3px rgba(12, 119, 121, 0.1);
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #0C7779 0%, #005461 100%);
            color: white;
            border: none;
            font-weight: 600;
            padding: 0.875rem 2rem;
            border-radius: 8px;
            width: 100%;
            transition: all 0.3s ease;
            font-size: 1rem;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(12, 119, 121, 0.3);
            color: white;
        }
        
        .btn-add {
            background: #e0f5f3;
            color: #0C7779;
            border: 1px solid #b3e5db;
            font-weight: 500;
            border-radius: 6px;
            padding: 0.5rem 1rem;
            transition: all 0.3s ease;
        }
        
        .btn-add:hover {
            background: #0C7779;
            color: white;
        }
        
        .item-row {
            background: #f8f9fa;
            padding: 0.875rem;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            transition: all 0.2s ease;
        }
        
        .item-row:hover {
            background: #f0f8f7;
            border-color: #b3e5db;
        }
        
        .btn-remove {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
            border-radius: 6px;
            padding: 0.5rem;
            transition: all 0.2s ease;
        }
        
        .btn-remove:hover:not(:disabled) {
            background: #dc2626;
            color: white;
        }
        
        .btn-remove:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .table-responsive {
            border-radius: 8px;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table thead th {
            background-color: #f0f8f7;
            color: #0C7779;
            font-weight: 600;
            border-color: #d4e8e6;
            padding: 1rem 0.75rem;
            font-size: 0.9rem;
        }
        
        .table tbody tr {
            border-color: #e9ecef;
            transition: all 0.2s ease;
        }
        
        .table tbody tr:hover {
            background-color: #f8fffe;
        }
        
        .table tbody td {
            padding: 1rem 0.75rem;
            vertical-align: middle;
        }
        
        .badge {
            padding: 0.5rem 0.875rem;
            font-weight: 500;
            border-radius: 6px;
            font-size: 0.85rem;
        }
        
        .badge.bg-warning {
            background: #fef3c7 !important;
            color: #b45309 !important;
        }
        
        .badge.bg-primary {
            background: #dbeafe !important;
            color: #1e40af !important;
        }
        
        .badge.bg-danger {
            background: #fee2e2 !important;
            color: #dc2626 !important;
        }
        
        .badge.bg-success {
            background: #dcfce7 !important;
            color: #166534 !important;
        }
        
        .alert-correction {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            border-radius: 8px;
            padding: 0.875rem;
            margin-top: 0.75rem;
        }
        
        .btn-edit-correction {
            background: #fbbf24;
            color: white;
            border: none;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            margin-top: 0.75rem;
            transition: all 0.2s ease;
        }
        
        .btn-edit-correction:hover {
            background: #f59e0b;
            color: white;
        }
        
        .page-header {
            margin-bottom: 2rem;
            padding-top: 1rem;
        }
        
        .page-header h1 {
            color: #1f2937;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .select2-container--default .select2-selection--single {
            border: 1.5px solid #e5e9f0;
            border-radius: 8px;
            height: auto;
            padding: 0.375rem 0;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            padding: 0.5rem 1rem;
            color: #374151;
        }
        
        .select2-dropdown {
            border-radius: 8px;
            border: 1.5px solid #e5e9f0;
        }
    </style>
    <link rel="stylesheet" href="{{ asset('css/pages/sales-delivery-order.css') }}">
@endsection

@section('content')
@php
    $initialItems = collect(old('items', [['part_number' => '', 'quantity' => '']]));
    if ($initialItems->isEmpty()) $initialItems = collect([['part_number' => '', 'quantity' => '']]);
@endphp
<div class="page-header sales-order-page-header">
    <span class="sales-order-eyebrow">Sales / Permintaan delivery</span>
    <h1>Sales Input & History</h1>
    <p>Buat pesanan baru untuk ditinjau PPC dan pantau permintaan yang sudah Anda kirim.</p>
</div>

<div class="container-fluid sales-order-page">
    <div class="row g-4">
        <!-- Form Section -->
        <div class="col-xl-6">
            @if(auth()->user()->canAccess('delivery_order'))
                <form action="{{ route('delivery.store') }}" method="POST" class="form-card sales-order-form"
                    id="sales-order-form" data-sales-order-form="create">
                    @csrf
                    <div class="card-header">
                        <span class="sales-order-card-icon"><i class="bi bi-plus-lg" aria-hidden="true"></i></span>
                        <div>
                            <h2>Pesanan delivery baru</h2>
                            <p>Lengkapi detail dan kebutuhan part sebelum mengirim ke PPC.</p>
                        </div>
                    </div>
                    <div class="card-body">
                        @include('operator.delivery.partials.sales-order-fields')
                        <div class="sales-order-submit-panel">
                            <p><i class="bi bi-info-circle" aria-hidden="true"></i> Pesanan akan berstatus <strong>menunggu</strong> sampai PPC memberi keputusan.</p>
                            <button type="submit" class="btn btn-primary sales-order-submit"><i class="bi bi-send" aria-hidden="true"></i> Kirim pesanan</button>
                        </div>
                    </div>
                </form>
            @else
                <div class="form-card">
                    <div class="card-header">
                        <h6>
                            <i class="bi bi-eye"></i> Mode Lihat Saja
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info mb-0">
                            <i class="bi bi-info-circle"></i> Anda sedang dalam mode lihat saja. PPC tidak dapat membuat atau mengubah pesanan.
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-xl-6">
            <section class="history-card sales-order-history" aria-labelledby="sales-history-title">
                <div class="card-header">
                    <div>
                        <span class="sales-order-eyebrow">Aktivitas Sales</span>
                        <h2 id="sales-history-title">Riwayat pesanan</h2>
                        <p>Hingga 50 pesanan terbaru milik Anda.</p>
                    </div>
                    <span class="sales-history-count">{{ $myOrders->count() }} ditampilkan</span>
                </div>
                <div class="sales-history-scroll" tabindex="0" role="region" aria-label="Riwayat pesanan Sales, geser untuk melihat semua kolom">
                        <table class="table sales-history-table {{ $myOrders->isEmpty() ? 'is-empty' : '' }}">
                            <caption class="visually-hidden">Tanggal delivery, pelanggan, status, dan rincian hingga 50 pesanan terbaru.</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Order / tanggal delivery</th>
                                    <th scope="col">Pelanggan / status</th>
                                    <th scope="col">Rincian &amp; tindakan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($myOrders as $order)
                                @php
                                    $historyStatus = match ($order->status) {
                                        'pending' => ['Menunggu PPC', 'pending', 'bi-clock'],
                                        'approved' => ['Disetujui', 'approved', 'bi-check-circle'],
                                        'correction' => ['Perlu koreksi', 'correction', 'bi-exclamation-circle'],
                                        'completed' => ['Selesai', 'completed', 'bi-check2-all'],
                                        'rejected' => ['Ditolak', 'rejected', 'bi-x-circle'],
                                        'partial' => ['Terbagi', 'partial', 'bi-diagram-3'],
                                        default => [ucfirst(str_replace('_', ' ', $order->status)), 'neutral', 'bi-circle'],
                                    };
                                @endphp
                                <tr class="sales-history-main-row">
                                    <th scope="row" class="sales-history-order-cell">
                                        <strong>Order #{{ $order->id }}</strong>
                                        <time datetime="{{ $order->delivery_date->format('Y-m-d') }}">{{ $order->delivery_date->format('d M Y') }}</time>
                                    </th>
                                    <td class="sales-history-customer-cell">
                                        <strong>{{ $order->customer_name }}</strong>
                                        <span class="sales-history-status is-{{ $historyStatus[1] }}">
                                            <i class="bi {{ $historyStatus[2] }}" aria-hidden="true"></i>{{ $historyStatus[0] }}
                                        </span>
                                    </td>
                                    <td class="sales-history-action-cell">
                                        <span class="sales-history-item-count">{{ $order->items->count() }} item</span>
                                        @if($order->status === 'correction')
                                            <p class="sales-history-note"><strong>Catatan PPC:</strong> {{ $order->notes ?: 'Periksa kembali pesanan sebelum mengirim ulang.' }}</p>
                                        @elseif($order->status === 'rejected' && $order->notes)
                                            <p class="sales-history-note"><strong>Catatan PPC:</strong> {{ $order->notes }}</p>
                                        @endif
                                        <div class="sales-history-actions">
                                            <button class="btn btn-outline-secondary sales-history-detail-toggle" type="button"
                                                data-bs-toggle="collapse" data-bs-target="#order-detail-{{ $order->id }}"
                                                aria-controls="order-detail-{{ $order->id }}" aria-expanded="false"
                                                aria-label="Lihat item Order #{{ $order->id }}">
                                                <i class="bi bi-list-ul" aria-hidden="true"></i> Lihat item
                                            </button>
                                            @if(auth()->user()->canAccess('delivery_order'))
                                                @if($order->status === 'correction')
                                                    <a href="{{ route('delivery.edit', $order->id) }}" class="btn sales-history-edit">
                                                        <i class="bi bi-pencil-square" aria-hidden="true"></i> Edit koreksi
                                                    </a>
                                                @endif
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                <tr class="sales-history-detail-row">
                                    <td colspan="3">
                                        <div class="collapse sales-history-detail" id="order-detail-{{ $order->id }}">
                                            <div class="sales-history-detail-heading">
                                                <strong>Item Order #{{ $order->id }}</strong>
                                                <span>{{ $order->items->count() }} nomor part</span>
                                            </div>
                                            <ul class="sales-history-item-list">
                                                @foreach($order->items as $item)
                                                    <li><span>{{ $item->part_number }}</span><strong>{{ number_format($item->quantity, 0, ',', '.') }} PCS</strong></li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr class="sales-history-empty-row">
                                    <td colspan="3">
                                        <i class="bi bi-inbox" aria-hidden="true"></i>
                                        <strong>Belum ada pesanan</strong>
                                        <span>Pesanan yang Anda kirim akan muncul di sini.</span>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                </div>
            </section>
        </div>
    </div>
</div>

@endsection

@section('scripts')
    <script src="{{ asset('vendor/jquery-3.7.1/dist/jquery.min.js') }}"></script>
    <script src="{{ asset('vendor/select2-4.1.0-rc.0/dist/js/select2.min.js') }}"></script>
    <script src="{{ asset('js/pages/sales-delivery-order.js') }}"></script>
@endsection
