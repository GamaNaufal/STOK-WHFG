# Memo Sistem STOK WHFG untuk Agent

Terakhir diverifikasi dari source code: 19 Juni 2026.

## Tujuan Memo

Dokumen ini adalah konteks awal utama bagi agent yang mengerjakan repository STOK WHFG.

Saat menerima perintah baru:

1. Baca memo ini terlebih dahulu.
2. Gunakan deskripsi di sini sebagai baseline sistem dan proses bisnis.
3. Jangan mengeksplorasi seluruh repository dari awal jika tugas dapat dipahami dari memo.
4. Buka hanya file yang relevan dengan perubahan atau pemeriksaan yang diminta.
5. Jika implementasi aktual berbeda dari memo, source code adalah sumber kebenaran dan memo ini harus diperbarui.

## Ringkasan Sistem

STOK WHFG adalah Warehouse Management System berbasis web untuk mengelola:

- master user, nomor part, kapasitas box, dan lokasi gudang;
- penerimaan dan penyimpanan stok dalam box dan palet;
- pemantauan serta koreksi stok aktif;
- delivery order dari Sales, approval PPC, assignment stok, picking, dan scanning;
- stock withdrawal ketika barang dikirim;
- box tidak penuh, expired box, merge palet, redo delivery, laporan, dan audit trail.

Teknologi utama:

- Laravel 12 dan PHP 8.2;
- Blade, Bootstrap, Tailwind, dan Vite;
- MySQL atau SQLite untuk pengembangan/testing;
- DomPDF untuk PDF;
- Maatwebsite Excel untuk export Excel.

Entry point penting:

- route dan pembatasan role: `routes/web.php`;
- proses bisnis backend: `app/Http/Controllers`;
- aturan lintas modul: `app/Services`;
- data dan relasi: `app/Models`;
- struktur database: `database/migrations`;
- antarmuka: `resources/views`;
- regression test: `tests/Feature` dan `tests/Unit`.

## Aktor dan Tanggung Jawab

### Sales

- Membuat delivery order.
- Mengisi customer, tanggal pengiriman, nomor part, dan quantity.
- Memperbaiki order yang dikembalikan PPC dengan status `correction`.

### PPC

- Meninjau delivery order berstatus `pending`.
- Melihat ketersediaan stok dan dampak approval terhadap order lain.
- Memberikan keputusan `approved`, `rejected`, atau `correction`.
- Melihat delivery schedule dan stock view.

### Warehouse Operator

- Melakukan stock input.
- Memilih atau membuat palet.
- Mengajukan request box not full untuk approval Supervisi.
- Melakukan assignment box ke delivery.
- Menjalankan picking, verification scan, dan scan pengiriman.
- Melakukan merge palet.
- Melihat stok.

### Admin Warehouse

- Mengelola master lokasi dan master nomor part.
- Mengajukan box not full.
- Melakukan assignment delivery, termasuk direct input box baru dengan PCS sesuai fixed qty Master Part.
- Mengoreksi detail box aktif.
- Menangani delivery scan issue.
- Melakukan split/restore split dan redo delivery.
- Memantau expired box.

### Supervisi

- Menyetujui atau menolak permintaan box not full.
- Melihat dan menangani expired box.
- Melihat laporan operasional, stock input, withdrawal, dan audit trail.
- Dapat menghapus stok aktif sesuai pembatasan route.

### Admin

- Memiliki akses penuh melalui middleware role.
- Mengelola user.
- Menjalankan seluruh fungsi operasional dan administrasi.
- Mengelola serta menghentikan lock picking yang macet.

## Entitas Utama

### Master data

- `users`: akun dan role.
- `part_settings`: nomor part dan kapasitas standar `qty_box`.
- `master_locations`: kode lokasi, status occupied, dan palet aktif.

### Inventory

- `pallets`: identitas palet.
- `boxes`: box fisik, nomor part, PCS, status withdrawn/not-full/expired.
- `pallet_boxes`: relasi box dengan palet.
- `pallet_items`: ringkasan jumlah box dan PCS per part dalam palet.
- `stock_locations`: lokasi penyimpanan palet.

### Transaksi

