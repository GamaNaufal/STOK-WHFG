@extends('shared.layouts.app')

@section('title', 'Tambah Pallet Baru - Warehouse FG Yamato')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        @if(auth()->user()->role === 'admin')
            <a href="{{ route('pallet-input.index') }}" class="btn btn-outline-secondary mb-3">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
        @endif
        <h1 class="h2">
            <i class="bi bi-plus-circle"></i> Tambah Pallet Baru
        </h1>
        <p class="text-muted">Isi data items/part yang terdapat dalam pallet. Nomor pallet akan di-generate otomatis.</p>
    </div>
</div>

<div class="row">
    <div class="col-md-10 mx-auto">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <form method="POST" action="{{ route('pallet-input.store') }}" id="palletForm">
                    @csrf

                    <!-- Items Section -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">
                                <i class="bi bi-plus-circle"></i> Detail Items dalam Pallet
                            </h5>
                            <button type="button" class="btn btn-sm" id="addItemBtn" style="background: #0C7779; color: white; border: none;">
                                <i class="bi bi-plus-lg"></i> Tambah Item
                            </button>
                        </div>

                        <div id="itemsContainer">
                            <!-- Item akan ditambahkan di sini -->
                        </div>

                        @error('items.*')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- Info Box -->
                    <div class="alert border-0" role="alert" style="background-color: #e0f5f3; color: #0C7779; border-left: 4px solid #0C7779;">
                        <i class="bi bi-info-circle"></i> 
                        <strong>Catatan:</strong> Satu pallet bisa berisi satu atau lebih item dengan No Part yang berbeda. Nomor pallet akan di-generate otomatis dengan format PLT-YYYYMMDD-XXX.
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="reset" class="btn btn-secondary">
                            <i class="bi bi-arrow-clockwise"></i> Reset
                        </button>
                        <button type="submit" class="btn btn-lg" style="background: #0C7779; color: white; border: none;">
                            <i class="bi bi-check-circle"></i> Simpan Pallet
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
            <button type="button" class="btn btn-sm remove-item-btn" style="background-color: #e74c3c; color: white; border: none;">
                <i class="bi bi-trash"></i> Hapus
            </button>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label fw-bold">
                            <i class="bi bi-search"></i> Nomor Part (SKU) *
                        </label>
                        <input type="text" class="form-control part-search" name="items[INDEX][part_number]" 
                               placeholder="Cari atau pilih part number..." autocomplete="off" required>
                        <div class="part-dropdown dropdown-list border bg-white rounded mt-1" 
                             style="display: none; max-height: 250px; overflow-y: auto; position: relative; border-left: 4px solid #0C7779;">
                            <!-- Options akan di-populate via JS -->
                        </div>
                        <small class="text-muted d-block mt-2">Ketik untuk mencari, atau klik option di bawah</small>
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

<style>
    .dropdown-list {
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    .dropdown-list .dropdown-item {
        padding: 8px 12px;
        cursor: pointer;
        border-bottom: 1px solid #eee;
        transition: background-color 0.15s;
        font-size: 14px;
    }
    
    .dropdown-list .dropdown-item:last-child {
        border-bottom: none;
    }
    
    .dropdown-list .dropdown-item:hover,
    .dropdown-list .dropdown-item.active {
        background-color: #e0f5f3;
        color: #0C7779;
    }
</style>

@endsection

@section('scripts')
<script>
    let itemCount = 0;
    let availableParts = @json($availableParts);

    function updateItemNumbers() {
        document.querySelectorAll('.item-card').forEach((card, index) => {
            const itemNum = index + 1;
            card.querySelector('.item-number').textContent = itemNum;
            
            // Update all input names
            card.querySelectorAll('input').forEach(input => {
                const name = input.getAttribute('name');
                if (name && name.includes('[')) {
                    const baseName = name.replace(/\[\d+\]/, `[${index}]`);
                    input.setAttribute('name', baseName);
                }
            });
        });
    }

    function setupPartSearchListener(searchInput) {
        const parentDiv = searchInput.parentElement;
        const dropdown = parentDiv.querySelector('.part-dropdown');
        
        if (!dropdown) return;
        
        // Show dropdown on focus
        searchInput.addEventListener('focus', function() {
            loadPartOptions(searchInput.value, dropdown, searchInput);
            dropdown.style.display = 'block';
        });
        
        // Filter on input
        searchInput.addEventListener('input', function() {
            loadPartOptions(this.value, dropdown, searchInput);
            dropdown.style.display = 'block';
        });
        
        // Close on blur
        searchInput.addEventListener('blur', function() {
            setTimeout(() => {
                dropdown.style.display = 'none';
            }, 150);
        });
        
        // Load options on init
        loadPartOptions('', dropdown, searchInput);
    }

    function loadPartOptions(searchTerm, dropdown, searchInput) {
        let filtered = availableParts;
        
        if (searchTerm.trim()) {
            filtered = availableParts.filter(part => 
                part.toLowerCase().includes(searchTerm.toLowerCase())
            );
        }
        
        dropdown.innerHTML = '';
        
        if (filtered.length === 0 && searchTerm.trim()) {
            const noResult = document.createElement('div');
            noResult.className = 'dropdown-item text-muted';
            noResult.textContent = 'Tidak ada hasil';
            dropdown.appendChild(noResult);
            return;
        }
        
        filtered.forEach(part => {
            const item = document.createElement('div');
            item.className = 'dropdown-item';
            item.textContent = part;
            item.style.cursor = 'pointer';
            item.addEventListener('click', function(e) {
                e.stopPropagation();
                searchInput.value = part;
                dropdown.style.display = 'none';
            });
            item.addEventListener('mouseover', function() {
                this.classList.add('active');
            });
            item.addEventListener('mouseout', function() {
                this.classList.remove('active');
            });
            dropdown.appendChild(item);
        });
    }

    document.getElementById('addItemBtn').addEventListener('click', function() {
        const template = document.getElementById('itemTemplate');
        const clone = template.content.cloneNode(true);
        
        // Replace INDEX with actual index
        const newIndex = document.querySelectorAll('.item-card').length;
        
        // Perlu convert template ke string HTML dulu
        const tempDiv = document.createElement('div');
        tempDiv.appendChild(clone);
        let html = tempDiv.innerHTML;
        html = html.replace(/\[INDEX\]/g, `[${newIndex}]`);
        
        const div = document.createElement('div');
        div.innerHTML = html;
        const card = div.querySelector('.item-card');
        
        // Setup part search for new item
        const partSearch = card.querySelector('.part-search');
        if (partSearch) {
            setupPartSearchListener(partSearch);
        }
        
        // Add remove event listener
        const removeBtn = card.querySelector('.remove-item-btn');
        if (removeBtn) {
            removeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                card.remove();
                updateItemNumbers();
            });
        }
        
        document.getElementById('itemsContainer').appendChild(card);
        updateItemNumbers();
    });

    // Add first item on load
    window.addEventListener('DOMContentLoaded', function() {
        document.getElementById('addItemBtn').click();
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
