# Rencana Reservasi Box per Customer Ekspor

Tanggal penyusunan dan pemeriksaan source: 16 November 2026.
Status: rancangan disetujui dan diterapkan pada kode checkout ini, 16 November 2026.
Implementasi, bukti verifikasi dan runbook: `docs/CUSTOMER_BOX_RESERVATIONS.md`.
Migration belum dijalankan pada database operasional atau production.

Dokumen ini memakai `docs/AGENT_SYSTEM_MEMO.md` dan source yang relevan sebagai
baseline. Kondisi schema dan data production belum diperiksa. Dokumen ini tetap
menyimpan rancangan awal; status implementasi mengikuti dokumen di atas.

**Keputusan pengguna yang sudah ditetapkan**

- Berlaku untuk Ekspor saja. Alur Lokal tetap memakai aturan yang sekarang.
- Reservasi customer melekat pada masing-masing box, termasuk sebelum ada delivery.
- Pemenuhan A memakai stok customer A dahulu, kemudian stok umum. FIFO diterapkan
  di dalam masing-masing kelompok tersebut.
- Stok customer lain tidak boleh diambil oleh alokasi otomatis.
- Operator dapat membuat pengecualian melalui hak akses tindakan khusus, dengan
  alasan wajib dan audit. Tidak ada approval Supervisi per transaksi pengecualian.
- Pengecualian hanya berlaku untuk delivery tujuan. Box B yang dipakai untuk A
  tetap tercatat sebagai milik B; jika dibatalkan, box kembali tersedia bagi B.
- Data production dan komitmen delivery yang sudah ada harus dipertahankan.

**Kondisi implementasi saat ini**

| Bagian | Kondisi source | Implikasi |
| --- | --- | --- |
| Identitas customer | `ExportDeliveryPlan.customer_name` berupa teks; kesiapan customer memakai nama yang dinormalisasi | Reservasi perlu identitas customer stabil; nama lama tidak boleh ditulis ulang otomatis |
| Stok fisik | `ExportStockBox` menyimpan penggunaan ID, part, PCS, posisi fisik, dan tanggal masuk pertama | Kepemilikan harus mengacu pada PK box/penggunaan yang tepat, bukan nomor label saja |
| Reservasi delivery | `ExportFulfillmentBox` menghubungkan stok ke item pallet rencana | Reservasi customer menjadi lapisan tambahan; komitmen delivery existing tetap dipakai |
| FIFO | `ExportStockAllocationService` memilih part + PCS, `first_received_at`, lalu ID; delivery diurutkan menurut tenggat dan ID | Tambahkan seleksi customer tanpa mengganti umur stok atau prioritas delivery |
| Penerimaan | `ExportStockReceivingService` dapat memanggil allocator dalam transaksi penerimaan | Customer harus tersimpan sebelum allocator berjalan |
| Revisi | `revisionCandidates` menghitung kebutuhan terdahulu terhadap kumpulan stok bersama | Pratinjau perlu simulasi konsumsi kumpulan customer/umum, bukan hanya offset global |
| Kesiapan customer | `ExportStockCustomerReadiness` memakai `freeBoxes` dari `ExportStockQueryService` | Pisahkan stok umum, stok customer, dan stok yang sudah terikat delivery |
| Mutasi kritis | `ExportInventoryService` menyediakan mutex inventory sebelum lock lain | Pengaturan reservasi dan pengecualian harus mengikuti urutan lock yang sama |

**Aturan yang akan diimplementasikan**

1. Satu penggunaan box hanya dapat mempunyai satu reservasi customer aktif.
   Tanpa reservasi customer, box diperlakukan sebagai stok umum, selama tetap
   memenuhi seluruh aturan kelayakan existing.
2. Reservasi customer tidak memindahkan pallet/lokasi, tidak membuat stok baru,
   tidak mengubah PCS, dan tidak mengubah `first_received_at`.