- `stock_inputs`: header transaksi stok masuk.
- `stock_input_boxes`: mapping box yang tepat untuk setiap transaksi stock input.
- `stock_withdrawals`: transaksi stok keluar per box/batch.
- `delivery_orders` dan `delivery_order_items`: permintaan pengiriman.
- `delivery_pick_sessions` dan `delivery_pick_items`: sesi dan daftar box picking.
- `delivery_issues`: kesalahan atau mismatch scanning.
- `not_full_box_requests`: pengajuan box dengan jumlah di bawah kapasitas standar.
- `expired_box_reports`: histori box expired/handled.
- `audit_logs`: histori aktivitas penting.

## Definisi Stok Aktif

Secara umum, box dianggap sebagai stok aktif apabila:

- belum di-soft-delete;
- `is_withdrawn = false`;
- `expired_status` bukan `expired` atau `handled`;
- terhubung ke palet yang memiliki lokasi penyimpanan valid dan bukan `Unknown`.

Untuk data lama, beberapa tampilan dan ringkasan masih menggunakan `pallet_items` sebagai fallback hanya ketika palet benar-benar tidak memiliki riwayat box.

Box dapat terhubung ke lebih dari satu palet pada data tertentu. Perhitungan stok harus melakukan deduplikasi box dan menggunakan canonical pallet agar tidak terjadi double count.

Canonical pallet untuk box shared ditentukan dari relasi `pallet_boxes` paling baru yang masih memiliki lokasi valid. Saat box dinonaktifkan karena withdrawal, ringkasan seluruh palet yang masih terhubung disinkronkan ulang dari box aktif agar tidak meninggalkan phantom stock.

Query stok mentah wajib mengabaikan `boxes.deleted_at`. Fallback `pallet_items` hanya boleh digunakan untuk pallet yang benar-benar tidak pernah memiliki histori box, termasuk histori box soft-deleted.

## Proses Bisnis

### 1. Login, profil, dan otorisasi

- User login melalui email dan password.
- Semua halaman utama dilindungi middleware `auth`.
- Akun dengan `is_active = false` ditolak saat login dan pada request terautentikasi berikutnya.
- Hak akses dibatasi berdasarkan role.
- Role `admin` selalu diizinkan oleh middleware role.
- Semua user yang login dapat memperbarui profil.

File utama:

- `app/Http/Controllers/AuthController.php`
- `app/Http/Controllers/ProfileController.php`
- `app/Http/Middleware/CheckRole.php`
- `routes/web.php`

### 2. Pengelolaan master data

Admin mengelola user, role, dan status aktif akun. Aksi hapus user menonaktifkan akun agar histori transaksi tidak ikut terhapus.

Admin Warehouse/Admin mengelola:

- master nomor part;
- kapasitas standar PCS per box;
- master lokasi gudang.

Lokasi yang sedang occupied tidak dapat diedit. Lokasi yang masih digunakan palet tidak dapat dihapus.

File utama:

- `app/Http/Controllers/UserController.php`
- `app/Http/Controllers/PartSettingController.php`
- `app/Http/Controllers/MasterLocationController.php`

### 3. Stock input

Alur normal:

1. Operator membuka halaman stock input.
2. Operator menggunakan palet baru atau memilih palet existing yang masih aktif.
3. Operator scan ID box delapan digit.
4. Operator scan atau memilih nomor part.
5. Sistem memvalidasi nomor part terhadap `part_settings`.
6. Sistem menentukan kapasitas standar berdasarkan `qty_box`.
7. Data scan disimpan sementara dalam session, belum langsung mengubah database.
8. Untuk palet baru, operator memilih lokasi kosong.
9. Saat Save, sistem:
   - membuat atau mengambil box;
   - menghubungkan box ke palet;
   - memperbarui `pallet_items`;
   - membuat `stock_locations` jika diperlukan;
   - membuat `stock_inputs`;
   - membuat mapping `stock_input_boxes`;
   - membuat audit log.

Aturan penting:

- ID box harus delapan angka.
- ID box yang pernah dipakai tidak boleh digunakan ulang, termasuk jika box lama sudah di-soft-delete.
- Box yang sudah tersimpan di palet berlokasi tidak dapat dimasukkan ulang.
- Box withdrawn, expired, handled, atau archived tidak dapat dimasukkan ulang melalui scan.
- Nomor part wajib terdaftar di master part.
- PCS tidak boleh melebihi kapasitas standar box.
- Palet existing yang sudah memiliki lokasi tidak boleh dipindahkan dari flow stock input.
- Palet baru wajib memilih lokasi yang terdaftar pada Master Location; kode lokasi bebas dari request tidak diterima.
- Request `pallet_id` harus sama dengan palet aktif dalam session.

