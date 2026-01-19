@extends('shared.layouts.app')

@section('title', 'Edit Pallet - Warehouse FG Yamato')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <a href="{{ route('pallet-input.index') }}" class="btn btn-outline-secondary mb-3">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
        <h1 class="h2">
            <i class="bi bi-pencil-square"></i> Edit Pallet
        </h1>
        <p class="text-muted">Update data pallet - {{ $pallet->pallet_number }}</p>
    </div>
</div>

<div class="row">
    <div class="col-md-10 mx-auto">
        <div class="card shadow-sm border-0" style="border-left: 4px solid #0C7779;">
            <div class="card-body">
                <form method="POST" action="{{ route('pallet-input.update', $pallet->id) }}" id="palletForm">
                    @csrf
                    @method('PUT')

                    <!-- Nomor Pallet -->
                    <div class="mb-4">
                        <label class="form-label fw-bold">
                            <i class="bi bi-box2"></i> Nomor Pallet *
                        </label>
                        <input type="text" class="form-control @error('pallet_number') is-invalid @enderror" 
                               name="pallet_number" value="{{ old('pallet_number', $pallet->pallet_number) }}" 
                               placeholder="Contoh: PLT-001-2026" required autofocus>
                        @error('pallet_number')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Items Section -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">
                                <i class="bi bi-plus-circle"></i> Detail Items dalam Pallet
                            </h5>
                            <button type="button" class="btn btn-sm" id="addItemBtn" style="background-color: #249E94; color: white; border: none;">
                                <i class="bi bi-plus-lg"></i> Tambah Item
                            </button>
                        </div>

                        <div id="itemsContainer">
                            <!-- Items akan ditambahkan di sini -->
                        </div>

                        @error('items.*')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Info Box -->
                    <div class="alert border-0" role="alert" style="background-color: #e0f5f3; color: #0C7779; border-left: 4px solid #0C7779;">
                        <i class="bi bi-info-circle"></i> 
                        <strong>Catatan:</strong> Perubahan data akan langsung mempengaruhi informasi di warehouse operator.
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="{{ route('pallet-input.index') }}" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Batal
                        </a>
                        <button type="submit" class="btn btn-lg" style="background-color: #0C7779; color: white; border: none;">
                            <i class="bi bi-check-circle"></i> Perbarui Pallet
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<template id="itemTemplate">
    <div class="card mb-3 item-card border" style="border-left: 4px solid #0C7779;">
        <div class="card-header d-flex justify-content-between align-items-center" style="background-color: #f5f7fa; color: #0C7779;">
            <span class="item-title fw-bold">Item <span class="item-number">1</span></span>
            <button type="button" class="btn btn-sm" style="background-color: #e74c3c; color: white; border: none; remove-item-btn" class="remove-item-btn">
                <i class="bi bi-trash"></i> Hapus
            </button>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label fw-bold">
                            <i class="bi bi-tag"></i> Nomor Part (SKU) *
                        </label>
                        <input type="text" class="form-control" name="items[INDEX][part_number]" 
                               placeholder="Contoh: PN-A001" required>
                        <small class="text-muted">Identitas unik dari produk</small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label fw-bold">
                            <i class="bi bi-box"></i> Jumlah Box *
                        </label>
                        <input type="number" class="form-control" name="items[INDEX][box_quantity]" 
                               placeholder="Contoh: 20" min="1" required>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-0">
                        <label class="form-label fw-bold">
                            <i class="bi bi-stack"></i> Jumlah PCS *
                        </label>
                        <input type="number" class="form-control" name="items[INDEX][pcs_quantity]" 
                               placeholder="Contoh: 200" min="1" required>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

@endsection

@section('scripts')
<script>
    let itemCount = 0;
    const existingItems = @json($pallet->items);

    function createItemElement(index, data = null) {
        const template = document.getElementById('itemTemplate');
        const clone = template.content.cloneNode(true);
        const html = clone.innerHTML.replace(/\[INDEX\]/g, `[${index}]`);
        
        const div = document.createElement('div');
        div.innerHTML = html;
        const card = div.querySelector('.item-card');
        
        if (data) {
            card.querySelector('input[name*="part_number"]').value = data.part_number;
            card.querySelector('input[name*="box_quantity"]').value = data.box_quantity;
            card.querySelector('input[name*="pcs_quantity"]').value = data.pcs_quantity;
        }
        
        card.querySelector('.remove-item-btn').addEventListener('click', function(e) {
            e.preventDefault();
            card.remove();
            updateItemNumbers();
        });
        
        return card;
    }

    function updateItemNumbers() {
        document.querySelectorAll('.item-card').forEach((card, index) => {
            const itemNum = index + 1;
            card.querySelector('.item-number').textContent = itemNum;
        });
    }

    document.getElementById('addItemBtn').addEventListener('click', function() {
        const newIndex = document.querySelectorAll('.item-card').length;
        const card = createItemElement(newIndex);
        document.getElementById('itemsContainer').appendChild(card);
        updateItemNumbers();
    });

    // Load existing items on page load
    window.addEventListener('DOMContentLoaded', function() {
        if (existingItems && existingItems.length > 0) {
            existingItems.forEach((item, index) => {
                const card = createItemElement(index, item);
                document.getElementById('itemsContainer').appendChild(card);
            });
            updateItemNumbers();
        } else {
            document.getElementById('addItemBtn').click();
        }
    });

    // Form validation
    document.getElementById('palletForm').addEventListener('submit', function(e) {
        const items = document.querySelectorAll('.item-card');
        if (items.length === 0) {
            e.preventDefault();
            alert('Silakan tambahkan minimal 1 item!');
        }
    });
</script>
@endsection