3. Box dapat sekaligus milik B dan berkomitmen ke delivery A hanya jika mempunyai
   pengecualian sah untuk kombinasi box, penggunaan, delivery, dan item tujuan itu.
   Pengecualian tidak membuka stok B lainnya dan tidak berlaku pada delivery A lain.
4. Pengecualian hanya mengambil kekurangan yang masih ada setelah stok sendiri
   dan umum yang layak diperhitungkan. Customer sumber dan box ditinjau operator;
   rekomendasi box sumber mengikuti FIFO dalam customer sumber tersebut.
5. Box yang terikat delivery lain, picking/issue aktif, QA/packing, penyiapan
   Not Full, atau tidak layak menurut aturan existing tetap terlindungi. Hak
   pengecualian customer tidak membuka penguncian proses tersebut.
6. Pengecualian tidak mengambil box yang sudah dijanjikan kepada delivery B.
   Melepas komitmen lama tetap melalui revisi/pengembalian existing lebih dahulu.
7. Pembatalan/pengurangan delivery melepas komitmen dan pengecualian terkait secara
   atomik. Box B yang kembali tetap milik B, termasuk setelah pengembalian fisik
   yang diwajibkan alur existing. Membuka ulang status Siap saja tidak melepas
   komitmen atau kepemilikan.
8. Saat pengiriman selesai, pengecualian ditandai terpakai dan snapshot menyimpan
   customer pemilik serta penerima. Tidak otomatis membuat hutang atau stok
   pengganti untuk B. Jika penggantian diperlukan, itu menjadi kebutuhan terpisah.
9. Pindah/merge pallet cadangan mempertahankan pemilik per box. Satu pallet dapat
   menampung beberapa customer secara logis; masuk ke pallet delivery tetap harus
   memvalidasi pemilik atau pengecualian setiap box.
10. Expired tetap tidak layak delivery dan tetap menahan lokasi sesuai aturan
    existing. Status expired tidak otomatis menghapus reservasi customer.
11. Hasil QA/packing dan pecah Not Full mewarisi pemilik sumber. Penerimaan ulang
    hasil proses tersebut harus menulis kepemilikan sebelum alokasi berjalan.
    Pengelompokan QA/packing perlu menjaga pemetaan pemilik sumber ke hasil;
    campuran customer tidak boleh kehilangan asal karena pengelompokan part/PCS.
    Pengecualian tidak diwariskan bebas ke semua hasil pecahan atau ID pengganti.
12. Penggunaan ulang nomor label membuat identitas penerimaan baru. Reservasi dan
    pengecualian penggunaan sebelumnya tidak ikut melekat pada penggunaan baru.
13. Perubahan customer delivery yang sudah berkomitmen tidak boleh mengubah hak
    atas box secara diam-diam. Perlu tinjauan ulang, atau ditolak sampai komitmen
    dilepas melalui alur existing.

Contoh: delivery A memerlukan 10 box; stok layak A ada 6, umum 2, B ada 8. Sistem
mereservasi 6 milik A dan 2 umum. Kekurangan 2 tetap terlihat. Operator berizin
dapat memilih 2 box B melalui pengecualian. Enam box B lainnya tetap terlindungi.
Jika delivery dibatalkan, dua box pinjaman tetap berada dalam kelompok B.

**Rancangan penyimpanan tambahan**

Utamakan tabel tambahan agar tidak perlu menulis ulang tabel stok/history besar.
Nama tabel di bawah merupakan usulan; definisi migration final mengikuti hasil
preflight schema production.

