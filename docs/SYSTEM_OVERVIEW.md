# Sistem & Flow Aplikasi

## Ringkasan Arsitektur

- Backend: Laravel 12 (PHP 8.2)
- Frontend: Blade + Bootstrap CDN + Tailwind (Vite)
- Database: MySQL

## Struktur Layout Shell (Blade)

Layout utama sekarang memakai pola orchestrator + sub-partial agar perubahan UI lebih terlokalisasi.

Struktur inti:

- `resources/views/shared/layouts/app.blade.php`
    - include `shared.layouts.partials.layout-shell-styles`
    - include `shared.layouts.partials.top-navbar`
    - include `shared.layouts.partials.sidebar`
    - include `shared.layouts.partials.layout-shell-scripts`

Entry partial (delegator):

- `resources/views/shared/layouts/partials/top-navbar.blade.php`
    - delegasi ke `shared.layouts.partials.top-navbar.index`
- `resources/views/shared/layouts/partials/sidebar.blade.php`
    - delegasi ke `shared.layouts.partials.sidebar.index`
- `resources/views/shared/layouts/partials/layout-shell-scripts.blade.php`
    - delegasi ke `shared.layouts.partials.scripts.index`

Sub-partial per domain:

- Top Navbar:
    - `resources/views/shared/layouts/partials/top-navbar/index.blade.php`
    - `resources/views/shared/layouts/partials/top-navbar/sidebar-toggle.blade.php`
    - `resources/views/shared/layouts/partials/top-navbar/global-search-form.blade.php`
    - `resources/views/shared/layouts/partials/top-navbar/profile-dropdown.blade.php`
- Sidebar:
    - `resources/views/shared/layouts/partials/sidebar/index.blade.php`
    - `resources/views/shared/layouts/partials/sidebar/brand-header.blade.php`
    - `resources/views/shared/layouts/partials/sidebar/user-summary.blade.php`
    - `resources/views/shared/layouts/partials/sidebar/role-warehouse-operator.blade.php`
    - `resources/views/shared/layouts/partials/sidebar/role-sales.blade.php`
    - `resources/views/shared/layouts/partials/sidebar/role-ppc.blade.php`
    - `resources/views/shared/layouts/partials/sidebar/role-supervisi.blade.php`
    - `resources/views/shared/layouts/partials/sidebar/role-admin-warehouse.blade.php`
    - `resources/views/shared/layouts/partials/sidebar/role-admin.blade.php`
- Scripts:
    - `resources/views/shared/layouts/partials/scripts/index.blade.php`
    - `resources/views/shared/layouts/partials/scripts/layout-shell-main.blade.php`
    - `resources/views/shared/layouts/partials/scripts/toast.blade.php`

Aturan maintenance singkat:

1. Perubahan menu role dilakukan di file `role-*.blade.php`, bukan di `sidebar/index.blade.php`.
2. Perubahan perilaku global search/sidebar toggle dilakukan di `scripts/layout-shell-main.blade.php`.
3. `index.blade.php` dipakai untuk komposisi, hindari logika panjang di file delegator.
4. Jika menambah blok UI baru, ikuti pola: `folder/index.blade.php` + sub-partial per concern.

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
