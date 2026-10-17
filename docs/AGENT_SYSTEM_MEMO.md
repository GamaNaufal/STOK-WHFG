# Memo Sistem STOK WHFG untuk Agent

Terakhir diverifikasi dari source code: 7 Oktober 2026.

Verifikasi tambahan 16 Oktober 2026 (presentasi dashboard): role `admin` (Admin Utama)
dan `supervisi` menggunakan dashboard laporan operasional yang sama melalui
`OperationalReportService` dan `operator.reports.operational`. Role lain tetap
menggunakan `shared.dashboard`. Statistik stok serta sinkronisasi expiry Admin
Utama tetap dijalankan; hak akses dan proses transaksi tidak berubah.

Verifikasi tambahan 15 Oktober 2026: pengaturan hak akses fitur per role oleh Admin Utama, pembatasan server dan navigasi dinamis.

Verifikasi tambahan 14 Oktober 2026: Stock View menampilkan stok expired dengan label, dan Handle dibatasi untuk box expired.

Verifikasi tambahan 13 Oktober 2026: audio scan, pembatalan dan timeout Picking Verification, serta aktivasi ulang box melalui Handle selama satu bulan.

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

Daftar berikut menjelaskan akses bawaan. Sejak 15 Oktober 2026, Admin Utama dapat mengubah akses fitur setiap role melalui Hak Akses; aturan kepemilikan transaksi dan integritas stok tetap berlaku.

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
- Melakukan pemindahan pallet.
- Melihat stok.

### Admin Warehouse

- Mengelola master lokasi dan master nomor part.
- Melakukan merge palet.
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

- Role `admin` adalah Admin Utama dan selalu memiliki akses penuh.
- Mengelola user.
- Mengatur hak akses fitur untuk Warehouse Operator, Admin Warehouse, Sales, PPC, dan Supervisi melalui menu Administrasi > Hak Akses.
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
- masa aktivasi ulang `reactivated_until` belum habis (atau null untuk box yang belum pernah diaktifkan ulang);
- terhubung ke palet yang memiliki lokasi penyimpanan valid dan bukan `Unknown`.

Untuk data lama, beberapa tampilan dan ringkasan masih menggunakan `pallet_items` sebagai fallback hanya ketika palet benar-benar tidak memiliki riwayat box.

Box dapat terhubung ke lebih dari satu palet pada data tertentu. Perhitungan stok harus melakukan deduplikasi box dan menggunakan canonical pallet agar tidak terjadi double count.

Canonical pallet untuk box shared ditentukan dari relasi `pallet_boxes` paling baru yang masih memiliki lokasi valid. Saat box dinonaktifkan karena withdrawal, ringkasan seluruh palet yang masih terhubung disinkronkan ulang dari box aktif agar tidak meninggalkan phantom stock.

Query stok mentah wajib mengabaikan `boxes.deleted_at`. Fallback `pallet_items` hanya boleh digunakan untuk pallet yang benar-benar tidak pernah memiliki histori box, termasuk histori box soft-deleted.

Halaman `Lihat Stok` menampilkan stok fisik aktif dan expired, termasuk box tanpa lokasi aktif. Box expired diberi label merah dan tetap tidak eligible FIFO. Total halaman mencakup expired, dengan rincian jumlah box/PCS expired pada ringkasan, grup part/pallet, dan export. Detail part/pallet serta cetak ikut menampilkan status expired; tombol Edit tidak ditampilkan untuk expired. Box soft-deleted, withdrawn, dan status handled legacy tetap tidak ditampilkan. Endpoint `/api/stock/by-part` untuk ketersediaan pengambilan stok tetap mengecualikan expired. Batas reaktivasi yang sudah habis langsung ditandai expired meskipun scheduler belum menyinkronkan status.

## Proses Bisnis

### 1. Login, profil, dan otorisasi