| Data tambahan | Tujuan dan batasan |
| --- | --- |
| Master customer Ekspor dan alias terverifikasi | ID stabil; nama/alias tidak digabung hanya berdasarkan kemiripan. Customer historis tidak dihapus |
| Pemetaan delivery ke customer | Satu hubungan per delivery Ekspor melalui FK; `customer_name` lama dipertahankan untuk kompatibilitas dan histori |
| Reservasi customer per stock box | FK ke `export_stock_boxes`, unique per box untuk keadaan terkini, customer, revision, pelaku, waktu, alasan |
| Header dan rincian pengecualian | Delivery/item tujuan, box yang tepat, pemilik asal, alasan, pelaku, token idempotensi dan status aktif/terpakai/dibatalkan |
| Riwayat reservasi dan snapshot | Catatan perubahan/pelepasan/pemakaian; nomor label, penggunaan, PK, part, PCS, pemilik, penerima dan referensi transaksi |

Histori tidak boleh ikut hilang karena cascade delete. Penanganan sumber yang
diarsipkan/dihapus harus mempertahankan snapshot. Database menjamin satu pemilik
terkini per box; mutex dan locking read menjamin tidak ada dua komitmen/pengecualian
aktif yang menghabiskan box yang sama. Jangan mengandalkan nomor label sebagai FK.

Usulan service:

- `ExportCustomerStockPolicy`: kumpulan kandidat sendiri/umum, FIFO, dan validasi
  box terhadap customer/delivery. Dipakai oleh preview maupun mutasi final.
- `ExportCustomerReservationService`: menetapkan/melepas reservasi pada stok
  existing dan saat penerimaan, dengan alasan, version check, dan audit.
- `ExportCustomerStockExceptionService`: membuat pengecualian dan komitmen tujuan
  dalam transaksi yang sama; tidak meninggalkan izin terbuka tanpa target.

Semua mutasi mengambil mutex `ExportInventoryService` lebih dahulu, kemudian
mengunci delivery/plan, stok, dan data reservasi dalam urutan konsisten. Revisi
stok maupun kepemilikan harus membatalkan preview/fingerprint lama. Kesalahan
audit, perubahan jumlah, konflik customer, atau kegagalan satu anggota batch
membatalkan seluruh transaksi. Retry token yang sama tidak menggandakan aksi.

**Tahapan implementasi**

1. **Preflight dan kontrak data.** Periksa schema/version production secara baca
   saja, jumlah stok dan komitmen, delivery aktif, variasi nama customer, draft
   penerimaan, proses QA/Not Full, dan migration yang belum diterapkan. Buat
   inventaris deployment yang tepat; status migration development bukan bukti
   production. Tentukan daftar customer dan alias melalui tinjauan eksplisit.
2. **Schema tambahan dan fondasi.** Buat tabel/FK/index baru serta service policy
   dan validasinya. Tabel reservasi/pengecualian awal kosong. Jangan mengisi pemilik
   dari histori pengiriman atau lokasi pallet. Perilaku sebelum ada pemetaan
   harus menghasilkan komitmen dan urutan FIFO yang sama dengan baseline.
3. **Penerimaan dan pengelolaan stok.** Tambahkan pilihan Customer/Umum pada
   penerimaan, disimpan per box sebelum allocator. Sediakan penetapan customer
   untuk box existing yang belum berkomitmen. Aksi massal menampilkan daftar dan
   konflik sebelum simpan; konflik menyebabkan seluruh permintaan ditolak.
   Draft lama yang tidak membawa customer tetap kompatibel sebagai Umum.
4. **Alokasi dan seluruh jalur pemenuhan.** Terapkan urutan sendiri, umum, lalu
   pengecualian eksplisit pada allocation, tinjauan FIFO/acknowledge, revisi,
   Not Full, merge ke permintaan, picking/scan, penempatan, Siap, dan completion.
   Perhitungan kebutuhan delivery terdahulu harus memakai simulasi stok bersama
   yang sadar customer. Komitmen existing tidak dialokasikan ulang otomatis.
