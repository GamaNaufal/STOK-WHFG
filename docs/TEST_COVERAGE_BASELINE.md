# Test Coverage Baseline (Feature + Unit)

Tanggal: 2026-02-16
Tujuan: baseline resmi untuk melihat area yang sudah terjaga test, area yang masih gap, dan prioritas penambahan.

## Update terbaru (2026-02-16)

- Tambahan cakupan fitur edit box + audit:
    - tests/Feature/StockViewBoxEditAuditTest.php
        - update box oleh admin warehouse/admin
        - role guard endpoint update
        - endpoint history perubahan box
        - not-full flag tetap terjaga saat edit
        - sinkronisasi `pallet_items` saat part/pcs berubah
        - dampak perubahan box ke angka pre-fulfillment di schedule delivery
    - tests/Feature/AuditBoxEditFilterTest.php
        - quick filter audit `box_edit` hanya memunculkan log perubahan box

- Stabilitas unit report:
    - tests/Unit/OperationalReportServiceTest.php
        - skenario weekly trend dibuat deterministik (tanggal fixed dalam minggu yang sama)
        - menghindari flaky test akibat boundary minggu dari `now()`

- Status verifikasi terakhir:
    - full suite: 44 passed, 0 failed

## 1) Ringkasan Cakupan Saat Ini

### Sudah ada dan aktif

#### Delivery flow (kritis)

- tests/Feature/DeliveryFlowConsistencyTest.php
    - final scan mismatch memblokir sesi + membuat issue
    - complete lalu redo mengembalikan konsistensi stock withdrawal, box, pallet item, delivery order

- tests/Feature/DeliverySalesPpcWorkflowTest.php
    - sales submit order multi-item
    - sales hanya lihat order milik sendiri
    - ppc tidak bisa akses sales-input
    - sales tidak bisa approval/status update ppc
    - ppc update target order tanpa kontaminasi order overlap

#### Stock Input + Not Full

- tests/Feature/StockInputAndNotFullWorkflowTest.php
    - not-full scan wajib alasan + wajib delivery
    - persist box not-full dan update item delivery saat store
    - request not-full by admin warehouse + approval supervisi ke existing pallet

#### Merge pallet

- tests/Feature/MergePalletFlowTest.php
    - merge 2 pallet aktif
    - source pallet cleanup
    - location occupancy update
    - konsolidasi item di pallet baru

#### Authorization + Export sanity

- tests/Feature/AuthorizationAndReportExportSanityTest.php
    - guard role untuk page/route inti
    - sanity ekspor utama (response + filename), dan larangan role yang tidak berhak

#### Unit model logic

- tests/Unit/DeliveryOrderTest.php
- tests/Unit/DeliveryOrderModelTest.php
- tests/Unit/MasterLocationModelTest.php
- tests/Unit/BoxScopeTest.php

## 2) Peta Cakupan per Modul

| Modul                                        | Status  | Catatan                                                                                               |
| -------------------------------------------- | ------- | ----------------------------------------------------------------------------------------------------- |
| Auth login/logout + throttle                 | Partial | Belum ada skenario invalid credential, lockout, brute-force behavior                                  |
| User management (admin)                      | None    | CRUD, validasi role, pagination/search belum ditest                                                   |
| Master location                              | Partial | Unit untuk occupy/vacate ada, tapi feature CRUD belum ada                                             |
| Part settings                                | None    | CRUD + search + validasi duplicate part belum ada                                                     |
| Stock Input                                  | Partial | Flow utama ada, tapi cabang QR scan lama, clear-session, duplicate race belum lengkap                 |
| Not Full request/approval                    | Partial | Existing pallet sudah ada; cabang target location baru, reject path, status non-pending belum lengkap |
| Delivery order (sales/ppc)                   | Partial | Banyak flow inti sudah ada, namun edit/update/destroy edge case belum lengkap                         |
| Delivery pick verification (pre-check)       | Partial | Split flow sudah ada di app, tetapi test khusus verification scan/audio boundary belum dibuat         |
| Final delivery scan + issue approval         | Partial | mismatch/redo sudah ada; approveIssue branch detail belum lengkap                                     |
| Merge pallet                                 | Partial | Happy path ada; error path (no active boxes, invalid location, partial source) belum lengkap          |
| Stock view + API detail                      | Partial | Export sanity ada; API part/pallet detail contract belum ada test formal                              |
| Reports (withdrawal/stock-input/operational) | Partial | Sanity export ada; filter matrix + aggregation assertion mendalam belum ada                           |
| Expired box handling                         | None    | index/handle + side-effect audit/report belum ditest                                                  |
| Audit trail + export                         | None    | filter + export + actor/value assertions belum ada                                                    |

## 3) Gap Risiko Utama

1. **Role drift** pada endpoint baru (route bertambah tapi test otorisasi belum ikut).
2. **Error branch** belum merata (status invalid, id invalid, duplicate/overlap ekstrem).
3. **Kontrak data report/export** belum tervalidasi detail isi (baru sanity).
4. **Race/concurrency** belum diuji (scan cepat/parallel action).
5. **UI behavior** (JS scanner/audio) belum ada browser-level test.

## 4) Prioritas Penambahan Test (Tanpa Eksekusi)

### Prioritas 1 (harus)

- Feature: approve/reject issue scan + semua cabang status invalid.
- Feature: not-full target location (buat pallet baru) + reject + double-process guard.
- Feature: merge pallet error paths.
- Feature: route-role matrix untuk endpoint kritikal baru.

### Prioritas 2 (segera)

- Feature: report filter matrix (date/status/user/part) dengan assertion jumlah agregat.
- Feature: stock view API contract (`/api/stock/by-part`, detail part, detail pallet).
- Unit: helper/service yang punya transformasi data (operational report calculator subset).

### Prioritas 3 (lanjutan)

- Browser test scanner UX (enter handling, clear input, mismatch feedback).
- Uji integrasi MySQL pipeline terpisah untuk query yang bergantung fungsi SQL engine.

## 5) Definisi “Cakupan Aman” (Target)

Minimal gate sebelum release:

- Semua flow kritikal inbound/outbound punya 1 happy + 1 sad path.
- Semua route kritikal role-protected punya test allow/deny.
- Semua proses rollback sensitif (redo/reverse/merge/not-full approve) punya assertion konsistensi data lintas tabel.
- Export/report minimal sanity + 1 assertion data terhitung.

---

Dokumen ini baseline awal. Update setiap ada penambahan fitur atau route baru agar coverage tetap sinkron dengan sistem.
