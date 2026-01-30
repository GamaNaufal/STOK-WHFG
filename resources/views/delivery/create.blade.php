@extends('shared.layouts.app')

@section('styles')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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
@endsection

@section('content')
<div class="page-header">
    <h1 class="h2">
        <i class="bi bi-cart-plus" style="color: #0C7779;"></i> Sales Input & History
    </h1>
    <p class="text-muted mb-0">Buat pesanan delivery baru dan kelola riwayat permintaan Anda</p>
</div>

<div class="container-fluid">
    <div class="row g-4">
        <!-- Form Section -->
        <div class="col-lg-5">
            @if(in_array(auth()->user()->role, ['sales', 'admin']))
                <form action="{{ route('delivery.store') }}" method="POST" class="form-card">
                    @csrf
                    <div class="card-header">
                        <h6>
                            <i class="bi bi-plus-circle"></i> Pesanan Delivery Baru
                        </h6>
                    </div>
                    <div class="card-body">
                        <!-- Customer Name -->
                        <div class="mb-4">
                            <label class="form-label">Nama Pelanggan</label>
                            <input type="text" name="customer_name" class="form-control" placeholder="Masukkan nama pelanggan" required>
                        </div>

                        <!-- Delivery Date -->
                        <div class="mb-4">
                            <label class="form-label">Tanggal Delivery</label>
                            <input type="date" name="delivery_date" class="form-control" required>
                        </div>

                        <!-- Items Section -->
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <label class="form-label mb-0">Item Barang</label>
                                <button type="button" class="btn btn-sm btn-add" id="add-item-btn">
                                    <i class="bi bi-plus-lg"></i> Tambah Item
                                </button>
                            </div>
                            
                            <div id="items-container" class="space-y-2">
                                <div class="row g-2 item-row">
                                    <div class="col-lg-6">
                                        <select name="items[0][part_number]" class="form-control form-select part-select" required>
                                            <option value="">Pilih No Part</option>
                                            @foreach($partNumbers as $partNumber)
                                                <option value="{{ $partNumber }}">{{ $partNumber }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-lg-4">
                                        <input type="number" name="items[0][quantity]" class="form-control" placeholder="Qty" min="1" required>
                                    </div>
                                    <div class="col-lg-2">
                                        <button type="button" class="btn btn-remove remove-item w-100" title="Hapus item">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div class="mb-4">
                            <label class="form-label">Catatan / Keterangan</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="Tambahkan catatan khusus jika ada..."></textarea>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="btn btn-submit">
                            <i class="bi bi-check-circle"></i> Submit Pesanan
                        </button>
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

        <!-- History Section -->
        <div class="col-lg-7">
            <div class="history-card">
                <div class="card-header">
                    <h6>
                        <i class="bi bi-clock-history"></i> Riwayat Pesanan
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th style="width: 12%;">Tanggal</th>
                                    <th style="width: 25%;">Pelanggan</th>
                                    <th style="width: 20%;">Status</th>
                                    <th style="width: 43%;">Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($myOrders as $order)
                                <tr>
                                    <td class="fw-500">{{ $order->delivery_date->format('d M Y') }}</td>
                                    <td>{{ $order->customer_name }}</td>
                                    <td>
                                        @if($order->status == 'pending')
                                            <span class="badge bg-warning">
                                                <i class="bi bi-clock"></i> Menunggu
                                            </span>
                                        @elseif($order->status == 'approved')
                                            <span class="badge bg-primary">
                                                <i class="bi bi-check-circle"></i> Disetujui
                                            </span>
                                        @elseif($order->status == 'correction')
                                            <span class="badge bg-danger">
                                                <i class="bi bi-exclamation-circle"></i> Koreksi
                                            </span>
                                        @elseif($order->status == 'completed')
                                            <span class="badge bg-success">
                                                <i class="bi bi-check2-all"></i> Selesai
                                            </span>
                                        @else
                                            <span class="badge bg-secondary">{{ ucfirst($order->status) }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($order->status == 'correction')
                                            <div class="alert-correction">
                                                <strong>Perlu Koreksi:</strong> {{ $order->notes }}
                                            </div>
                                            @if(in_array(auth()->user()->role, ['sales', 'admin']))
                                                <a href="{{ route('delivery.edit', $order->id) }}" class="btn btn-edit-correction btn-sm">
                                                    <i class="bi bi-pencil-square"></i> Edit Koreksi
                                                </a>
                                            @endif
                                        @else
                                            <small class="text-muted">{{ $order->items->count() }} Item</small>
                                        @endif
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="text-center py-4">
                                        <i class="bi bi-inbox" style="font-size: 2rem; color: #9ca3af;"></i>
                                        <p class="text-muted mt-2 mb-0">Belum ada pesanan</p>
                                    </td>
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

@endsection

@section('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let itemIndex = 1;
            const container = document.getElementById('items-container');
            const addBtn = document.getElementById('add-item-btn');

            if (!container || !addBtn) {
                return;
            }

            function initPartSelects(context) {
                const selects = (context || document).querySelectorAll('.part-select');
                selects.forEach(select => {
                    if (!select.dataset.enhanced) {
                        window.jQuery(select).select2({
                            width: '100%',
                            placeholder: 'Pilih No Part',
                            allowClear: true,
                            minimumResultsForSearch: 0
                        });
                        select.dataset.enhanced = 'true';
                    }
                });
            }

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
                row.className = 'row g-2 item-row';
                row.innerHTML = `
                    <div class="col-lg-6">
                        <select name="items[${itemIndex}][part_number]" class="form-control form-select part-select" required>
                            <option value="">Pilih No Part</option>
                            @foreach($partNumbers as $partNumber)
                                <option value="{{ $partNumber }}">{{ $partNumber }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-4">
                        <input type="number" name="items[${itemIndex}][quantity]" class="form-control" placeholder="Qty" min="1" required>
                    </div>
                    <div class="col-lg-2">
                        <button type="button" class="btn btn-remove remove-item w-100" title="Hapus item">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                `;
                container.appendChild(row);
                itemIndex++;
                updateRemoveButtons();
                initPartSelects(row);
            });

            container.addEventListener('click', function(e) {
                if (e.target.closest('.remove-item')) {
                    const row = e.target.closest('.item-row');
                    if (container.getElementsByClassName('item-row').length > 1) {
                        row.remove();
                        updateRemoveButtons();
                    }
                }
            });

            updateRemoveButtons();
            initPartSelects(document);
        });
    </script>
@endsection