5. **Pengecualian dan hak akses.** Tambahkan tindakan kelola reservasi dan gunakan
   stok customer lain ke sistem Hak Akses existing. Hak pengecualian tidak
   diwariskan otomatis dari izin scan/penerimaan. Admin mengaktifkannya untuk
   role yang dipilih; pembatasan saat ini berbasis role, bukan user individual.
   Server memeriksa izin, alasan, kekurangan terkini, revision, dan semua box
   saat commit. Picker berikutnya memvalidasi pengecualian yang tersimpan untuk
   delivery tersebut, tanpa membuat pengecualian baru dari request scan.
6. **Visibilitas, audit dan proses turunan.** Tampilkan Pemilik customer terpisah
   dari Delivery tujuan di stok/detail/picklist. Kesiapan customer menghitung
   stok miliknya dan stok umum yang masih tersedia tanpa memasukkan stok B
   sebagai jaminan bagi A. Stok umum yang sama tidak boleh dijanjikan dua kali
   dalam simulasi lintas delivery. Excel, audit, pengembalian dan hasil QA/Not Full
   memuat informasi customer yang benar. Histori lama tanpa snapshot ditampilkan
   sebagai belum tercatat, bukan dilengkapi dari pemilik stok saat ini.
7. **Regresi dan aktivasi bertahap.** Jalankan pengujian berikut, rehearsal MySQL,
   serta UAT operator. Aktifkan hanya setelah seluruh jalur penjaga terpasang.
   Perbarui memo sistem dan tanggal verifikasinya setelah implementasi selesai.

File integrasi utama yang sudah diperiksa:

- `app/Models/ExportStockBox.php`, `ExportDeliveryPlan.php`, `ExportFulfillmentBox.php`.
- `app/Services/ExportStockAllocationService.php`, `ExportStockReceivingService.php`,
  `ExportInventoryService.php`, `ExportStockQueryService.php`,
  `ExportStockCustomerReadiness.php`.
- `app/Services/ExportFulfillmentRevisionService.php`, `ExportFulfillmentCompletionService.php`,
  `ExportNotFullService.php`, `ExportPalletMergeService.php`, `ExportPickingService.php`.
- Integrasi berikutnya mencakup `ExportDeliveryPlanService`, impor delivery,
  penanganan expired/penerimaan packing, retur issue dan seluruh pemanggil service
  tersebut yang relevan; sumber ini perlu dibaca terarah saat implementasi.
- `config/feature_actions.php`, `config/feature_access.php`, route serta form
  penerimaan/stock/planning dan laporan Ekspor terkait.

**Strategi mempertahankan production**

- Migration bersifat tambahan, tanpa reset database, penggantian ID, atau backfill
  kepemilikan otomatis. Tidak mengubah part, PCS, jumlah fisik, tanggal FIFO,
  lokasi, withdrawal, histori audit, dan reservasi delivery existing.
- Stok lama tanpa pemetaan customer tetap mengikuti perilaku Umum. Ini berarti
  stok lama belum mendapat perlindungan customer sampai dipetakan secara eksplisit.
  Jika perlindungan diperlukan sejak hari aktivasi, siapkan daftar box yang telah
  diverifikasi dan proses pemetaannya pada jendela cutover sebelum operasi dibuka.
- Box berkomitmen/picking existing tidak boleh dipetakan ke customer yang konflik.
  Untuk fase awal, tunda perubahan kepemilikan box tersebut sampai prosesnya
  selesai atau komitmennya dilepas melalui prosedur existing.
- Delivery lama tanpa hubungan customer tetap mempertahankan komitmennya. Alokasi
  tambahan hanya boleh memakai Umum sampai customer dipetakan secara eksplisit;
  nama tidak boleh ditebak untuk membuka akses ke stok customer.
- Uji migration pada salinan production dengan versi MySQL yang sama. Rekam
  jumlah dan checksum kolom bisnis tabel lama sebelum/sesudah, waktu DDL, locking,
  indeks dan query plan. SQLite saja tidak cukup untuk validasi production.
- Siapkan backup dan uji restore; gunakan daftar migration terverifikasi, bukan
  menjalankan seluruh migration pending tanpa mengetahui dampaknya.