- User login melalui email dan password.
- Semua halaman utama dilindungi middleware `auth`.
- Akun dengan `is_active = false` ditolak saat login dan pada request terautentikasi berikutnya.
- Hak akses fitur berdasarkan role dikelola melalui `config/feature_access.php` dan tabel `role_feature_access`.
- Role tanpa pengaturan tersimpan memakai akses bawaan sesuai pembatasan sebelumnya. Pengaturan tersimpan mengganti seluruh daftar akses role tersebut; daftar kosong berarti seluruh fitur yang dapat diatur dinonaktifkan.
- Role `admin` (Admin Utama) selalu memiliki akses penuh. Hak Akses, Kelola User, dan Lock Management tidak dapat diberikan ke role lain; ini juga mencegah eskalasi melalui pengubahan role akun.
- Middleware `features` memeriksa setiap route terlindungi, termasuk POST/PUT/DELETE, export, dan API pendukung. Route fitur yang tidak memiliki pemetaan ditolak. Request tanpa izin mengembalikan 403, termasuk Sales/PPC yang sebelumnya dialihkan ke halaman lain.
- Sidebar, pencarian fitur, tautan dashboard, serta tombol aksi stok dan delivery mengikuti izin yang sama. Dashboard dan profil tetap tersedia untuk seluruh akun aktif; ringkasan dashboard tetap mengikuti role.
- Perubahan berlaku pada request berikutnya tanpa login ulang. Izin hanya di-cache selama satu request, bukan di session atau cache lintas request.
- Edit/hapus stok memerlukan Lihat Stok; picking, split/restore, hapus dan redo delivery memerlukan Jadwal Delivery. Form menyelaraskan pilihan dependensi, dan backend menolak kombinasi yang tidak valid.
- Izin fitur tidak menghapus pembatasan kepemilikan order/sesi maupun aturan integritas inventory/delivery. Role baru yang diberi picking hanya boleh membaca picklist sesi miliknya; Admin Warehouse/Admin mempertahankan hak melihat picklist sesuai aturan sebelumnya.
- Setiap penyimpanan izin dicatat sebagai audit `role_feature_access`, berisi role, izin sebelum/sesudah, dan admin pelaksana, dalam transaksi yang sama.
- Semua user yang login dapat memperbarui profil.

File utama:

- `app/Http/Controllers/AuthController.php`
- `app/Http/Controllers/ProfileController.php`
- `app/Http/Middleware/CheckFeatureAccess.php`
- `app/Services/FeatureAccessService.php`
- `app/Http/Controllers/FeatureAccessController.php`
- `app/Models/RoleFeatureAccess.php`
- `config/feature_access.php`
- `resources/views/admin/access/index.blade.php`
- `resources/views/shared/layouts/partials/sidebar/features.blade.php`
- `tests/Feature/FeatureAccessManagementTest.php`
- `routes/web.php`

Migration `2026_10_15_000001_create_role_feature_access_table` telah diterapkan ke database lokal pada 15 Oktober 2026. Deployment lain memerlukan migration ini sebelum menggunakan versi aplikasi baru. Role dan akun yang sudah ada tidak diubah oleh migration.

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

### Pembatalan dan batas tidak aktif Picking Verification (13 Oktober 2026)

- Halaman Picking Verification memiliki tombol `Batalkan Verifikasi`, dengan gaya tombol, `WarehouseAlert.confirm`, dan endpoint pembatalan yang sama dengan Scan Final Delivery. Pemilik sesi atau Admin dapat membatalkan; sesi `blocked` hanya dapat dibatalkan Admin.
- Timer khusus verifikasi menggunakan `delivery_pick_sessions.verification_last_activity_at`. Sesi `scanning` dibatalkan setelah 10 menit tanpa aktivitas pengguna di halaman verifikasi. Scan, keyboard, input, klik/sentuhan, pergerakan pointer, dan scroll mengirim pembaruan aktivitas yang dibatasi frekuensinya; polling status tidak memperpanjang waktu.
- Waktu diverifikasi di server dengan row lock sebelum perpanjangan aktivitas, scan, dan perpindahan tahap. Sesi kedaluwarsa tidak dapat dihidupkan kembali oleh request terlambat. Scan ulang box yang sudah terverifikasi tetap dikirim ke server untuk memeriksa validitas sesi.
- Timeout mengubah status menjadi `cancelled`, menghapus pick item dan progres verifikasi, serta mencatat alasan pada `approval_notes`. Stok fisik, withdrawal, dan assignment delivery yang sudah ada tidak diubah; lock picking dilepas.
- Sesi `blocked` atau sesi dengan pending issue tidak dibatalkan otomatis. Setelah approval issue, waktu verifikasi yang masih aktif dimulai kembali. Saat membuka Scan Final Delivery, timer verifikasi dimatikan. Tab verifikasi lama tidak dapat mengaktifkan kembali timer melalui endpoint aktivitas.
- Sesi lama sebelum migration belum memiliki timer sampai halaman verifikasi dibuka atau proses start-verification diakses kembali. Sesi yang telah memiliki item final `scanned` tidak diaktifkan timer verifikasinya saat membuka kembali halaman verifikasi.
- Command `php artisan delivery:expire-verifications` dijadwalkan setiap menit; cleanup background dapat terlambat sampai sekitar satu menit setelah batas 10 menit. Halaman aktif juga mengecek batas waktu, dan membuka daftar/start verifikasi membersihkan sesi kedaluwarsa tanpa menunggu scheduler.
- Development: migration `2026_10_13_000001_add_verification_activity_to_delivery_pick_sessions` diperlukan. Untuk cleanup ketika browser ditutup, jalankan Laravel scheduler (`php artisan schedule:work` di terminal development). Production perlu menjalankan migration dan memastikan scheduler Laravel berjalan setiap menit saat deployment; perubahan ini tidak otomatis diterapkan ke server.
- File utama tambahan: `app/Services/PickingVerificationActivityService.php`, `public/js/pages/picking-verification-session.js`, `tests/Feature/PickingVerificationActivityTest.php`, dan `tests/Frontend/picking-verification-session.test.js`.

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