File utama:

- `app/Http/Controllers/StockInputController.php`
- `app/Models/StockInput.php`
- `app/Models/Box.php`
- `app/Models/Pallet.php`

### 4. Aturan PCS pada stock input dan direct assignment

- PCS full box mengikuti `qty_box` pada Master Part sesuai nomor part.
- Stock Input tidak menerima PCS di bawah atau di atas fixed qty.
- Direct input box baru pada Delivery Assignment juga hanya menerima PCS yang sama dengan fixed qty.
- Jika PCS aktual lebih kecil, user wajib memakai menu Request Box Not Full.
- Tidak ada jalur direct yang boleh membuat box not full menjadi stok aktif tanpa approval Supervisi.
- Fitur Edit Box juga tidak boleh mengubah full box menjadi not-full atau mengubah identitas/PCS not-full tanpa request approved yang cocok.

### 5. Pengajuan box not full dengan approval

Flow Warehouse Operator/Admin Warehouse/Admin:

1. Requester mengisi ID box, nomor part, PCS aktual, alasan, delivery, dan tujuan.
2. PCS aktual harus lebih kecil dari kapasitas standar.
3. Tujuan dapat berupa palet existing atau lokasi kosong untuk palet baru.
4. Request dibuat dengan status `pending`.
5. Supervisi/Admin menyetujui atau menolak.
6. Box belum menjadi stok aktif selama request masih `pending`.

Jika disetujui:

- box dibuat dan ditandai not full;
- box ditempatkan pada palet tujuan;
- palet baru dapat dibuat bila targetnya lokasi;
- stok dan stock input diperbarui;
- box di-assign ke delivery;
- untuk tipe `additional`, quantity delivery order ikut ditambah;
- status request menjadi `approved`.

Jika ditolak, status menjadi `rejected` dan stok tidak dibuat.

Jenis request:

- `supplement`: menyediakan box untuk delivery tanpa menambah quantity order.
- `additional`: menyediakan box dan menambah quantity order.

Aturan khusus `supplement`:

- nomor part wajib sudah ada pada item delivery;
- delivery masih memiliki kebutuhan yang belum terpenuhi;
- PCS supplement tidak boleh melebihi kebutuhan yang belum ter-cover oleh box assigned aktif;
- kapasitas tersebut diperiksa ulang dengan row lock saat approval.

Request box not full tidak dapat dibuat atau disetujui ketika delivery memiliki sesi picking `pending/scanning/blocked/approved`.

Selama request box not full jenis apa pun masih `pending`, schedule, picking, split, dan fulfillment delivery terkait diblokir.

File utama:

- `app/Http/Controllers/NotFullBoxRequestController.php`
- `app/Models/NotFullBoxRequest.php`

### 6. Stock view dan pemeliharaan stok

Stock view menyediakan tampilan:

- by part;
- by box ID;
- by pallet;
- box not full.

Fitur:

- pencarian part, box, palet, dan lokasi;
- pengurutan;
- summary jumlah part, box, PCS, dan palet;
- detail part dan palet melalui API;
- histori perubahan box;
- export Excel.

Admin Warehouse/Admin dapat mengubah:

- nomor part;
- PCS;
- tanggal penyimpanan box;
- alasan koreksi wajib.

Koreksi box juga memperbarui QR, header transaksi stock input yang terhubung, dan tanggal umur box. Box yang sudah assigned ke delivery atau sedang dipakai sesi picking aktif tidak dapat diedit atau dihapus.

Supervisi/Admin Warehouse/Admin dapat menghapus box atau palet dari stok aktif selama tidak memuat box assigned atau pick-locked. Penghapusan box dan palet menggunakan soft delete agar histori transaksi tetap tersedia. Jika box terakhir dihapus, palet dan occupancy lokasinya ikut dibersihkan.

File utama:

- `app/Http/Controllers/StockViewController.php`
- `app/Observers/StockLocationObserver.php`