- Deploy schema dahulu, lalu kode pada seluruh instance/worker dengan pembuatan
  reservasi masih nonaktif. Jalankan simulasi baca saja dan bandingkan baseline.
  Aktivasi dilakukan setelah tidak ada proses aplikasi lama yang bisa mengabaikan
  reservasi baru; gunakan jeda write singkat bila diperlukan hasil rehearsal.
- Pisahkan sakelar pembuatan reservasi/pengecualian dari penegakan reservasi.
  Setelah reservasi pertama ada, mematikan UI atau fitur baru tidak boleh membuat
  stok terlindungi kembali tersedia bagi semua customer.
- Jika terjadi masalah setelah aktivasi, hentikan aksi baru yang terdampak dan
  gunakan perbaikan maju atau versi rollback yang tetap memahami reservasi.
  Jangan kembali ke binary lama yang mengabaikan tabel reservasi, menghapus tabel
  baru, atau melakukan restore yang menghilangkan transaksi sah setelah backup.
- Pilot membatasi volume penetapan reservasi dan pemberian izin; penjaga stok
  tetap berlaku untuk seluruh jalur Ekspor pada semua instance.

**Pengujian dan syarat selesai**

| Skenario | Hasil wajib |
| --- | --- |
| A mempunyai stok lebih muda; B mempunyai stok lebih tua | Delivery A hanya memilih stok A/Umum sesuai kebijakan, tanpa mengambil B |
| Stok Umum lebih tua daripada stok A | Stok A tetap didahulukan, kemudian FIFO di Umum |
| Dua delivery customer yang sama | Prioritas tenggat/ID/pallet tetap; satu box tidak dipakai dua kali |
| A kurang, B tersedia | Alokasi otomatis mencatat kekurangan; tidak mengambil B |
| Pengecualian B untuk satu delivery A | Hanya box dan tujuan terpilih yang boleh dipakai; alasan dan pelaku tercatat |
| Permintaan API langsung tanpa izin/pengecualian | Ditolak, termasuk jalur merge, Not Full, placement dan completion |
| Kekurangan hilang setelah preview | Hitung ulang atau tolak preview; tidak mengambil stok B berlebihan |
| B sudah terikat delivery lain/picking/QA | Ditolak meskipun operator punya hak pengecualian |
| Dua operator merebut box yang sama | Hanya satu transaksi berhasil; transaksi lain mendapat konflik yang jelas |
| Audit gagal atau satu anggota batch berubah | Seluruh mutasi dibatalkan |
| Submit/retry berulang | Tidak membuat reservasi, pengecualian atau komitmen ganda |
| Delivery A dibatalkan/dikurangi | Pengecualian yang dilepas berakhir; box tetap milik B |
| Pindah/merge cadangan dan pallet campuran | Pemilik per box dan tanggal FIFO tetap benar |
| Hasil QA/packing atau pecah Not Full | Customer sumber diwarisi; tidak menjadi Umum tanpa tindakan eksplisit |
| Nomor label digunakan ulang | Penggunaan baru tidak mewarisi reservasi/pengecualian lama |
| Pemetaan customer belum tersedia/ambigu | Tidak membuka stok customer berdasarkan tebakan nama |
| Migration pada snapshot berisi transaksi lama | Nilai bisnis lama dan komitmen tetap identik; tidak ada backfill spekulatif |
| Fitur pembuatan dimatikan setelah reservasi ada | Reservasi tetap ditegakkan |
| Regresi Lokal | Alokasi, picking, penerimaan dan hak akses Lokal tetap sesuai baseline |

Selesai jika seluruh jalur backend memakai aturan yang sama, data lama tetap
utuh, kasus konkurensi lulus pada MySQL, operator dapat meninjau/membatalkan
pengecualian dengan jejak lengkap, dan runbook deployment/pemulihan telah diuji.

**Default usulan yang dapat disesuaikan saat perincian implementasi**