- membuat satu palet baru otomatis untuk seluruh box dari delivery yang di-redo;
- memindahkan seluruh box hasil restore ke palet konsolidasi tersebut;
- meminta satu lokasi kosong untuk palet konsolidasi;
- mengosongkan lokasi dan ringkasan palet asal hanya jika tidak ada box aktif yang tersisa;
- mengubah withdrawal menjadi `reversed`;
- mengembalikan nilai `pallet_items`;
- mengembalikan `fulfilled_quantity`;
- menghapus assignment delivery dari box;
- membatalkan request not-full terkait;
- mengunci dan mengisi lokasi tujuan palet konsolidasi secara atomik;
- menandai completion sebagai `redone`;
- mengubah order menjadi `processing`;
- membuat audit redo.

Redo hanya berlaku satu kali untuk session `completed` dengan `completion_status=completed` dan `redo_until` yang belum lewat. Untuk shared box, seluruh box yang direstore dipindahkan ke pallet konsolidasi baru; pallet asal yang masih memiliki box aktif tetap mempertahankan lokasi dan ringkasannya, sedangkan pallet asal yang kosong dilepas dari lokasi dan stock location-nya. Session assignment pending yang tersisa dibatalkan saat redo.

### 15. Merge palet

Warehouse Operator, Admin Warehouse, dan Admin dapat menggabungkan minimal dua palet.

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

### 16. Pemindahan pallet

Warehouse Operator, Admin Warehouse, dan Admin dapat memindahkan pallet aktif tanpa
mengubah nomor pallet, box, atau ringkasan pallet.

Alur:

- operator memilih pallet melalui scan atau pencarian nomor pallet;
- pallet wajib memiliki box aktif, lokasi master yang valid, dan tidak sedang digunakan delivery/picking;
- lokasi tujuan wajib berasal dari Master Location dan tersedia;
- backend mengunci pallet serta lokasi sumber/tujuan dalam transaksi;
- occupancy lokasi sumber dilepas, lokasi tujuan diklaim atomik, dan `stock_locations` diperbarui;
- kegagalan validasi atau konflik lokasi membatalkan seluruh transaksi;
- pemindahan dicatat sebagai audit type `pallet_location_moved`.

File utama:

- `app/Http/Controllers/MovePalletController.php`
- `resources/views/operator/move-pallet/index.blade.php`

### 17. Expired box dan aktivasi ulang (13 Oktober 2026)

Umur box menggunakan `boxes.created_at` sebagai tanggal penyimpanan per box. Mapping `stock_input_boxes -> stock_inputs.stored_at` tetap menjadi fallback untuk data legacy yang tidak memiliki tanggal box.

Status:

- di bawah 9 bulan: `active`;
- 9 sampai kurang dari 12 bulan: `warning`;
- 12 bulan atau lebih: `expired`;
- Handle pada warning/expired: `active` sampai `reactivated_until`, satu bulan kalender sejak Handle (`addMonthNoOverflow`, tanggal akhir menyesuaikan panjang bulan);
- setelah masa aktivasi ulang habis: `expired`, wajib Handle lagi agar dapat masuk FIFO;
- `handled` pada data box lama tetap nonaktif; migration tidak mengaktifkan data lama secara massal.

