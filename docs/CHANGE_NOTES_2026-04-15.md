# Change Notes - 2026-04-15

## Ringkasan
Perubahan hari ini fokus pada konsistensi perhitungan stok aktif, pencegahan overcount akibat shared box (1 box ter-attach ke banyak pallet), penguatan integritas session stock input, konsistensi lock status delivery pick, dan penyelarasan perilaku preview vs confirm pada stock withdrawal.

## Area Perubahan

### 1) Dashboard dan Stock View

#### app/Http/Controllers/DashboardController.php
- Menyatukan sumber perhitungan ringkasan stok ke active box (is_withdrawn=false, expired_status bukan handled/expired).
- Menjaga fallback ke pallet_items hanya untuk pallet legacy tanpa riwayat box.
- Menambahkan dedupe shared active box dengan canonical pallet mapping agar box yang sama tidak dihitung ganda di dashboard.
- Menyelaraskan nilai total_items, total_box, total_pcs, dan pallets_with_location dari snapshot stok yang sama.

#### app/Http/Controllers/StockViewController.php
- Menetapkan active box sebagai sumber utama data stock view.
- Menjaga fallback legacy item hanya jika pallet tidak punya box history.
- Menambahkan dedupe shared active box dengan canonical pallet mapping agar:
  - tampilan by part/by pallet,
  - summary total,
  - API stock by part,
  - export by part/by pallet
  tidak mengalami double count.

### 2) Delivery Schedule dan Delivery Pick

#### app/Http/Controllers/DeliveryOrderController.php
- Mengganti join langsung pivot pallet_boxes menjadi EXISTS-based stored-location filter untuk menghindari overcount pada stock availability.
- Menyesuaikan perhitungan buildFifoPoolsByPart, getAvailableStockByPart, dan getReservedStockByOrder agar menghitung stok per box unik.

#### app/Http/Controllers/DeliveryPickController.php
- Menambahkan EXISTS-based stored-location filter untuk query verifikasi dan pemilihan box (menghilangkan efek duplikasi dari join pivot langsung).
- Menyamakan definisi lock aktif agar query lock exclusion menggunakan ACTIVE_LOCK_STATUSES yang konsisten.
- Menambahkan guard hardening pada complete(): setiap part harus memenuhi remaining qty order sebelum sesi boleh completed.

### 3) Stock Input Integrity

#### app/Http/Controllers/StockInputController.php
- Menghilangkan mutasi data permanen saat tahap preview scan (session-only preview).
- Menghapus perpindahan pallet_items antar pallet existing saat select existing pallet.
- Menambahkan validasi bahwa request pallet_id harus sama dengan pallet aktif di session.
- Menolak override lokasi untuk existing pallet yang sudah memiliki lokasi.
- Menyinkronkan ulang pallet_items dari active boxes saat save (store) agar data turunan tetap konsisten.

### 4) Merge Pallet

#### app/Http/Controllers/MergePalletController.php
- Menyelaraskan filter active box dengan rule global stok aktif:
  - is_withdrawn = false
  - expired_status bukan handled/expired
- Berlaku untuk list, detail load, count, dan search.

### 5) Stock Withdrawal

#### app/Http/Controllers/StockWithdrawalController.php
- Mengganti query join langsung ke pivot dengan EXISTS-based stored-location filter untuk mencegah overcount.
- Menambahkan deterministic pivot subquery (satu row per box) untuk listing lokasi FIFO agar stabil saat box ter-attach ke lebih dari satu pallet.
- Menyamakan perilaku preview dengan confirm untuk exact quantity:
  - preview akan gagal jika kombinasi box tidak bisa memenuhi qty exact.
- Memperbaiki decrement remaining FIFO agar mengurangi qty yang benar-benar diambil (will_take_pcs), bukan full qty box.

## Perubahan Test

### Test baru
- tests/Feature/DashboardStockSummaryTest.php
- tests/Feature/StockAvailabilityDeduplicationTest.php
- tests/Feature/StockInputSessionIntegrityTest.php

### Test yang ditambah skenario
- tests/Feature/DeliveryFlowConsistencyTest.php
  - shared-box double count rejection saat start pick
  - lock consistency untuk legacy approved session
  - complete reject jika qty scan belum memenuhi remaining order qty
- tests/Feature/MergePalletFlowTest.php
  - search reject untuk pallet yang hanya punya expired box
- tests/Feature/StockViewApiContractTest.php
  - shared active box dihitung sekali pada API stock by part

## Validasi
- Focused regression suite: PASS (29 tests, 173 assertions).
- Full test suite: PASS (82 tests, 446 assertions).
- Diagnostics (file yang diubah): no errors.

## Daftar File Terdampak
- app/Http/Controllers/DashboardController.php
- app/Http/Controllers/DeliveryOrderController.php
- app/Http/Controllers/DeliveryPickController.php
- app/Http/Controllers/MergePalletController.php
- app/Http/Controllers/StockInputController.php
- app/Http/Controllers/StockViewController.php
- app/Http/Controllers/StockWithdrawalController.php
- tests/Feature/DeliveryFlowConsistencyTest.php
- tests/Feature/MergePalletFlowTest.php
- tests/Feature/StockViewApiContractTest.php
- tests/Feature/DashboardStockSummaryTest.php
- tests/Feature/StockAvailabilityDeduplicationTest.php
- tests/Feature/StockInputSessionIntegrityTest.php