### 7. Pembuatan dan approval delivery order

Alur:

1. Sales membuat order.
2. Status awal `pending`.
3. PPC meninjau order dan ketersediaan stok.
4. PPC dapat memilih:
   - `approved`;
   - `rejected`;
   - `correction`.
5. Keterangan PPC wajib ketika approve.
6. Order `correction` dapat diedit Sales.
7. Setelah dikirim ulang, status kembali `pending`.

PPC dapat melihat dampak approval sebuah order terhadap order approved lain berdasarkan prioritas tanggal dan ketersediaan stok.

File utama:

- `app/Http/Controllers/DeliveryOrderController.php`
- `app/Models/DeliveryOrder.php`
- `app/Models/DeliveryOrderItem.php`

### 8. Prioritas dan ketersediaan delivery

Delivery aktif yang ditampilkan pada schedule menggunakan status:

- `approved`;
- `processing`;
- `partial`.

Ketersediaan mempertimbangkan:

- stok aktif per part;
- box yang sudah reserved untuk order;
- box yang dikunci sesi picking aktif;
- FIFO berdasarkan umur box;
- kapasitas box dan kebutuhan box not full;
- delivery dengan tanggal lebih awal;
- request box not-full jenis apa pun yang masih pending.

### 9. Assignment box ke delivery

Warehouse Operator/Admin Warehouse/Admin dapat melakukan assignment sebelum picking:

- memilih box existing;
- memilih palet dan mengambil box eligible di dalamnya;
- scan serta membuat box baru langsung untuk delivery;
- menempatkan box baru pada palet existing atau palet baru.

Box tidak eligible apabila:

- sudah withdrawn;
- expired atau handled;
- terkunci dalam picking aktif;
- sudah di-assign ke delivery lain;
- belum memiliki lokasi penyimpanan valid;
- nomor part tidak ada dalam kebutuhan delivery.

Jika jumlah box yang dipilih melebihi sisa permintaan, sistem meminta konfirmasi. Setelah dikonfirmasi, quantity delivery order dinaikkan sebesar overage.

Assignment membuat atau menggunakan pick session `pending` dan membuat `delivery_pick_items`.

Saat picking dimulai, session pending terbaru dipakai ulang, itemnya dibangun ulang berdasarkan kondisi stok terkini, dan session pending duplikat dibatalkan. Order dan box dikunci serta divalidasi ulang di dalam transaction untuk mencegah race condition.

File utama:

- `app/Http/Controllers/DeliveryAssignController.php`

### 10. Picking dan verification

Alur:

1. Operator memulai picking atau picking verification.
2. Sistem mengunci delivery dan memeriksa apakah terdapat sesi aktif global.
3. Sistem mengambil reserved box terlebih dahulu.
4. Kekurangan dipenuhi dengan pemilihan box FIFO.
5. Sistem membuat pick session berstatus `scanning`.
6. Sistem membuat pick item per box.
7. Picklist dapat dicetak atau diekspor ke PDF.
8. Verification scan dapat dilakukan sebelum scan pengambilan.
9. Operator scan seluruh box yang masuk picklist.

Lock aktif menggunakan status `scanning` dan `blocked`.

Reserved box hanya dimasukkan selama totalnya tidak melebihi sisa kebutuhan order. Box reserved tanpa lokasi valid tidak dianggap sebagai stok yang dapat dipicking.

Start picking menggunakan lock serialisasi global sehingga sistem hanya dapat memiliki satu session `scanning/blocked` aktif pada satu waktu.

Satu operator tidak boleh memulai delivery lain sebelum sesi aktifnya diselesaikan atau dibatalkan. Operator lain juga tidak dapat mengambil alih sesi tanpa tindakan admin.

File utama:

- `app/Http/Controllers/DeliveryPickController.php`
- `app/Models/DeliveryPickSession.php`
- `app/Models/DeliveryPickItem.php`

### 11. Scan issue

Issue dibuat ketika:

- box yang discan tidak ada dalam picklist (`scan_mismatch`);
- box sudah withdrawn (`box_withdrawn`);
- box expired atau handled (`box_expired`).

Akibatnya:

- issue dibuat dengan status `pending`;
- session berubah menjadi `blocked`;
- scan berikutnya ditolak;
- Admin Warehouse/Admin harus approve issue;
- setelah approval, session kembali `scanning`.

