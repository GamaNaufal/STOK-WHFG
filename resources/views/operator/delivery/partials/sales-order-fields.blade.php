@if($errors->any())
    <div class="sales-order-errors" role="alert">
        <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
        <div>
            <strong>Periksa kembali data pesanan.</strong>
            <span>Kolom yang bermasalah ditandai di bawah. Item yang sudah diisi tetap tersedia.</span>
        </div>
    </div>
@endif
<p class="sales-order-inline-error sales-order-form-error" id="sales-order-form-error" role="alert" hidden></p>

<section class="sales-order-section" aria-labelledby="sales-order-detail-title">
    <div class="sales-order-section-heading">
        <span>01</span>
        <div>
            <h3 id="sales-order-detail-title">Detail pengiriman</h3>
            <p>Isi pelanggan dan tanggal kebutuhan delivery.</p>
        </div>
    </div>
    <div class="sales-order-field-grid">
        <div class="sales-order-field">
            <label for="sales-customer-name" class="form-label">Nama pelanggan</label>
            <input type="text" id="sales-customer-name" name="customer_name"
                class="form-control @error('customer_name') is-invalid @enderror"
                value="{{ old('customer_name', $order->customer_name ?? '') }}" required
                placeholder="Nama customer pada order"
                aria-invalid="{{ $errors->has('customer_name') ? 'true' : 'false' }}"
                aria-describedby="sales-customer-help @error('customer_name') sales-customer-error @enderror">
            <p id="sales-customer-help" class="sales-order-help">Nama ini akan tampil pada jadwal delivery.</p>
            @error('customer_name')<div id="sales-customer-error" class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
        </div>
        <div class="sales-order-field">
            <label for="sales-delivery-date" class="form-label">Tanggal delivery</label>
            <input type="date" id="sales-delivery-date" name="delivery_date"
                class="form-control @error('delivery_date') is-invalid @enderror"
                value="{{ old('delivery_date', isset($order) ? $order->delivery_date->format('Y-m-d') : '') }}" required
                aria-invalid="{{ $errors->has('delivery_date') ? 'true' : 'false' }}"
                aria-describedby="sales-date-help @error('delivery_date') sales-date-error @enderror">
            <p id="sales-date-help" class="sales-order-help">Tanggal yang diminta untuk pengiriman.</p>
            @error('delivery_date')<div id="sales-date-error" class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
        </div>
    </div>
</section>

<section class="sales-order-section" aria-labelledby="sales-order-items-title">
    <div class="sales-order-items-heading">
        <div class="sales-order-section-heading">
            <span>02</span>
            <div>
                <h3 id="sales-order-items-title">Kebutuhan part</h3>
                <p>Masukkan PCS yang diminta untuk setiap nomor part.</p>
            </div>
        </div>
        <button type="button" class="btn btn-outline-primary sales-order-add" id="add-item-btn">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> Tambah item
        </button>
    </div>
    <div class="sales-order-item-summary" role="status" aria-live="polite">
        <span id="sales-order-item-count">{{ $initialItems->count() }} item</span>
        <span id="sales-order-pcs-total">— PCS diminta</span>
    </div>
    @if($errors->has('items'))
        <p class="sales-order-inline-error" role="alert">{{ $errors->first('items') }}</p>
    @endif
    <p class="sales-order-inline-error" id="sales-order-items-error" role="alert" hidden></p>
    <div id="items-container" class="sales-order-items" data-next-index="{{ max(0, (int) $initialItems->keys()->max() + 1) }}">
        @foreach($initialItems as $index => $item)
            @php
                $partError = $errors->first("items.{$index}.part_number");
                $quantityError = $errors->first("items.{$index}.quantity");
            @endphp
            <div class="item-row sales-order-item-row" data-item-row>
                <div class="sales-order-item-topline">
                    <strong data-item-number>Item {{ $loop->iteration }}</strong>
                    <button type="button" class="btn btn-outline-danger remove-item" aria-label="Hapus item {{ $loop->iteration }}">
                        <i class="bi bi-trash" aria-hidden="true"></i> Hapus
                    </button>
                </div>
                <div class="sales-order-item-fields">
                    <div class="sales-order-field">
                        <label for="sales-part-{{ $index }}" class="form-label">Nomor part</label>
                        <select id="sales-part-{{ $index }}" name="items[{{ $index }}][part_number]"
                            class="form-select part-select {{ $partError ? 'is-invalid' : '' }}" required
                            aria-invalid="{{ $partError ? 'true' : 'false' }}">
                            <option value="">Pilih No Part</option>
                            @foreach($partNumbers as $partNumber)
                                <option value="{{ $partNumber }}" @selected(($item['part_number'] ?? '') === $partNumber)>{{ $partNumber }}</option>
                            @endforeach
                        </select>
                        @if($partError)<div class="sales-order-inline-error" role="alert">{{ $partError }}</div>@endif
                    </div>
                    <div class="sales-order-field">
                        <label for="sales-quantity-{{ $index }}" class="form-label">Jumlah diminta</label>
                        <div class="sales-order-quantity-input">
                            <input type="number" id="sales-quantity-{{ $index }}" name="items[{{ $index }}][quantity]"
                                class="form-control {{ $quantityError ? 'is-invalid' : '' }}" min="1" step="1" inputmode="numeric"
                                value="{{ $item['quantity'] ?? '' }}" required
                                aria-invalid="{{ $quantityError ? 'true' : 'false' }}">
                            <span>PCS</span>
                        </div>
                        @if($quantityError)<div class="sales-order-inline-error" role="alert">{{ $quantityError }}</div>@endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</section>

<template id="sales-order-item-template">
    <div class="item-row sales-order-item-row" data-item-row>
        <div class="sales-order-item-topline">
            <strong data-item-number>Item</strong>
            <button type="button" class="btn btn-outline-danger remove-item" aria-label="Hapus item">
                <i class="bi bi-trash" aria-hidden="true"></i> Hapus
            </button>
        </div>
        <div class="sales-order-item-fields">
            <div class="sales-order-field">
                <label class="form-label" for="sales-part-template">Nomor part</label>
                <select id="sales-part-template" name="items[0][part_number]" class="form-select part-select" required>
                    <option value="">Pilih No Part</option>
                    @foreach($partNumbers as $partNumber)
                        <option value="{{ $partNumber }}">{{ $partNumber }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sales-order-field">
                <label class="form-label" for="sales-quantity-template">Jumlah diminta</label>
                <div class="sales-order-quantity-input">
                    <input type="number" id="sales-quantity-template" name="items[0][quantity]"
                        class="form-control" min="1" step="1" inputmode="numeric" required>
                    <span>PCS</span>
                </div>
            </div>
        </div>
    </div>
</template>
