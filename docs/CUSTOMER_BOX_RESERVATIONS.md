# Reservasi Box Customer Ekspor

Implementasi awal: 16 November 2026. Aktivasi lokal diverifikasi 18 November 2026.
Database lokal checkout ini sudah diaktifkan; server production belum diaktifkan
atau diperiksa dalam aktivasi ini.

Pembaruan pencocokan nama/alias 18 November 2026, form customer kini menyelaraskan
ID dan memeriksa master yang baru ditambahkan, backend form versi 1 juga dapat
mencocokkan nama/kode/alias saat hidden ID kosong. Konflik Excel dan kompatibilitas
integrasi lama tetap dijaga. Diagnosis perangkat lain, verifikasi dan paket
penerapan: [Perbaikan pencocokan customer](EXPORT_CUSTOMER_MATCHING_FIX_2026-11-18.md).

**Diagnosis dan aktivasi lokal, 18 November 2026**

- Admin Utama sudah memiliki seluruh izin customer. Tombol tambah dan kontrol
  reservasi tidak muncul karena migration customer masih Pending dan delapan
  tabel fiturnya belum tersedia. Panduan bergambar sebelumnya memakai database
  uji terpisah, sehingga tidak membuktikan aktivasi database aplikasi pengguna.
- Target terverifikasi: `local`, MySQL 8.4.3, `127.0.0.1`, database `db_stock`.
  Delapan tabel customer sekarang tersedia dan `ready()` bernilai true.
  `EXPORT_CUSTOMER_STOCK_WRITES_ENABLED` efektif true dari konfigurasi default.
- Pemeriksaan form juga menemukan tiga prasyarat tertunda: migration nomor part
  dokumen, versi izin tindakan dan penggunaan ulang nomor box. Ketiganya sudah
  diperiksa dan dijalankan secara terarah setelah rehearsal salinan database.
  Tidak menjalankan seeder atau mengisi customer/alias/reservasi contoh.
- Backup SQL dibuat sebelum setiap tahap dan berhasil dipulihkan ke database
  salinan pada server MySQL yang sama. Checksum kolom bisnis dan jumlah baris
  diperiksa sebelum/sesudah; ID, part, PCS, lokasi, tanggal, komitmen dan history
  lama tetap. Tiga prasyarat menambahkan metadata, mengonversi satu konfigurasi
  izin tanpa mengubah akses efektif role, dan menambahkan satu audit konversi.
  Pemetaan dokumen historis tetap null dan box lama tetap Umum. Metadata
  penggunaan pertama serta versi draft penerimaan mengikuti migration existing.
- Pemeriksaan render menggunakan Admin Utama lokal berhasil untuk Tambah customer,
  form master, Reservasi customer, pemilih customer pada penerimaan dan pemetaan
  master pada form delivery. Pembuatan customer diperiksa pada salinan database;
  database lokal tidak diisi data contoh. Maintenance sudah dilepas.
- Regresi SQLite in-memory: 74 tes / 1.256 assertions lulus, meliputi reservasi,
  migrasinya, nomor dokumen part, izin rinci/manajemen akses dan label reuse.
  Backup dan laporan privat berada di `storage/app/private/customer-activation-*`
  serta `storage/app/private/customer-prerequisites-*`, tidak masuk Git.
- Status ini hanya untuk database lokal tersebut. Deployment production tetap
  mengikuti runbook di bawah, termasuk verifikasi prasyarat dan seluruh instance.

**Alur operator**

1. Master Data Ekspor > Master Customer menyimpan kode, nama resmi, alias Excel,
   status aktif dan revisi. Nama/kode/alias unik antarcustomer setelah kapitalisasi
   dan spasi dinormalisasi. Alias tidak dibuat dari kemiripan nama. Nonaktif tidak
   menghapus histori atau membatalkan delivery yang sudah berjalan.
2. Penerimaan Transfer Box Ekspor mempunyai pilihan Pemilik stok sebelum scan.
   Kosong berarti Umum. Customer disimpan per baris draft di tabel tambahan;
   mengganti pilihan hanya berlaku untuk scan berikutnya. Proses penerimaan
   menyimpan pemilik sebelum alokasi otomatis. Draft lama tetap Umum.
3. Stok Ekspor > Reservasi customer menyediakan pencarian, filter pemilik/status,
   pilihan box per halaman serta Tetapkan customer dan Lepas reservasi. Penetapan
   hanya untuk stok umum. Alasan wajib; pilihan diperiksa ulang secara atomik
  terhadap revisi dan penguncian proses. Lokasi, PCS dan tanggal FIFO tetap.
4. Form rencana delivery menghubungkan nama/alias ke master melalui pemilih yang
   dapat diketik. Impor Excel tetap menerima format lama, satu customer per file.
   Nama/kode/alias yang cocok memilih ID master; konflik atau nama tidak dikenal
   perlu dikoreksi di preview. Metadata Kode Customer bersifat opsional. Preview,
   undo impor dan perubahan nama/pilihan menjaga ID dan nama sumber tetap selaras.