Box warning tetap boleh masuk FIFO tanpa Handle. Box expired/handled atau box dengan masa aktivasi ulang habis tidak termasuk stok aktif dan tidak boleh dipakai untuk delivery. Batas `reactivated_until` diperiksa langsung pada query stok/FIFO/assignment dan validasi scan/withdrawal/completion, sehingga box langsung ditolak meskipun scheduler belum memperbarui label status menjadi expired.

Saat status berubah menjadi expired, sistem menghitung ulang `pallet_items`, menghapus stock location pallet yang sudah kosong, dan mengosongkan master location terkait. Snapshot lokasi disimpan sebelum pelepasan lokasi.

Supervisi/Admin hanya dapat menekan Handle pada box expired untuk mengaktifkan box satu bulan. Warning tetap terlihat pada halaman Expired Box tanpa tombol Handle/lokasi; backend menolak request Handle untuk warning dan stok aktif yang belum expired. Box withdrawn, soft-deleted, assigned ke delivery, atau terkunci dalam sesi picking tidak dapat diaktifkan ulang. Request berulang saat masa aktif belum habis tidak memperpanjang deadline maupun menggandakan riwayat. Tanggal `boxes.created_at` tidak berubah, sehingga posisi FIFO tetap mengikuti penyimpanan asli.

Handle mempertahankan pallet dan lokasi yang masih valid. Jika lokasi sudah dilepas, UI meminta lokasi kosong; backend juga mendukung lokasi historis bila request tidak menentukan lokasi. `LocationAssignmentService` mengunci dan memvalidasi lokasi, menolak lokasi yang sudah ditempati pallet lain, lalu memulihkan ringkasan box/PCS. Kegagalan assignment lokasi membatalkan aktivasi ulang secara atomik.

Setiap aktivasi ulang disimpan sebagai baris baru `expired_box_reports` berstatus `handled`, termasuk petugas, waktu aktivasi, lokasi, dan `reactivated_until`. Riwayat sebelumnya tetap tersimpan. Halaman menampilkan kolom Aktif Sampai. Constraint unik `(box_id,status)` dihapus untuk mendukung riwayat berulang; index biasa dipertahankan. Jenis delivery issue `box_expired` kini didukung schema.

Command `expired-box:sync-statuses` dijadwalkan setiap menit untuk mengubah status yang waktunya habis dan menyinkronkan stok/lokasi. Scheduler harus aktif di deployment. Migration `2026_10_13_000002_add_box_reactivation_period` menambahkan deadline pada box dan history, serta memperbarui constraint riwayat dan jenis issue.

Setiap hari pukul 07.00 sistem:

- menyinkronkan status umur box;
- mengirim email ringkasan warning dan expired ke user role Supervisi jika terdapat data.

File utama:

- `app/Services/ExpiredBoxService.php`
- `app/Http/Controllers/ExpiredBoxController.php`
- `routes/console.php`

### 18. Laporan dan audit

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

## Audio Scan (diverifikasi 13 Oktober 2026)

