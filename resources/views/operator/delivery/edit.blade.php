@extends('shared.layouts.app')

@section('styles')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .item-row {
            background: #f8f9fa;
            padding: 0.875rem;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        .btn-remove {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
            border-radius: 6px;
            padding: 0.5rem;
        }
        .btn-remove:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .btn-add {
            background: #e0f5f3;
            color: #0C7779;
            border: 1px solid #b3e5db;
            font-weight: 500;
            border-radius: 6px;
            padding: 0.5rem 1rem;
        }
    </style>
@endsection

@section('content')
<div class="container-fluid">
    <!-- Modern Gradient Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); 
                        color: white; 
                        padding: 40px 30px; 
                        border-radius: 12px; 
                        box-shadow: 0 8px 24px rgba(245, 158, 11, 0.15);">
                <div>
                    <h1 class="h2" style="margin: 0 0 10px 0; font-weight: 700;">
                        <i class="bi bi-pencil-square"></i> Edit Request (Correction)
                    </h1>
                    <p style="margin: 0; opacity: 0.95; font-size: 15px;">
                        Perbaiki delivery request yang sudah dibuat
                    </p>
                </div>
            </div>
        </div>
    </div>

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
                            <small><i class="bi bi-info-circle"></i> Anda dapat memperbarui No Part dan Qty untuk koreksi sebelum diajukan kembali.</small>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label mb-0">Item Barang</label>
                                <button type="button" class="btn btn-sm btn-add" id="add-item-btn">
                                    <i class="bi bi-plus-lg"></i> Tambah Item
                                </button>
                            </div>

                            <div id="items-container" class="space-y-2">
                                @foreach($order->items as $index => $item)
                                    <div class="row g-2 item-row">
                                        <div class="col-lg-6">
                                            <select name="items[{{ $index }}][part_number]" class="form-control form-select part-select" required>
                                                <option value="">Pilih No Part</option>
                                                @foreach($partNumbers as $partNumber)
                                                    <option value="{{ $partNumber }}" {{ $item->part_number === $partNumber ? 'selected' : '' }}>
                                                        {{ $partNumber }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-lg-4">
                                            <input type="number" name="items[{{ $index }}][quantity]" class="form-control" placeholder="Qty" min="1" value="{{ $item->quantity }}" required>
                                        </div>
                                        <div class="col-lg-2">
                                            <button type="button" class="btn btn-remove remove-item w-100" title="Hapus item">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
    
                        <div class="mb-3">
                            <label class="form-label">Notes (optional)</label>
                            <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                        </div>
    
                        <button type="submit" class="btn btn-warning w-100">Submit Correction</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

@section('scripts')
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let itemIndex = {{ $order->items->count() }};
            const container = document.getElementById('items-container');
            const addBtn = document.getElementById('add-item-btn');

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

            // Add SweetAlert2 confirmation before form submission
            document.querySelector('form')?.addEventListener('submit', function(e) {
                e.preventDefault();
                const form = this;
                const items = form.querySelectorAll('.item-row').length;
                const customerName = form.querySelector('[name="customer_name"]')?.value || '';
                
                if (!customerName || items === 0) {
                    WarehouseAlert.error({
                        title: 'Data Tidak Lengkap',
                        message: 'Harap isi nama customer dan minimal 1 item pesanan'
                    });
                    return;
                }

                WarehouseAlert.info({
                    title: 'Konfirmasi Submit Correction',
                    message: 'Perubahan pada delivery order akan dikirim untuk <strong style="color: #f59e0b;">review ulang oleh PPC</strong>.',
                    details: {
                        'Customer': customerName,
                        'Jumlah Item': `${items} item`,
                        'Status': 'Menunggu approval PPC'
                    },
                    infoText: 'Order akan kembali ke status pending setelah submit.',
                    confirmText: 'Submit Correction',
                    onConfirm: () => {
                        form.submit();
                    }
                });
            });
        });
    </script>
@endsection
@endsection