- Penerimaan menyediakan pilihan eksplisit Customer atau Umum; default draft lama
  adalah Umum. Pemetaan disimpan per box, walau dapat diterapkan bersama per batch.
- Reservasi tidak mempunyai tanggal kedaluwarsa otomatis. Pelepasan dilakukan
  melalui tindakan operator yang berizin dan tercatat.
- Perpindahan kepemilikan permanen B ke A tidak termasuk pengecualian delivery ini;
  jika kelak dibutuhkan, buat tindakan terpisah dengan dampak yang ditinjau jelas.
- Tidak ada kewajiban penggantian stok B otomatis sampai kebutuhan itu ditentukan.

**Perincian usulan menu dan integrasi Excel, 16 November 2026**

Bagian ini menjawab pembahasan lanjutan tentang sidebar, master customer dan
input Excel. Usulan berikut kemudian disetujui pengguna bersama prototipe UI dan
diterapkan dalam implementasi pada tanggal yang sama.

Usulan penempatan mengikuti pola navigasi existing serta `apple-design` dan
`docs/DESIGN_PREFERENCES.md`:

- Tambahkan satu menu `Master Data Ekspor > Master Customer` untuk daftar customer,
  kode stabil, nama resmi, alias terverifikasi, dan status aktif/nonaktif. Menu
  serta tindakan kelola mengikuti Hak Akses existing.
- Reservasi customer dikelola pada `Stok Ekspor`, dengan filter customer dan aksi
  tetapkan/lepas reservasi pada box yang layak. Tidak perlu sidebar reservasi baru.
- Pilihan Customer/Umum berada pada penerimaan Ekspor existing.
- Aksi `Gunakan stok customer lain` berada pada konteks kekurangan delivery atau
  pemenuhan pallet terkait. Operator tetap melihat customer sumber, tujuan,
  kekurangan dan box yang dipilih pada satu alur.
- Kolom customer di form rencana delivery menjadi pemilih yang dapat dicari.
  Hasil impor memilih master secara otomatis bila kecocokannya tunggal dan sah.
  Status Cocok/Perlu dipetakan/Konflik dijelaskan dengan teks, tanpa mengharuskan
  operator berpindah menu pada setiap impor.

Source yang diverifikasi:

- `ExportDeliveryPlanImportService::parseRows` membaca nama dari metadata
  Customer/Pelanggan/Nama Customer atau bentuk `Customer: ...`, lalu mengembalikan
  `customer_name`. Saat ini belum ada pencocokan ke master customer.
- `resources/js/export-delivery-plan.js` memasukkan nama hasil impor ke input
  `plan-customer`. Operator masih harus meninjau hasil impor sebelum simpan.
- `ExportDeliveryPlanController` memvalidasi `customer_name` sebagai teks, dan
  `ExportDeliveryPlanService` menyimpan nama serta memasukkannya ke hash request
  dan snapshot. Integrasi master harus mempertahankan replay/token historis.
- Batas existing satu customer per file/sheet tetap berlaku; dukungan beberapa
  customer dalam satu file tidak termasuk penambahan ini.

Alur impor yang diusulkan:

1. Parser membaca file dengan format existing. Tidak perlu mewajibkan ID internal
   aplikasi atau kolom baru pada Excel yang sudah dipakai operasional.
2. Resolver server mencocokkan customer menggunakan kode customer jika tersedia,
   kemudian nama resmi yang cocok persis setelah normalisasi kapitalisasi dan
   spasi, atau alias yang sudah disetujui. Kode customer pada template baru
   bersifat opsional; perubahan dukungan parser ditambahkan secara eksplisit.
3. Pencocokan otomatis hanya dilakukan jika hasilnya satu customer aktif. Jika
   kode dan nama/alias menunjuk customer berbeda, tampilkan Konflik dan minta
   koreksi, bukan diam-diam mengutamakan salah satunya.