- Stock Input, Picking Verification, dan Scan Final Delivery menggunakan pemutar bersama `public/js/scan-feedback.js`, dimuat melalui `resources/views/shared/partials/scan-feedback.blade.php`.
- Audio berasal dari `public/audio/OK.mp3` untuk input/scan berhasil dan `public/audio/NG.mp3` untuk input/scan gagal. File disertakan dalam repository dan diakses melalui URL `asset()`, sehingga deployment tidak bergantung pada folder Downloads komputer development.
- Stock Input memutar OK setelah server menerima ID box atau nomor part; seluruh pemanggilan suara error sebelumnya memakai NG. Pesan sedang memproses tidak memutar OK.
- Picking Verification dan Scan Final Delivery memutar NG untuk validasi input gagal, penolakan server, dan kegagalan koneksi. Scan ulang box yang sudah diverifikasi tetap memakai OK sesuai perilaku sukses yang sudah ada.
- Beep sintetis dan text-to-speech sebelumnya dihapus. Pemutar menyiapkan audio pada interaksi keyboard/pointer, menghentikan suara sebelumnya saat hasil baru datang, dan tidak menggagalkan proses scan jika audio tidak tersedia.
- Audio OK dan NG memakai penguatan 2x (sekitar +6 dB sebelum kompresi) melalui GainNode bersama, lalu kompresor untuk menekan puncak suara dan mengurangi risiko clipping. Volume akhir tetap mengikuti perangkat pengguna.
- Perubahan audio ini dibuat di project development; production memerlukan deployment file terkait.

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
- Riwayat Pallet mengagregasi audit log Pallet, transaksi input stok (`stock_inputs`), dan mutasi perpindahan box (`box_pallet_moved`).
- Riwayat Box mengagregasi audit log Box, transaksi input stok (`stock_inputs` via `stock_input_boxes`), dan catatan withdrawal (`stock_withdrawals`).
- Pencarian Stock View mencakup box aktif/expired, termasuk orphan, dan tetap mengecualikan withdrawn, soft-deleted, serta handled legacy. Pencarian otomatis beralih ke mode box_id saat user mencari box tanpa kecocokan nomor part.
- Merge Pallet mendukung pemilihan lokasi tujuan dari salah satu pallet sumber yang digabung; pembersihan pallet sumber melepas okupansi master location secara tuntas dan backend mendukung fallback pencarian master location via kode string `warehouse_location`.
- Stock Input memvalidasi ketersediaan lokasi secara defensif terhadap `master_locations` dan `stock_locations`; baris `stock_locations` yatim (orphan) yang tertinggal dari palet non-aktif/terhapus dibersihkan secara aman (self-healing) untuk mencegah bentrok unique constraint `master_location_id`.
- Assignment lokasi pada Stock Input, Delivery Assign, approval Box Not Full, Restore Withdrawal, Redo/relokasi, dan Merge Pallet menggunakan `App\Services\LocationAssignmentService` untuk locking `master_locations`, validasi konflik `stock_locations`, dan assignment atomik di dalam transaksi flow masing-masing (diverifikasi 6 Oktober 2026).
- Redo Withdrawal selalu memakai satu lokasi tujuan untuk satu pallet konsolidasi baru; UI tidak lagi meminta relokasi per pallet asal.
- Nomor pallet otomatis dialokasikan melalui `App\Services\PalletNumberService` dan tabel `pallet_number_sequences` yang dikunci database; generator tidak lagi bergantung hanya pada pola `MAX + 1` di tiap controller (diverifikasi 6 Oktober 2026).

## Keputusan Bisnis

- Fitur backorder dan partial withdrawal telah dihapus.
- Kekurangan stok tidak boleh dikirim sebagian melalui picking atau withdrawal.
- Delivery dengan kebutuhan yang akan dipisahkan harus memakai fitur split order.
- Status delivery `partial` tetap dipakai khusus untuk parent order yang memiliki child hasil split.
- Semua box not full wajib melalui request dan approval Supervisi; Stock Input dan Delivery Assignment tidak boleh membuat box not full secara langsung.
- PCS direct input wajib sama dengan fixed qty Master Part.
- Warning tetap dapat masuk FIFO dan tidak dapat di-Handle. Handle hanya pada expired, mengaktifkan box selama satu bulan kalender; saat masa aktif habis, box menjadi expired dan wajib Handle lagi. Aktivasi ulang tidak boleh dilakukan pada box assigned atau pick-locked.

## Status Schema dan Validasi Terakhir

- Seluruh migration sampai `2026_06_19_000005_make_audit_log_type_extensible` telah dijalankan pada MySQL lokal tanggal 19 Juni 2026.
- Backup sebelum migration terbaru tersimpan di `storage/app/backups/db_stock_before_integrity_hardening_2026-06-19_082641.sql`.
- Audit data aktif tidak menemukan duplicate stock location, lokasi tanpa master link, mismatch `pallet_items`, box aktif tanpa lokasi, session pending yatim, assignment invalid, lebih dari satu session picking aktif, maupun mismatch PCS Master Part tanpa approval not-full.
- Full regression suite terakhir: diverifikasi 15 Oktober 2026, 199 unit & feature test lulus (1.227 assertions), termasuk 12 test pengaturan hak akses. Form juga diverifikasi di browser untuk pilihan massal, dependensi, dan pembatasan fitur Admin Utama.
- Kolom `audit_logs.type` menggunakan string terindeks, bukan enum tertutup, sehingga audit fitur baru seperti `delivery_assignment` dapat disimpan tanpa melanggar constraint.

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