5. Delivery Schedule > detail delivery > Stok customer lain membuka tinjauan
   kekurangan per item pallet. Pilih customer sumber, box FIFO yang sesuai dan
   alasan. Pemilik sumber tetap, pengecualian hanya untuk delivery/item tersebut.
   Simpan mengaktifkan tahap tinjauan FIFO existing, bukan melewati Oke/Mulai
   Picking/verifikasi/penempatan. Tidak memerlukan approval Supervisi baru.
6. Pengecualian yang belum dipicking/ditempatkan dapat dibatalkan di tinjauannya.
   Jika proses fisik berjalan, gunakan Revisi pallet/pengembalian existing.
   Pengurangan atau pembatalan delivery menutup pengecualian bersama pelepasan
   komitmen. Pengiriman menyimpan snapshot pemilik dan penerima, lalu menandai
   pengecualian terpakai. Nomor penggunaan box dan revisi pengecualian melindungi
   dari label reuse dan formulir pembatalan lama.

**Aturan inventory**

- FIFO customer tujuan didahulukan, lalu FIFO Umum. `first_received_at` dan prioritas
  tenggat/ID delivery serta urutan pallet tetap dipakai; stok B tidak masuk alokasi
  otomatis A. Komitmen yang sudah ada tidak diseimbangkan ulang.
- Pengecualian mengambil kekurangan yang masih ada setelah allocator memeriksa
  ulang stok normal di bawah mutex inventory. Box yang terikat delivery lain,
  issue, penyiapan Not Full atau pallet sumber dalam picking tidak dipinjamkan.
- Customer delivery dikenali melalui ID, bukan tebakan nama. Integrasi lama yang
  tidak mengirim identitas master tetap kompatibel, tetapi hanya boleh mendapat
  stok Umum. Pemetaan delivery historis disediakan pada detail delivery bagi
  pemilik izin kelola reservasi, dengan alasan dan pemeriksaan konflik komitmen.
- Rename master tidak menulis ulang nama pada delivery/history yang sudah ada,
  termasuk ketika quantity rencana yang memakai ID customer sama diperbarui.
- Kelola reservasi hanya mengubah stok cadangan yang sudah ditempatkan dan bebas.
  Box dalam draft, belum ditempatkan, expired, handling atau terkunci tetap tidak
  dapat dilepas/dipetakan melalui pengelolaan ini.
- Pengecualian memilih box dengan part dan PCS yang tepat. Fitur ini tidak
  melewati approval atau aturan pemecahan Not Full existing.
- Hasil pecah dan packing dengan `source_box_id` mewarisi pemilik sumber sebelum
  dapat dikomitmenkan. Pengecualian sumber tidak otomatis dibagikan ke hasilnya.
  Kepemilikan tidak hilang karena move, merge, expired atau QA. Reuse nomor label
  adalah PK/penggunaan baru dan tidak mewarisi kepemilikan penggunaan lama.
- Penjaga yang sama dipakai pada pembuatan komitmen, tinjauan/ACK, revisi, merge ke
  permintaan, Not Full, mulai/cari/scan picking, placement, Siap dan completion.
- Kesiapan Customer memakai identitas master untuk delivery yang sudah dipetakan,
  dengan fallback nama pada data historis. Stok customer lain dikeluarkan dari
  ketersediaan. Stok umum tetap merupakan ketersediaan bersama, bukan janji baru
  kepada setiap customer. Komitmen final dibuat oleh allocator dalam transaksi.
- Lokal tidak mendapat perubahan aturan reservasi/alokasi.

**Hak akses**

| Izin | Kegunaan |
| --- | --- |
| `export_customers` | Baca daftar master customer dan menu baru |
| `export_customers_manage` | Tambah/ubah kode, nama, alias dan status master |
| `export_stock_customer_manage` | Tetapkan/lepas reservasi dan petakan delivery lama |
| `export_stock_customer_exception` | Tinjau, buat dan batalkan pengecualian customer lain |

Ketiga izin tulis tidak diberikan otomatis dari hak penerimaan/picking atau
pengelolaan stok existing. Admin Utama mengaturnya per role melalui Hak Akses.
Role dengan grant tersimpan mempertahankan daftar existing; migration tidak
mengubah grant tersebut. Admin Utama tetap penuh. Membaca pilihan master pada
form existing mengikuti izin baca modul terkait, tanpa membuka hak kelola master.

**Penyimpanan dan integritas**

Migration `2026_11_16_000001_create_export_customer_stock_reservations` hanya membuat
delapan tabel: master/alias, hubungan delivery, pemilik box, pemilik baris draft,
pengecualian, event dan request idempotensi. Tidak menambah kolom atau melakukan
backfill pada tabel stok/delivery existing. Tidak membuat customer contoh.

Pemilik terkini unik per PK `export_stock_boxes`; event dan audit menyimpan
snapshot label, penggunaan, part, PCS, customer dan alasan. Pengecualian menyimpan
ID komitmen serta revisinya agar izin lama tidak berlaku pada komitmen baru.
Foreign key tidak menghapus histori saat komitmen/item direvisi. Draft yang
dihapus hanya melepaskan pilihan customer baris draft itu.