Admin memiliki lock management untuk menghentikan sesi aktif yang macet. Pembatalan menghapus pick item dan pending issue serta melepaskan lock.

### 12. Penyelesaian delivery dan stock withdrawal

Delivery hanya dapat diselesaikan apabila:

- session bukan blocked, stale, atau completed;
- tidak ada pending issue;
- tidak ada pick item berstatus pending;
- quantity hasil scan memenuhi seluruh sisa quantity setiap part.
- quantity hasil scan sama persis dengan seluruh sisa quantity setiap part;
- snapshot part dan PCS pick item masih sama dengan data box aktif saat completion;
- seluruh box masih berada pada canonical pallet dengan lokasi valid.

Saat completion:

- `fulfilled_quantity` diperbarui;
- dibuat `stock_withdrawals` per box;
- box menjadi withdrawn;
- `pallet_items` dikurangi;
- lokasi dikosongkan jika palet tidak lagi memiliki inventory;
- order menjadi `completed`;
- session menjadi `completed`;
- `completion_status` menjadi `completed`;
- window redo ditetapkan selama lima hari;
- audit withdrawal dibuat.

File utama:

- `app/Http/Controllers/DeliveryPickController.php`
- `app/Http/Controllers/StockWithdrawalController.php`

### 13. Split delivery

Admin Warehouse/Admin dapat membagi quantity dari order berstatus `approved` atau `partial`.

Aturan:

- order hasil split tidak dapat di-split lagi;
- order yang sudah memiliki assignment box atau sesi `pending/scanning/blocked/approved` tidak dapat di-split;
- nomor part dalam payload split harus unik;
- quantity split harus lebih kecil dari quantity parent;
- child order dibuat dengan status `approved`;
- quantity parent dikurangi;
- parent menjadi `partial`.

Restore split:

- child belum boleh `completed` atau `deleted`;
- child tidak boleh memiliki assignment box atau sesi `pending/scanning/blocked/approved`;
- seluruh quantity setiap part pada child dikembalikan ke parent;
- child di-soft-delete;
- parent kembali `approved` bila tidak ada child aktif lain.

### 14. Redo delivery

Admin Warehouse/Admin dapat melakukan redo selama belum melewati `redo_until`, yaitu lima hari setelah completion.

Redo:

- memulihkan box ke palet;
- mengubah withdrawal menjadi `reversed`;
- mengembalikan nilai `pallet_items`;
- mengembalikan `fulfilled_quantity`;
- menghapus assignment delivery dari box;
- membatalkan request not-full terkait;
- memulihkan atau merelokasi lokasi palet;
- menandai completion sebagai `redone`;
- mengubah order menjadi `processing`;
- membuat audit redo.

Redo hanya berlaku satu kali untuk session `completed` dengan `completion_status=completed` dan `redo_until` yang belum lewat. Untuk shared box, pallet dari withdrawal menjadi sumber kanonik; pivot box dinormalkan ke pallet tersebut dan ringkasan pallet terdampak dihitung ulang. Session assignment pending yang tersisa dibatalkan saat redo.

### 15. Merge palet

Warehouse Operator/Admin dapat menggabungkan minimal dua palet.

Alur:

- hanya palet dengan box aktif yang dapat dipilih;
- sistem membuat nomor palet baru;
- seluruh box aktif dipindahkan ke palet baru;
- merge ditolak jika terdapat box yang sudah assigned ke delivery atau dikunci sesi picking aktif;
- ringkasan `pallet_items` digabung;
- referensi transaksi stock input diarahkan ke palet baru;
- relasi box aktif yang dipindahkan dan lokasi palet sumber dibersihkan; relasi box inactive dipertahankan untuk histori;
- lokasi lama dibuat available;
- palet sumber dihapus;
- palet baru ditempatkan pada lokasi tujuan yang wajib berasal dari Master Location;
- perpindahan box dan merge dicatat di audit log.

Ringkasan pallet hasil merge dibangun ulang dari box aktif unik yang benar-benar dipindahkan, bukan menjumlahkan `pallet_items` sumber yang mungkin sudah basi.

File utama:

- `app/Http/Controllers/MergePalletController.php`

### 16. Expired box