4. Nama singkatan dapat cocok otomatis setelah terdaftar sebagai alias. Salah
   ketik/kemiripan nama hanya menjadi saran untuk dipilih operator; jangan membuat
   alias atau master customer baru secara otomatis dari setiap impor.
5. Preview mempertahankan nama asli dari file dan memperlihatkan master yang
   dipilih. Simpan menautkan ID master dan menyimpan snapshot nama; nama mentah
   dokumen dipertahankan untuk jejak sumber. Server memvalidasi ulang hubungan
   tersebut, tidak mempercayai label atau ID dari browser saja.
6. Untuk rencana baru, customer yang belum cocok perlu dipilih sebelum simpan.
   Nomor part, PCS dan hasil baca lainnya tetap tersedia saat operator memperbaiki
   customer. Pengubahan file/customer/pilihan master membatalkan peninjauan lama.
7. Pembatalan impor, pergantian file, atau hasil respons yang datang terlambat
   tidak boleh meninggalkan ID customer dari impor sebelumnya. Pemulihan form
   dan undo mengembalikan nama, ID, serta status pemetaan sebagai satu kesatuan.

Contoh, master berisi kode `CUST-001`, nama `PT Alpha Indonesia`, dan alias
`ALPHA` yang telah diverifikasi:

| Isi Excel | Hasil yang diusulkan |
| --- | --- |
| `PT Alpha Indonesia` | Terhubung ke CUST-001 |
| `pt alpha indonesia` atau spasi tambahan | Terhubung ke CUST-001 melalui pencocokan nama yang dinormalisasi |
| `ALPHA` | Terhubung ke CUST-001 melalui alias terdaftar |
| `PT Alfa Indonesia` | Perlu pemilihan/koreksi; tidak dihubungkan otomatis berdasarkan kemiripan |
| Kode customer lain dengan nama `PT Alpha Indonesia` | Konflik, perlu koreksi |

Dampak pada data dan proses lama:

- Rencana delivery tetap berada pada halaman dan alur impor existing. Relasi
  customer ditambahkan; rencana, item, quantity, kapasitas, kode dan histori tidak
  dibuat ulang karena penambahan master.
- Nama customer pada rencana/history lama dipertahankan. Rename master tidak
  mengganti snapshot nama di delivery/pengiriman yang sudah tercatat.
- Pemetaan historis bersifat eksplisit dan tidak memicu alokasi ulang. Delivery
  tanpa pemetaan mengikuti aturan kompatibilitas yang telah dijelaskan di atas;
  perubahan item pada legacy tidak harus memaksa penulisan ulang customer.
- Mengganti customer pada delivery berkomitmen tetap memerlukan tinjauan dampak
  atau pelepasan komitmen. Mengedit nama master bukan cara mengganti penerima
  delivery yang sudah berjalan.
- Customer nonaktif tetap dapat dibaca dalam histori, tetapi tidak dipilih
  otomatis untuk rencana baru. Menonaktifkan master tidak membatalkan komitmen
  delivery existing atau menghapus reservasi box secara otomatis.
- Sebelum aktivasi, siapkan daftar kandidat customer dari nama yang sudah dipakai
  dan tinjau penggabungan/aliasnya. Daftar kandidat bukan backfill otomatis.
- Pertahankan kompatibilitas hash/token request lama. Hash untuk request baru
  perlu mencakup identitas master agar mengganti ID customer dianggap perubahan
  payload; pemeriksaan duplikasi delivery harus memakai identitas yang konsisten
  tanpa mengubah fingerprint historis secara sembarangan.

Tambahkan tes untuk format Excel lama tanpa kode, nama/alias yang cocok, nama
ambigu, konflik kode-nama, customer tidak ditemukan/nonaktif, perubahan master
antara preview dan save, request ID yang dimanipulasi, undo dan pergantian impor,
replay token lama, duplikasi rencana lintas alias, serta pemetaan legacy tanpa
perubahan stok atau komitmen.
