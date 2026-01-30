# Sistem & Flow Aplikasi

## Ringkasan Arsitektur

- Backend: Laravel 12 (PHP 8.2)
- Frontend: Blade + Bootstrap CDN + Tailwind (Vite)
- Database: MySQL

## Domain Utama

1. Stock Input (Warehouse)
2. Stock View & Reporting
3. Delivery Order (Sales → PPC → Warehouse)
4. Audit Trail
5. Master Location & Pallet Management

## Flow Utama

### 1. Stock Input

1. Operator scan box/part.
2. Data box masuk ke session.
3. Saat simpan:
    - Pallet dibuat/digunakan.
    - Box di-attach ke pallet.
    - Lokasi pallet dicatat di `stock_locations`.
    - Log input dicatat di `stock_inputs`.
    - `master_locations` di-update sebagai occupied.
4. Audit log dicatat.

Referensi:

- Controller: app/Http/Controllers/StockInputController.php
- Model: app/Models/Box.php, app/Models/Pallet.php, app/Models/StockInput.php

### 2. Stock View

- Sistem menampilkan stok berdasarkan pallet dan lokasi.
- Untuk pallet legacy tanpa box, memakai `pallet_items`.

Referensi:

- Controller: app/Http/Controllers/StockViewController.php
- View: resources/views/shared/stock-view/index.blade.php

### 3. Delivery Flow

1. Sales membuat `delivery_orders` + `delivery_order_items`.
2. PPC approve/reject/correction.
3. Warehouse start pick → `delivery_pick_sessions` + `delivery_pick_items`.
4. Warehouse scan box:
    - Jika mismatch, buat `delivery_issues` dan blok sesi.
5. Complete:
    - Buat `stock_withdrawals`.
    - Update `boxes` + `pallet_items`.
    - Update status order & session.

Referensi:

- Controller: app/Http/Controllers/DeliveryOrderController.php
- Controller: app/Http/Controllers/DeliveryPickController.php
- Model: app/Models/DeliveryOrder.php, app/Models/DeliveryPickSession.php

### 4. Merge Pallet

- Gabung beberapa pallet menjadi satu pallet baru.
- Semua box dipindah ke pallet baru.
- Lokasi lama divacate.
- Audit log dicatat.

Referensi:

- Controller: app/Http/Controllers/MergePalletController.php

### 5. Audit Trail

- Semua aktivitas penting dicatat di `audit_logs`.

Referensi:

- Service: app/Services/AuditService.php
- Model: app/Models/AuditLog.php

## Struktur Database (Tabel Utama)

- users
- pallets
- pallet_items
- boxes
- pallet_boxes
- stock_locations
- master_locations
- stock_inputs
- stock_withdrawals
- delivery_orders
- delivery_order_items
- delivery_pick_sessions
- delivery_pick_items
- delivery_issues
- audit_logs

Referensi migrasi ada di database/migrations.