Umur box menggunakan `boxes.created_at` sebagai tanggal penyimpanan per box. Mapping `stock_input_boxes -> stock_inputs.stored_at` tetap menjadi fallback untuk data legacy yang tidak memiliki tanggal box.

Status:

- di bawah 9 bulan: `active`;
- 9 sampai kurang dari 12 bulan: `warning`;
- 12 bulan atau lebih: `expired`;
- setelah ditangani: `handled`.

Box `expired` atau `handled` tidak termasuk stok aktif dan tidak boleh dipakai untuk delivery.

Saat status berubah menjadi `expired` atau `handled`, sistem langsung menghitung ulang `pallet_items`, menghapus stock location pallet yang sudah kosong, dan mengosongkan master location terkait.

Supervisi/Admin dapat menandai box berstatus `warning` maupun `expired` sebagai `handled`. Endpoint memverifikasi ulang umur minimal sembilan bulan. Box yang masih assigned ke delivery atau dikunci sesi picking aktif tidak dapat ditandai `handled`. Sistem menyimpan histori penanganannya.

Setiap hari pukul 07.00 sistem:

- menyinkronkan status umur box;
- mengirim email ringkasan warning dan expired ke user role Supervisi jika terdapat data.

File utama:

- `app/Services/ExpiredBoxService.php`
- `app/Http/Controllers/ExpiredBoxController.php`
- `routes/console.php`

### 17. Laporan dan audit

Laporan yang tersedia:

- histori stock input;
- histori withdrawal;
- stok yang sedang ditangani;
- matching kebutuhan dan fulfillment delivery;
- durasi picking dan scanning;
- throughput inbound/outbound;
- tren planned vs actual delivery;
- peak operational hours;
- delivery scan issue;
- fulfillment;
- audit trail.

Laporan dapat difilter dan diekspor ke Excel. Picklist delivery dapat dibuat dalam PDF.

Audit penting meliputi:

- stock input;
- stock withdrawal dan reversal;
- perubahan detail box;
- penghapusan box atau palet;
- assignment delivery;
- merge dan perpindahan box;
- delivery redo.

File utama:

- `app/Http/Controllers/ReportController.php`
- `app/Http/Controllers/AuditController.php`
- `app/Services/OperationalReportService.php`
- `app/Services/AuditService.php`

## Status Delivery

Status yang digunakan:

- `pending`: menunggu review PPC.
- `approved`: disetujui dan siap dialokasikan/diproses.
- `rejected`: ditolak PPC.
- `correction`: dikembalikan ke Sales untuk diperbaiki.
- `processing`: sedang atau kembali dalam proses.
- `partial`: parent order telah di-split atau pengiriman belum dianggap penuh.
- `completed`: seluruh proses delivery selesai.
- `deleted`: schedule dihapus secara administratif.

## Status Pick Session

- `pending`: assignment telah dibuat tetapi picking belum aktif.
- `scanning`: sesi aktif.
- `blocked`: terdapat issue yang membutuhkan approval.
- `stale`: perlu dihitung ulang akibat perubahan prioritas/kondisi order.
- `completed`: withdrawal selesai.
- `cancelled`: sesi dibatalkan atau lock dihentikan.

`completion_status` dapat bernilai `completed` atau `redone`.

## Aturan Integritas Penting