Semua mutasi mengikuti mutex `ExportInventoryService` sebelum lock lain. Request
reservasi/pengecualian memakai token UUID, hash payload/aktor dan transaksi yang
sama dengan audit. Audit gagal atau satu anggota berubah membatalkan satu batch.
Hak akses dan kondisi stock diperiksa lagi saat commit, bukan hanya di tombol.
Retry penerimaan membandingkan customer pada snapshot penerimaan asli, sehingga
perubahan reservasi sesudahnya tidak ditimpa dan tidak membuat box baru.

`EXPORT_CUSTOMER_STOCK_WRITES_ENABLED=false` menghentikan tindakan baru fitur ini.
Nilai tersebut tidak mematikan penjaga reservasi yang sudah tersimpan. Schema
yang belum tersedia tetap mendukung alur existing tanpa pemetaan customer.

**Runbook deployment**

1. Periksa daftar migration/schema production yang benar, termasuk inventory
   Ekspor, penggunaan ID box, draft penerimaan dan hak akses rinci. Jangan memakai
   status database development sebagai bukti status production.
2. Buat backup dan uji pemulihannya. Rehearsal pada salinan production dengan
   versi MySQL yang sama tetap diperlukan untuk waktu DDL, metadata lock dan
   jumlah data nyata. Catat jumlah/checksum kolom bisnis dan komitmen sebelum.
3. Set `EXPORT_CUSTOMER_STOCK_WRITES_ENABLED=false` dalam konfigurasi deployment.
   Siapkan jeda write/cutover yang mencegah request lama berjalan setelah aktivasi.
4. Jalankan hanya migration yang sudah diperiksa, bukan seluruh pending secara
   otomatis. Migration fitur ini:

   ```sh
   php artisan migrate --path=database/migrations/2026_11_16_000001_create_export_customer_stock_reservations.php --force
   ```

5. Deploy kode dan aset ke seluruh instance, perbarui cache konfigurasi sesuai
   prosedur hosting, restart/drain worker dan proses lama. Pastikan tidak ada
   instance lama yang dapat mengalokasikan stok tanpa penjaga customer.
6. Cocokkan data/checksum lama, uji halaman tanpa pemetaan, kemudian aktifkan write
   dan perbarui cache konfigurasi. Buat master/alias melalui tinjauan operasional;
   berikan izin tulis hanya ke role yang dikehendaki.
7. Box lama tetap Umum sampai dipetakan secara eksplisit. Jika harus terlindungi
   sejak operasi dibuka, verifikasi daftar fisiknya dan petakan di jendela cutover.
   Jangan menebak customer dari pallet atau histori pengiriman. Box berkomitmen
   menunggu proses selesai/pelepasan existing sebelum kepemilikannya diubah.
8. Mulai dengan sedikit box, pantau kekurangan, penolakan konflik dan audit. Jika
   terjadi masalah, hentikan write baru dan perbaiki maju. Penjaga customer tetap
   aktif. Jangan kembali ke binary yang mengabaikan reservasi atau menghapus tabel
   baru setelah digunakan. `down()` menolak penghilangan data fitur yang terpakai.

**Bukti verifikasi dan batasnya**

- 21 tes khusus fitur/migration lulus, 98 assertions: FIFO sendiri/umum, penolakan
  lintas customer, izin operator dan revokasi, idempotensi, snapshot pengiriman,
  pembatalan/stale form, alias/konflik kode, draft per box, lineage, label reuse,
  rename master, retry penerimaan setelah reservasi berubah, audit rollback dan
  checksum tabel lama sebelum/sesudah migration.
- Suite penuh SQLite in-memory: 754 tes, 7.948 assertions, 747 lulus, tujuh
  kegagalan baseline yang sama seperti memo awal, tanpa error. Kegagalan baseline:
  rentang laporan, dua binder Excel, seeder production, dan tiga preview dashboard.
- Seluruh 169 tes frontend dan build Vite lulus. Guard scan tambahan diperiksa
  kembali bersama regresi picking/rekomendasi dan tes reservasi.
- MySQL 8.4.3 dijalankan pada port dan direktori data sementara yang terpisah.
  Delapan DDL baru berhasil, baris probe existing tetap sama, koneksi kedua
  tertahan mutex inventory dan unique per-box menolak pemilik ganda. Instance
  probe dihentikan dan direktori datanya dibersihkan. Ini bukan salinan data
  production dan bukan bukti durasi DDL production.
- Browser Chrome memakai aplikasi Laravel berjalan dengan akun fiktif dan file
  SQLite terpisah. Mencakup scan/customer per box, pengelolaan reservasi, stok
  terkunci, pengecualian/pembatalan, alias pada form dan simpan delivery, serta
  viewport desktop/390 px. Pemeriksaan memakai perangkat lunak, bukan scanner
  fisik atau ponsel nyata. Database operasional/production tidak dimigrasikan.

File utama: `ExportCustomerService`, `ExportCustomerReservationService`,
`ExportCustomerStockPolicy`, `ExportCustomerStockExceptionService`, dua controller
Customer baru, integrasi service Ekspor existing, view `export/customers`,
`export/stock/customer-reservations` dan `export/fulfillment/customer-exception`.