- Operasi stok dan delivery kritis dijalankan dalam database transaction.
- Row locking digunakan untuk mencegah double assignment, double scan, dan race condition lokasi.
- Start picking diserialisasi melalui row `operation_locks.global_delivery_pick`.
- Endpoint withdrawal hanya dapat diakses Warehouse Operator, Admin Warehouse, dan Admin.
- Withdrawal exact di-rollback jika quantity box aktual tidak sama dengan quantity request.
- `fulfilled_quantity` diperbarui dari quantity yang benar-benar berhasil di-withdraw.
- Box ID bersifat unik dan umumnya harus delapan digit.
- `stock_input_boxes` adalah sumber mapping audit transaksi input yang paling akurat.
- Box aktif adalah sumber utama perhitungan stok.
- `pallet_items` merupakan ringkasan/fallback legacy, bukan satu-satunya sumber kebenaran.
- Status lokasi kosong ditentukan dari box aktif; `pallet_items` hanya dipakai untuk pallet legacy yang tidak pernah memiliki histori box.
- Box yang sama harus dihitung satu kali walaupun memiliki relasi ke lebih dari satu palet.
- Canonical pallet box shared adalah relasi lokasi valid paling baru.
- Box dalam sesi picking aktif tidak boleh dialokasikan ke order lain.
- Box dalam sesi picking aktif tidak boleh diedit atau dihapus.
- Box assigned ke delivery tidak boleh diedit, dihapus, ditangani sebagai expired, atau dipindahkan melalui merge pallet.
- Semua lokasi baru dari Stock Input dan Merge Pallet wajib berasal dari Master Location.
- Semua request box not full pending memblokir fulfillment; supplement hanya boleh menutup kebutuhan order yang benar-benar masih tersisa.
- Completion delivery wajib exact dan membatalkan transaksi jika snapshot box, status, quantity, assignment, atau lokasi telah berubah.
- Lokasi hanya boleh ditempati satu palet aktif.
- Database menjamin satu `stock_locations` per pallet dan satu relasi `master_location_id` aktif melalui unique constraint.
- Undo withdrawal memulihkan pallet soft-deleted dan `stock_locations`; undo ditolak jika lokasi asal sudah ditempati pallet lain.
- Soft delete digunakan untuk box, palet, stock input, withdrawal, dan delivery agar histori tetap dapat diaudit.
- User dinonaktifkan melalui `is_active`, bukan dihapus, sehingga foreign key dan histori tetap utuh.
- Master part yang sudah digunakan histori/transaksi tidak boleh diubah nomor part-nya atau dihapus.
- Sales hanya boleh mengubah order berstatus `correction`; PPC hanya boleh memutuskan order berstatus `pending`.
- Delivery yang sudah memiliki completion atau withdrawal tidak boleh dihapus.
- Picklist PDF/print hanya dapat diakses role warehouse; Warehouse Operator hanya dapat mengakses session miliknya.

## Keputusan Bisnis

- Fitur backorder dan partial withdrawal telah dihapus.
- Kekurangan stok tidak boleh dikirim sebagian melalui picking atau withdrawal.
- Delivery dengan kebutuhan yang akan dipisahkan harus memakai fitur split order.
- Status delivery `partial` tetap dipakai khusus untuk parent order yang memiliki child hasil split.
- Semua box not full wajib melalui request dan approval Supervisi; Stock Input dan Delivery Assignment tidak boleh membuat box not full secara langsung.
- PCS direct input wajib sama dengan fixed qty Master Part.
- Box berstatus `warning` (9 sampai kurang dari 12 bulan) boleh ditandai `handled` selama tidak assigned atau pick-locked.

## Status Schema dan Validasi Terakhir

- Seluruh migration sampai `2026_06_19_000004_harden_location_and_pick_serialization` telah dijalankan pada MySQL lokal tanggal 19 Juni 2026.
- Backup sebelum migration terbaru tersimpan di `storage/app/backups/db_stock_before_integrity_hardening_2026-06-19_082641.sql`.
- Audit data aktif tidak menemukan duplicate stock location, lokasi tanpa master link, mismatch `pallet_items`, box aktif tanpa lokasi, session pending yatim, assignment invalid, lebih dari satu session picking aktif, maupun mismatch PCS Master Part tanpa approval not-full.
- Full regression suite terakhir: 139 test, 671 assertion, seluruhnya lulus.

## Catatan yang Belum Diputuskan

Tidak ada catatan terbuka terkait approval box not full atau handling status warning. Keputusan final tercatat pada bagian Keputusan Bisnis.

## Panduan Agent Saat Menerima Tugas

- Untuk pertanyaan umum tentang sistem, jawab berdasarkan memo ini.
- Untuk perubahan fitur, baca memo ini lalu inspeksi controller, model, migration, view, dan test yang terkait saja.
- Untuk diagnosis bug, jangan berasumsi memo selalu mutakhir; verifikasi jalur kode yang terlibat.
- Jangan melakukan full-repository exploration kecuali:
  - user secara eksplisit memintanya;
  - tugas lintas domain memang membutuhkannya;
  - implementasi yang ditemukan bertentangan dengan memo.
- Setelah perubahan proses bisnis yang signifikan, perbarui bagian terkait dan tanggal verifikasi memo.
