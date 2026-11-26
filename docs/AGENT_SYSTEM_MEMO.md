# Memo Sistem STOK WHFG untuk Agent

Terakhir diverifikasi dari source code: 26 November 2026 (pembaruan inkremental).

Insiden migrasi V2.0 dengan MySQL legacy, 26 November 2026:

- Log production: 9 migrasi selesai, migrasi `2026_10_29_000001` gagal 1067 pada
  `reserved_at`. Kolom `entered_fulfillment` dan backfill dapat sudah tersimpan
  karena DDL MySQL tidak atomik untuk seluruh migrasi. Laporan operator telah
  mengonfirmasi MySQL 5.7.42, default timestamp 0 dengan NO_ZERO_DATE, kolom
  awal ada, tabel target belum ada, status 42 Ran dan 22 Pending.
- Patch menambah default eksplisit untuk timestamp wajib pada migrasi terkait,
  tanpa default auto-update. Migrasi melanjutkan bila hanya kolom awal yang telah
  ada, dan menolak tabel target yang sudah ada saat status masih Pending. Tidak
  ada migration baru atau perubahan konfigurasi global server.
- MySQL 8.4.3 terisolasi dengan aturan timestamp lama mereproduksi error dan
  kondisi parsial, lalu melewati 22 migrasi tersisa. FK, indeks, backfill, serta
  nilai timestamp eksplisit tetap benar. Belum diuji pada salinan MySQL 5.7
  production. Panduan: docs/PRODUCTION_MIGRATION_1067_2026-11-26.md.

Penghapusan Master Part dengan histori saja, 26 November 2026:

- Tombol Hapus mengarsipkan master dari katalog aktif bila tidak ada stok atau
  proses yang masih berjalan. Histori transaksi selesai, FK Ekspor, pemetaan
  dokumen, dan ID master tetap tersimpan. Nomor part dan alias yang diarsipkan
  tetap dilindungi dari duplikasi. Rename master yang pernah dipakai tetap ditolak.
- Stok fisik termasuk box expired yang masih tersimpan, delivery aktif/draft,
  picking, permintaan Not Full, draft penerimaan, QA/packing, dan Redo Lokal
  yang masih berlaku tetap dapat memblokir pengarsipan. Pesan Hapus menyebut
  alasan aktif. Scan baru dan draft Lokal yang mencoba membuat box dengan part
  yang sudah diarsipkan ditolak saat simpan.
- Migration `2026_11_26_000001_allow_archiving_part_settings` menambah kolom
  nullable tanpa mengubah riwayat. Ini satu tambahan setelah kandidat produksi
  `806a092` di panduan 24 November dan belum diterapkan di produksi.
- Regresi terarah SQLite in-memory 107 tes / 878 assertions dan seluruh 180
  tes frontend lulus. Uji mencakup histori Lokal/Ekspor, window Redo, pencarian
  master, dan penolakan simpan draft scan yang part-nya sudah diarsipkan.

Persiapan upgrade production dan preservasi reaktivasi, 24 November 2026:

- Baseline production dari daftar pengguna: 33 migration sudah Ran sampai
  15 Oktober; checkout b0bbc60 memiliki 30 tambahan sampai reservasi customer
  16 November. Status server belum dibaca langsung. Docker/Compose dan backup
  production belum diverifikasi; belum melakukan deployment.
- Keputusan pengguna: pertahankan deadline reaktivasi yang masih berlaku.
  Migration QA 23 Oktober yang masih pending pada baseline tersebut direvisi
  agar hanya mengisi legacy_reactivated_until tanpa mengubah reactivated_until
  atau expired_status. Deadline habis tetap habis, handled tetap nonaktif.
  Berlaku untuk penerapan baru migration; server yang telah menjalankan versi
  pemotongan deadline memerlukan pemeriksaan/perbaikan terarah tersendiri.
- Tes baseline berisi enam kondisi box, master/user, delivery processing, sesi
  picking dan audit menjalankan semua pending pada SQLite in-memory. Kolom
  bisnis/PK/count lama, deadline, registry serta relasi picking terjaga. Bersama
  tes reaktivasi: 24 tes / 238 assertions lulus. Seluruh 180 frontend, build,
  validasi Composer/platform lokal dan pemeriksaan sintaks PHP/Bash lulus.
  Regresi penuh final: 787 tes / 8.450 assertions, 780 lulus dan tujuh kegagalan
  pada rentang laporan, binder Excel, snapshot seeder serta preview dashboard
  yang sebelumnya tercatat, tanpa error. Tidak memperbaiki area tersebut di sesi ini.
  Ini bukan rehearsal MySQL/MariaDB salinan production.
- Panduan upgrade diperbarui, skrip production-preflight.sh/.php hanya membaca
  konfigurasi aman dan SELECT database dalam transaksi READ ONLY. Tidak mencetak
  credential/data bisnis rinci, tidak menjalankan migration/restart/scheduler.
  Perintah deploy/backup spesifik menunggu laporan operator. Status rilis dan
  batas pengujian: docs/PRODUCTION_READINESS_2026-11-24.md.

Fokus scan setelah pergantian customer penerimaan Ekspor, 19 November 2026:

- Mengganti Reservasi customer, termasuk kembali ke Umum, memakai reset input
  existing dan langsung memfokuskan Scan ID Box tanpa jeda. ID/part yang belum
  ditambahkan disiapkan ulang, pending check dilepas, tanggal masuk dipertahankan.
  Baris draft server tetap memakai pemilik awal. Bantuan pada layar menjelaskan
  perilaku ini. Pilihan customer dinonaktifkan sementara selama request scan
  berjalan agar pergantian tidak bertabrakan dengan validasi/penambahan box.
- Tidak mengubah payload, reservasi tersimpan, FIFO, izin atau database operasional.
  Seluruh 180 tes frontend, pemeriksaan sintaks JS dan build Vite lulus. Browser
  dengan fixture SQLite terpisah memverifikasi Umum ke Alpha, Alpha ke Beta
  dengan input belum ditambahkan, dan Beta ke Umum, fokus selalu ke ID Box yang
  writable, tanpa error konsol. Bukan uji scanner fisik. Bukti privat:
  storage/app/private/receiving-customer-scan-focus.jpg.

Filter customer dan nomor part pada Lihat Stok Ekspor, 19 November 2026:

- Pilihan customer kini hanya dari Master Customer, termasuk yang belum punya
  delivery dan customer nonaktif berlabel untuk membaca reservasi existing.
  Identitas memakai customer:ID dan relasi tersimpan. Nama/snapshot delivery
  legacy tidak dicocokkan otomatis, bookmark nama lama perlu memilih master lagi.
- Sesuai jawaban pengguna, daftar customer memuat gabungan box milik customer
  dan box yang terikat delivery customer itu, termasuk stok Umum yang sudah
  dialokasikan. Box yang memenuhi keduanya dihitung sekali. Stok umum bebas dan
  stok customer lain tidak disertakan hanya karena nomor part sama. Pengecualian
  lintas customer mempertahankan pemilik asal dan penerima delivery-nya.
- Ringkasan total terkait, terikat delivery dan tanpa delivery memuat box/PCS.
  Ringkasan fisik dan total mengikuti customer, part, pencarian serta lokasi,
  sebelum filter kategori/keterikatan delivery. Progres delivery tetap menampilkan
  seluruh kebutuhan aktif customer. Pembanding stok bersih hanya menghitung
  reservasi customer itu yang belum terikat delivery, sesuai cakupan daftar.
- Toolbar mempunyai kolom ketik sebagian atau seluruh nomor part, sesuai koreksi pengguna
  tanggal 19 November, serta pilihan keterikatan delivery. Filter part diterapkan
  otomatis setelah jeda ketik 250 ms atau langsung dengan Enter, tanpa dropdown
  atau saran. Nomor yang diketik dipertahankan saat customer berganti, nomor yang
  tidak memiliki stok terkait menghasilkan daftar kosong.
  Pencocokan mulai satu karakter, mencari teks di awal/tengah/akhir tanpa
  membedakan kapitalisasi. Karakter %, _ dan ! dicari sebagai teks literal.
  Tautan kebutuhan dengan PCS/box tetap memakai satu part persis, pengetikan
  manual membersihkan PCS/box tersebut sebelum memakai pencarian sebagian.
  Pencarian q tetap mendukung potongan nomor part. Reset mempertahankan customer,
  pilihan Semua customer menampilkan seluruh stok sesuai filter lainnya.
- Daftar Box/Pallet, isi pallet, pagination, filter cepat dan Excel memakai query
  yang sama. Baris box menunjukkan customer pemilik, customer delivery dan tanpa
  delivery. Tidak mengubah reservasi, alokasi, FIFO, izin atau database operasional,
  tidak menambah migration. Build aset lokal sudah diperbarui.
- Verifikasi SQLite in-memory, 80 tes backend terkait / 1.237 assertions dan
  seluruh 180 frontend lulus, build, sintaks PHP/JS dan diff --check lulus.
  Browser memakai SQLite terpisah dengan enam box fiktif. Customer, part persis,
  kombinasi tanpa delivery, pallet campuran/modal, customer kosong, Reset/Semua,
  keyboard dan viewport 1366/390 px diperiksa, tanpa overflow halaman atau error
  konsol. Bukan scanner atau ponsel fisik, tidak melakukan deployment.
  Koreksi kolom ketik diverifikasi ulang, 18 tes backend / 384 assertions dan
  enam tes frontend terkait lulus, build/diff --check lulus. Browser memeriksa
  ketik otomatis, Enter, hapus input, fokus tetap, dan lebar 390 px tanpa overflow.
  Koreksi pencarian sebagian lulus 19 tes backend / 424 assertions. Browser
  memverifikasi P langsung menampilkan empat box dan extra menampilkan satu,
  tetap pada customer terpilih dan tanpa error konsol.
  Rincian: docs/EXPORT_STOCK_CUSTOMER_FILTERS.md.

Pencocokan nama/alias customer rencana Ekspor, 18 November 2026:

- Bug direproduksi pada perangkat lain melalui tunnel pengguna, master dan
  katalog nama/alias tersedia tetapi input tidak mengisi customer_id. Bundle
  yang dimuat berbeda dari checkout. Database lokal checkout masih tanpa master
  customer saat diagnosis, tidak diisi contoh untuk meniru perangkat lain.
- Form kini menyelaraskan ID pada init/input/change/pageshow/submit/impor/undo,
  lookup existing membaca master yang ditambahkan setelah form dibuka dan
  menolak respons lambat yang stale. Konflik kode/nama Excel tetap diblokir
  sampai koreksi operator. Snapshot existing dan legacy belum dipetakan dijaga.
- Backend mencocokkan nama/kode/alias pasti bila form customer versi 1 tidak
  mengirim ID. Integrasi legacy tetap tanpa pemetaan otomatis, customer tidak
  dikenal/nonaktif tetap ditolak, retry dibaca sebelum perubahan master.
  Nama sumber kosong memakai input aslinya, alias manual tetap tercatat.
- 78 tes backend / 663 assertions dan seluruh 180 frontend lulus, build, PHP,
  Pint tes baru dan diff --check lulus. Browser SQLite terpisah benar-benar
  menyimpan tiga rencana dari alias manual, XLSX, serta master baru setelah
  form dibuka, ID/source/PCS diverifikasi. Desktop/390 px, tanpa console error.
- Tidak ada migration/database operasional/deployment perangkat lain. Paket
  customer-match-fix.zip menyediakan empat source, dua aset dan penggabungan
  manifest terkait saja, dengan backup/checksum dan installer yang sudah diuji
  pada project probe. Perangkat tunnel masih memerlukan penerapan paket atau
  sinkronisasi source dan build. Rincian: docs/EXPORT_CUSTOMER_MATCHING_FIX_2026-11-18.md.

Penomoran dan penempatan wajib part cadangan Ekspor, 18 November 2026:

- Kode fisik pallet menjadi PLT-EXP-001, minimal tiga digit, setelah 999 lanjut
  1000. Nomor memakai PK auto-increment database ekspor, tidak memakai MAX+1
  atau counter browser. Lokal tetap PLT-001 dengan urutannya sendiri. ID/FK,
  posisi perencanaan 1..N dan snapshot audit lama dipertahankan, tanpa migration.
  Pencarian pindah pallet menerima kode baru dan alias label EX-000001 lama,
  respons memakai kode baru. Pencarian stok dan riwayat mengikuti format baru.

- Penerimaan tanpa alokasi rencana merekomendasikan pallet cadangan yang masih
  berisi part sama, mengikuti penempatan part paling baru dan lokasi pallet saat
  ini. Penempatan pertama tetap dipilih operator. Pallet rencana, pallet pensiun,
  box terhapus dan lokasi yang tidak tersedia tidak dijadikan default.
- Jika seluruh tambahan muat, tujuan sebelumnya WAJIB dipakai, operator tidak
  dapat menggantinya atau membaginya ke tujuan lain. Ini menggantikan aturan
  pilihan bebas pada implementasi awal. Batas menghitung seluruh isi fisik
  pallet, termasuk part lain dan expired, ditambah pilihan kelompok lain dalam
  transaksi. Null/0 tetap berarti tanpa batas, peringatan 36/72 tetap terpisah.
- Jika tidak seluruhnya muat, tampilkan lokasi/pallet sebelumnya, isi, maksimal,
  ruang tersisa dan jumlah kelebihan. Isi sisa N box membuat pembagian ke pallet
  tersebut dan meminta tujuan sisanya. Pallet baru untuk seluruh N box membuka
  pilihan lokasi dan batas pallet baru. Pallet penuh hanya menawarkan tujuan
  lain, tidak dipilih otomatis. Draft pilihan bebas hanya dipulihkan bila masih
  sesuai aturan, tujuan wajib menggantikan pilihan bebas lama yang tidak valid.
- ExportSparePlacementPolicy dipakai halaman transaksi, modal satu box, cetak
  dan validasi server. Seluruh box part yang masih menunggu dalam receipt dibaca
  dari database, bukan dari subset payload atau return_batch client. Pilihan
  overflow dinilai sekali sebelum mutasi satu konfirmasi di bawah mutex inventory.
  Retry, perubahan kapasitas dan dua operator diperiksa ulang. Relokasi stok
  tersimpan, QA/packing, Not Full dan tujuan alokasi rencana tetap lewat alurnya.
- Rekomendasi tidak menyimpan master default atau menempatkan stok otomatis.
  Konfirmasi fisik, cetak per box, FIFO, reservasi customer, hak akses, mutex,
  revision/token dan pemeriksaan kapasitas server tetap melalui alur existing.
  Tidak ada migration, perubahan database operasional atau deployment.
- Verifikasi: 362 tes terkait ekspor / 3.928 assertions lulus dari suite penuh
  772 tes / 8.199 assertions, 765 lulus dan tujuh kegagalan baseline laporan,
  binder Excel, seeder dan preview dashboard yang sudah tercatat, tanpa error.
  Tambahan pemeriksaan split tujuan wajib lulus 1 tes / 27 assertions. Seluruh
  172 frontend, build, sintaks PHP dan diff --check lulus. MySQL 8.4.3 pada port
  dan data terpisah memverifikasi dua proses menghasilkan kode berbeda, rebutan
  dua ruang terakhir menolak satu receipt secara utuh, dan satu lokasi kosong
  tidak mendapat dua pallet. Instance dihentikan dan data sementara dibersihkan.
  Browser SQLite in-memory memeriksa tujuan wajib, draft lama yang dialihkan,
  overflow 2+3 atau seluruh 5 box ke pallet baru, refresh, keyboard, 1366/390 px,
  tanpa console error. Bukan uji scanner fisik atau database operasional. Rincian:
  docs/EXPORT_SPARE_LOCATION_RECOMMENDATIONS.md.

Pengalih Daftar stok / Reservasi customer, 18 November 2026:

- Header dan pengalih stok Ekspor memakai satu partial, posisi stabil pada kedua
  tampilan. Indikator transform 200 ms memakai token easing existing; perpindahan
  dokumen memakai native View Transitions dengan fade 120 ms pada data yang diam.
  Tidak menunda navigasi, mengganti route, memuat ulang script melalui AJAX,
  menambah dependency, mengubah data atau menjalankan migration.
- Keyboard/reduced motion langsung; tautan native tetap berfungsi tanpa JS atau
  dukungan View Transitions. Pending dibersihkan saat kembali, pembatalan dan
  navigasi gagal. Izin tombol header dan guard kesiapan customer dipertahankan.
- 53 tes backend / 784 assertions serta 169 frontend lulus. Chrome memakai SQLite
  dan akun fiktif memeriksa desktop, 390/320 px, teks 200%, posisi pengalih,
  filter/Back, keyboard, reduced motion, respons lambat dan pergantian balik.
  Sampling frame menunjukkan translasi bertahap sampai posisi akhir 200 ms.
  Tidak menguji scanner atau ponsel fisik dan tidak melakukan deployment production.

Aktivasi lokal fitur customer dan prasyarat, 18 November 2026:

- Keluhan Admin Utama tidak dapat menambah Master Customer disebabkan schema
  belum diaktifkan, bukan kurang izin. Database lokal MySQL 8.4.3 `db_stock`
  belum memiliki delapan tabel customer; role admin memiliki keempat izin.
- Migration customer dan tiga prasyarat tertunda sudah dijalankan secara
  terarah di database lokal: nomor part dokumen, versi izin rinci, dan penggunaan
  ulang nomor box. Tidak mengaktifkan atau memverifikasi server production.
- Backup sebelum setiap tahap berhasil dipulihkan pada database salinan MySQL
  yang sama. Salinan uji dihapus setelah verifikasi. Pembandingan 69 tabel
  memverifikasi 816 baris existing, dengan proyeksi kolom bisnis sebelum
  penambahan metadata. ID/nomor box, part, PCS, lokasi, tanggal, komitmen dan
  history lama dipertahankan. Satu konfigurasi izin dikonversi ke versi 2 dengan
  akses efektif identik, disertai satu audit baru. Metadata penggunaan awal dan
  draft diperiksa; pemetaan dokumen/customer lama tidak diisi otomatis.
- Render aplikasi lokal sebagai Admin Utama memverifikasi tombol Tambah customer,
  form master, halaman reservasi, pemilih customer penerimaan dan master customer
  pada form delivery. Tidak menambahkan customer/box contoh pada database lokal.
  Sebanyak 74 tes reservasi, dokumen part, izin dan label reuse / 1.256 assertions
  lulus pada SQLite in-memory. Backup dan laporan checksum tersimpan privat di
  `storage/app/private/customer-activation-*` dan `customer-prerequisites-*`.
  Rincian status: docs/CUSTOMER_BOX_RESERVATIONS.md.

Nomor part dokumen dan rincian Stock Opname, 18 November 2026:

- Lokal dan Ekspor menampilkan seluruh nomor dokumen untuk satu part dalam satu
  kolom pada rincian, feedback scan dan Excel. Nomor label/scan tetap tersedia
  sebagai acuan. Banyak kode dokumen tidak menggandakan jumlah part/box/PCS.
- Pemetaan baru dicatat pada JSON kondisi awal, saat scan dan perbandingan akhir.
  Perubahan alias master tidak dianggap sebagai perubahan stok fisik. Hasil
  selesai yang sudah menyimpan nomor dokumen memakai catatannya sendiri.
- Atas pilihan pengguna, catatan lama yang belum memiliki pemetaan memakai
  Master Part saat ini dengan keterangan eksplisit pada layar dan setiap baris
  Excel. Pembacaan/unduhan tidak mengubah session, item atau log scan tersimpan.
  Master yang belum ada memakai nomor label dengan keterangan; pemetaan yang
  kemudian tersedia dapat ditampilkan sebagai pemetaan terkini.
- Workbook bertambah dari tiga menjadi 12 tab: Ringkasan, Semua Scan,
  Rekonsiliasi Box, Rekap per Part, Scan OK, Scan Error NG, Scan Duplikat,
  Belum Terscan, Box Sesuai, Perubahan Data, Box Masuk, Box Keluar dan ID Diganti.
  Waktu/operator/alasan, posisi/PCS awal-akhir serta penggunaan ID tersedia.
- Tanpa migration/backfill, perubahan stok, pengubahan izin atau deployment.
  Verifikasi SQLite in-memory: 40 tes opname/reuse/master dokumen, 570 assertions
  lulus; seluruh 169 frontend dan build lulus. Browser memakai akun/SQLite
  fiktif, desktop/390 px, unduhan XLSX nyata dan audio dimatikan hanya di browser
  uji agar tidak ditangkap IDM. Dua XLSX dibaca OpenPyXL, 12 tab dirender, dan
  Excel asli memverifikasi ID berawalan nol serta baris keterangan yang membungkus.
  Rincian: docs/STOCK_OPNAME_DOCUMENT_PARTS.md.

Reservasi box customer Ekspor, 16 November 2026:

- Master Customer menjadi menu baru Master Data Ekspor. Kode/nama/alias menunjuk
  satu ID, pilihan form dan hasil Excel terhubung bila cocok pasti. Rencana lama
  mempertahankan nama/snapshot dan token request existing. Pemetaan historis
  eksplisit; integrasi tanpa ID hanya mendapat stok Umum. Rename master tidak
  mengganti snapshot delivery lama, termasuk edit quantity dengan customer sama.
- Penerimaan menyimpan pemilik per box/draft sebelum allocator, mengganti pilihan
  hanya memengaruhi scan berikutnya. Stok Ekspor > Reservasi customer menyediakan
  tetapkan pada stok umum dan pelepasan, alasan wajib, revision dan audit. Hanya
  cadangan tersimpan bebas yang dapat diubah; lokasi/PCS/tanggal FIFO tetap.
- Alokasi memakai stok customer tujuan dahulu, lalu Umum, FIFO asli dalam setiap
  kelompok. Reservasi customer terpisah dari komitmen delivery. Pengecualian
  operator berizin hanya untuk box/penggunaan/delivery/item tertentu, tanpa
  approval Supervisi baru, dengan alasan dan tinjauan FIFO existing. Pemilik
  sumber tetap setelah pembatalan. Pengiriman menyimpan pemilik/penerima dan
  menandai terpakai. Revisi/merge/Not Full/picking/placement/Siap/completion ikut
  penjaga. Hasil pecah/packing mewarisi pemilik sumber, label reuse tidak.
- Delapan tabel tambahan tanpa perubahan kolom/backfill stok/delivery lama.
  Mutex inventory, FK/unique, idempotensi, revisi pengecualian dan audit atomik
  melindungi konflik dan formulir stale. Retry penerimaan membaca snapshot customer
  penerimaan asli, tidak menimpa perubahan reservasi berikutnya. down menolak data
  fitur yang terpakai.
  Tiga izin tulis baru diberikan Admin per role, tidak diwariskan otomatis dari
  penerimaan/picking. Grant tersimpan existing tidak diubah oleh migration.
- EXPORT_CUSTOMER_STOCK_WRITES_ENABLED=false menghentikan tindakan baru, tidak
  membuka reservasi existing. Aktivasi wajib setelah semua instance/worker lama
  selesai. Stok lama tetap Umum sampai dipetakan; komitmen existing tidak dihitung
  ulang. Tidak menjalankan migration pada database operasional/production.
- Verifikasi: 21 tes baru/98 assertions; seluruh 169 frontend dan build lulus.
  Suite penuh SQLite: 754 tes/7.948 assertions, 747 lulus, tujuh kegagalan baseline
  laporan/binder/seeder/dashboard yang sama, tanpa error. DDL dan dua koneksi
  MySQL 8.4.3 memakai direktori/port probe terpisah, mempertahankan baris lama,
  mutex dan unique ownership lulus. Browser Laravel memakai SQLite/akun fiktif,
  desktop/390 px; bukan scanner/ponsel nyata atau rehearsal salinan production.
  Rincian dan runbook: docs/CUSTOMER_BOX_RESERVATIONS.md.

Retensi lokasi pallet berisi box expired, 16 November 2026:

- Atas instruksi pengguna, expired tidak lagi melepas lokasi pallet. Lokasi adalah
  panduan letak fisik dan tetap terisi selama masih ada box tersimpan, walaupun
  semua box expired atau masa reaktivasi sudah habis. FIFO/scan/delivery tetap
  memakai kelayakan aktif; ringkasan pallet_items tidak memasukkan expired.
- Pallet::storedBoxes/hasStoredInventory memisahkan okupansi fisik dari activeBoxes.
  Box withdrawn, soft-deleted dan handled (sudah ke QA) tidak menahan lokasi.
  Fallback pallet_items hanya untuk pallet legacy tanpa histori box tetap berlaku.
- MasterLocation, sinkronisasi expiry, completion/withdrawal, pembersihan Redo,
  penghapusan box dan klaim lokasi memakai okupansi fisik. Klaim lokasi tidak boleh
  membersihkan baris milik pallet yang masih berisi expired sebagai data orphan.
- Merge tetap memindahkan box eligible saja. Jika expired tertinggal, pallet sumber,
  lokasi serta header input-nya dipertahankan; lokasi tersebut tidak dapat dipakai
  pallet hasil merge. Semua perubahan dibatalkan bila tujuan masih terisi.
- Penyerahan QA melepas lokasi hanya setelah box fisik terakhir meninggalkan pallet.
  Tidak ada migration/backfill atau deployment. Lokasi yang sudah hilang sebelumnya
  tidak ditebak dari history; perbaikannya memerlukan pemeriksaan lokasi fisik.
- Verifikasi SQLite in-memory: sembilan tes baru / 107 assertions lulus. Suite penuh
  733 tes / 7.810 assertions: 726 lulus, tujuh gagal pada laporan rentang/binder
  Excel, seeder dan preview dashboard, tanpa error. Tes expiry/QA/pallet/lokasi,
  withdrawal/Redo/merge serta perbaikan snapshot audit lulus. Rincian dan batas
  verifikasi: docs/EXPIRED_PALLET_LOCATION_RETENTION.md.

Perbaikan snapshot audit pengambilan delivery lokal, 16 November 2026:

- Completion menyimpan PK, nomor label sebagai string, identity_cycle, part, PCS
  dan lokasi canonical yang sudah divalidasi sebelum box/pallet dinonaktifkan.
  Ledger withdrawal memakai lokasi snapshot yang sama. Pallet habis maupun relasi
  shared legacy tidak menghilangkan/mengganti lokasi pada audit.
- AuditService::logBatchStockWithdrawal mewajibkan snapshot eksplisit dari caller,
  menghitung total dari rincian yang sama dan memakai record yang wajib tersimpan.
  Tidak membaca ulang relasi inventory setelah mutasi atau melewatkan box terhapus.
- Audit reversal Redo menyimpan nomor/penggunaan seluruh box dan lokasi tujuan
  konsolidasi yang diselesaikan server; lokasi null pada legacy tanpa master tidak
  ditebak dari lokasi asal. Redo menolak anggota yang tidak lengkap. Audit batch,
  event Redo dan pemulihan Not Full wajib dalam transaksi; kegagalan tulis membatalkan
  mutasi stok/order/sesi/pallet/lokasi. Izin serta aturan eligibility tetap.
- Histori lama tidak ditulis ulang dan tidak dilengkapi dari stok terkini. Tidak ada
  migration/deployment. Regresi SQLite in-memory: 104 tes / 1.627 assertions lulus,
  termasuk 13 tes baru / 165 assertions. Bukti dan batas pemulihan data lama:
  docs/AUDIT_WITHDRAWAL_DIAGNOSIS_2026-11-16.md.

Lima animasi kontekstual, 15 November 2026:

- Rencana pengguna diterapkan pada panel input Excel, menu akun Bootstrap,
  disclosure pemetaan master part, ikon hasil impor dan centang download siap.
  CSS/WAAPI memakai token existing, tanpa library baru. Keyboard, validasi,
  inisialisasi dan reduced motion langsung; interupsi membatalkan completion lama.
  Scan, data tabel, payload, fungsi server dan izin tetap.
- `public/js/ui-motion.js` memusatkan helper. Form master part menjadi script
  module, layout menginisialisasi menu dan Vite membundel helper panel/impor.
  Ikon download mengikuti ack server, timer auto-close tetap 3 detik.
- Seluruh 169 frontend dan 70 backend / 1.301 assertions lulus. Render/stream
  fixture terpisah lulus 1 tes / 12 assertions, SQLite in-memory. Build lulus.
  Browser memeriksa pointer/keyboard, pembalikan, validasi, download, preview 5×,
  simulasi reduced motion dan viewport 390 px. Catatan dan batas verifikasi:
  docs/UI_CONTEXTUAL_MOTION_2026-11-15.md.

Penyelesaian sisa rules UI/UX, 15 November 2026:

- Template Excel rencana delivery ikut helper download existing. Target tombol
  tutup bersama dan aksi tabel/modal minimal 44 px, validasi tetap merah saat
  fokus, teks panjang membungkus dan jarak footer modal konsisten.
- Master part mengelompokkan identitas/kapasitas serta pemetaan dokumen native.
  Kode berbeda/error terbuka; invalid membuka bagian sebelum fokus. Field tetap
  dikirim saat diringkas dan fallback markup terbuka tanpa script enhancement.
  Rencana delivery diberi urutan tiga bagian, koreksi box memakai dua fieldset.
  Tidak mengubah name/payload, validasi bisnis, scanner atau hak akses.
- 70 tes backend / 1.301 assertions dan seluruh 162 tes frontend lulus.
  Render fixture/stream template asli terpisah lulus satu tes / 12 assertions.
  Testing memakai SQLite in-memory, browser memakai data fiktif. Desktop dan
  viewport 390 px memeriksa keyboard, error, FormData, target klik dan overflow.
  Before/after: docs/UI_RULES_COMPLETION_2026-11-15.md.

Feedback download Excel, 15 November 2026:

- Helper `public/js/excel-download.js` memberi feedback persiapan dan menahan
  request URL sama selama pending serta 1 detik setelah ready untuk mencegah
  klik beruntun pada respons cepat; filter/GET/filename/isi/izin tetap. Mencakup
  laporan/dashboard, Stock View, stok Ekspor, audit dan stock opname selesai.
- Global `ExcelDownloadStatus` hanya menambahkan cookie acknowledgement untuk
  GET bertoken acak valid dengan respons attachment Excel sukses. Cookie
  non-rahasia dipasang sesudah enkripsi cookie web, Secure pada HTTPS,
  SameSite Lax dan berumur 120 detik. Tidak mengubah cookie sesi atau database.
- Browser tetap mengunduh native melalui iframe, tanpa buffer Blob JS. Ready
  berarti respons diserahkan ke browser, bukan file sudah tersimpan di disk.
  Error/sesi berakhir menyediakan retry/native; timer tidak menciptakan sukses
  atau progres persentase. Bila ack tak terbaca selama 60 detik, pemantauan dan
  spinner berhenti, tombol pulih dan status Belum terkonfirmasi muncul. Iframe
  dipertahankan agar request native tetap berjalan; cek daftar unduhan browser.
- Atas arahan pengguna, status Excel siap menutup otomatis setelah 3 detik,
  tanpa tertahan hover/fokus dan tanpa tombol Tutup. Fokus panel dikembalikan
  ke tombol asal; error tetap menyediakan tindakan pemulihan.
- Verifikasi: seluruh frontend lulus; enam tes middleware/34 assertions dan
  37 regresi ekspor/564 assertions lulus pada testing. Browser mengunduh XLSX
  fiktif valid, memeriksa duplicate, form/filter, auth/error dan desktop/390 px.
  Tidak menjalankan migration/deployment. Dokumen: docs/EXCEL_DOWNLOAD_FEEDBACK.md.

Perbaikan keamanan terarah, 15 November 2026:

- Tidak menambah migration atau mengubah data operasional. Withdrawal delivery
  langsung mengunci parent sebelum item/stock dan hanya menerima approved,
  processing atau partial. Split menolak Not Full pending sebelum mutasi, sesuai UI.
- Payload relokasi Redo legacy tetap dapat menyampaikan lokasi, tetapi key pallet
  dari client tidak menentukan target. Relokasi memakai pallet konsolidasi server.
  Shared box legacy tidak dipindah ulang ke pallet asal setelah konsolidasi,
  ringkasan asal dihitung dari box aktif tersisa agar tidak ada stok terhitung ganda.
  History dan hasil Redo lama tidak ditulis ulang.
- Bootstrap stock-view memakai JSON_HEX flags; picker/status/box/FIFO meng-escape
  teks dinamis; toast dan fallback error memperlakukan pesan sebagai teks.
- TextSafeExport melindungi StockInput, AuditTrail dan tiga StockView exporter:
  string tetap teks termasuk leading zero/formula, angka tetap numerik. Download
  memulihkan binder global pada finally. Master audit Lokal/Ekspor ikut terlindungi.
- Penerima default email expired menyaring akun aktif; konfigurasi override tetap.
  Seeder user contoh tidak berjalan pada environment production, tanpa reset akun
  existing. Laporan lama memvalidasi tanggal dan memakai tanggal beraktivitas untuk
  grafik rentang panjang, tanpa membatasi pembacaan history/agregat.
- Tiga gap audit ditelusuri: master-to-audit Excel ditutup oleh binder, sedangkan
  persistence delivery Ekspor dan planning/finalisasi/acknowledge FIFO sudah memiliki
  kontrol actor/target/revision, mutex, scope/reservasi dan committed box yang relevan.
- Verifikasi source/sintaks saja. Tes regresi ditambahkan tetapi BELUM dijalankan,
  karena sandbox target-code sesuai panduan security-audit tidak tersedia. Tidak
  mengadopsi jumlah tes development sebelumnya sebagai hasil verifikasi patch ini.
  Status audit lama tetap incomplete. Rincian: docs/SECURITY_FIXES_2026-11-15.md.

Penggunaan ulang ID Box Lokal/Ekspor, 15 November 2026:

- Atas persetujuan pengguna, nomor dapat dipakai lagi setelah penggunaan lama
  berakhir dalam segala kondisi, termasuk hapus manual, pengambilan/kirim,
  penolakan QA dan label asal setelah packing selesai. Penerimaan baru boleh
  memiliki part/PCS berbeda sesuai validasi biasa. Stok aktif/expired yang belum
  dilepas, QA/packing berjalan, request Not Full pending/prepared dan issue picking
  terbuka tetap memblokir penerimaan baru. Izin hapus dan penguncian proses aktif
  tetap berlaku. Bagian ini menggantikan larangan reuse pada catatan lama.
- Migration 2026_11_15_000001_allow_box_number_reuse menambah last_cycle pada
  registry dan identity_cycle pada Box, ExportStockBox serta ExportFulfillmentBox.
  Data lama bernilai 1, PK/FK/tanggal/kuantitas tidak ditulis ulang. Unique stock
  menjadi nomor + penggunaan, unique label QA diganti index biasa, unique Not Full
  hanya untuk request pending melalui kolom terhitung. Rollback yang meratakan
  beberapa penggunaan menjadi satu nomor ditolak.
- BoxIdentity mengunci registry global serta memeriksa pemilik aktif memakai
  locking read saat klaim dalam transaksi insert. Registry dipertahankan sesudah
  penghapusan, penghitung bertambah untuk penerimaan baru, origin_scope menunjukkan
  lingkup penerimaan terakhir. Klaim gagal tidak mengonsumsi penghitung. Pencatatan
  fisik komitmen ekspor legacy tetap adopsi penggunaan yang sama, bukan receipt baru.
- Riwayat lokal (modal) dan ekspor (halaman history) dapat memilih penggunaan dalam
  lingkup masing-masing. Picking history ekspor dibaca lewat FK fulfillment agar
  nomor sama tidak mencampur scan. Detail dan Excel Rincian Box Laporan Pengiriman
  memuat Penggunaan ID Box. QA hard delete tetap memakai snapshot penanganan.
- Redo/restore penggunaan lama ditolak jika nomor pernah digunakan oleh penerimaan
  lain, walaupun penerimaan berikutnya juga dihapus. Sebelum reuse, Redo mengikuti
  aturan existing. Scan input lokal/draft ekspor menyimpan penghitung saat scan dan
  menolak daftar yang stale. Retry token receipt lama mengembalikan record lama,
  tidak membuat box baru. Stock opname mempertahankan snapshot awal/scan, mengganti
  snapshot kondisi kini bila PK berbeda, dan mewajibkan scan ulang penggunaan baru.
- inventory:unification-check pada schema baru hanya melaporkan benturan pemilik
  aktif lintas lingkup. Sebelum migrasi shared master, pemeriksaan legacy tetap.
- Barcode fisik tetap delapan digit sehingga label lama dan baru bernomor sama
  tidak dapat dibedakan scanner. Label lama harus disingkirkan sebelum reuse.
  Dokumen: docs/BOX_NUMBER_REUSE.md. Belum dijalankan pada DB operasional/production.
- Verifikasi akhir: 697 tes backend / 7.428 assertions, 694 lulus, tiga kegagalan
  UiReviewPreviewTest dashboard sesuai baseline, tanpa error atau kegagalan fitur
  reuse. Seluruh 147 tes frontend lulus, diff --check lulus. Tes mencakup migration
  berisi data lama/draft, reuse lintas lingkup, hapus/kirim/QA, FK laporan/history,
  Excel numerik penggunaan, pending Not Full, stale scan, Redo/restore, rollback
  klaim dan kompatibilitas sesi opname existing. Pengujian memakai SQLite in-memory,
  bukan database operasional. DDL production tetap perlu diuji pada engine yang sama.

Laporan Pengiriman per kejadian Lokal/Ekspor, 15 November 2026:

- Menu Laporan Pengambilan menjadi Laporan Pengiriman. URL reports.withdrawal
  dan export-nya tetap kompatibel, serta reports.records dengan kind=shipments
  mendelegasikan ke ShipmentReportController. Jenis laporan shipments diberi label
  Pengiriman. Route reports.shipments.show/export menyediakan detail dan unduhan
  satu kejadian, menggunakan izin reports dan reports_download existing.
- ShipmentReportService hanya membaca data. Lokal dikelompokkan per sesi selesai,
  termasuk beberapa sesi pada order yang sama dan histori Redo terpisah. Ekspor
  per planning completed_at, termasuk delivery/box yang sudah diarsipkan. Pallet
  siap atau pemenuhan lengkap saja tidak dicatat sebagai sudah dikirim.
- Ringkasan seluruh histori per lingkup memuat jumlah delivery selesai, PCS/box
  kirim netto dan kejadian Redo. Redo tidak menambah netto. Status campuran dalam
  satu kejadian ditandai Perlu diperiksa. Catatan lama tanpa sesi dikelompokkan per
  batch atau withdrawal asli, tanpa menebak customer/order. Kuantitas tetap dibaca
  sesuai status, tetapi catatan ini tidak menambah jumlah delivery berreferensi.
- Filter tanggal memakai waktu selesai kirim, bukan tenggat delivery. Pencarian
  part/box/lokasi/operator memilih seluruh kejadian terkait, sehingga kuantitas
  daftar, rincian dan Excel tetap mencakup seluruh isinya. Filter tabel/Excel tidak
  mengubah kartu ringkasan seluruh histori. Default Pengiriman selesai, tersedia
  tab Histori Redo/pengembalian dan Semua catatan. Status kosong dipertahankan pada
  link agar tab Semua tidak kembali ke default saat pagination/unduhan.
- Tombol Lihat seluruh keterangan membuka metadata delivery/sesi, waktu proses,
  catatan delivery/approval, agregasi part, semua box, pallet/lokasi tercatat,
  scan/verifikasi/penempatan dan petugas yang tersedia. Part dan box dipaginasi
  terpisah. Data yang tidak tersedia pada catatan lama ditandai belum tercatat.
  Agregasi part memakai identitas biner agar kode yang berbeda huruf tetap terpisah.
  Kode dokumen ekspor memakai snapshot delivery, tidak membaca ulang alias master.
- ShipmentReportExport/ShipmentReportSheet menghasilkan Ringkasan Delivery,
  Rincian Part dan Rincian Box, seluruh halaman filter atau satu delivery pilihan.
  ID/kode dan teks formula tetap teks, kuantitas numerik (box pecahan tidak dibulatkan
  ke atas), waktu berupa tanggal Excel. Ekspor memakai transaksi baca tanpa mutex
  inventory. Binder PhpSpreadsheet yang bersifat global dikembalikan melalui event
  BeforeWriting dan finally controller, sehingga tidak memengaruhi Excel berikutnya.
- delivery:purge-completions kini mempertahankan sesi dan item selesai, command
  tetap tersedia untuk scheduler lama. Masa Redo operasional tidak diperpanjang.
  Riwayat yang sudah terhapus sebelumnya tidak direkonstruksi secara spekulatif.
  Tidak ada migration baru, perubahan ini tidak dijalankan pada database production.
- UI mengikuti Bootstrap/teal dan preferensi Apple existing, kartu ringkas, filter,
  tabel per kejadian, tombol rincian tetap terlihat saat tabel digulir, fokus dan
  target sentuh, catatan ringkas di daftar serta catatan utuh di detail/Excel.
  File UI: operator/reports/shipments, shipment-detail beserta partials dan
  public/css/pages/report-shipments.css. CSS dimuat melalui ajax-styles bersama
  seluruh laporan agar pergantian jenis/lingkup lewat AJAX tidak kehilangan gaya.
  Panduan: docs/SHIPMENT_REPORTS.md.
- Verifikasi: 58 tes terkait laporan/impor/ekspor lulus (608 assertions), termasuk
  regresi isolasi binder, semua halaman, leading zero/formula, arsip, pembatasan
  izin baca/unduh, fallback catatan lama, Redo, retensi dan pembacaan tanpa mutasi.
  Verifikasi akhir 15 November: 681 tes backend/7.295 assertions, 678 lulus,
  tiga kegagalan dashboard UiReviewPreviewTest sesuai baseline, tanpa error atau
  kegagalan impor Excel baru. Seluruh 147 frontend lulus. Sembilan tes khusus
  ShipmentReportTest (102 assertions) juga lulus setelah penambahan pemeriksaan
  pemuatan CSS sebelum pergantian laporan lewat AJAX. Diff --check lulus.
  Template HTML berhasil
  dirender dengan data uji, pemeriksaan visual browser terhalang kebijakan file lokal.

Perincian Hak Akses seluruh Lokal/Ekspor, 13 November 2026:

- Bagian ini menggantikan acuan izin menu pada catatan terdahulu di bawah.
  Ada 141 izin, termasuk 100 tindakan rinci. `config/feature_actions.php` berisi
  tindakan tambah/ubah/hapus/cetak, scan/simpan/clear penerimaan, assignment stok
  vs input box baru, keputusan PPC/issue/Not Full, split/restore, tahap expired
  QA/packing/receive, tahap opname, serta seluruh tindakan planning/pemenuhan.
  `config/feature_access.php` membangun dependensi dan default dari izin existing.
- `FeatureAccessService` memilih tindakan spesifik sebelum wildcard menu. Route
  bersama memeriksa payload action/status, termasuk draft/finalize, reopen/cancel,
  approve/reject/correction, dan tahap expired. Preview/search murni tetap dapat
  dibaca pemilik modul. POST assignment dengan new_boxes memerlukan izin tersendiri.
  Stok ekspor kini memiliki izin baca `export_stock_view` tersendiri; akses baca
  existing dari receiving/manage dikonversi, tanpa memberi izin kelola baru.
- Izin revisi pallet `export_fulfillment_revise` dapat diatur untuk semua role.
  `export_delivery_delete` tetap dibatasi admin_warehouse/supervisi (admin penuh).
  `export_fulfillment_cancel` memerlukan izin hapus. Revisi menolkan pallet terakhir
  memerlukan keduanya; revisi sebagian tidak memerlukan izin pembatalan seluruh
  permintaan. Hapus planning berlokasi memerlukan `export_pallet_cancel`, pelepasan
  box juga memerlukan `export_fulfillment_cancel`. Aturan pengembalian fisik tetap.
- Service planning dan completion memeriksa lagi izin tindakan sebelum mutasi.
  Rincian dashboard `dashboard_details` bisa dicabut dari Supervisi; jalur fallback
  `?show_details=1`/audit pagination juga tidak memuat data detail saat dicabut.
  Izin stock_history juga menyaring history yang tertanam pada API detail pallet;
  akses membaca stok tidak membuka ulang histori yang telah dicabut.
  Admin Utama tetap penuh; users/access_management/delivery_locks tetap khusus admin.
- `GranularFeaturePermissions::expand` mempertahankan akses efektif data role
  versi lama. Migration `2026_11_12_000002_version_granular_feature_permissions.php`
  menambah `role_feature_access.permissions_version`, mengonversi grant lama dan
  mencatat audit. Versi 2 menyimpan daftar tindakan eksplisit, sehingga pencabutan
  tidak diwariskan ulang dari menu. Row versi 2 dilewati saat migration diulang.
  Tidak mengubah data stok, transaksi, reservasi atau delivery. Pembacaan row lama
  sebelum migration tetap didukung; menyimpan form rinci memerlukan kolom versi.
  Downgrade otomatis ditolak karena kode lama dapat mengaktifkan kembali tindakan
  melalui grant menu. Migration BELUM diterapkan pada database operasional.
- Admin mengatur peran melalui pencarian menu/tindakan, uraian dan dependency
  otomatis pada `admin/access/index`, `public/js/pages/feature-access.js` dan CSS.
  Form mengirim permissions_version=2; integrasi admin lama tanpa versi masih
  ditafsirkan sebagai daftar menu lama. Save dan audit satu transaksi; audit gagal
  membatalkan perubahan. Panduan dan katalog: `docs/FEATURE_ACCESS_MATRIX.md`.
- Layout mengirim kebijakan route/feature server ke `public/js/action-permissions.mjs`.
  Script menyembunyikan link/form/button terlarang, memeriksa action pada form
  bersama, dan mengamati kontrol modal/AJAX. Kontrol scanner/JS memakai data-permission.
  Middleware tetap otoritatif; kontrol tidak dihapus agar inisialisasi modul aman.
- Verifikasi regresi: 672 tes backend/7.193 assertions, 669 lulus, tiga kegagalan
  UiReviewPreviewTest dashboard yang sudah ada; tidak ada error baru. Tes cakupan
  route yang sebelumnya gagal pada dashboard/details kini lulus. Seluruh 147 tes
  frontend dan npm build lulus. Tes khusus mencakup konversi/idempotensi, izin baca
  vs tulis, action payload, prasyarat, role tetap, revoke tanpa regrant, pemanggilan
  service, fallback dashboard, audit rollback, filter pencarian, dependency bertingkat
  serta kontrol AJAX. Visual browser belum diverifikasi karena batas akses sesi.

Pembatasan role penghapusan delivery ekspor, 12 November 2026:

- Hanya Admin Utama (admin), Admin Warehouse (admin_warehouse), dan Supervisi
  (supervisi) yang boleh menghapus rencana. User::canDeleteExportDeliveryPlan
  juga memeriksa izin export_delivery_plans existing dan status akun aktif.
  Tidak menambahkan izin default baru atau mengabaikan pencabutan Hak Akses.
- Tombol/section hapus pada daftar/detail delivery dan hasil planning disembunyikan
  untuk role lain. FeatureAccessService membatasi route deletion-preview, destroy,
  fulfillment.cancel-form dan fulfillment.cancel sebelum controller/validasi.
  Service preview/delete/cancel memeriksa lagi role agar pemanggilan langsung
  serta token review lama setelah perubahan role tidak melewati pembatasan.
- Pembatalan seluruh permintaan ikut dibatasi karena mengarsipkan delivery.
  Revisi yang menolkan pallet terakhir ditolak 403 untuk role lain sebelum mutasi;
  preview revisi menampilkan penjelasan dan menonaktifkan Simpan. Pengurangan
  sebagian/penghapusan satu pallet ketika masih ada pallet lain dan pengiriman
  selesai tetap mengikuti hak serta aturan operasional existing.
- Admin Warehouse/Supervisi tetap memerlukan akses fitur yang relevan; pelepasan
  lokasi/box mempertahankan pemeriksaan export_pallet_plans/export_fulfillment.
  Tidak memerlukan migration atau perubahan data akun/database operasional.
- Verifikasi: matriks tiga role diizinkan dan empat role ditolak mencakup tombol,
  preview, DELETE, pembatalan, akses service langsung, perubahan role setelah
  review, pencabutan izin, akun nonaktif dan revisi pallet terakhir. Regresi penuh
  657 tes/6.305 assertions: 653 lulus, empat kegagalan yang sama seperti verifikasi
  sebelumnya pada aturan dashboard/details dan tiga tampilan UiReviewPreviewTest.
  Tidak ada error baru. Seluruh 141 tes frontend lulus; diff --check lulus.

Tinjauan penghapusan dua tahap, 12 November 2026:

- Klik Hapus pada daftar/detail delivery atau hasil planning membuka popup
  tinjauan terlebih dahulu melalui GET deletion-preview dengan izin
  export_delivery_plans existing. Tinjauan tidak melepas reservasi/mengubah stok.
  Daftar mencakup setiap ID Box, part, PCS, pallet/lokasi asal tercatat, tujuan,
  dampak, reservasi lokasi tujuan, serta kebutuhan/persiapan Not Full terkait.
- Setelah Konfirmasi tinjauan, alert kedua menjelaskan bahwa rencana akan
  dihapus dari daftar aktif dan kode tidak dapat dipakai ulang. Batal pada
  salah satu tahap tidak mengirim penghapusan. Script memakai data preview
  server, validasi alasan, escaping Blade/text alert, serta pencegahan klik ganda.
- Reservasi box yang belum dipicking/ditempatkan kini dapat dilepas melalui
  tinjauan ini bila planning final, versi sesuai dan operator memiliki izin
  export_pallet_plans serta export_fulfillment. Alasan wajib. Penghapusan memakai
  ExportFulfillmentCompletionService::cancel existing, tanpa tujuan pengembalian,
  menjaga ID/part/PCS, lokasi asal dan FIFO, menaikkan revisi box untuk membatalkan
  instruksi lama, lalu mengarsipkan delivery. Audit tambahan mencatat daftar box
  dan lokasi yang dilepas. Histori pengurangan/pembatalan mengikuti service ini.
- Box yang sudah mulai picking, terverifikasi, ditempatkan, atau menunggu
  verifikasi merge ditampilkan sebagai wajib pengembalian fisik. Popup tidak
  menyediakan konfirmasi hapus langsung; gunakan tautan pembatalan/pengembalian
  existing. Pallet siap, issue terbuka, izin kurang, dan shipment selesai tetap
  dilindungi. Hapus tanpa token tinjauan tetap hanya mengizinkan kondisi kosong
  seperti implementasi sebelumnya, bukan pelepasan box tanpa review.
- Tinjauan memakai token terenkripsi terikat akun, delivery, fingerprint box,
  lokasi, versi dan Not Full, dengan masa berlaku 15 menit. Server membandingkan
  ulang dalam mutex inventory sebelum mutasi; perubahan sejak popup dibuka wajib
  ditinjau ulang. Kegagalan audit me-rollback pelepasan box, perubahan planning,
  lokasi, delivery, dan token aksi pembatalan. Tidak memerlukan migration baru.
- Verifikasi: 92 tes backend terkait (998 assertions) dan 141 frontend lulus.
  Mencakup GET tanpa mutasi, dua konfirmasi/batal/retry, posisi stok tersimpan di
  lokasi asal, FIFO, picking yang memerlukan pengembalian, box baru setelah
  preview, token kedaluwarsa/akun lain/tamper, izin/alasan dan rollback audit.
  Template popup diuji melalui respons HTTP; visual browser belum diverifikasi
  karena batas akses browser pada sesi ini.

Hapus rencana delivery ekspor, 12 November 2026:

- Daftar rencana dan hasil Perencanaan Pallet menyediakan tautan Hapus menuju
  bagian penghapusan pada detail delivery. Tombol Hapus rencana meminta
  konfirmasi dengan kode yang di-escape, kemudian menghapus dari daftar aktif
  melalui soft delete. Histori item, audit, dan ledger kode tetap dipertahankan;
  kode delivery yang dihapus tidak dapat digunakan ulang.
- Delivery draft tanpa komitmen box boleh dihapus meski sudah mempunyai planning
  draft/final. Planning kosong otomatis dibatalkan lewat transition existing,
  lalu reservasi lokasi dilepas dalam satu transaksi inventory yang sama dengan
  penghapusan dan audit. Tidak perlu membatalkan planning secara terpisah.
- Pembatalan planning otomatis tetap memerlukan izin export_pallet_plans selain
  izin route export_delivery_plans. Revisi delivery dan pallet diperiksa lagi
  saat simpan. Planning baru/berubah sejak halaman dibuka, box berreservasi atau
  ditempatkan, issue picking terbuka, pallet siap, dan pengiriman selesai
  melindungi data dari hapus langsung. Jika final dan pengguna memiliki izin
  export_fulfillment, detail menyediakan tautan ke tinjauan pembatalan existing
  untuk pelepasan reservasi/pengembalian fisik. Tidak ada perluasan hak akses.
- ExportDeliveryPlanDeletionService memakai mutex inventory sebelum lock parent
  dan planning, memeriksa ulang kondisi terkini, menyinkronkan pengumuman serta
  kebutuhan Not Full, dan mencatat audit. Kegagalan audit me-rollback soft delete,
  pembatalan planning dan pelepasan lokasi sekaligus. Tidak memerlukan migration.
- Verifikasi: 52 tes backend terkait (508 assertions) dan 136 frontend lulus.
  Pengujian mencakup planning kosong draft/final, box masuk setelah pratinjau,
  revisi lama, hak akses terpisah, pengiriman selesai, rollback audit, histori,
  konfirmasi batal, escaping kode serta pencegahan submit ganda. Pemeriksaan
  visual browser belum dilakukan karena batas akses browser pada sesi ini.

Perbaikan tinjauan FIFO kosong, 12 November 2026:

- Setelah finalisasi tanpa rekomendasi box, tombol Oke tetap mengonfirmasi
  tinjauan dan menutup modal. stock-review.mjs hanya memperbarui petunjuk
  alokasi bila elemen tersebut ada; view stok kosong memang tidak merendernya.
  Ini memperbaiki error textContent null setelah acknowledgment berhasil.
- Aturan reservasi, antrean, FIFO dan hak akses tidak berubah. Tes regresi
  mereproduksi kegagalan sebelum perbaikan dan lulus setelahnya; seluruh
  135 tes frontend lulus. Tidak memerlukan perubahan schema/database.

Kode label dan dokumen Master Part, 12 November 2026:

- Master bersama mempertahankan part_settings.id dan part_number existing sebagai
  identitas operasional/kode label scan. Satu form tambah/edit juga mengelola kode
  dokumen melalui part_document_numbers. Opsi sama dengan label tidak memerlukan
  alias; beberapa kode diisi satu per baris, maksimal 100 kode x 100 karakter.
  Tanda / diperlakukan literal, tidak dipecah atau dipetakan otomatis dari Excel.
- Pencarian master mencakup kode dokumen dan mengembalikan kode label existing.
  Izin CRUD, larangan rename master terpakai, serta perlindungan perubahan
  kapasitas pada delivery aktif tetap berlaku. Master dan pemetaan disimpan
  atomik di mutex inventory ekspor, beserta snapshot audit sebelum/sesudah.
  Kode dokumen tidak boleh bertabrakan dengan kode label/alias master lain.
- Input manual dan Excel Rencana Delivery Ekspor menerima kode dokumen atau
  kode label. Preview menampilkan label tujuan; server memeriksa ulang pemetaan
  dan expected_part_setting_id dari preview. Alias berbeda yang mengarah ke
  master sama wajib digabung operator, bukan membuat baris kebutuhan ganda.
  Kode tidak dikenal/ambigu ditolak. Penerimaan tetap memakai kode label existing.
- Item delivery menyimpan part_number operasional, FK master existing dan
  document_part_number sebagai snapshot kode input. Detail/cetak delivery memakai
  kode dokumen dengan label pendamping bila berbeda. Item lama bernilai null
  memakai snapshot part_number semula. Edit kode dokumen yang sama pada delivery
  existing mempertahankan identitas dan kapasitas historis meski alias berubah;
  transaksi baru memakai pemetaan terbaru. Replay token tetap memakai payload
  asli dan diselesaikan sebelum memeriksa pemetaan/master terbaru.
- Migration 2026_11_12_000001 hanya menambah tabel alias dan kolom nullable.
  Tidak mengisi ulang master dari workbook, menggabungkan master/stok, mengganti
  ID, atau mengubah PCS/FIFO/reservasi/transaksi lama. Belum diterapkan pada
  database operasional lokal maupun production; pengujian memakai SQLite
  in-memory. Panduan: docs/PART_DOCUMENT_MAPPING.md.
- Verifikasi: 49 tes backend terkait / 437 assertions dan 134 frontend lulus;
  Vite build serta diff --check lulus. Uji tambahan mencakup penerimaan label,
  alokasi ke delivery dokumen, cetak, dan migrasi populated tanpa perubahan
  master/box/item lama. Regresi lengkap pada tahap sebelumnya menjalankan 635
  tes: 631 lulus, 4 gagal pada FeatureAccessManagementTest (aturan dashboard/details)
  dan tiga tes dashboard UiReviewPreviewTest. Keempatnya juga gagal saat
  dijalankan terpisah; file dashboard/aturan akses tidak diubah oleh pekerjaan ini.
  HTML empat halaman berhasil dirender; verifikasi visual browser belum dilakukan
  karena IAB tidak menjangkau server lokal dan akses Brave tidak diizinkan.

Posisi stok dan kesiapan customer ekspor, 10 November 2026

- Stok Ekspor menampilkan filter cepat Stok Bersih, Sedang Diproses dan
  Siap Kirim. Stok bersih mensyaratkan box aktif dan layak, sudah tersimpan
  di pallet cadangan dengan lokasi, tanpa reservasi atau issue picking terbuka.
- Kesiapan Customer menggabungkan nama customer tanpa membedakan kapitalisasi,
  menampilkan delivery aktif menurut tenggat, dan memisahkan box siap, sudah
  di pallet, masih direservasi, dan belum dialokasikan.
- Kekurangan digabung per nomor part dan PCS per box agar stok yang sama
  tidak dihitung berulang untuk beberapa delivery customer. Pencarian
  stok bersih tetap mengecualikan reservasi semua customer. Panel customer
  berdiri berdampingan dengan ringkasan kekurangan di desktop.

Delivery Schedule Ekspor, 5 November 2026

- Menu Pemenuhan Permintaan kini bernama Delivery Schedule Ekspor. Kalender
  bulanan membuka bulan berjalan dan menempatkan nama customer pada tenggat
  rencana delivery. Setiap nama membuka rincian delivery yang sama.
- Pencarian customer/kode dan status berlaku pada bulan yang dipilih, dan
  kalender diperbarui lewat AJAX. Saat mengetik, saran delivery dapat langsung
  dibuka. Hover atau fokus menampilkan kode, tenggat dan status.
- Hak akses, status, dan tindakan pemenuhan pada rincian tetap sama.

Draf penempatan cadangan ekspor per transaksi, 5 November 2026

- Tab browser menyimpan tujuan, pembagian jumlah dan batas pallet baru per
  operator dan transaksi penerimaan. Konfirmasi satu part memuat status
  transaksi terbaru dan otomatis memulihkan pilihan part yang masih pending.
- Jika beberapa part memilih lokasi kosong yang sama, respons konfirmasi
  memetakan lokasi ke pallet yang benar-benar dibuat dalam batch tersebut.
  Pilihan lain mengikuti pallet itu selama masih berada di lokasi semula
  dan dapat dipilih. Kapasitas dihitung ulang dari data terbaru.
- Pilihan yang sudah dikonfirmasi dibuang dari draf. Perubahan box atau
  tujuan yang sudah tidak tersedia meminta peninjauan ulang pada part
  terkait. Payload memakai revisi dan token dari halaman terbaru.
- Regresi 22 tes Laravel dan 131 tes frontend lulus. Chrome menguji lima
  part, penempatan satu part, pemulihan empat part termasuk tujuan bersama
  dan pembagian box, serta lokasi yang menjadi tidak tersedia.

Pengumuman delivery Lokal dan Ekspor, 5 November 2026

- Admin Utama, Supervisi dan Admin Warehouse mengelola fitur melalui
  Warehouse > Pengumuman Delivery. Customer dan nomor part dicocokkan
  tanpa membedakan kapitalisasi. Customer, part dan jenis kuota ditetapkan
  saat terbit. Admin dapat memperbarui instruksi, jumlah kuota dan status.
- Kuota per delivery dipakai saat order Lokal disetujui atau perencanaan
  pallet Ekspor final. Delivery aktif yang cocok saat penerbitan juga
  dipertimbangkan. Pembatalan mengembalikan slot ke delivery aktif yang
  menunggu. Membuka revisi perencanaan menahan slot delivery yang sudah
  diperoleh selama customer dan part masih cocok.
- Kuota per box direservasi untuk ID Box yang sudah dipilih oleh proses
  assignment/picking Lokal atau komitmen stok Ekspor. FIFO mengikuti
  data pemilihan stok. Kuota berubah menjadi terpakai saat pengiriman
  dikonfirmasi. Pembatalan, revisi dan redo melepaskan box terkait.
  Keterangan perencanaan dapat menandai instruksi yang masih menunggu ID.
- DeliveryAnnouncementService menyinkronkan kuota pada transaksi status
  delivery dan assignment. Satu mutex database menyelaraskan kuota lintas
  lingkup. DeliveryAnnouncementDisplay mencatat snapshot instruksi, lingkup,
  kode delivery, halaman/dokumen, akun, serta waktu pertama dan terakhir.
  Penayangan tidak memengaruhi kuota. Keterangan per pallet atau transaksi
  hanya membawa part/ID Box pada dokumen atau layar tersebut.
- Nonaktif menyembunyikan instruksi pada delivery aktif dan menghentikan
  alokasi baru. Pengaktifan kembali memakai sisa kuota dan kandidat aktif.
  Riwayat penayangan serta pemakaian sebelumnya tetap tersimpan.
- Migration 2026_11_05_000005 dijalankan di MySQL development lokal.
  Regresi 623 tes Laravel lulus. Tes fitur pengumuman dan verifikasi
  Chrome mencakup kuota bersama, picking Lokal, penyelesaian Ekspor,
  pemilahan ID Box saat cetak pallet, hak akses dan tampilan ponsel.

Kelola stok ekspor, 5 November 2026

- Admin Utama, Supervisi, dan Admin Warehouse mengakses Kelola box,
  Kelola pallet, dan Riwayat box melalui Stok Ekspor. Hak
  export_stock_manage dibatasi pada ketiga role tersebut. Hak kelola
  dan baca stok tidak memberikan akses penerimaan.
- Koreksi stok cadangan aktif dapat mengubah nomor part, PCS sesuai
  master bersama, dan waktu pertama masuk yang dipakai FIFO serta
  umur stok. ID Box global, histori perpindahan, dan snapshot jumlah
  PCS saat penerimaan dipertahankan. Isi Box Not Full mengikuti alur
  persetujuan, sedangkan data hasil packing mengikuti alur QA.
- Batas maksimal dapat diedit pada pallet cadangan aktif. Nilai 0
  berarti tanpa batas dan nilai positif minimal sebesar isi pallet
  saat ini. Penghapusan pallet mencakup semua isinya dalam satu
  transaksi. Menghapus box terakhir juga menonaktifkan pallet cadangan
  dan membebaskan lokasi. Box, ID global, dan jejak audit tetap bisa
  dicari berdasarkan delapan digit ID Box.
- Pallet rencana dan box yang masih terikat reservasi, picking, QA,
  packing, atau penyiapan Box Not Full dilindungi dari penghapusan
  administratif. Aksi stok cadangan memakai mutex inventory ekspor
  dan memeriksa revisi box atau versi isi pallet sebelum menyimpan.
  Semua aksi edit dan hapus meminta alasan dan konfirmasi, kemudian
  mencatat operator, waktu, serta data sebelum dan sesudah.
- Riwayat box menggabungkan penerimaan, perpindahan, reservasi, picking
  terverifikasi, asal box hasil packing atau pecah, perubahan stok,
  dan penyelesaian pengiriman sesuai catatan yang tersedia.
- Migration hak kelola dijalankan di MySQL development lokal.
  Pengujian mencakup 619 tes Laravel, 131 tes frontend, serta klik
  Batal dan Konfirmasi pada form edit/hapus box dan pallet di Chrome.

Batas kapasitas pallet cadangan Ekspor 5 November 2026:
- export_stock_pallets.box_limit menyimpan batas fisik opsional per ID pallet
  cadangan. Kosong atau 0 dinormalisasi ke null. Pallet Perencanaan Pallet
  dikecualikan; peringatan kuning 36 dan merah 72 tetap independen.
- Pada penempatan cadangan ke lokasi kosong, operator dapat mengisi batas
  pallet baru. Semua kelompok yang menuju lokasi sama memakai batas yang
  sama. Opsi pallet existing menunjukkan isi, batas dan ruang tersisa.
  UI menahan konfirmasi atau cetak tujuan yang melebihi kapasitas dan
  operator membagi box dengan kontrol tujuan yang sudah tersedia.
- ExportStockReceivingService memeriksa kapasitas fisik dalam mutex
  inventory untuk penerimaan, relokasi, pengembalian revisi, dan hasil
  packing. Picking issue memeriksa saat approval dan konfirmasi, kecuali
  box yang masih tercatat pada pallet asal. Box Not Full dihitung satu.
  Jika melebihi kapasitas, perubahan satu konfirmasi di-rollback.
- Pemindahan pallet utuh mempertahankan ID dan batas. Merge cadangan
  membuat ID baru dengan batas opsional baru yang harus muat seluruh isi;
  nilai pada sumber tetap tersimpan untuk histori. Hasil merge rencana
  mengikuti kapasitas rencananya. Edit batas pallet aktif tersedia melalui Kelola pallet.
- Migration kolom nullable dijalankan pada MySQL development lokal; 7
  pallet sebelum dan sesudah, semua batas awal null. Regresi 615 tes
  Laravel dan 131 tes frontend serta Vite build lulus. Chrome memeriksa
  penerimaan dan merge pada viewport 1.366/390 px, termasuk kasus 34/36,
  pembagian lintas part dan input 0 pada merge.

Unduhan Excel Stock Opname Lokal / Ekspor 5 November 2026:
- Hasil sesi selesai menyediakan Export Excel dengan izin baca yang sama
  seperti riwayat opname dan pemeriksaan lingkup dari route.
- Satu workbook per sesi berisi Ringkasan, Semua Scan, dan Rekonsiliasi Box.
  Semua percobaan OK/NG/duplikat beserta alasan, waktu, operator, dan
  snapshot saat scan diambil dari log sesi. Rekonsiliasi mencatat hasil
  setiap box, termasuk belum terscan dan perubahan kondisi awal/akhir.
- Workbook memakai snapshot akhir yang tersimpan, tanpa membaca ulang stok
  aktif atau membatasi hasil berdasarkan filter/pagination halaman. ID Box,
  nomor part dan teks scan disimpan sebagai teks untuk menjaga nol di depan.
- Verifikasi file aktual mencakup sesi Lokal/Ekspor, sesi kosong, pembatasan
  hak akses, dan input scan yang menyerupai rumus. Workbook terbaca juga
  oleh OpenPyXL; posisi tombol diperiksa di Chrome 1.366/390 px.

Pemulihan otomatis scan Penerimaan Transfer Box Ekspor 5 November 2026:
- Draft box yang sudah diterima scan dimuat kembali dari server. ID Box yang
  belum ditambahkan, fase cek, nomor part dan tanggal pilihan dipulihkan
  otomatis dari localStorage per operator di browser yang sama.
- Snapshot dikaitkan dengan batch aktif. Pemulihan mengabaikan ID Box yang
  sudah muncul di draft server agar permintaan scan yang berhasil saat
  navigasi tidak diulang. Saat kembali melalui cache navigasi browser,
  halaman scan mengambil draft server terbaru dan memulihkan input lokal.
- Pengosongan draft membersihkan input, termasuk tanggal. Saat batch
  sebelumnya telah diproses, input batch lama tidak dibawa ke batch baru.
- 22 tes Laravel terkait penerimaan, 130 tes JavaScript, dan build Vite lulus.
  Chrome headless memverifikasi navigasi keluar dan kembali dengan draft
  berisi, fokus nomor part, tanggal, serta kelanjutan scan pada 1.366/390 px.

Tanggal masuk Penerimaan Transfer Box Ekspor 5 November 2026:
- Form proses menerima input_date opsional untuk seluruh daftar scan.
  Jika kosong, semua box memakai satu waktu saat batch diproses. Jika diisi,
  first_received_at memakai pukul 00.00 WIB pada tanggal pilihan. Tanggal
  mendatang ditolak dan draft tetap tersedia untuk koreksi.
- Tanggal efektif menjadi acuan FIFO, umur stok, dan laporan penerimaan.
  Waktu proses tetap dicatat di processed_at dan ditampilkan terpisah di
  rincian transaksi. Audit penerimaan menyimpan kedua waktu.
- Backdate yang membuat stok langsung expired mengikuti alur QA. Box ini
  tetap terlihat pada transaksi penerimaan, dengan status QA dan tanpa
  instruksi penempatan. Tanggal dan audit transaksi yang sudah diproses
  dipertahankan saat permintaan diulang.
- Regresi 608 tes Laravel dengan SQLite in-memory, 125 tes JavaScript, dan
  build Vite lulus. Chrome headless memeriksa form kosong dan berisi pada
  viewport 1.366 dan 390 piksel, termasuk reset tanggal saat draft dibatalkan.

Revisi jumlah pallet ekspor 5 November 2026:
- Pemenuhan Permintaan > Revisi pallet menyediakan target baru per part,
  termasuk menambah atau mengurangi jumlah yang ada dan mencari nomor part
  tambahan dari master bersama. Baris baru memakai PCS/box standar. Baris
  Not Full yang sudah ada tetap dikelola menurut komposisi PCS yang tepat.
- Tinjauan menampilkan target sebelum dan sesudah, stok FIFO yang cocok,
  kebutuhan tambahan yang belum tersedia, serta daftar box terbaru untuk
  pengembalian/pelepasan. Tujuan pengembalian adalah pallet cadangan atau
  lokasi kosong. Peringatan kapasitas berlaku mulai 36 dan 72 box.
- Revisi mengubah kebutuhan delivery yang sama, menyesuaikan Not Full dan
  membuka kembali status siap pallet yang direvisi. Rencana tetap final selama
  masih memiliki pallet. Perubahan ditangani secara atomik, dengan pemeriksaan
  revisi, fingerprint stok, token idempotensi dan audit.
- Stok bebas dan aktif dialokasikan FIFO menurut nomor part dan PCS per box,
  dengan prioritas tenggat dan urutan pallet. Tombol simpan dari tinjauan
  mengaktifkan transaksi antrean baru untuk tambahan yang direkomendasikan.
  Jika stok belum cukup, sisa kebutuhan tetap dapat disimpan. Transfer antar
  delivery di luar lingkup revisi.
- Tidak memerlukan migration. Regresi SQLite in-memory lulus 605 tes
  (5.724 assertions) dan regresi JavaScript lulus 125 tes. Chrome headless
  memverifikasi pencarian, fokus input, hapus part, dan peringatan kapasitas
  pada viewport 1.366 dan 390 piksel.

Expired Box dan Handle Lokal / Ekspor 5 November 2026:
- UI disetujui pengguna: satu menu Warehouse dengan pemilih Lokal/Ekspor,
  tab Perlu ditangani/Dalam proses/Riwayat/Warning, grup part + PCS, serta
  AJAX pencarian, filter, pagination dan pergantian lingkup. Header transaksi
  selalu menampilkan lingkup; rincian box asal dan riwayat dapat dibuka.
  Scanner dan tujuan penyimpanan berdampingan di desktop, bertumpuk di ponsel.
- Default akses kedua lingkup: Supervisi, Admin Warehouse, Admin Utama.
  Operator Lokal/Ekspor tidak mendapat default akses QA. Menu sidebar digabung
  tetapi endpoint/otorisasi tetap per lingkup. Izin Supervisi yang dicabut
  secara eksplisit tetap dihormati saat migration; Admin Warehouse ditambahkan
  sesuai persetujuan pengguna.
- Tiga tabel penanganan/transaction/receipt memakai scope default domestic.
  Model lokal memakai HasMasterScope; subclass ExportExpiredBox* menetapkan
  export. Unique source/replacement PK menjadi per scope karena PK stok lokal
  dan ekspor dapat sama. Sejak 15 November, ID Box unik global selama penggunaan
  aktif dan dapat dipakai ulang setelah penggunaan sebelumnya berakhir.
  Route/payload tidak dapat memindahkan histori atau keanggotaan lintas lingkup.
- ExportExpiredBoxService memakai first_received_at asli: warning >=9 bulan,
  expired >=12 bulan, atau mengikuti reactivated_until jika pernah packing.
  Expiry label dan eligibility dihitung dari tanggal, tidak bergantung scheduler.
  Command expired-box:sync-statuses juga menyinkronkan label ekspor.
- ExportStockBox::eligible/assertEligible melindungi reservasi FIFO, penempatan
  normal, mulai/scan/cari picking, penyiapan Not Full, merge ke permintaan,
  konfirmasi Siap dan penyelesaian pengiriman. Not Full hasil split mewarisi
  deadline sumber. Box expired tetap terlihat sebagai stok fisik dengan label;
  box dalam QA/packing tidak masuk tabel stok/antrean operasional biasa.
- Box berreservasi wajib dilepas melalui revisi sebelum QA. Pallet Siap perlu
  dibuka ulang; picking/issue aktif dan penyiapan Not Full sumber memblokir QA.
  Reopen dan pengembalian lewat revisi secara khusus mengizinkan box expired
  dipindah ke cadangan, sehingga jalan keluar menuju QA tidak buntu. Pengecualian
  ini merupakan argumen internal service, bukan flag yang bisa dikirim browser.
- ExportExpiredBoxHandlingService memakai mutex inventory. Saat QA, stok menjadi
  handling, current_pallet_id dilepas dan pallet kosong retired/melepas okupansi.
  Penolakan mengarsipkan stok operasional dengan snapshot dan ID global tetap
  tersimpan; soft-delete menjaga FK movement/Not Full ekspor. Alur lokal tetap
  mempertahankan implementasi force-delete dengan snapshot yang sudah ada.
- Hasil packing diterima bertahap melalui service receipt bersama. ID berasal
  dari scan packing, PCS aktual/Not Full dan FIFO asal tertua diwarisi, deadline
  received_at server + satu bulan kalender. Hasil ekspor langsung ditempatkan
  sebagai cadangan ke pallet existing/lokasi ekspor kosong; alokasi selanjutnya
  mengikuti FIFO normal. Token, jumlah tersisa, lokasi, ID global, audit dan
  penempatan divalidasi atomik. Warning 36/72 box juga tampil di tujuan penerimaan.
- Stock Opname ekspor mencakup QA/packing aktif dan mengenali ID asal yang sudah
  diganti/ditolak sebagai keluar/arsip. Histori dan audit export_expired_box
  menyimpan lingkup, acuan FIFO otomatis, penerimaan dan tujuan.
- Migration 2026_11_05_000001 dan 000002 diterapkan pada MySQL development stock
  setelah backup storage/app/backups/before-expired-workspace-20261105-093738-b12dd2.sql
  (193.067 byte). Jumlah stok, master dan histori sebelum/sesudah tetap sama.
  Production tidak diakses. Error 500 karena schema scope belum diterapkan serta
  manifest Vite ber-key absolut sudah ditangani. Build harus dari path project
  sebenarnya; alias W: hanya dipakai untuk pengujian PHP, bukan npm build.
- Verifikasi: seluruh 601 tes backend lulus (5.674 assertions), 125 frontend
  dan Vite build lulus. Manifest diverifikasi mempunyai key resources/js/* relatif
  dan tes tambahan rollback schema kosong/grant izin migration lulus (4 assertions).
  File aset tersedia dan HTTP localhost port 8000 merespons 200 setelah perbaikan.
  Pratinjau browser desktop dan viewport 390px memakai
  HTML hasil render SQLite in-memory; pilihan pallet existing/baru diverifikasi.
  Belum merupakan pengujian scanner fisik atau E2E browser dengan DB pengguna.
  File preview di storage/app/previews/expired-workspace-20261105; server dan
  tab preview dihentikan setelah pemeriksaan.

Catatan penerimaan lokal berikut bersifat historis; status penerapan migration
dan lingkup ekspor terbaru mengikuti bagian di atas.

Penerimaan hasil packing expired box 5 November 2026:
- Transaksi baru wajib satu nomor part dan PCS aktual per box yang sama.
  Pemilihan awal dikelompokkan part + PCS, termasuk pemisahan Box Not Full;
  filter pencarian mempertahankan pilihan dalam kelompok. Pergantian kelompok
  mengosongkan pilihan. Review dan service menolak campuran sebelum mutasi.
- Hasil QA tetap passed/rejected per box. Ditolak wajib alasan, dihapus dari
  inventory dengan snapshot histori. Hanya yang lolos diserahkan ke packing.
  Penerimaan boleh bertahap; sisanya at_packing dan transaksi baru selesai jika
  seluruh anggota completed/rejected. QA dan penyerahan packing tetap satu tahap
  penuh per transaksi seperti sebelumnya.
- UI penerimaan hanya scan ID Box baru dari divisi packing dan pilih satu
  pallet existing/lokasi kosong untuk penerimaan itu. Part, PCS, atribut Not Full
  dan umur FIFO diwarisi otomatis; jumlah tidak boleh melebihi sisa kelompok.
  Daftar scan diperiksa AJAX, audio OK/NG, dapat hapus/periksa ulang, menahan
  simpan saat pemeriksaan/NG, dan memperingatkan jika daftar belum disimpan.
  Pemeriksaan tidak menciptakan stok/reservasi; semua divalidasi ulang saat simpan.
- Acuan sumber dipilih original_stored_at tertua, source_box_id lalu handling ID;
  urutan scan mendapat acuan tersebut. replacement_mapping=automatic_fifo
  ditampilkan dan diaudit sebagai acuan data, bukan konfirmasi pasangan fisik
  ID lama-baru. Tidak ada ID yang diterbitkan/dibuat otomatis oleh sistem.
- Penerimaan baru menggunakan received_at server + satu bulan kalender
  (addMonthNoOverflow), bukan tanggal penerbitan label. Tidak perlu memasukkan
  tanggal label/dokumen pengantar. Deadline transaksi lama tidak diubah.
  Route cetak lama dipertahankan untuk kompatibilitas, tanpa kewajiban pengantar
  atau tombol pengantar pada alur operasional baru.
- ExpiredBoxPackingReceiptService mengunci header/anggota; token UUID unik dan
  hash payload/aktor memastikan retry tidak menggandakan atau memperpanjang stok.
  Seluruh mutasi box/identitas/pallet/lokasi/audit atomik. Lokasi tetap lokal,
  assignment/picking lock dan registri ID Box global termasuk Not Full dilindungi.
- Migration 2026_11_05_000001 membuat expired_box_receipts (waktu, petugas, tujuan)
  dan receipt_id/replacement_mapping pada handling. Riwayat penerimaan bertahap
  tampil pada transaksi; rollback ditolak jika sudah ada receipt. Transaksi lama
  dengan campuran part/PCS tetap utuh dan dapat diterima per kelompok yang dipilih.
- Verifikasi: 123 tes backend terkait expiry, penerimaan, opname, identitas,
  izin, Stock Input dan Not Full lulus (1.388 assertions). Enam tes frontend baru
  meliputi scan cepat, NG/retry, batas jumlah, grup dan pencegahan kehilangan scan.
  Seluruh 119 tes frontend dan Vite production build lulus. Belum diverifikasi
  dengan perangkat scanner fisik atau pemeriksaan visual browser.
  Migration belum diterapkan ke database kerja; tes menggunakan SQLite in-memory.

Stock Opname Lokal / Ekspor 4 November 2026:
- Menu terpisah Stock Opname Lokal (Warehouse) dan Stock Opname Ekspor
  (Pengiriman Ekspor), menggunakan StockOpnameController/Service/Inventory.
  Permission stock_opname_local/export untuk baca, masing-masing _manage
  untuk mulai/scan/perbarui/selesai. Operator sesuai lingkup, Admin Warehouse
  dan Admin Utama mengelola; Supervisi default hanya membaca.
- Migration 2026_11_04_000002 membuat sessions/items/scans. active_scope unique
  menjamin maksimal satu sesi aktif per lingkup. Operator berizin dapat
  melanjutkan sesi bersama; first_scanned_by dan log setiap percobaan menyimpan
  petugas/waktu. Token scan idempotent, duplikat terpisah dari OK unik.
- Acuan awal semua box belum keluar/diarsipkan. Lokal termasuk expired,
  orphan dan handled dengan proses QA/packing aktif; handled lama tanpa proses
  aktif serta ID sumber yang sudah diganti dikecualikan. Ekspor mencakup
  received/stored, cadangan, reservasi dan pallet Siap. Scan salah lingkup,
  histori keluar, tidak dikenal, dan ID selain tepat 8 angka menjadi NG.
  NG tidak memblokir opname dan tidak mengajukan approval atau mengubah stok.
- Operasional tetap berjalan. Perbandingan merekam kondisi awal, kondisi saat
  scan, dan kondisi terbaru saat Perbarui perbandingan stok/penyelesaian.
  Ini rekonsiliasi snapshot, bukan ledger setiap perpindahan antara dua
  pembacaan. Masuk selama sesi dan keluar/diarsipkan/ID diganti terpisah dari
  belum terscan. source_max_id mengenali penerimaan yang mewarisi tanggal FIFO
  lama serta receipt baru yang sudah soft-delete selama sesi.
- Sesi selesai menyimpan summary dan snapshot akhir permanen tanpa FK box
  sehingga history tetap terbaca sesudah box dikirim atau dihapus. Daftar
  history, tab belum terscan/NG/sesuai/perubahan/semua scan/semua box dan
  pagination memakai scope server. Audit start/complete wajib dalam transaksi.
- UI mengikuti apple-design dan DESIGN_PREFERENCES: fokus scanner stabil,
  indikator OK/NG/duplikat dengan teks, audio OK.mp3/NG.mp3 melalui ScanFeedback,
  antrean scan serial + retry token sama, AJAX rincian, konfirmasi sebelum
  selesai. Antrean belum terkirim ditampilkan dan menahan refresh/selesai;
  beforeunload memperingatkan bila ada scan belum tersimpan.
- Verifikasi: 8 tes opname lulus (152 assertions), 70 tes regresi izin,
  navigasi, expiry, identitas dan stok lulus, seluruh 113 tes frontend lulus,
  serta Vite build lulus. Migration baru belum diterapkan ke database kerja.

Kategori Stok Ekspor 4 November 2026:
- Filter stok hanya Tersimpan dan Stok cadangan. Tersimpan mengikuti ready_at
  pada pallet permintaan setelah Konfirmasi Pallet siap. Semua box aktif lain
  termasuk stok cadangan, termasuk box berreservasi atau sudah ditempatkan
  tetapi pallet belum dikonfirmasi Siap. Revisi/pembukaan status Siap
  mengembalikan kategorinya ke Stok cadangan secara otomatis.
- Label kategori, ringkasan jumlah box, detail popup dan Excel memakai aturan
  yang sama. Reservasi ditampilkan bersama kode RD, nomor pallet tujuan,
  lokasi tujuan dan jumlah box pada tampilan kelompok pallet.
- Nomor part, ID Box dan pallet dicari melalui satu field pencarian. Toolbar
  ringkas memakai pilihan Box/Pallet, kategori dan lokasi. Penjelasan kategori
  dapat dibuka melalui Arti kategori. URL part_number terdahulu ditampilkan
  sebagai chip yang bisa dihapus agar filter aktif tetap terlihat.
  AJAX, pagination, popup pallet,
  riwayat browser dan Excel mengikuti seluruh filter. URL lama pending/reserved
  dinormalisasi ke kategori spare.
- Verifikasi tabel/filter/Excel/modal: 17 tes (274 assertions) lulus;
  6 tes AJAX stok dan Vite build lulus, termasuk pencarian gabungan, radio
  Box/Pallet, reset dan riwayat browser. Pemeriksaan visual native browser
  dihentikan computer-use karena URL browser tidak dapat diverifikasi.
  Regresi penerimaan dan penyelesaian pallet turut diperiksa.

Istilah lingkup 4 November 2026:
- Tampilan, filter, pesan validasi, laporan Excel, dan panduan memakai istilah
  Lokal/Ekspor. Nilai teknis scope lokal tetap `domestic`. Pencarian navigasi
  menerima istilah lama dan menormalkannya ke lokal.

Pemindahan Pallet Ekspor 4 November 2026:
- Warehouse > Pemindahan Pallet memakai tampilan bersama dan pemilih lingkup
  Lokal/Ekspor sesuai izin. Endpoint export.move-pallet.* memakai izin
  export_move_pallet untuk Operator Ekspor, Admin Warehouse, dan Admin Utama.
- ExportPalletMoveService memindahkan pallet fisik utuh ke lokasi ekspor kosong
  yang tidak direservasi, di dalam transaksi inventory. Lokasi rencana final,
  reservasi lokasi, dan snapshot box yang sudah ditempatkan ikut diperbarui.
  Status Siap dibuka setelah persetujuan operator dan wajib dikonfirmasi ulang.
  Identitas, PCS, tanggal FIFO, dan alokasi box tetap. Audit mencatat perpindahan;
  penyiapan Box Not Full dengan sumber atau tujuan terkait harus diulang.
- Oke FIFO memasukkan rekomendasi ke antrean. Mulai Picking mengunci pallet
  sumber dan tujuan selama box masih menunggu penempatan. Scan terverifikasi
  dan issue terbuka juga menghalangi perpindahan. Revisi atau pembatalan
  meminta konfirmasi pengembalian untuk box dari picking yang telah dimulai.
  Rekomendasi tanpa scan satuan tetap tidak memerlukan scan. Box baru dari
  penerimaan tetap bisa ditempatkan melalui transaksi penerimaan seperti biasa.
- Verifikasi: 276 tes ekspor, navigasi dan pemindahan pallet lulus
  (2.832 assertions) dengan SQLite in-memory.

Picking Verification Ekspor 4 November 2026:
- Kewajiban scan dinilai per part dalam gelombang rekomendasi. Jika seluruh
  stok Part B yang cocok masuk satu tujuan dari sumber satu jenis part,
  kekurangan Part B dan keberadaan Part A pada rencana tidak mewajibkan scan.
  Sumber campuran dan part yang dibagi ke beberapa tujuan tetap wajib scan.
- ExportFulfillmentBox::requiresPickingScan dipakai oleh daftar tugas, detail
  picking, penempatan, dan merge. Alokasi menyimpan tanda wajib untuk rekomendasi
  baru. Rekomendasi lama yang masih terbagi tetap dikenali dari datanya.
- Cari Box tersedia setelah Oke FIFO untuk transaksi yang masih menunggu
  penempatan dari stok tersimpan, termasuk transaksi tanpa scan wajib.
  Setelah antrean habis, form Cari Box ditutup dan pencarian dari halaman lama
  mengembalikan status sesi tidak aktif.
- Verifikasi: 83 tes picking, transaksi rekomendasi, merge, tinjauan FIFO,
  dan penyelesaian pallet lulus (797 assertions).

Navigasi Merge Pallet 4 November 2026:
- Warehouse menampilkan satu menu Merge Pallet. Akun yang memiliki kedua izin
  masuk ke Lokal dari halaman lain dan bisa memilih Ekspor lewat tombol geser.
  Pada halaman merge, menu tetap aktif dan mengarah ke lingkup yang sedang dibuka.
- Akun dengan satu izin langsung masuk ke lingkupnya. Endpoint dan pemeriksaan
  izin lokal dan ekspor tetap terpisah.
- Verifikasi: 45 tes navigasi dan merge, 7 tes navigasi setelah penyesuaian posisi
  menu, serta perpindahan klik dan keyboard di browser dan viewport 390px.

Merge Pallet Ekspor 3 November 2026:
- ExportPalletMergeService/Controller dan export.merge.* memakai permission
  export_merge_pallet untuk export_operator/admin_warehouse/admin. UI terpisah di
  Pengiriman Ekspor mengikuti teal/Bootstrap, pencarian sumber, ketersediaan
  lokasi, pratinjau perubahan kebutuhan dan konfirmasi pemindahan fisik.
- Minimal dua pallet fisik, seluruh box ikut. Pallet permintaan hanya boleh
  satu delivery final aktif, termasuk jika customer sama. Cadangan + permintaan
  melindungi reservasi RD lain. Cadangan + cadangan juga ditolak bila reservasi
  terkait lebih dari satu RD; satu RD tetap mempertahankan target masing-masing. Pengambilan yang sudah mulai
  atau issue terbuka memblokir merge. Reservasi belum dipindahkan mengikuti target.
- Cadangan bebas: operator memilih mengisi kebutuhan belum direservasi pada
  pallet terpilih dahulu atau menambah semuanya. Tambahan part/PCS/box wajib
  disetujui melalui peringatan besar. FIFO menolak melewati box bebas lebih tua
  dengan part dan kapasitas sama, menunjukkan ID Box/lokasi yang benar.
- Result fisik baru, nomor rencana terkecil dipertahankan; logical sumber lain
  dilepas setelah referensi aktif dipindah. Snapshot sebelum/sesudah di audit
  export_pallet_merge dan export_pallet_merges; sumber fisik retired tetap ada
  untuk histori movement. Lokasi, reservasi dan penempatan diperbarui atomik
  melalui mutex inventory. Fingerprint dan token menjaga data stale/replay.
- Tetap final dengan revisi baru. Status siap hanya pada sumber terpilih dibuka
  setelah warning wajib, harus dikonfirmasi ulang. 36/72 tetap warning. Picking
  wajib tidak hilang saat merge: merge_pending_verification menahan fulfillment
  yang ikut fisik target sampai scan dan penempatan normal selesai. Kebutuhan
  lain yang belum dipindah tetap di sumber. Notice belum ditinjau dipindah ke
  notice revisi baru sehingga acknowledgment tidak buntu.
- Migration 2026_11_03_000002 menambah box_composition nullable pada delivery item,
  required_boxes pada request Not Full, unique per item+kapasitas dan tabel merge.
  ExportBoxRequirements menjadi sumber komposisi pasti; pengurangan pallet ikut
  mengurangi kapasitas tersebut, bukan ceil(PCS/master) yang dapat mengubah box.
  Not Full mendukung beberapa kapasitas/jumlah per part, satu box per approval,
  dengan hitungan seluruh reservasi/penempatan per kapasitas. Prepared target atau
  sumber yang berubah dikembalikan waiting untuk disiapkan/disetujui ulang.
- Panduan dan kasus uji: docs/EXPORT_PALLET_MERGE.md. Preview browser memakai
  SQLite terpisah; bukti storage/app/previews/export-merge-20261103/.
- Pallet campuran ditentukan berdasarkan part_setting_id aktif, sehingga
  perbedaan huruf/angka pada nomor part tidak melemahkan verifikasi picking.
  merge_pending_verification juga wajib masuk pengembalian fisik pada revisi/
  pembatalan; tidak boleh hanya melepas reservasi sambil meninggalkan box di target.
- Verifikasi akhir: 102 tes area perubahan (820 assertions), 52 tes merge/picking/
  transaksi rekomendasi (469 assertions), 110 frontend, Vite build, sintaks JS
  serta diff --check lulus. Termasuk 22 kasus merge, batas warning 36/72, rollback
  sesudah mutasi, token replay dan error stale browser yang kembali ke form GET
  dengan pilihan/pesan tersimpan. IAB desktop dan viewport 390px memeriksa lokasi,
  perubahan demand, status siap, sidebar, pencarian kosong dan keyboard.
- MySQL development lokal stock sudah dimigrasi setelah backup
  storage/app/backups/before-export-merge-20261103-200810-c4aaab.sql (295.809 byte).
  Sebelum migrasi: 0 box lokal, 0 box ekspor, 0 delivery ekspor; tidak ada seed
  atau merge inventory pada DB pengguna. Production tidak diakses. Server/tab
  preview terisolasi dihentikan setelah pemeriksaan.

Cloudflare Tunnel / HTTPS 3 November 2026:
- Diagnosis proses menunjukkan cloudflared --url http://127.0.0.1:8001 dan PHP
  melayani checkout OneDrive/WHFG/STOK-WHFG. Browser tunnel awalnya menghasilkan
  CSS/JS dan form action http pada halaman https, sehingga styling tidak termuat.
  public/hot/config cache/route cache tidak aktif dan build terbaru tersedia.
- bootstrap/app.php kini trustProxies hanya 127.0.0.1 dan ::1 serta hanya header
  X-Forwarded-Proto. Tidak memakai wildcard, X-Forwarded-Host/Port/For, forceScheme
  global, domain tunnel hardcoded, atau perubahan .env. HTTP localhost tetap
  berfungsi; identitas host/port tidak dapat dipilih melalui header forwarding.
- Empat tes TunnelHttpsTest mencakup aset/form HTTPS melalui loopback IPv4,
  loopback IPv6 dengan host/port forwarding asing, HTTP lokal langsung, dan
  penolakan spoof HTTPS dari REMOTE_ADDR non-loopback (15 assertions).
- Regresi lengkap setelah perbaikan: 544 backend (4.941 assertions) lulus;
  Pint dua file baru/diubah dan diff --check lulus. HTTP localhost juga
  diverifikasi lewat browser tetap menghasilkan URL HTTP lokal.
- Browser pada URL Quick Tunnel pengguna memverifikasi URL CSS/JS/form sekarang
  HTTPS dan tampilan login rapi. Tidak login/mengirim kredensial atau mengubah
  database pengguna. Bukti: storage/app/previews/cloudflare-https-20261103/.
- Cara menjalankan server/tunnel dan penjelasan mixed content dicatat pada
  docs/SETUP_LOCAL.md. Proxy container/IP lain perlu dikonfigurasi eksplisit.

Istilah ID Box 3 November 2026:
- Seluruh label identitas box memakai "ID Box" dengan spasi dan kapitalisasi
  tersebut pada alur lokal/ekspor, placeholder, validasi, audit, panduan cetak,
  header Excel/laporan, serta dokumentasi operator. Jumlah box dan nama fitur
  fisik tetap menggunakan "box". Tidak mengubah field box_number/box_id, route,
  struktur database, aturan scan delapan angka, FIFO, atau hak akses.
- StockViewByBoxIdExport mempertahankan dua kolom existing: ID stok untuk PK
  internal dan ID Box untuk identitas label delapan angka; sort internal memakai
  label ID stok agar tidak ambigu. Data dan urutan kolom tidak diganti.
- Catatan dashboard yang masih menyebut ID sama boleh lintas lingkup dikoreksi
  mengikuti registri global yang telah diterapkan sebelumnya.
- Verifikasi: 540 tes backend (4.926 assertions), 110 frontend, Vite build dan
  diff --check lulus. Preview IAB SQLite terpisah memeriksa label form/tabel
  penerimaan dan input 123 menghasilkan NG dengan pesan "ID Box harus tepat
  8 angka". Bukti: storage/app/previews/id-box-20261103/penerimaan.png.

Penyatuan master dan identitas 3 November 2026 (keputusan terbaru pengguna):
- PartSetting menjadi satu master global tanpa scope; ExportPartSetting, menu/
  route export.part-settings dan permission export_parts dihapus. Lokasi tetap
  HasMasterScope lokal/ekspor. Part case-sensitive mengikuti aturan existing.
  Master lokal menjadi acuan bila nomor sama; master hanya-ekspor dipertahankan.
- Migration 2026_11_03_000001 remap tujuh FK ekspor ke ID master acuan, menghapus
  baris duplikat dan scope part, menambah unique part_number. Snapshot PCS/box,
  jumlah PCS, first_received_at serta audit lama dipertahankan. Perubahan PCS
  master lewat CRUD tetap diblokir saat ada delivery ekspor aktif, termasuk draft.
- PartSettingController dan pencarian dipakai kedua alur. Operator Ekspor mendapat
  default parts hanya pada local/testing untuk debug. Explicit Hak Akses tetap
  menang; production tidak mendapat perluasan default. Migration membersihkan
  export_parts, menukar ke parts hanya untuk export_operator lokal/testing yang
  sebelumnya memiliki izin tersebut. Shared master audit memakai scope shared
  dan dikelompokkan Umum pada dashboard, bukan Lokal.
- Box dan ExportStockBox memakai ClaimsGlobalBoxIdentity: registry box_identities
  ber-PK box_number global, origin_scope hanya provenance. Seluruh box existing,
  termasuk soft-deleted dan fulfillment legacy tanpa physical stock, dibackfill.
  Histori hard-delete lokal pada ledger tetap dipertahankan. Insert ledger
  dan model atomik, identitas tidak dapat diubah. BoxIdentity juga memeriksa
  pending Not Full lintas lingkup. Sejak 15 November registry mengunci penggunaan
  aktif dan menghitung reuse, bukan melarang nomor sepanjang histori.
  Penerimaan, scan, Not Full dan approval koreksi
  picking menolak ID milik lingkup lain. Stok/operasi tetap di tabel masing-masing.
- registerLegacyIdentity hanya mengadopsi ID asal ekspor dengan fulfillment aktif
  tanpa stock, part/PCS sesuai, dan tanpa physical box aktif lain. Tanggal
  FIFO lama dipertahankan; bukan jalur untuk menduplikasi identitas lokal.
- Kode delivery wajib, trim dan uppercase, unik sepanjang histori termasuk arsip/
  cancelled/completed. Generated plan_code_key berindeks unique ditambah ledger
  export_delivery_plan_codes menjaga alias sesudah rename/hard-delete. Alias audit
  lama dibackfill. Override duplikat seluruh isi rencana dihapus (service/exception/
  panel/JS); isi sama dengan kode baru boleh. Token replay penyimpanan dipertahankan,
  termasuk bentuk hash field duplicate_* hanya untuk request lama yang berhasil.
- inventory:unification-check hanya baca dan dipakai sebelum DML/DDL. Benturan ID
  lintas lingkup, kode kosong/reuse histori, FK item yang akan bertabrakan, atau
  kapasitas berbeda pada rencana aktif menghentikan migration sebelum mutasi.
  Tidak mengganti ID/menebak kode/menggabungkan jumlah otomatis. DDL MySQL implicit
  commit; panduan deployment/backup/maintenance: docs/SHARED_INVENTORY_MIGRATION.md.
- SQL import master sebelumnya sekarang insert-only ke shared parts, mempertahankan
  kapasitas existing, dan tetap membuat lokasi ekspor. Paket UAT diperbarui: 34
  master bersama, SHARED=100 PCS, box lokal98009999 dan ekspor98009998. Jangan
  impor ulang namespace UAT26 yang sudah ada. Panduan/checklist/verifikasi disesuaikan.
- Migration development MySQL lokal stock diterapkan setelah backup lengkap
  storage/app/backups/before-shared-master-20261103-131314-0fa1d8.sql (96.562 byte).
  Saat eksekusi tidak ada box/plan/items; 2 lokasi existing dipertahankan.
  Pemeriksaan sebelum/sesudah tanpa konflik. Production tidak diakses.
- Verifikasi: regresi backend 538 tes/4.914 assertions sebelum dua tes tambahan;
  28 tes fokus/306 assertions termasuk kedua tes tambahan lolos. Frontend 110
  dan Vite build lulus. Migration populated SQLite 5 tes; MySQL8.4 scratch membuktikan
  canonical ID, snapshot lama, penolakan konflik sebelum mutasi dan unique code. UAT MySQL scratch
  menguji rollback/import ulang, 25 query baca, remapping ID serta 5 alur service.
  Pemeriksaan IAB SQLite terpisah membuktikan menu shared/lookup100 PCS pada form
  ekspor dan kode wajib, bukti storage/app/previews/shared-master-20261103/.

Bagian historis di bawah mencatat keputusan pada tanggalnya; aturan shared
master/global identity/kode unik di atas menggantikan pemisahan part dan ID lama.

Penempatan cadangan per part pada penerimaan ekspor 3 November 2026:
- Transaksi penerimaan tetap satu batch. ExportStockViewService mengelompokkan
  box tanpa fulfillment berdasarkan part_setting_id (spare-<id>); kelompok
  teralokasi tetap mengikuti pallet rencana. Status seluruh box cadangan pada
  tiap part dapat dibuka lewat details agar pilihan tujuan langsung terjangkau.
- Default satu lokasi kosong/pallet existing untuk semua box part tersebut.
  Opsi Bagi ke beberapa tujuan menerima jumlah bulat minimal satu, total harus
  tepat sebanyak box pending. Identitas box dibagi mengikuti urutan FIFO yang
  sudah diberikan server; rincian pilihan menampilkan box mana menuju ke mana.
  Tujuan hanya pilihan penerimaan ini, tidak disimpan sebagai default master.
- Konfirmasi dapat per part atau seluruh kelompok cadangan sekaligus. JS
  spare-placement.mjs menyusun destination per baris placements_json; controller
  memvalidasi format serta menolak campuran tujuan umum dan tujuan per baris.
  placeMany existing tetap atomik, mengunci inventory, memeriksa revisi/reservasi/
  lokasi/picking dan memakai token replay. Penempatan baru tidak mengubah tanggal
  first_received_at. Box teralokasi tidak dapat dialihkan ke tujuan cadangan.
- Peringatan 36/72 box menjumlahkan box masuk dari semua kelompok yang menunjuk
  tujuan sama, ditambah incoming kelompok rencana jika pallet fisiknya sama.
  Label tetap peringatan, bukan batas keras. Pilihan bebas ke beberapa lokasi
  tetap memakai master ekspor dan guard ketersediaan existing.
- Panduan cetak mengikuti pilihan per box/part/tujuan sebelum konfirmasi.
  GET print lama dengan spare_destination tetap kompatibel; route bernama print
  batch/legacy kini menerima GET+POST dengan izin sama dan CSRF existing. POST
  membawa spare_placements_json agar 1.000 box tidak menjadi URL sangat panjang.
  Cetak memeriksa membership box cadangan pending pada transaksi, tidak menerima
  box asing/teralokasi, tidak mengubah stok/audit. Target kosong/tidak tersedia
  diberi peringatan, bukan dibuatkan tujuan fiktif. Hasil yang sudah ditempatkan
  memakai lokasi aktual. ExportPlacementGuideService juga menjumlah kapasitas
  lintas kelompok. Tidak ada schema, permission atau dependency baru.
- Verifikasi: 541 backend (5.009 assertions), 110 frontend, Vite build, Pint dan
  diff --check lulus. Enam tes fitur baru mencakup pemisahan part satu transaksi,
  beberapa tujuan/part, replay/FIFO, cetak identik, rollback, transaksi asing,
  target tidak tersedia, box reserved dan revokasi izin POST print.
- IAB SQLite/cookie spare-placement-preview-20261103 terpisah dari MySQL menguji
  5 box Part A dibagi 3+2 dan 3 box Part B ke tujuan ketiga, input qty 4/5 ditolak,
  tambah/hapus tujuan, satu/split, preview ID, total 77 merah dari dua kelompok,
  POST cetak 3/2/3, konfirmasi 8 box dan antrean selesai. Penerimaan berikutnya
  melalui scan tiga box membuktikan tujuan awal kosong. Lebar DOM preview desktop
  1265px; override ponsel tidak terkonfirmasi pada pemeriksaan form ini. CSS
  menyediakan susunan satu kolom di 575px dan target 44px. Bukti:
  storage/app/previews/spare-placement-20261103/. Database pengguna tidak diubah.

Dashboard analitik Admin Utama/Supervisi 3 November 2026:
- Atas permintaan pengguna, shared.operational-dashboard kini menonjolkan KPI,
  arus stok, komposisi stok dan tiga tautan perhatian. Filter lingkup/periode
  berbentuk pilihan cepat AJAX; tanggal, pengelompokan dan status pada panel
  Sesuaikan analisis. Tabel pemenuhan/stok/pallet/durasi/issue/audit tetap ada
  pada partial operational-dashboard-details, ditampilkan bergantian melalui
  tab agar halaman tidak menjadi tumpukan laporan. Tanpa JS, semua rincian dan
  grafik tetap tersedia dengan navigasi anchor dan filter GET native.
- OperationalDashboardService/Sources dan ekspor Excel tidak berubah. Stok tetap
  snapshot seluruh periode; KPI pemenuhan berbasis jumlah permintaan penuh dan
  tidak menganggap reservasi sebagai penempatan. Issue terbuka memakai is_open
  periode existing. Grafik lokal PCS dan ekspor box tetap terpisah, masing-
  masing berskala sendiri. Tidak ada SLA, pembanding periode atau delta buatan.
- SVG lokal kini garis/area dengan tampilan Arus stok/Pemenuhan/Per jam. Modul
  public/js/pages/operational-analytics.js menambahkan inspeksi titik melalui
  pointer/geser, klik tahan/lepas, panah/Home/End/Enter/Escape dan legenda seri
  (minimal satu seri terlihat). Angka pasti dan tabel data tetap tersedia.
  JSON grafik memakai HEX escaping; readout memakai textContent.
- Animasi angka/garis/ring/bar/tab terbatas, tanpa dependency/CDN. Toggle animasi
  disimpan di whfg.dashboard.motion; reduced-motion selalu diutamakan. Observer
  hanya memantau penggantian langsung root AJAX; listener/frame lama dibersihkan,
  state tab/view/seri/filter terbuka dipertahankan. Mendukung pemulihan BFCache;
  fokus filter kembali sesudah panel fallback dibuka. Tidak mengubah sidebar,
  role, izin reports, expiry admin, reservasi/picking atau operasi inventory.
- Koreksi presentasi 15 November 2026 setelah penilaian pengguna: ritme before
  dipulihkan, KPI count-up 700 ms, garis 850 ms, ring 800 ms, batang 700 ms.
  Popup/ikon, modal, toast dan scope kembali ke animasi before. Feedback tekan
  langsung dan pelepasan tombol 120 ms dipertahankan. Klik tab memakai transisi
  220 ms; keyboard dan pemulihan panel tetap langsung. Reduced motion mengikuti
  OS. Koreksi lulus 14 tes frontend terkait dan pratinjau komponen; hasil 13 tes
  backend/138 assertions adalah verifikasi iterasi awal, tidak dijalankan ulang.
  Rincian before/after dan batas verifikasi: docs/MOTION_REFINEMENTS_2026-11-15.md.
- Tahap rules UI lanjutan 15 November: flash success server memakai toast
  penutup dengan penegasan centang 240 ms, judul/pesan dan permukaan hijau terang.
  API showToast biasa/scan tetap tanpa emphasis. Target kontrol grafik/tutup
  diperbesar ke 44 px; pencarian Hak Akses menormalisasi NFKC hanya pada teks
  pencarian, tanpa mengubah grant/key. 20 tes frontend terkait lulus. Status
  seluruh rules dan before/after: docs/UI_RULES_STATUS_2026-11-15.md.
- Tahap pengelompokan Hak Akses 15 November: details/summary native membuka satu
  kelompok awal; jumlah terpilih tetap tampil. Pencarian membuka hasil, memberi
  status jumlah/kosong, dan memulihkan keadaan kelompok saat dibersihkan.
  Pilihan/grant/dependency dan cakupan tindakan massal tetap. Tanpa JS seluruh
  kelompok terbuka. 22 tes frontend terkait dan 12 tes backend Hak Akses
  (360 assertions, SQLite in-memory) lulus; pratinjau desktop/390 px diperiksa.
- Tahap volume badge sukses 15 November: centang memakai squash/stretch
  sumbu X/Y berbeda selama 240 ms, rim/cahaya/bayangan badge memberi volume
  statis. Tombol, analitik dan pemanggilan scan biasa tetap. 22 tes frontend
  terkait dijalankan ulang dan lulus; pose serta desktop/390 px diperiksa.
  Wind-up/arcs tidak dipilih untuk area saat ini. Rincian pada status rules UI.
- Feedback pemuatan laporan 15 November: acknowledgement tetap langsung,
  setelah 1.200 ms muncul penjelasan hasil lama dan tombol retry. Retry
  membatalkan request browser lama dengan version guard existing, timer lama
  dibersihkan, fokus retry dipindah ke status saat tombol disembunyikan.
  Tidak menambah timeout atau mutasi data. 24 tes frontend terkait lulus;
  simulasi cepat/lambat/gagal/retry serta keyboard/390 px diperiksa.
- HTML dan JSON menggunakan judul Dashboard Analitik - Warehouse FG. Deploy
  view, CSS, modul analytics serta DashboardController terbaru; tidak ada schema,
  seeder atau konfigurasi PC tambahan. Skill apple-design tetap tidak tersedia;
  mengikuti DESIGN_PREFERENCES.md (pengecualian motion dashboard sesuai pengguna).
- Verifikasi: 535 backend (4.959 assertions), 106 frontend dan Vite build lulus.
  Preview analytics-preview-20261103.sqlite/cookie terpisah memeriksa Admin Utama,
  filter AJAX/Back, tab/audit pagination, keyboard titik/legenda, pointer klik,
  rentang kosong, 422/pemulihan, toggle animasi/reload dan fokus filter. Database
  MySQL pengguna tidak diubah. Browser desktop 1265px dan simulasi mobile 375px
  memeriksa tab, filter Supervisi, batas tabel dan lebar halaman tanpa overflow.
  Label sumbu mobile diringkas menjadi tiga agar tidak bertabrakan. Target
  sentuh 44px; pengujian bukan pada perangkat sentuh/scanner fisik. Console
  pengujian akhir tanpa error JS; respons 422 sebelumnya adalah skenario uji.
  Bukti: storage/app/previews/analytics-20261103/.

Transaksi rekomendasi stok per pallet tujuan 3 November 2026:
- Setelah Oke pada hasil FIFO, antrean penerimaan transfer menampilkan satu
  transaksi per pasangan allocation_notice_id dan pallet tujuan, berkode
  FIFO-<notice 6 digit>-<planned pallet 6 digit>. Beberapa part, pallet sumber,
  dan transaksi penerimaan asal digabung jika tujuan serta gelombang rekomendasi
  sama. Rekomendasi tambahan setelah revisi memakai notice/transaksi baru.
- Pengelompokan dihitung dari fulfillment existing; tanpa migration/backfill,
  tanpa membuat penerimaan baru atau mengubah tanggal FIFO/reservasi/posisi fisik.
  Penerimaan scan massal tetap TRX dan legacy tetap BOX. Box rekomendasi tidak
  dihitung sebagai tugas penerimaan asal lagi; detail asal menautkan transaksi
  FIFO, mengurangi hitungan selesai palsu dan tidak mencetak tugas ganda.
- Detail, cetak panduan dan Picking Verification memakai anggota transaksi
  yang sama. Rute receiving/recommendations/{notice}/pallets/{pallet} beserta
  /print berada di izin penerimaan existing. Picking memakai ?notice=<id>;
  URL pallet lama tetap kompatibel. Scan/replay dan POST penempatan memeriksa
  keanggotaan notice serta pallet tujuan, bukan hanya nomor part yang cocok.
- Wajib scan tetap hanya pengambilan stok rekomendasi dari sumber campuran.
  Sumber satu jenis part ditandai Tanpa scan satuan; input box baru tidak diberi
  verifikasi tambahan. NG pencarian tetap nonblocking, NG wajib tetap memakai
  approval/pengembalian existing. Issue dan audit menyimpan konteks notice;
  setelah pengembalian operator kembali ke transaksi picking terkait.
- Penempatan boleh bertahap; replay tidak membuat mutasi ganda, kembali ke
  detail transaksi yang sama, dan transaksi hilang dari antrean setelah selesai.
  Oke tetap pada halaman planning. Audit acknowledgment mencatat kode, tujuan,
  lokasi dan jumlah per transaksi; reservation audit juga menyimpan identitasnya.
- Implementasi utama: ExportReceivingTransactionService, ExportStockViewService,
  ExportPickingService, ExportReceivingTransactionController,
  ExportStockReceivingController, ExportPickingController dan view export
  receiving/picking. ExportRecommendationTransactionsTest mencakup pengelompokan,
  tambahan gelombang, batas transaksi, replay, penempatan bertahap dan pagination.
- Verifikasi: 535 tes backend (4.956 assertions), 101 frontend dan Vite build
  lulus. Query antrean/picking diuji pada MySQL development memakai transaksi
  READ ONLY lalu ROLLBACK. Uji browser memakai SQLite/cookie terpisah: empat box
  menjadi dua transaksi (3/1), cetak tepat anggota transaksi, pencarian lintas
  transaksi NG, scan wajib tiga box campuran dan penempatan tanpa scan untuk
  sumber satu part; console error kosong. Data MySQL tidak diubah.
  Bukti: storage/app/previews/recommendation-transactions-20261103/.

Animasi sidebar 3 November 2026:
- Atas permintaan pengguna, sidebar desktop bergeser 240 ms sambil mengubah
  ruang konten melalui margin sidebar; isi tidak diberi animasi terpisah.
  visibility ditunda sampai transisi tutup selesai, sedangkan inert dan status
  tombol langsung mengikuti tujuan. Toggle berulang membalik transisi CSS.
- navigation-motion hanya aktif setelah klik desktop, dilepas saat pageshow/
  resize agar pemulihan preferensi tidak dianimasikan. Drawer mobile memakai
  transisi transform 240 ms dan tetap mengikuti event/fokus Bootstrap existing.
  prefers-reduced-motion:reduce menonaktifkan transisi; tidak ada library baru.
- 101 tes frontend dan diff --check lulus. IAB preview SQLite terpisah memeriksa
  buka/tutup, toggle berulang, Tab melewati sidebar inert, lebar konten, 390px,
  pencarian setelah drawer tertutup, Escape/resize, tanpa overflow/console error.
  Bukti: storage/app/previews/sidebar-motion-20261103/open.png dan closed.png.

Navbar dan sidebar bersama 3 November 2026 (8 usulan UI/UX disetujui pengguna):
- NavigationContextService menambahkan presentasi pada daftar yang sudah diizinkan
  FeatureAccessService: satu menu aktif berdasarkan destination route, breadcrumb,
  label pendek dan alias pencarian. Tidak mengubah permissions, middleware atau
  route operasi. Receiving/stock dan dua laporan dipisahkan walaupun key izin sama.
  Halaman turunan memilih induk; picking wajib memakai menu picking bila tersedia,
  dengan fallback receiving bagi akun yang hanya memiliki izin penerimaan.
- Kelompok operasional lebih dulu, lalu master, laporan, administrasi. Label
  ekspor diringkas di sidebar, label lengkap tetap pada title/aria-label/pencarian.
  Navbar memuat jejak halaman dan parent link sesuai izin. Dashboard/profile
  memiliki konteks tersendiri. Menu akun menampilkan nama+role, Profil Saya/Keluar;
  identitas berulang di footer sidebar dilepas.
- public/js/layout-shell.js kini ES module (script type=module, tetap filemtime).
  Preferensi disimpan pada whfg.navigation.v2.<id>-<role>: collapsed/groups/recent
  di localStorage, scroll per tab di sessionStorage. Storage gagal/rusak memakai
  default. Group aktif dibuka saat masuk halaman; group lain mengikuti pilihan
  terakhir. Scroll dipulihkan dan disesuaikan hanya di wadah sidebar. Desktop
  tersembunyi memakai inert, toggle aria-expanded/label sesuai keadaan, submenu
  memakai button native untuk Enter/Space.
- Pencarian mencocokkan setiap kata/alias (stock/stok, export/ekspor, lokal/
  lokal, palet/pallet), mendahulukan kecocokan label; default 8 hasil dengan
  hitungan dan Lihat semua yang dapat diakses lewat Tab. Ada tombol hapus,
  ArrowUp dari posisi awal memilih hasil terakhir, Enter/Escape/fokus luar
  konsisten. Render memakai textContent; riwayat 5 route difilter terhadap izin
  saat ini. Navigasi native mempertahankan guard unsaved-change editor existing.
- Drawer mobile, pencarian dan akun bergantian aktif. Permintaan header menunggu
  drawer benar-benar tertutup sebelum membuka panel/fokus baru. Escape/close/
  backdrop mengembalikan fokus; resize ke desktop membersihkan backdrop dan
  aria-hidden. Navbar memakai dua baris di layar kecil, konteks tidak menekan input.
  CSS khusus public/css/navigation.css dimuat setelah fondasi Apple; sertakan
  aset ini dan shell module saat deploy. Tidak ada migration/dependency baru.
- Verifikasi akhir: 528 tes backend (4.852 assertions), 100 frontend, Vite build,
  PHP lint dan diff --check lulus. Dua tes penerimaan lama diperbaiki agar mengecek
  keberadaan tugas legacy FIFO, bukan menganggap transaksi terlama selalu baris
  pertama; default antrean terbaru yang sudah disetujui tetap dipertahankan.
- IAB dengan salinan navigation-ui-preview-20261103.sqlite/cookie terpisah menguji
  Admin Utama dan Operator Ekspor: keyword multi kata/alias, semua hasil lewat
  keyboard, clear/Escape/reopen, fokus sidebar tersembunyi, menu aktif tunggal,
  breadcrumb tambah/back, scroll/menu setelah reload, preferensi antar akun,
  profil/logout, drawer/search/akun, backdrop, dan resize 390/768/1024px. Tidak ada
  overflow halaman atau console error. MySQL development/production tidak diubah.
  Bukti: storage/app/previews/navigation-ui-20261103/. Skill apple-design tetap
  tidak tersedia; mengikuti docs/DESIGN_PREFERENCES.md dan token UI existing.

Feedback scan picking ekspor 2 November 2026:
- Halaman pencarian dan verifikasi wajib memakai shared.partials.scan-feedback,
  dengan aset public/audio/OK.mp3 dan NG.mp3. Form diberi data-scan-feedback
  agar audio disiapkan saat interaksi scanner/keyboard, mengikuti pola lokal.
- Scan yang berhasil mencocokkan rekomendasi menandai baris hijau dan teks
  "Sudah di-scan". NG/input tidak valid/gagal jaringan tidak menandai box lain.
  Tanda pencarian hanya pada halaman yang terbuka, tidak mengesahkan picking.
  Scan wajib memakai picking_ready untuk mempertahankan tanda saat HTML AJAX
  diganti atau halaman dimuat ulang; FIFO/approval/return tidak berubah.
- Verifikasi: 23 tes backend terkait (252 assertions), 93 frontend, Vite build
  dan diff --check lulus. Preview SQLite terpisah membuktikan scan OK/NG,
  tanda kumulatif pencarian, progres wajib tidak berubah oleh pencarian,
  serta tanda verifikasi wajib setelah reload. Kedua MP3 dimuat browser,
  console error kosong; bukan pengujian speaker/scanner fisik.
  Bukti: storage/app/previews/export-picking-feedback-20261102/scan-ok-highlight.png.

Revisi UI ekspor 2 November 2026:
- Rencana delivery memakai rekomendasi part kustom, case-insensitive, semua part
  jika kosong. Pilihan berada pada alur dokumen (tidak menutupi field lain),
  mendukung keyboard panah/Enter/Escape. Modul search-suggestions.mjs dipakai
  bersama pemilihan lokasi planning; lokasi kosong menampilkan semua lokasi,
  yang occupied/reserved atau dipilih pallet lain dinonaktifkan dengan alasan.
- Lokasi planning lebih menonjol dan tetap menyimpan master_location_id valid;
  mengetik teks yang belum dipilih tidak mengirim ID lama. Header nomor pallet
  ditonjolkan di editor maupun hasil draft/final. Varian full/NF tetap terpisah.
- Daftar planning default created_at delivery terbaru; sort=oldest membalik
  urutan, dengan id sebagai tie-breaker. Antrean penempatan default waktu
  transaksi dibuat terbaru, pending_sort=oldest untuk terlama. Batch memakai
  created_at batch, legacy memakai created_at box; tidak mengubah FIFO alokasi.
- Modal FIFO dipersiapkan saat halaman hasil dimuat (100 box/halaman), tombol
  tinjau hanya membuka modal lokal. Konfirmasi wajib dikirim AJAX; saat berhasil
  menutup modal, mengaktifkan antrean dan tetap pada halaman planning. Gagal
  tidak menutup modal, tombol dapat dicoba lagi. Fallback POST juga kembali ke
  planning. Kewajiban acknowledgment dan guard revision/otorisasi tetap berlaku.
- Sidebar menandai semua subroute delivery/planning dan halaman picking/receiving
  sesuai kelompoknya, dengan aria-current. Logika izin FeatureAccess tidak diubah.
- Daftar serta layar scan Picking Verification ekspor memakai fondasi CSS
  lokal: header/konteks, kartu scan/progres, tabel rekomendasi. Backend seek,
  scan blocking, approval dan return tidak berubah. Lookup NG tetap nonblocking;
  scan wajib salah tetap blocked sampai penempatan return dikonfirmasi.
- Verifikasi: 106 tes backend terkait (1.204 assertions; 61 + 45), 90 frontend,
  Vite build dan diff --check lulus. IAB preview memakai salinan SQLite terpisah
  export-ui-preview-20261102.sqlite, cookie sendiri, bukan MySQL development.
  Browser menguji part kosong/ketikan/keyboard, 50 lokasi termasuk18 disabled,
  draft/final, Oke/tinjau ulang tanpa pindah halaman, sidebar, dua sort, lookup
  NG dan scan wajib OK/NG serta viewport390px tanpa overflow horizontal halaman.
  Bukti gambar di storage/app/previews/export-ui-20261102/. Bukan uji scanner fisik.
- Skill apple-design tidak ditemukan di folder skill yang tersedia; mengikuti
  docs/DESIGN_PREFERENCES.md, token teal dan komponen Bootstrap/CSS lokal.

Panduan manual ekspor 1 November 2026:
- Pengguna mengklarifikasi bahwa pengujian dilakukan sendiri lewat aplikasi,
  dengan data pendukung dan panduan kasus dari buat delivery, planning,
  penerimaan/picking sampai selesai; fokus seluruh fitur ekspor.
- Pemeriksaan PDO transaksi READ ONLY pada MySQL lokal stock memastikan impor
  UAT26 ada: 18 part ekspor, 50 lokasi, 123 box aktif + 4 histori. Stok MIX/MONO
  dan NF20 existing masih bebas; beberapa full milik NF/REV/CANCEL sudah reserved.
  Tidak ada mutasi, pelepasan reservasi atau impor tambahan.
- docs/EXPORT_MANUAL_TEST_GUIDE.md menyajikan 14 rangkaian kasus. Kasus dasar
  membuat delivery baru TES-EXP-xx sendiri, ID input 9811xxxx (belum tercatat
  saat diperiksa); kasus lanjutan memakai rencana contoh final agar stok tidak
  berebut. Mencakup warning, FIFO, scan blocking/return, NF dua asal/existing,
  revisi/pembatalan, prioritas, Excel/duplikasi, master, stok/laporan/dashboard.
- Panduan disertakan dalam ZIP existing oleh scripts/uat/package.py.
  Checklist manual belum dijalankan agent; pengecekan ini hanya memastikan
  data pendukung tersedia. Tidak perlu impor ulang untuk mengikuti panduan baru.

Paket UAT bisnis 31 Oktober 2026:
- SQL aditif development di database/uat/01_seed_business_uat.sql; 9 akun uji,
  35 part, 100 lokasi, 37 box lokal, 127 box ekspor, 12 delivery lokal
  dan 17 rencana ekspor (termasuk histori/arsip). Namespace UAT26, ID Box
  9800xxxx baseline dan 9801xxxx untuk langkah input; tanggal relatif impor.
- Dibangun melalui model/service pada SQLite baru, lalu diekspor beserta ledger
  box_identities, pivot, ringkasan, reservasi, Not Full dan audit. Bukan contoh
  raw insert Box untuk operasi biasa. ID/FK/snapshot dipetakan ke offset target.
- Import procedure atomik, guard database/hostname/InnoDB/namespace/picking;
  tidak menimpa role permissions atau master/data existing, impor ulang ditolak.
- MySQL 8.4.3 sementara memverifikasi rollback gagal di tengah impor, penolakan
  impor ulang, data existing tetap, 22 query baca/integritas, dan runtime service
  FIFO picking/pengiriman lokal/ekspor, issue approval + konfirmasi return,
  split NF menjaga PCS, redo histori, serta penerimaan packing label kedaluwarsa.
  DB sementara dihapus; database aplikasi hanya dibaca skemanya, tidak diisi.
- Panduan manual semua fitur: docs/UAT_BUSINESS_GUIDE.md; peta cakupan,
  manifest dan bukti validasi di database/uat. Checklist UI belum dianggap lulus
  hanya karena fixture/service tervalidasi. Regenerasi: scripts/uat/build.php;
  validasi ulang: scripts/uat/validate-mysql.php. Tidak ada perubahan proses bisnis.
- Paket akhir memuat 116 kasus manual untuk 32 fitur dan lembar hasil terpisah
  docs/UAT_RESULT_TEMPLATE.md (semua Belum diuji). scripts/uat/package.py membuat
  storage/app/uat/STOK-WHFG-UAT26.zip tanpa koneksi DB, dengan checksum dan
  pemeriksaan isi arsip. Panduan memperjelas kapasitas/nama part dan scan 8 digit.

Box Not Full ekspor 31 Oktober 2026:
- Input manual/Excel kini menerima sisa PCS: 100 PCS pada standar 80 berarti
  1 full 80 + 1 Not Full 20, total 2 box. Master tetap 80; item delivery menyimpan
  not_full_pcs. Pratinjau hanya mendeteksi/menandai, save membuat satu request
  wajib per item delivery. Retry/edit tidak menggandakan request yang sama;
  rencana berbeda tetap punya request terpisah. Alert sesudah simpan menjelaskan
  kebutuhan dan mengarahkan operator menghubungi admin.
- ExportBoxRequirements membuat varian part+PCS untuk planning. Part sama boleh
  muncul sebagai full dan Not Full pada satu pallet; keduanya harus tepat.
  Unique planned item menjadi pallet+part+PCS. Selector, sisa alokasi, total box,
  cetak dan revisi membedakan kapasitas. Not Full tetap satu box pada warning 36/72.
- ExportNotFullService/ExportNotFullRequest menambah antrean ekspor pada fitur
  Box Not Full existing, dengan filter Lokal/Ekspor, badge kebutuhan belum
  terpenuhi untuk Admin Warehouse/Admin Utama/Supervisi, rincian dan audit.
  Admin Warehouse/Admin Utama boleh menyiapkan; hanya Admin Utama/Supervisi
  boleh approve/reject. Hak fitur existing tetap diperiksa, termasuk guard role
  eksplisit. Operator ekspor melihat kebutuhan/status di delivery/planning/
  pemenuhan dan tidak diberi hak approval.
- Penyiapan/pembuatan menunggu pallet FINAL yang valid. Dua opsi baru: datang
  sudah dikemas Not Full, atau pecah box PENUH dari stok. Alokasi FIFO existing
  tetap mendahulukan kebutuhan penuh. Varian PCS cocok (mis. stok 20) dapat
  direservasi seperti stok biasa; tidak membuat box baru dan mengikuti antrean
  penempatan/verifikasi existing. Reservasi belum dihitung terpenuhi.
- Pemecahan hanya memilih full box part/kapasitas cocok, stored, lokasi valid,
  bebas reservasi dan issue, pallet belum ready, urut FIFO. Penyiapan tidak
  menahan full box dari permintaan penuh yang datang kemudian. Approval
  memeriksa ulang sumber FIFO/revision/tujuan; sumber terpakai/berubah wajib
  disiapkan ulang. Box hasil memakai ID baru 8 digit khusus lingkup ekspor.
- Approval mewajibkan konfirmasi fisik dan langsung membuat stok serta konfirmasi
  penempatan di pallet rencana, tanpa penerimaan/scan ulang box hasil. Internal
  split 80 -> 20 + 60 menjaga total PCS: sisa 60 memakai ID/lokasi asal, kedua
  box mewarisi first_received_at sumber. Semua mutasi memakai mutex inventory,
  transaksi atomik dan versi request. Retry approval tidak mengurangi dua kali.
- ExportStockBox menambah is_not_full, standard_pcs_quantity, receipt_pcs_quantity,
  origin dan source_box_id. Origin split tidak dihitung sebagai inbound baru;
  receipt snapshot sumber menjaga histori penerimaan awal 80 meski kini sisa 60.
  Box baru datang Not Full tetap inbound eksternal. ID yang masih disiapkan
  Not Full ditolak oleh penerimaan normal agar tidak salah menjadi box penuh.
- Revisi mengurangi PCS sesuai varian yang dihapus, bukan mengalikan seluruh
  box dengan kapasitas master. Pengembalian mempertahankan FIFO, request yang
  tidak dibutuhkan dibatalkan, rejection tetap menyisakan kebutuhan wajib.
  Pallet belum dapat ready/permintaan selesai sebelum isi exact sesuai rencana.
- Migration 2026_10_31_000001 diterapkan hanya ke MySQL lokal stock/127.0.0.1
  setelah backup storage/app/backups/stock_export_not_full_20261031_191724_0955cd.json.
  Seluruh kolom/data lama pada 15 tabel dibandingkan identik; 4 header delivery
  dan 8 stok ekspor tetap, request baru lokal 0. Production remote tidak diubah.
- Regresi penuh 518 backend (4.731 assertions), setelah guard sumber/audit akhir
  25 tes terkait (173 assertions) lulus; 88 frontend dan Vite build lulus.
  Preview SQLite terpisah memeriksa impor Excel 100/80, alert, planning full/NF
  satu pallet, reservasi full tertua, role penyiapan/approval, stok langsung,
  sisa 60 di asal, desktop 1366px/ponsel 390px dan overflow/error konsol.
  Detail: docs/EXPORT_NOT_FULL_WORKFLOW.md. Bukan stress test production.

Dashboard Admin Utama dan Supervisi lintas lingkup 31 Oktober 2026:
- Kedua role memakai shared.operational-dashboard melalui
  OperationalDashboardService dan OperationalDashboardSources. Default Semua;
  filter Semua/Lokal/Ekspor, periode/tanggal, pengelompokan grafik, status
  pemenuhan dan audit memakai AJAX report-browser.js existing. Role lain tetap
  pada dashboard masing-masing. Izin reports existing mengendalikan tautan
  rincian dan Excel; tidak memberi Supervisi akses mutasi operasi ekspor.
- Grafik lokal memakai PCS; grafik ekspor memakai box, selalu terpisah.
  Masuk ekspor memakai first_received_at, keluar hanya completed_at permintaan.
  Reservasi/pemindahan/pengembalian cadangan tidak dihitung sebagai barang baru
  masuk atau barang keluar. Arsip pengiriman tetap masuk histori.
- KPI pemenuhan adalah persentase jumlah permintaan penuh, tidak menjumlahkan
  kebutuhan PCS dengan box. Ekspor memakai overview/itemMetrics existing:
  hanya konfirmasi penempatan valid yang terpenuhi, bukan reservasi. Draft belum
  pernah final serta cancelled dikecualikan. Pallet siap tetap stok sampai
  Selesaikan permintaan. Tenggat menjadi dasar periode kebutuhan/pemenuhan.
- Stok dan pallet aktif adalah posisi saat ini, tidak dibatasi periode. Total
  mencakup semua data walaupun rincian dibatasi 200 baris. Identitas lokal/
  ekspor tetap terpisah. Lokal mempertahankan guard expiry/legacy; box dengan
  tautan dua pallet dihitung sekali pada KPI dan diberi peringatan posisi ganda,
  sedangkan daftar pallet menampilkan kedua tautan untuk pemeriksaan. Parent
  delivery split tidak menggandakan kebutuhan leaf delivery aktif.
- Issue scan dihitung per created_at pada periode, termasuk histori input_error
  yang sudah ditutup. Audit diberi label Lokal/Ekspor/Umum; lingkup dikenali
  dari type/model/snapshot scope. Audit global hanya tampil pada Semua. JSON
  lama yang tidak valid tidak menggagalkan dashboard. Audit 20 baris/halaman,
  issue 50 terbaru, tabel lain 200; Excel memuat seluruh hasil sesuai filter.
- Durasi lokal tetap picking/sesi scan, ekspor finalisasi terakhir sampai
  permintaan selesai; fase ditulis terpisah, tidak dirata-rata atau membuat
  durasi scan ekspor fiktif. Nilai nol dan menit:detik tetap terbaca.
- Excel memakai OperationalDashboardExport dan native PhpSpreadsheet charts,
  satuan terpisah, header/filter/freeze baris, ID Box nol awal sebagai teks.
  Grafik browser SVG lokal dengan rincian tabel; tidak memakai CDN/generator
  gambar grafik eksternal. Jalur Excel legacy tanpa scope tetap tersedia.
- AJAX hanya membaca dan melewati kalkulasi statistik/expiry lama. Load HTML
  Admin tetap mempertahankan sinkronisasi expiry existing. Dashboard ini tidak
  menambah migration, seeder atau dependency. Query MySQL lokal dijalankan dalam
  transaksi READ ONLY; production remote tidak diubah. Preview memakai SQLite
  khusus operational-dashboard-preview.sqlite.
- Verifikasi: 501 backend (4.609 assertions) pada regresi penuh; setelah rapikan
  label/grafik/Excel, 58 tes terkait (607 assertions) lulus. 87 frontend lulus.
  Browser desktop 1366px dan ponsel 390px memeriksa filter, audit/pagination,
  Back, tanggal 422/pemulihan, unduh Excel, keyboard, rincian grafik dan laporan.
  Tidak ada overflow halaman/error konsol. Bukan stress test production.
  Definisi dan file utama: docs/OPERATIONAL_DASHBOARD_SCOPES.md.

Pencegahan duplikasi rencana delivery ekspor 30 Oktober 2026:
- Simpan manual/hasil Excel dan edit diperiksa terhadap rencana aktif serta
  completed (termasuk soft-deleted karena sudah dikirim). Cancelled dan draft
  yang diarsipkan diabaikan; edit mengecualikan dirinya sendiri. Duplikat berarti
  customer/kode/tenggat dan pasangan part/PCS sama, tanpa membedakan huruf besar,
  whitespace atau urutan baris. Nama file tidak menentukan identitas dokumen.
- ExportDeliveryPlanDuplicateService membaca data lama langsung, tidak memakai
  backfill atau mengubah histori. Calon pembanding dibatasi tanggal/total PCS dan
  dibaca per 100 header. Semua mutasi memakai mutex inventory ekspor existing.
- Deteksi menghentikan penyimpanan tanpa membuat header/item/token/audit baru.
  Form menampilkan pembanding, alasan wajib dan checkbox tinjauan. Konfirmasi
  HMAC terikat ke aktor, token form, target edit, isi dan daftar/revisi pembanding.
  Jika isian atau pembanding berubah, tinjauan ulang wajib. Rencana identik tetap
  boleh disimpan dengan alasan, yang dicatat pada audit bersama ID pembanding.
  Kode rencana tidak dijadikan unik global. API menerima 422 + duplicate_review.
- Create/update web wajib UUID request_token. Tabel export_delivery_plan_requests
  menyimpan hash isi/aktor/target dan ID hasil secara atomik dengan data/audit.
  Retry identik mengembalikan ID lama sebelum validasi master/revisi terkini,
  termasuk setelah arsip; token sama dengan isi/aktor/aksi lain ditolak.
  Token hanya dikonsumsi saat sukses. Form lama dengan isian berbeda mendapat
  token baru dan mempertahankan input, tetapi memerlukan submit/tinjauan lagi.
- Submit ganda dicegah pada JS dan backend; pageshow memulihkan tombol setelah
  Back. Perubahan input/impor/undo menghapus persetujuan duplikasi lama.
  Rincian penanganan: docs/EXPORT_DELIVERY_DUPLICATE_PROTECTION.md.
- Migration 2026_10_30_000004 hanya membuat tabel token baru. Diterapkan ke MySQL
  lokal stock/127.0.0.1 setelah backup
  storage/app/backups/stock_export_delivery_dedup_20261030_231757_59e689.json.
  Data 12 tabel lama dibandingkan identik, 4 rencana/8 stok ekspor tetap, token
  lokal 0. Query pembanding MySQL diuji baca saja. Production remote tidak diubah.
- Regresi akhir: 488 backend (4.489 assertions), 87 frontend dan Vite build lulus.
  Browser SQLite khusus memeriksa perbedaan huruf/spasi/urutan, peringatan sebelum
  simpan, perubahan input membatalkan review, alasan/checkbox wajib, satu override
  berhasil dan audit. Desktop 1366px/ponsel 390px tanpa overflow halaman atau
  error konsol. Belum merupakan stress test konkurensi MySQL production.

Perbaikan pengecualian proses bisnis ekspor 30 Oktober 2026:
- Revisi pada Pemenuhan Permintaan kini boleh saat pallet belum penuh, tanpa
  reservasi, sampai semua part nol/pallet dihapus, atau membatalkan seluruh
  permintaan. Jalur revisi planning/delivery umum tetap melindungi komitmen;
  gunakan jalur pemenuhan untuk pelepasan dan pengembalian secara atomik.
- ExportFulfillmentRevisionService memeriksa konsistensi reservasi/posisi tanpa
  mensyaratkan penuh; validasi konfirmasi siap/pengiriman tetap wajib exact.
  Kebutuhan yang belum terisi dapat dikurangi tanpa pengembalian fiktif.
  Komitmen terpilih adalah yang terbaru, agar box tertua tetap untuk dikirim.
- Reservasi belum dipindahkan dilepas di posisi asal; revision box naik untuk
  menolak instruksi lama. Box sudah ditempatkan atau sudah diverifikasi diambil
  wajib konfirmasi dan tujuan pengembalian. Pengambilan belum ditempatkan boleh
  dikembalikan ke pallet asal. Tanggal FIFO tetap; tidak dialokasikan ulang oleh
  revisi. Seluruh isi/sumber termasuk dalam fingerprint untuk menolak tinjauan
  lama setelah penerimaan/penempatan berubah.
- Pallet nol dilepas klaimnya dan dihapus setelah stok kosong, fisiknya diretir;
  nomor pallet lain tidak diganti. Menghapus pallet terakhir membatalkan request.
  Pembatalan massal memakai satu tujuan cadangan di luar pallet request dan
  alasan wajib. Delivery cancelled diarsipkan, tidak ditandai completed/shipped.
  Retry dapat diproses setelah parent/pallet diarsipkan. Audit kegagalan rollback.
- Admin/Admin Warehouse dapat menutup issue sebagai input_error hanya dengan
  alasan dan pernyataan tidak ada box fisik diambil. Tidak membuat stok/movement
  atau confirmed_at pengembalian. Issue fisik tetap memakai approval dan
  konfirmasi returned. Scope open, replay scan, indikator dan riwayat mencakup
  status baru; audit mencatat input_error_closed serta aktor. Izin delivery_issues
  existing tetap dipakai, dengan pemeriksaan role tambahan untuk penutupan ini.
- Perubahan qty_box master ekspor ditolak selama part dipakai kebutuhan positif
  delivery aktif termasuk draft; nilai sama boleh disimpan. Setelah selesai/
  dibatalkan/arsip boleh diubah, snapshot lama tetap. Update master memakai mutex
  ekspor sebelum lock part, konsisten dengan pembuatan rencana/penerimaan.
- Laporan pengembalian menghitung box/PCS yang benar-benar dikembalikan, bukan
  selisih kebutuhan yang belum terisi; revision tanpa pengembalian ditandai revised.
  Snapshot audit menampilkan pengembalian dan pelepasan reservasi secara terpisah.
- Tidak ada migration, dependency, seeder atau perubahan MySQL/production.
  Detail operasional: docs/EXPORT_BUSINESS_EXCEPTION_FIXES.md.
- Validasi: regresi penuh 468 backend (4.314 assertions) dan 87 frontend lulus;
  setelah penambahan penyajian audit, 82 tes terkait lulus (896 assertions).
  Browser preview SQLite memeriksa revisi parsial memilih box terbaru, formulir
  pembatalan 1366px/390px tanpa overflow halaman, dan formulir salah input admin
  dengan alasan/checkbox terpisah. Tidak ada error konsol; scanner/printer fisik
  dan production remote tidak diuji.

Koreksi FIFO pengurangan pallet 30 Oktober 2026:
- Pengurangan mengembalikan box terbaru menurut first_received_at menurun,
  lalu ID stok terbesar. Box tertua tetap pada permintaan untuk pengiriman FIFO.
  Tanggal pertama diterima tidak diubah ketika kembali menjadi stok cadangan.
- 15 tes penyelesaian/pengurangan lulus (144 assertions) pada SQLite in-memory,
  termasuk pallet campuran dan satu part dikurangi sampai nol. Tidak ada
  perubahan database asli atau migration.

Verifikasi AJAX Filter Laporan 30 Oktober 2026:
- Laporan lokal/ekspor dan rincian kini memakai public/js/report-browser.js.
  Lingkup/jenis/select/tanggal langsung diterapkan; teks debounce 300 ms. GET
  biasa tetap berfungsi; tombol Terapkan/Tampilkan disembunyikan saat JS aktif.
- ReportPageResponse mengirim JSON html/title/url berisi partial tanpa layout
  untuk Accept application/json; HTML biasa tetap view existing. Cache private/
  no-store dan Vary Accept. Fokus/caret/scroll dipertahankan; URL, judul,
  ringkasan serta tautan Excel sinkron. Pagination/reset dan Back/Forward AJAX.
- AbortController/versi mengabaikan respons lama sejak awal edit/debounce.
  Gagal/422 mempertahankan hasil, menampilkan Coba lagi dan menahan export/
  pagination hingga filter berhasil. Pilihan operator/status tidak hilang pada
  hasil kosong. Export pengambilan lokal kini mengikuti filter part/operator
  juga. Izin, pencatatan inventory dan dashboard tidak berubah.
- 454 tes backend lulus (4.191 assertions), 87 tes frontend lulus. Browser
  memeriksa pencarian lokal, perpindahan ekspor, 27 hasil/pagination, Back,
  satu box, reset/retry tanggal salah, Excel URL, fokus dan viewport 390px tanpa
  overflow halaman atau exception JS. Fixture hanya SQLite preview khusus;
  tidak ada migration/dependency/build baru atau perubahan MySQL/production.
  Detail: docs/WAREHOUSE_SCOPED_REPORTS.md.

Verifikasi Laporan Gabungan Lokal/Ekspor 30 Oktober 2026:
- Fitur Laporan dan Export Operasional existing memiliki pemilih scope
  domestic/export dan jenis penerimaan, pengiriman, stok aktif/cadangan,
  pemenuhan serta revisi/pengembalian. Tanpa scope tetap lokal. Halaman dan
  export penerimaan/pengambilan lokal dipertahankan, dengan pemilih tambahan.
- Default reports untuk Admin Warehouse/Supervisi; Admin utama tetap akses
  existing. Izin role tersimpan/revokasi tetap dihormati; operator ekspor tidak
  diberi akses default. Rincian report baca sendiri tanpa izin operasi ekspor.
- WarehouseReportService memakai query sama untuk tabel/ringkasan/Excel baru.
  Histori penerimaan per box termasuk arsip dan lokasi movement pertama;
  pengiriman hanya completed_at; stok hanya aktif dan posisi fisik. Pemenuhan
  mencakup arsip selesai (overview includeArchived opsional, default tetap
  aktif), terpisah dari metrik PCS lokal. Retur ekspor berasal dari snapshot
  audit reduced, tidak dihitung inbound baru. Riwayat selesai tetap terbaca.
- Filter tanggal inklusif, referensi/box/part/customer/lokasi/operator/status;
  tabel 25 baris, rincian 50. Excel seluruh filter/halaman, leading zero dan
  formula teks aman, tanggal/jumlah numerik. Route reports.records/export/detail
  dan scope=export pada URL laporan/export lama memakai izin reports existing.
- 450 tes backend akhir lulus (4.100 assertions); 31 tes terkait laporan/izin
  lulus (474 assertions). Browser preview memeriksa filter
  lingkup, histori pengiriman, satu box/Excel, rincian arsip, retur dan ponsel/
  tanpa error konsol. Query MySQL lokal delapan jenis laporan/filter diuji baca
  saja. Tidak ada mutation inventory, migration/dependency/seeder baru, fixture
  MySQL atau perubahan dashboard/grafik maupun production remote. Detail:
  docs/WAREHOUSE_SCOPED_REPORTS.md.

Verifikasi Kesiapan Pallet dan Penyelesaian Permintaan Ekspor 30 Oktober 2026:
- Detail pemenuhan memiliki Konfirmasi pallet siap setelah kebutuhan tepat.
  Pop-up rincian part/PCS/box mewajibkan lokasi semula atau lokasi ekspor kosong
  serta konfirmasi fisik. Lokasi/reservasi/komitmen dan movement ikut diperbarui
  jika seluruh pallet berpindah, tanpa mengganti tanggal FIFO.
- Pallet siap dikunci pada backend dari penempatan/penambahan dan alokasi.
  Revisi membuka ulang kesiapan untuk koreksi lokasi, atau mengurangi kebutuhan
  per part (boleh nol, termasuk pallet belum penuh). Tinjauan menentukan box terbaru
  menurut first_received_at/ID menurun; wajib tujuan pallet existing belum siap selain
  asal atau lokasi kosong serta konfirmasi pengembalian. Setelahnya reservasi
  box dilepas, stok cadangan/FIFO tetap, kebutuhan delivery dan pallet/total/
  revision disesuaikan atomik. Kesiapan pallet lain dipertahankan.
- Selesaikan permintaan hanya setelah semua pallet siap dan isi fisik valid,
  tanpa issue picking aktif. Alert/checkbox wajib menyatakan seluruh box sudah
  keluar gudang. Box/delivery diarsipkan dari daftar aktif (soft delete), pallet
  fisik diretir, lokasi/reservasi dibebaskan. Cadangan dan master tetap utuh.
- Audit export_fulfillment menyimpan snapshot seluruh kebutuhan/pallet/lokasi/
  box/PCS/FIFO sebelum selesai; filter dan rincian audit existing mendukungnya.
  Mutex/revision/token mencegah konflik/retry; audit gagal membatalkan mutasi.
  Izin export_fulfillment existing, tanpa seeder/dependency/config PC baru.
- Migration 2026_10_30_000003 diterapkan ke MySQL lokal stock setelah backup
  storage/app/backups/stock_export_fulfillment_20261030_193906_977c63.json.
  Hash kolom lama/24 tabel tetap sama, 12 box lokal/8 ekspor dan 16 reservasi
  tetap; aksi baru 0. Tidak ada fixture MySQL atau perubahan production remote.
- 438 tes backend akhir (3.969 assertions) lulus; 15 tes khusus penyelesaian
  (143 assertions) mencakup tambahan part campuran/part nol/pengembalian bulk.
  Browser SQLite khusus menguji
  lokasi kosong/semula, revisi/pengembalian FIFO, konfirmasi ulang, alert selesai,
  stok cadangan tersisa, rincian audit sesudah arsip dan viewport ponsel/tanpa
  error konsol halaman fitur. Detail implementasi
  dan deployment: docs/EXPORT_FULFILLMENT_COMPLETION.md.

Verifikasi Picking Verification Ekspor dan Approval Pengembalian 30 Oktober 2026:
- Menu baru export_picking_verification (requires export_stock_input) berisi
  rekomendasi dari source campuran. Helper hanya mencari; NG tidak membuat
  issue, sertifikat pengambilan, atau blokir. Detail: docs/EXPORT_PICKING_VERIFICATION.md.
- Tahap wajib pada penerimaan/penempatan hanya memvalidasi pengambilan stok
  rekomendasi finalisasi rencana pallet (allocation_notice_id terisi), dengan
  ID Box reservasi FIFO. Penerimaan/input baru dan alokasi penerimaan berikutnya
  tanpa notice tidak memerlukan verifikasi tambahan, termasuk pallet campuran;
  scan input dan konfirmasi penempatan tetap berlaku. Campuran ditentukan dari
  seluruh part di pallet fisik asal; flag hanya diwajibkan untuk rekomendasi
  tersebut saat reservasi/ketika source menjadi campuran. Pallet satu part tetap boleh
  menyuplai sebagian isi tanpa scan (teruji 36 dari 72 box). Bukti terikat
  source dan revision box; penempatan satuan/bulk memeriksanya pada backend.
- Input selain tepat 8 digit ASCII (termasuk 123) ditolak tanpa issue. Salah
  box delapan digit membuat ExportPickingIssue pending yang persisten, menahan
  operator/tujuan terkait dan box salah. Refresh/POST langsung tidak melepasnya.
- Scan Issues dan Approval existing kini memuat Lokal dan Ekspor,
  berlabel per baris. Approval ekspor hanya menjadi awaiting_return. Asal
  diketahui tetap asalnya; lokasi hilang wajib dipilih admin; box baru juga
  wajib part master ekspor dengan PCS default. Data lokal tidak dipakai.
  Indikator pending sidebar/dashboard Admin Warehouse juga menghitung kedua lingkup.
- Konfirmasi penempatan oleh operator pelapor menjadi returned dan
  melepas issue. Box baru dibuat pada tahap ini, dengan waktu pertama terdeteksi;
  return ke asal existing tidak menambah stok/mengubah FIFO. Tujuan/kapasitas
  berubah memerlukan pembaruan approval. Retry dilindungi token/revision,
  alokasi mengabaikan box ditahan dan revisi rencana ditolak selama issue aktif.
  Pengecualian salah input tanpa box fisik: penutupan input_error oleh admin
  dengan alasan/pernyataan wajib, tanpa pembuatan stok atau pengembalian fiktif.
- Migration 2026_10_30_000002 menambah metadata pada fulfillment dan tabel
  export_picking_issues/scans. Diterapkan ke MySQL lokal stock setelah backup
  storage/app/backups/stock_export_picking_20261030_173847_0c056a.json. Hash kolom
  existing dan jumlah data tetap, termasuk 12 box lokal/8 ekspor/8 komitmen;
  issue/scan baru 0. Tidak ada fixture MySQL atau perubahan production remote.
- 422 tes backend (3.802 assertions), 80 frontend dan build lulus. Penyempurnaan
  UI/indikator approval dilanjutkan 36 tes (494 assertions), lalu 15 tes picking
  (173 assertions) untuk urutan tenggat dan query MySQL baca saja. Browser menguji
  helper, NG format/nomor, approval gabungan, refresh, konfirmasi baru unblock,
  registrasi part/PCS/lokasi box baru serta penempatan sesudah verifikasi dan
  pengecualian satu part. Contoh hanya SQLite preview khusus.
- Koreksi lingkup scan: 17 tes picking lulus (194 assertions), termasuk input
  bulk setelah finalisasi serta alokasi berikutnya dari source campuran tanpa
  notice/meskipun flag lama terisi. Browser SQLite preview juga mengonfirmasi
  dua box input langsung berhasil ditempatkan tanpa verifikasi tambahan,
  dengan tugas rekomendasi campuran tetap tersedia dan konsol tanpa error.
  Tidak ada migration atau perubahan data MySQL untuk koreksi ini.

Verifikasi Pengelompokan Box/Pallet dan AJAX Stok Ekspor 30 Oktober 2026:
- Dropdown Pallet fisik dihapus sesuai koreksi pengguna. mode=box/pallet
  memilih daftar box (20/halaman) atau kelompok berdasarkan current_pallet_id
  (10 kelompok/halaman). Baris daftar menampilkan Pallet N/lokasi/kode
  delivery, jumlah box/PCS sesuai filter dan total box fisik bila difilter.
  Pallet 1 dari delivery berbeda tetap kelompok terpisah; box tanpa posisi
  berada dalam kelompok Belum ditempatkan, bukan pallet tujuan reservasinya.
- Pencarian mencakup box, part, lokasi fisik dan kode delivery pallet asal.
  Frasa Pallet 1/Pallet1 dicocokkan dengan posisi tepat (tidak Pallet 10);
  Pallet cadangan juga didukung. Lokasi/status digabungkan di kedua mode.
- GET stock dengan Accept JSON hanya merender partial hasil + jumlah box.
  stock-browser.mjs menerapkan select langsung dan teks setelah debounce
  250 ms, tanpa tombol Cari stok/reload. AbortController + versi respons
  mencegah hasil lama menimpa filter terbaru. Reset, pagination dan Back
  didukung; kegagalan mempertahankan daftar lama dan menawarkan Coba lagi.
- Sesuai koreksi pengguna, daftar pallet memakai tabel dengan Lihat detail;
  tidak ada expand/accordion atau box yang langsung tampil pada daftar utama.
  Klik memuat GET stock/pallets/{ID|unplaced}/boxes secara lazy ke pop-up,
  dengan identitas pallet/lokasi/delivery dan 20 box/halaman urut FIFO.
  Pagination isi tetap dalam pop-up dengan filter identik. Detail box memakai
  dialog yang sama, dilengkapi Kembali ke isi pallet (termasuk halaman semula).
  Tutup/Escape membatalkan respons tertunda dan mengembalikan fokus ke aksi
  daftar. HTML/JSON memakai private/no-store dan Vary Accept.
- ExportStockQueryService menyatukan pencarian/filter daftar, isi, dan Excel.
  Unduhan memuat semua hasil; mode pallet mengurutkan per pallet lalu FIFO.
  Kolom posisi asal/tujuan terpisah, ID teks/PCS dan tanggal numerik, header
  teal/freeze/autofilter tetap. Izin export_stock_input dan dependency existing.
- Verifikasi pengelompokan sebelumnya: 57 tes backend (705 assertions).
  Pembaruan pop-up: 31 tes backend terkait lulus (402 assertions), 18 tes
  frontend dan Vite build lulus. Browser memeriksa daftar tanpa expand,
  pop-up dua box, detail box/kembali, pagination pallet 70 box, Escape/fokus
  tanpa backdrop tersisa, desktop/ponsel dan konsol tanpa error. Fixture hanya
  SQLite preview; tidak ada migration atau perubahan data MySQL/production.

Verifikasi Tinjauan FIFO Wajib dan Tinjau Ulang 30 Oktober 2026:
- Pop-up hasil finalisasi terkini yang belum diakui hanya mempunyai satu tombol
  Oke, masukkan ke antrean (Oke bila hasil kosong). Tinjau nanti dan tombol X
  dihapus; backdrop static dan keyboard false menahan klik luar/Escape.
  Reservasi otomatis dan pengaktifan antrean melalui POST tetap seperti sebelumnya.
- Tinjau alokasi FIFO tetap tersedia setelah pengakuan. Parameter review_stock=1
  membuka hasil finalisasi terakhir sebagai tampilan baca saja, dengan kondisi
  posisi fisik dan status penempatan saat ini. Hanya tombol Tutup; tidak ada
  formulir ACK/reservasi ulang. Pengakuan tidak menghilangkan akses tinjauan.
- Halaman biasa setelah ACK hanya memuat header hasil; daftar 100 baris/paging
  dimuat ketika tinjauan dibuka. Riwayat revisi terakhir juga bisa dibaca saat
  planning sudah draft/revisi tanpa memaksa ACK atas revisi lama.
- 35 tes backend terkait lulus (424 assertions), termasuk ketiadaan penundaan,
  flag modal wajib, tinjauan setelah pemindahan fisik dan pagination baca saja.
  Browser Chromium memeriksa satu tombol, Escape/klik luar tetap terbuka, Oke
  menghasilkan satu transaksi antrean, lalu Tinjau alokasi FIFO/Tutup tanpa
  tombol aktivasi ulang atau error konsol. Contoh hanya SQLite preview.
  Tidak ada migration/dependency baru atau perubahan data MySQL pada pembaruan ini.

Verifikasi Pop-up Stok FIFO dan Pengakuan Antrean 30 Oktober 2026:
- Finalisasi tetap mengalokasikan stok ekspor sesuai part/PCS, tenggat/pallet
  dan first_received_at/ID. Reservasi baru untuk plan yang baru final menunggu
  tinjauan, dengan header export_stock_allocation_notices per plan/revision dan
  FK nullable allocation_notice_id pada komitmen. Detail: docs/EXPORT_FIFO_STOCK_REVIEW.md.
- Pop-up otomatis menampilkan box/part/PCS, tanggal pertama, posisi asal dan
  tujuan. Daftar 100 baris/halaman; refresh/pagination tidak mengakui hasil.
  Konfirmasi bersifat wajib dengan satu tombol; hasil tetap dapat ditinjau ulang
  setelah tugas diaktifkan, sesuai pembaruan di atas.
- Oke mengaktifkan antrean lewat POST pallet-plans.acknowledge-stock, izin
  export_pallet_plans existing; ACK diaudit atomik/idempotent pada mutex ekspor
  dengan pemeriksaan parent/final/revision. Redirect ke antrean hanya jika juga
  berizin export_stock_input. Box tetap di lokasi asal sampai konfirmasi fisik.
- Queue SQL dan scope queueReady mengecualikan reservasi belum diakui. Batch
  campuran memisahkan pending/waiting/placed; detail dan cetak memberi status
  Menunggu Oke FIFO. POST penempatan langsung ditolak sebelum Oke. Sesudahnya
  tugas tetap dikelompokkan menurut transaksi penerimaan asal dan FIFO.
- Hasil kosong juga ditampilkan; penerimaan baru sesudah final mengikuti alur
  existing tanpa ditahan tinjauan kosong. Reservasi lama dengan FK NULL tidak
  ditahan/backfill; guard revisi/pengembalian existing dipertahankan.
- 107 tes backend terkait lulus (1.125 assertions). Browser memeriksa finalisasi,
  tiga box tertua/dari empat, kembali/refresh sebelum Oke, belum ada antrean,
  Oke lalu satu transaksi dengan tiga tugas/dua pallet, tanpa error konsol.
  Contoh mutasi hanya SQLite preview.
- Migration 2026_10_30_000001 diterapkan pada MySQL lokal stock (APP_ENV local,
  host 127.0.0.1). Jumlah data dan isi 8 baris fulfillment existing tetap utuh;
  tabel tinjauan baru 0, reservasi lama ditahan 0; SQL MySQL antrean valid.
  Backup storage/app/backups/stock_allocation_notice_20261030_140820_3df0d6.json.
  Tidak ada contoh stok di MySQL atau perubahan production remote.

Verifikasi Cetak Rencana Final 30 Oktober 2026:
- Sesuai jawaban pengguna, rencana delivery dan rincian pallet hanya dapat
  dicetak setelah planning final yang sesuai revision delivery terbaru.
  ExportPalletPlan::isFinalFor dipakai untuk visibilitas dan guard server.
  Draft/reopen/cancel atau revision stale ditolak 409, arsip 404.
- ExportPlanPrintController menyediakan delivery-plans/{deliveryPlan}/print,
  pallet-plans/{deliveryPlan}/print dan
  pallet-plans/{deliveryPlan}/pallets/{pallet}/print. Key izin existing delivery
  dan pallet independen, pallet asing ditolak, respons private/no-store.
- Cetak delivery memakai snapshot kapasitas/PCS/box dan total. Cetak semua atau
  satu pallet memakai kartu berisi nomor/lokasi besar, customer/kode/tenggat/
  revisi, part dan jumlah box, termasuk teks peringatan 36/72. A4 portrait,
  margin 10 mm, tinggi otomatis sesuai rincian; beberapa kartu berbagi kertas.
  Thead mengulang identitas/judul pada daftar panjang. Cetak tidak memutasi data.
- View standalone export/print dan export-plan-print.css; tanpa migration,
  dependency/konfigurasi PC atau izin baru. Detail di docs/EXPORT_PLAN_PRINTING.md.
- 44 tes backend terkait lulus (509 assertions). Browser memeriksa cetak final,
  semua/satu pallet, delivery dan tidak ada tombol pada draft, tanpa error
  konsol. Lima contoh PDF QA Dompdf diperiksa seluruh halamannya, termasuk
  20 kartu dengan beberapa pallet/A4 dan satu pallet 80 part. Bukan uji printer
  fisik/mesin cetak Chrome. Semua fixture hanya SQLite preview.

Verifikasi Nama Transfer dan Peringatan Muatan Pallet 30 Oktober 2026:
- Nama fitur sekarang Penerimaan Transfer Box Ekspor pada menu/hak akses,
  dashboard, judul halaman dan tautan stok. Key export_stock_input, URL,
  identitas, izin, transaksi dan alur FIFO tidak berubah; tanpa migration/seeder.
- App\Support\ExportPalletLoad menetapkan kuning untuk 36–71 box dan merah
  mulai 72 box. Komponen export-pallet-load menyediakan threshold bagi JS
  export-stock/pallet-load.mjs. Ini peringatan, bukan hard limit/pemblokiran.
- Editor menjumlah seluruh part per pallet dan memperbarui label langsung di
  atas lokasi/part, termasuk status teks pada navigator. Hasil simpan juga
  menampilkan label. Validasi alokasi/lokasi/finalisasi existing dipertahankan.
- Penempatan cadangan menampilkan jumlah fisik saat ini + box yang akan masuk
  dalam kelompok tersebut. Berlaku pada bulk transaksi, penerimaan lama dan
  relokasi/detail box yang dimuat di modal (delegated change). Pergantian ke
  lokasi kosong/placeholder menghapus indikator pilihan lama.
- Kelompok tujuan rencana memakai agregat fisik seluruh transaksi di pallet
  tersebut + anggota pending kelompok yang belum berada di tujuan. Box yang
  sudah di sana tidak dihitung dua kali. Agregat target diambil satu query
  untuk seluruh kelompok; scan/daftar stok tanpa detail tidak memuat katalog.
- Panduan cetak menampilkan teks peringatan berdasarkan proyeksi kelompok
  saat dibuka; tetap read-only. Angka merupakan snapshot, bukan batas kapasitas
  atau reservasi baru. Tidak ada perubahan proses penerimaan/penempatan fisik.
- 38 tes backend (434 assertions), 67 tes frontend dan Vite build lulus.
  Browser Chromium memeriksa 35/36 dan 71/72, simpan draft dua part dengan
  label hasil, 34+2/70+2 pada bulk, 70+1 pada modal, dan peringatan cetak;
  tanpa error konsol. Contoh hanya pada SQLite preview; MySQL/production
  tidak diubah. Tampilan desktop diperiksa; bukan uji perangkat/printer fisik.

Verifikasi Sorotan Tujuan dan Panduan Cetak Ekspor 30 Oktober 2026:
- Halaman transaksi menonjolkan Antarkan box ke, nomor pallet dan lokasi di
  atas daftar box. Cadangan mengikuti pilihan operator secara langsung;
  belum dipilih ditandai eksplisit, bukan memakai nomor internal EX sebagai tujuan.
- Cetak tersedia per transaksi pada antrean dan detail, termasuk penerimaan
  lama tanpa batch. GET transactions/{batch}/print dan
  transactions/box/{stockBox}/print memakai izin export_stock_input existing,
  menolak draft, dan mengirim private/no-store. Tidak ada migration/izin baru.
- ExportPlacementGuideService menyusun kelompok tujuan A4 dengan kode/waktu
  transaksi, operator, box/part/PCS, posisi fisik, checklist dan penanda Sudah.
  Tujuan dan judul kolom diulang pada halaman lanjutan; terbaca hitam-putih.
- Pilihan cadangan hanya parameter cetak sementara, diverifikasi terhadap
  lokasi/pallet ekspor yang tersedia. GET tidak mengonfirmasi, mereservasi,
  mengubah stok/occupancy/audit. Tujuan kosong/stale diberi peringatan dan
  BELUM DITENTUKAN. Box selesai tetap dicetak pada lokasi fisiknya sendiri.
- Empat tes panduan (35 assertions), 64 tes frontend dan build lulus, di
  samping regresi transaksi/penerimaan sebelumnya. Browser Chromium memeriksa
  sorotan, pilihan cadangan dan halaman cetak tanpa error konsol. QA PDF A4
  memakai renderer Dompdf existing: 10 box dua pallet muat satu halaman;
  60 box dengan part panjang menjadi empat halaman, header berulang dan
  seluruh halaman diperiksa visual. Ini bukan uji printer fisik/mesin cetak
  Chrome. Fixture hanya SQLite preview; database operasional tidak diubah.

Verifikasi Antrean Per Transaksi Penerimaan Ekspor 29 Oktober 2026:
- Antrean di bawah scan memakai satu baris per batch processed yang masih
  mempunyai box menunggu. Kode TRX-000001 berbasis ID batch; menampilkan waktu,
  operator, total/sudah ditempatkan/menunggu. Pengelompokan memakai FK existing.
- ExportReceivingTransactionService mengagregasi di SQL dan menggabungkan
  penerimaan lama tanpa batch sebagai transaksi satu box (kode BOX-000001).
  FIFO mengikuti first_received_at box pending lalu ID; 20 transaksi per halaman.
  Transaksi selesai keluar antrean dan bisa kembali jika stok cadangannya
  mendapat reservasi pemindahan. Header batch tetap processed.
- Atur penempatan membuka halaman /export/receiving/transactions/{batch}.
  Anggota transaksi tampil menurut tujuan pallet/lokasi dengan konfirmasi
  bertahap per kelompok. Penerimaan lama memakai transactions/box/{stockBox}.
  Proses penerimaan dan konfirmasi kembali ke halaman transaksi; ?batch=ID
  lama diarahkan ke halaman baru. Pop-up Lihat box di Stok Ekspor tetap tersedia.
- Transaksi processed dapat dibaca/diselesaikan operator berizin lain seperti
  stok bersama. Draft/state/scan/discard/process tetap milik pembuat. Konfirmasi
  memeriksa anggota return_batch/return_box. Tugas FIFO dari stok lama tampil
  melalui transaksi asalnya, tidak dicampur ke anggota penerimaan yang baru.
- 45 tes terkait lulus (503 assertions); sesudah perapian sidebar, 11 tes
  transaksi/role lulus lagi (142 assertions). Browser menguji 10 box jadi satu
  baris, halaman dua pallet, progres 5/10 dan selesai/keluar antrean tanpa error.
  Query agregasi valid di MySQL lokal stock secara baca saja. Mutasi contoh
  hanya pada SQLite preview; tidak ada migration atau perubahan inventory
  operasional/production.

Verifikasi Label Pallet dan Pop-up Stok Ekspor 29 Oktober 2026:
- Lokasi fisik di stok/antrean/hasil penerimaan/pilihan pallet memakai
  ExportStockPallet::display_name: Pallet N dari plannedPallet.position pada
  pallet fisik saat ini, atau Pallet cadangan bila tidak terkait perencanaan.
  Lokasi menyusul label. Nomor EX-000001 tetap identifier internal; tidak ada
  migrasi/renumbering. Jangan memakai target reservasi sebagai posisi fisik
  stok lama yang belum dipindahkan. Semua relasi label dimuat eager.
- Lihat box membuka modal Bootstrap seperti pola detail stok lokal, bukan panel
  di atas tabel. GET /export/stock/{stockBox} (export.stock.show) mengirim HTML
  detail di JSON, private/no-store, memakai izin export_stock_input existing.
  Detail dimuat saat klik, menjaga filter/posisi daftar saat dibuka/ditutup;
  request lama dibatalkan/diabaikan, tersedia error/retry dan pemulihan fokus.
- Deep link ?box=ID dari antrean atau redirect penempatan membuka modal otomatis.
  Konfirmasi/relokasi tetap POST native dengan token/revision/validasi existing;
  kegagalan validasi HTML kembali ke modal box yang sama. Submit guard delegated
  melindungi form yang baru dimuat. Modal memuat ID Box, part/PCS, status,
  posisi fisik, instruksi yang relevan dan waktu penerimaan/penempatan.
- 39 tes backend terkait lulus (460 assertions), 62 tes frontend dan build lulus.
  Browser memeriksa label rencana/cadangan, dialog tanpa navigasi, pergantian box,
  Escape/fokus, filter tetap, hasil kosong dan konfirmasi lewat dialog dengan
  modal otomatis setelah redirect. Tanpa error konsol. Semua data contoh tetap
  pada SQLite preview; database operasional/production tidak diubah.

Verifikasi Batas Input ID Box Ekspor 29 Oktober 2026:
- Kolom receive-box-number memakai maxlength 8 dan filter input angka ASCII di
  resources/js/export-stock-receiving.js. Nol di awal dan posisi kursor dijaga;
  validasi server tetap tepat delapan digit. Build dan pemeriksaan sintaks lulus.
  Browser memverifikasi huruf/simbol dibuang, digit kesembilan ditolak, campuran
  input difilter, serta 00123456 tetap utuh dan bisa lanjut ke part. Tanpa error
  konsol. Pengujian memakai SQLite preview tanpa menambah penerimaan stok.

Verifikasi Pemindahan Antrean Penempatan Ekspor 29 Oktober 2026:
- Tabel Antrean penempatan / pemindahan dipindah dari /export/stock ke bawah
  area scan/daftar scan pada /export/receiving. Partial baru:
  resources/views/export/receiving/pending-queue.blade.php. Tabel juga tersedia
  di bawah hasil batch. Urutan first_received_at lalu ID dan 20 baris per halaman
  dipertahankan; pagination mengarah ke anchor tabel pada halaman penerimaan.
- ExportStockViewService::pendingQuery berbagi predicate box received atau
  reservasi belum ditempatkan. Stok Ekspor tetap memuat counter menunggu, daftar,
  pencarian/filter dan detail/relokasi box; tidak memuat tabel antrean lagi.
  Tombol Atur penempatan tetap membuka detail box pada halaman stok.
- 35 tes terkait penerimaan, batch, performa dan role lulus (431 assertions).
  Browser memeriksa tabel berisi di bawah area input, urutan FIFO, tautan ke
  detail penempatan dan ketiadaan antrean di stok; tanpa error konsol. Preview
  memakai SQLite terpisah. Tidak ada migration atau perubahan DB operasional.

Verifikasi Performa Navigasi 29 Oktober 2026:
- Detail pengukuran dan deployment: docs/PERFORMANCE_NAVIGATION.md. Bootstrap,
  ikon, SweetAlert, kedua jQuery existing dan Select2 kini memakai public/vendor
  versi tetap/lisensi/checksum; sertakan folder ini saat deploy. Dependency grafik
  laporan operasional masih terpisah. Tidak ada dependency runtime/PC baru.
- CSS kerangka dan JS sidebar/pencarian/toast dipindah dari inline Blade ke
  public/css/layout-shell.css serta public/js/layout-shell.js dan toast.js.
  Partial lama hanya include aset dengan v=filemtime. Data menu sesuai izin
  tetap per request; urutan script dan fungsi bisnis/hak akses dipertahankan.
- Query katalog penempatan hanya saat dibutuhkan cadangan; total antrean memakai
  paginator; daftar stock tidak memuat hierarki delivery yang hanya dipakai detail.
  HTML preview turun 65–70%; query penerimaan 6 ke 3, stok 15 ke 9. Ini bukan
  klaim persentase durasi production; boot PHP lokal masih berpengaruh.
- Regresi 368 backend (3.151 assertions), 57 frontend dan build lulus. Browser
  memeriksa navigasi ekspor, pencarian, dialog, scan/proses, pilihan dan toast
  tanpa error. Optimize Laravel diuji hanya pada cache preview terisolasi;
  cache permanen development, database operasional dan production tidak diubah.

Verifikasi Penerimaan Bulk dan Stok Ekspor terpisah 29 Oktober 2026:
- Menggantikan UI penerimaan satu box pada catatan sebelumnya. Scan box lalu
  part menambah draft, maksimal 1.000 box. OK/NG visual dan audio memakai helper
  Stock Input existing. PCS dari master ekspor; draft belum menjadi stok atau
  reservasi. Daftar tersimpan di DB dan tetap ada setelah refresh.
- ExportReceivingBatchService / export_receiving_batches dan items menangani
  scan, hapus baris, batal draft, token retry, revision/tab lama, serta kepemilikan
  operator. Model memberi default status/revision pada respons draft pertama.
- Proses penerimaan memvalidasi seluruh baris dan kapasitas ulang dalam satu
  transaksi, membuat stock boxes, lalu alokasi FIFO sekali. Pada saat rilis ini,
  waktu scan server menjadi first_received_at; aturan terbaru ada di awal memo.
  Hasil berkelompok per pallet/lokasi dan cadangan;
  tugas stok lama didahulukan. Konfirmasi kelompok atomik, cadangan satu lokasi
  memakai satu pallet fisik. Pemenuhan tetap menunggu konfirmasi fisik.
- /export/stock (ExportStockController) memisahkan daftar/filter/antrean/detail
  dan relokasi stok dari /export/receiving. Keduanya memakai izin existing
  export_stock_input; tidak ada permission baru. Legacy single-receipt API
  dipertahankan. Detail di docs/EXPORT_STOCK_RECEIVING.md.
- Migration 2026_10_29_000003 hanya menambah dua tabel draft setelah 000002.
  Diterapkan pada MySQL lokal stock; tidak menambahkan data uji. Jumlah data
  existing dipertahankan: 12 boxes/identities, 16 pallets, 3 stock_locations,
  276 part/27 lokasi, 2 delivery/planning dan 2 stock box/komitmen ekspor.
  Kedua tabel draft baru kosong; production remote tidak diubah.
- Regresi 366 backend (3.092 assertions), 57 frontend dan build lulus; setelah
  perbaikan respons draft pertama, 13 tes batch (133 assertions) lulus kembali.
  Browser Chromium desktop memeriksa lima scan, OK/NG, refresh, tidak ada stok
  sebelum proses, dua pallet + cadangan, konfirmasi grup, halaman stok terpisah
  dan daftar baru. Tanpa error konsol; bukan uji scanner fisik. Semua data uji
  browser berada pada SQLite preview, bukan MySQL operasional.

Verifikasi Penerimaan Box Ekspor 29 Oktober 2026:
- Rincian: docs/EXPORT_STOCK_RECEIVING.md. Izin export_stock_input, menu
  /export/receiving. Sesuai jawaban pengguna: scan ID Box lalu nomor part,
  PCS otomatis dari master ekspor; nomor delapan digit boleh sama dengan lokal.
- Penerimaan mencatat export_stock_boxes sebagai received dan melakukan alokasi
  otomatis, tanpa menulis boxes/box_identities lokal. Konfirmasi penempatan
  menghasilkan export_stock_pallets, export_stock_movements, status stored,
  occupancy lokasi ekspor dan placed_at pada komitmen. Reservasi belum terpenuhi.
- Finalisasi planning dan penerimaan memeriksa stock tersedia. Kebutuhan diurut
  tenggat/ID delivery lalu posisi pallet; stok memakai first_received_at lalu ID.
  Reservasi existing dipertahankan. Stok lama dapat mendapat tugas pemindahan,
  sedangkan box baru menjadi cadangan. Snapshot PCS harus persis cocok.
- Cadangan dapat memilih lokasi ekspor kosong atau pallet fisik ekspor existing,
  boleh part campuran; relokasi cadangan tidak mengubah tanggal FIFO. Sumber
  dibebaskan setelah box terakhir berpindah. Pallet rencana bernomor 1..N berbeda
  dari pallet fisik berlabel EX-000001. Jangan menulis ID fisik ekspor ke kolom
  current_pallet_id master yang merupakan FK pallet lokal.
- Semua mutasi inventory/planning/delivery ekspor mengunci row mutex ekspor
  sebelum lock lain agar FIFO/finalisasi/receipt tidak berlomba. Token unik
  mencegah double submit; revision dan tujuan server menolak instruksi stale.
  Mutasi dan audit satu transaksi. Guard master mencakup histori stok ekspor.
- Pengembalian/perubahan kebutuhan dengan komitmen tersedia melalui Pemenuhan
  Permintaan (lihat perbaikan 30 Oktober di atas). Guard revisi umum tetap
  melindungi komitmen dan stok fisik. Alih lingkup tetap belum diterapkan.
- Migration 2026_10_29_000002 membuat empat tabel dan FK komitmen nullable serta
  memperbarui izin saved role. Existing monitoring tidak dibackfill menjadi
  stok fiktif. Regresi 353 backend (2.954 assertions), 55 frontend, dan build
  lulus; browser Chromium menguji dua scan, pemenuhan, FIFO, cadangan, duplikat,
  desktop dan simulasi 390px tanpa error. Bukan uji scanner/perangkat fisik.
- MySQL lokal stock sudah teramati memiliki migration/izin/query yang sesuai,
  dengan 0 stock ekspor dan 0 pending. Verifikasi lokal baca saja; agent tidak
  menjalankan ulang migration/menambahkan contoh ke MySQL. Transaksi contoh
  hanya pada SQLite preview, tidak mengubah inventory lokal/production remote.

Verifikasi Pemenuhan Permintaan Ekspor 29 Oktober 2026:
- Detail implementasi/tahap berikutnya ada di docs/EXPORT_FULFILLMENT.md. Menu
  /export/fulfillment memakai izin export_fulfillment, bawaan Operator Export,
  independen dari hak mengubah perencanaan. Halaman daftar dan detail bersifat
  pemantauan; belum ada input/scan, penerimaan inventory ekspor, FIFO alokasi
  otomatis, atau alur pengembalian. Jangan menghitung stok lokal di sini.
- Finalisasi mengisi entered_fulfillment pada export_pallet_plans. Rencana yang
  pernah final tetap muncul saat revisi, meski seluruh lokasi sudah dilepas.
  Draft awal tidak ditampilkan; cancelled/arsip dikeluarkan dari daftar aktif.
  Migration 2026_10_29_000001 melakukan backfill penanda dari final/klaim/audit.
- export_fulfillment_boxes menyimpan komitmen aktif unik per ID Box beserta
  snapshot penerimaan pertama, part/PCS, aktor/waktu reservasi dan konfirmasi
  penempatan/lokasi. Ini fondasi pencatatan, bukan penerimaan inventory fisik.
  Belum ada endpoint write. Catatan baru tidak dibuat oleh GET/finalisasi.
- Terpenuhi hanya dari konfirmasi dengan part/PCS sesuai item dan lokasi tujuan
  benar. Reservasi belum ditempatkan dipisah. Catatan tidak sesuai dan kelebihan
  ditandai; kekurangan/progres dihitung per item agar kelebihan part lain tidak
  menutupi kebutuhan. Daftar box urut penerimaan pertama lalu ID Box.
- Selama ada komitmen, perubahan delivery/pembagian, buka revisi, dan pembatalan
  melalui editor umum ditolak agar penempatan tidak hilang. Revisi/pembatalan
  dengan pengembalian memakai jalur pemenuhan atomik yang dijelaskan di atas.
  Transaksi delivery rollback seluruh perubahan jika guard gagal.
- Regresi 338 tes backend lulus (2.806 assertions); pemeriksaan terakhir 13 tes
  pemenuhan (110 assertions) lulus. Browser Chromium: desktop 1440x900, simulasi
  390x844, pencarian/status/reset/hasil kosong, rincian keyboard, tanpa error.
- MySQL lokal stock diperiksa baca saja: migration baru sudah tersedia, query
  agregasi dan izin operator valid, 0 komitmen dan 0 permintaan dipantau. Agent
  tidak menjalankan ulang migration/menambahkan data uji di MySQL lokal. Semua
  contoh nonnol memakai SQLite preview; production remote tidak diubah.

Verifikasi UI Perencanaan Pallet 29 Oktober 2026 pada branch Export-plan:
- Editor desktop memakai dua panel: kebutuhan part yang dapat dicari/difilter
  di kiri, satu pallet aktif dengan navigator di kanan. Klik part menambah atau
  memfokuskan baris pada pallet aktif; tidak menggandakan part yang sudah ada.
- Kebutuhan, total alokasi, dan sisa tampil di dekat jumlah box. Alokasikan sisa
  mengganti jumlah pada baris tersebut dengan kebutuhan yang tersedia setelah
  memperhitungkan alokasi pada baris/pallet lain; tidak mengambil alokasi lain.
- Semua pallet tetap dikirim dalam pallets_json meskipun hanya satu ditampilkan.
  Tombol simpan/finalisasi tetap terlihat; layar sempit memakai panel bertumpuk.
  Route, payload, otorisasi, draft/final, revisi, dan reservasi tidak berubah.
- Build dan 55 tes frontend lulus; 32 tes delivery/perencanaan lulus (323
  assertions). Browser Chromium memeriksa 59 part/20 pallet, simpan-buka ulang,
  navigasi, pencarian/filter/keyboard, koreksi kelebihan, lokasi duplikat, dan
  finalisasi. Tampilan diperiksa pada 1440x900, 1366x768, dan simulasi 390x844.
  Preview menggunakan SQLite terpisah; tidak ada perubahan database operasional
  atau migration tambahan pada perubahan UI ini.

Verifikasi database lokal 29 Oktober 2026 (import master ekspor): atas permintaan
pengguna, 275 part dengan Qty/Bx valid dari Test 1.xlsx ditambahkan ke MySQL
127.0.0.1:3306, database stock (APP_ENV local). A1-A20 sudah ada sebagai lokasi
ekspor sehingga tidak ditambahkan ulang. Seluruh data master existing, termasuk
lokal, kapasitas, ID dan occupancy lokasi, diverifikasi tetap utuh. Import
memakai transaksi dan menambahkan 275 audit master_part bertipe created dengan
aktor sistem. Tiga part G kosong belum diimpor: 7105-0477, 7210-4285, BR-139.
Backup sebelum import dan receipt tersimpan di storage/app/backups dengan prefix
export_master_import_2026-10-29_082342_a7d2aa. Migration ekspor 000001-000006
teramati sudah diterapkan pada database lokal stock sebelum import; agent tidak
menjalankan migration dalam langkah ini. Ini bukan perubahan database production
remote. Catatan SQLite preview sebelumnya tetap merupakan histori pengujian.

Verifikasi tambahan 28 Oktober 2026 (Perencanaan Pallet Ekspor):
- Implementasi dan keputusan tahap lanjutan ada di docs/EXPORT_PALLET_PLANNING.md.
  Menu /export/pallet-plans memakai izin export_pallet_plans, bawaan Operator
  Export. Migration 000006 menambah empat tabel dan memperbarui izin tersimpan
  beserta audit. Belum dimigrasikan pada database operasional/production.
- Satu perencanaan per delivery, nomor pallet 1..N, part campuran, pembagian
  box manual. Draft boleh parsial; finalisasi mensyaratkan alokasi tepat untuk
  semua part serta lokasi ekspor berbeda dan tersedia pada setiap pallet.
- Finalisasi oleh Operator Export mereservasi lokasi melalui tabel
  export_pallet_location_reservations dengan master_location_id sebagai primary
  key. Reservasi berbeda dari occupancy fisik. Buka revisi mempertahankan klaim;
  simpan draft melepas lokasi yang dikeluarkan, finalisasi mengklaim lokasi baru.
  Pembatalan mempertahankan baris sebagai riwayat dan melepas semua reservasi.
- Revisi delivery mengembalikan perencanaan aktif ke draft sambil mempertahankan
  baris, ID, dan lokasi yang sudah direservasi. Kekurangan/kelebihan dan part yang
  dihapus harus dikoreksi operator sebelum finalisasi ulang. Revision mencegah
  penimpaan bersamaan; transaksi mengunci delivery, planning, dan lokasi.
- Master lokasi ekspor menampilkan status Direservasi serta menyaringnya dari
  pencarian lokasi tersedia. Identitas part/lokasi yang direferensikan dilindungi.
  Arsip delivery aktif mensyaratkan pembatalan perencanaan terlebih dahulu.
- Input box, stok ekspor/cadangan, reservasi ID Box, FIFO dan pengembalian
  fisik merupakan tahap Pemenuhan Permintaan berikutnya. Jangan mengambil stok
  lokal otomatis. Keputusan pengguna: FIFO tanggal penerimaan pertama,
  prioritas tenggat lalu pallet terkecil; saat kebutuhan turun, box terbaru
  dikembalikan ke lokasi/pallet pilihan dengan tanggal FIFO asal dipertahankan.
- Verifikasi: regresi 324 tes backend (2.685 assertions) lulus, dilanjutkan 32 tes
  delivery/planning (323 assertions) setelah perbaikan terakhir. Seluruh 53 tes
  frontend dan build lulus. Browser memverifikasi draft/final, part campuran,
  lokasi A1/A2, alokasi berlebih, revisi delivery dan finalisasi ulang. Semua data
  uji memakai SQLite terpisah; produksi tidak diubah.

Integrasi 28 Oktober 2026 pada checkout utama branch Export-plan: fitur master
dan Rencana Delivery Ekspor digabungkan dengan alur QA/packing terbaru. Handle
tetap memulai penyerahan QA; validasi lokasi penerimaan packing tunggal, massal,
dan per transaksi hanya menerima scope domestic. Seluruh regresi gabungan lulus
304 tes backend (2.493 assertions) memakai SQLite in-memory serta 51 tes frontend;
npm ci dan build aset berhasil pada checkout utama. Jalur Unicode Windows diuji
melalui alias drive sementara ke checkout yang sama. Tidak ada migration pada
database kerja/production dalam integrasi ini. Sesuai instruksi pengguna, pekerjaan
selanjutnya dilakukan langsung pada branch aktif tanpa branch/worktree baru.

Verifikasi tambahan 28 Oktober 2026 (Rencana Delivery Ekspor):
- Format contoh dan keputusan pemetaan dicatat di
  docs/EXPORT_DELIVERY_IMPORT_NOTES.md. Impor memakai kolom QTY(PCS) (C pada
  contoh), mengabaikan jumlah kosong/nol dengan peringatan terpisah untuk kedua
  jumlah baris. Qty2(Pcs), footer box, serta total pallet tidak menjadi input.
- Kode dokumen (contoh RDYR0925) menjadi plan_code, terbaca dari header Excel
  atau diisi manual, dapat dikoreksi di form dan dicari dari daftar/detail. Migration
  000005 menambahkan kolom nullable dan index, mempertahankan data lama dengan
  kode null. Kode belum dibatasi unik; relasi tetap memakai ID internal rencana.
  Template Excel menyediakan label Kode Rencana Delivery. Kode masuk audit dan
  perubahan field mewajibkan pemeriksaan ulang hasil impor sebelum simpan.
- Menu /export/delivery-plans memakai izin export_delivery_plans, bawaan
  Operator Export dan dapat diatur Admin Utama. Migration 000004 membuat tabel
  export_delivery_plans/items dan menambah izin ke pengaturan role existing
  beserta audit sistem. Produksi belum dimigrasikan oleh agent.
- Input kode rencana (opsional untuk kompatibilitas data lama), customer,
  batas tanggal pengiriman, nomor part dan jumlah PCS.
  Kapasitas diambil hanya dari master ekspor; box dan total dihitung ulang di
  server. Part duplikat tetap ditolak. Sejak 31 Oktober 2026 jumlah tidak wajib
  kelipatan: sisa PCS menjadi kebutuhan Not Full melalui request/approval ekspor
  sebagaimana catatan di awal memo. Tidak ada input jumlah pallet.
- Rencana delivery disimpan sebagai draft, bisa diubah dan diarsipkan (soft
  delete). Perencanaan pallet tersedia sebagai modul tersendiri; finalisasi dan
  reservasi lokasi dijelaskan di atas. Belum ada mutasi inventory fisik.
- Setiap item menyimpan snapshot nomor part dan kapasitas. Mengubah kapasitas
  master tidak mengubah rencana lama; item yang baru ditambahkan mengambil
  kapasitas master terkini. Identitas part yang sudah direferensikan rencana,
  termasuk yang diarsipkan, tidak boleh diganti/dihapus dari master.
  Simpan memakai transaksi, lock master/header, revision untuk mencegah
  penimpaan edit bersamaan, serta audit sebelum/sesudah.
- Impor Excel .xlsx/.xls maksimal 5 MB, 500 part, satu sheet berisi satu
  customer. Template dapat diunduh; header dua tingkat pada layout contoh
  didukung. Preview tidak membuat data. Formula tidak dieksekusi; hasil cache
  Excel dipakai jika tersedia, selain itu operator harus mengoreksi nilainya.
- Pembacaan foto dihapus pada 28 Oktober 2026 atas permintaan pengguna. Form
  hanya menyediakan input manual dan Excel. Kode OCR, pemrosesan gambar,
  dependency Tesseract/OpenCV/model bahasa, serta aset vendor/ocr dihapus.
  Build memakai Vite langsung dan hanya public/build yang perlu disertakan.
  Sumber photo tidak diterima untuk rencana baru atau rencana manual/Excel.
  Rencana historis bersumber photo tetap bisa dilihat/diedit dengan provenance
  yang sama; label sumber menunjukkan foto (riwayat), tanpa opsi deteksi foto.
  Verifikasi penghapusan: 18 tes Rencana Delivery (184 assertions), 49 tes
  frontend dan build lulus. Browser memastikan hanya input manual/Excel,
  tanpa error konsol; penghapusan tidak memerlukan migration tambahan dan
  tidak mengubah database operasional.
- Verifikasi tahap awal sebelum penghapusan foto: 274 tes backend
  (2.212 assertions) dan 51 tes frontend lulus.
  Browser Chromium terpisah berhasil membaca 9 baris foto contoh, customer,
  tanggal, total 732 box, lalu menyimpan draft; impor Excel dan penolakan
  jumlah tidak kelipatan juga diverifikasi. Chrome desktop adalah target,
  tetapi koneksi Chrome aktual tidak tersedia pada sesi verifikasi.
  Seluruh data UI uji memakai SQLite preview terpisah; production tidak diubah.
- Verifikasi pembaruan kode/pemetaan (28 Oktober 2026): 309 tes backend
  (2.534 assertions), 52 tes frontend, serta build lulus pada Export-plan.
  Browser memverifikasi file contoh menjadi 59 part, peringatan 219 jumlah
  kosong/0 jumlah nol, kode RDYR0925 dan 662 box menggunakan kapasitas master
  preview dari contoh. Pembatalan impor memulihkan kode/peringatan; simpan dan
  pencarian kode berhasil. Migration 000005 hanya diuji pada SQLite test/preview,
  belum diterapkan pada database operasional/production.



Verifikasi tambahan 28 Oktober 2026 (Master ekspor): tabel `part_settings` dan
`master_locations` mempunyai `scope=domestic/export`. Migration
`2026_10_28_000002_add_scope_to_master_data` memberi default domestic pada data
existing tanpa mengganti ID, kapasitas, occupancy, atau foreign key; unique
menjadi (scope, part_number) dan (scope, code). Kapasitas part yang sama dapat
berbeda lintas lingkup, dan kode lokasi dapat dipakai di area fisik yang terpisah.
Master ekspor dimulai kosong; migration/seeder tidak menyalin master lokal.

Model PartSetting/MasterLocation memakai scope lokal secara otomatis;
ExportPartSetting/ExportMasterLocation memakai scope ekspor pada tabel yang sama.
Lingkup tetap ditentukan oleh model dan nama route, bukan role/login atau input
request. Endpoint ekspor terpisah di /export/part-settings dan /export/locations;
CRUD, pencarian, validasi unik dan akses ID semuanya dibatasi sesuai lingkup.
API/validasi lokasi pada transaksi lokal dan LocationAssignmentService tetap
hanya menerima master lokal. Master ekspor belum terhubung ke inventory fisik.
Rencana Delivery dan Perencanaan Pallet Ekspor memakai master ekspor. Pemeriksaan
referensi mencakup kedua modul, termasuk rencana yang diarsipkan/dibatalkan.

Izin baru `export_parts` dan `export_locations` merupakan bawaan Operator Export.
Admin Utama dapat mengakses kedua lingkup melalui menu terpisah. Migration
`2026_10_28_000003_enable_export_master_features` menambahkan dua izin ini ke
pengaturan Operator Export yang sudah tersimpan, mempertahankan izin lain, dan
mencatat audit sistem sekali. Setelah deployment, Admin Utama tetap dapat
mencabut/mengubahnya melalui Hak Akses seperti biasa. Sebelum membuka fitur,
jalankan seluruh migration baru; production belum dimigrasikan oleh agent.

Verifikasi master ekspor pada 28 Oktober 2026: seluruh suite lulus 263 tes
(2.079 assertions) menggunakan SQLite sementara, termasuk migrasi skema legacy,
isolasi ID/API, kapasitas part lintas lingkup, penolakan assignment lokasi ekspor
pada transaksi lokal, dan pengaturan ulang hak akses. Browser diperiksa pada
viewport desktop 1440x900 dan ponsel 390x844, kondisi kosong/berisi, pencarian dan
navigasi keyboard. Database preview terpisah dipakai untuk data uji antarmuka;
database operasional/production tidak diubah. Cabang MySQL migration belum
dijalankan pada database production.

Verifikasi tambahan 28 Oktober 2026 (Operator Export): role `export_operator`
tersedia pada tambah/edit/filter user dan pengaturan Hak Akses. Akses fitur
saat ini mencakup Rencana Delivery, Perencanaan Pallet dan dua master ekspor; login, profil, logout,
dan dashboard ekspor tetap tersedia. Dashboard role ini tidak memuat statistik atau transaksi lokal.
Migration `2026_10_28_000001_add_export_operator_role_to_users` memperluas enum
`users.role` tanpa mengubah akun atau inventory existing; wajib dijalankan saat
deployment sebelum membuat akun Operator Export. Alur ekspor dan alih peruntukan
stok belum diimplementasikan. Admin Utama tetap dapat mengatur izin role ini
melalui mekanisme Hak Akses yang sama dengan role lain.

Seeder akun `ExportOperatorSeeder` (28 Oktober 2026) dipanggil oleh `UserSeeder`
dan dapat dijalankan sendiri melalui `php artisan db:seed --class=ExportOperatorSeeder`
setelah migration role. Akun awal: `export@warehouse.local`, sandi `password`,
role `export_operator`, aktif. Menggunakan `firstOrCreate` berdasarkan email;
seeding ulang tidak mengubah akun existing, sandi, status aktif, maupun hak akses.
Seeder ini tidak membuat stok atau transaksi ekspor.


Verifikasi tambahan 21 Oktober 2026 (Audit Trail): Handle box expired kini membuat
`audit_logs` bertipe `expired_box` dalam transaksi yang sama dengan aktivasi dan
`expired_box_reports`; request berulang saat box masih aktif tidak membuat entri baru.
Pengajuan/keputusan Box Not Full, perubahan sesi picking (mulai, verifikasi sukses,
scan final sukses, batal/timeout/selesai, terminate lock), pembuatan dan approval
issue scan, withdrawal langsung, CRUD master lokasi/part dan user, serta pembaruan profil kini
memiliki entri audit tersendiri. Jalur baru memakai `AuditService::record` yang
mewajibkan audit tersimpan dalam transaksi mutasi; sandi tidak masuk snapshot.
CRUD/status PPC/split/restore/hapus Delivery Order tetap dikecualikan atas permintaan
pengguna. Jalur audit lama yang memakai `AuditService::log` masih best-effort dan
menelan kegagalan tulis; riwayat lama tidak di-backfill.

Verifikasi tambahan 20 Oktober 2026 (mutasi pallet saat delivery): box assigned delivery
atau not-full tetap boleh ikut Move/Merge jika sesi pick baru `pending` atau tidak ada.
Box mana pun yang masih terhubung ke pallet dan berada dalam sesi `scanning`, `blocked`,
atau `approved` mengunci Move/Merge dan penambahan box melalui Stock Input, Delivery
Assign (box existing maupun baru), approval Box Not Full, koreksi/hapus Stock View,
atau Handle expired; termasuk box expired/withdrawn yang masih terhubung.
Merge mengalihkan request Box Not Full `pending` ke pallet hasil. Assignment dan PCS box
tidak berubah. Validasi mutasi diulang dalam transaksi; Move tetap mengklaim lokasi atomik.

Verifikasi tambahan 20 Oktober 2026 (Delivery Assign): UI menyimpan draft pilihan
per tab untuk refresh dan menampilkan pratinjau box sebelum assign. Pallet baru
dari UI mendapat nomor melalui `PalletNumberService`; payload nomor manual lama
tetap didukung. Lokasi harus berasal dari Master Location dan diklaim atomik
melalui `LocationAssignmentService` saat transaksi assign.

Verifikasi tambahan 20 Oktober 2026 (perbedaan tahap scan): pada Picking Verification,
kode salah, kosong, tidak ada di picklist, atau box withdrawn/expired ditolak tanpa
menambah progres, issue, atau memblokir sesi; operator dapat langsung scan ulang.
Pada Scan Final Delivery, setiap scan salah tetap membuat issue `pending` dan
memblokir sesi sampai approval Admin Warehouse/Admin. Kode kosong dicatat
`(kosong)`; kode issue panjang disimpan maksimal 255 karakter.
Masa Handle yang habis ditolak pada kedua tahap meskipun label expiry belum tersinkron;
respons scan menyebut masa Handle habis dan langkah operator. Verifikasi tetap aktif,
sedangkan scan final membuat issue `box_expired` dan memblokir sesi (tes diperbarui 20 Oktober 2026).

Verifikasi tambahan 20 Oktober 2026 (Split Delivery): MySQL lokal `delivery_orders.status`
sebelumnya tidak memuat `partial` meski migrasi dasar dan alur Split memakainya.
Migration `2026_10_20_000001_add_partial_to_existing_delivery_order_status` telah
diterapkan pada `db_stock` lokal; aturan Split/Restore dan data order tidak diubah.

Verifikasi tambahan 20 Oktober 2026 (konsistensi penghapusan Split): hasil split
tidak boleh dihapus melalui Hapus Jadwal; gunakan Restore Split. Parent dengan
child split aktif juga tidak boleh dihapus sebelum semua child dipulihkan.

Verifikasi tambahan 16 Oktober 2026 (presentasi dashboard): role `admin` (Admin Utama)
dan `supervisi` mulai menggunakan dashboard laporan operasional yang sama.
Pada 31 Oktober 2026 jalur dashboard diganti dengan OperationalDashboardService
dan shared.operational-dashboard untuk lingkup lokal/ekspor; lihat catatan
di awal memo. OperationalReportService/operator.reports.operational tetap untuk
laporan operasional legacy. Statistik stok serta sinkronisasi expiry pada load
HTML Admin Utama tetap dijalankan; hak akses dan proses transaksi tidak berubah.

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
- Login ditolak pada email/password menampilkan getar horizontal panel satu kali
  selama 240 ms (18 November 2026). Hanya transform, tidak menahan input; reduced
  motion memakai pesan error statis. Halaman awal/login sukses tidak bergetar.
  CSS login memakai versi file untuk pembaruan cache. Tidak mengubah autentikasi.
  Sebelas tes auth/role/HTTPS terkait lulus (245 assertions); browser SQLite
  fiktif memeriksa password/email salah, Enter, login sukses, normal/reduced
  motion dan viewport 390 px, tanpa error JavaScript atau overflow halaman.
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
3. Operator scan ID Box delapan digit.
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

- ID Box harus delapan angka.
- Sejak 15 November, ID Box dapat dipakai ulang setelah penggunaan sebelumnya berakhir, termasuk soft-delete. Penerimaan baru memakai PK dan nomor penggunaan berbeda.
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

1. Requester mengisi ID Box, nomor part, PCS aktual, alasan, delivery, dan tujuan.
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
- by ID Box;
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

Picking Verification menolak kode salah tanpa issue atau blokir sesi, termasuk
box withdrawn/expired; box tersebut tidak dihitung sebagai terverifikasi.

Scan Final Delivery membuat issue ketika kode yang dikirim:

- kosong, tidak ditemukan, atau tidak ada dalam picklist (`scan_mismatch`),
  tanpa syarat panjang atau format ID;
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
- Hapus Jadwal menolak child hasil split serta parent yang masih memiliki child aktif;
  child hanya dapat dibatalkan melalui Restore Split agar quantity kembali ke parent.

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

Redo hanya berlaku satu kali untuk session `completed` dengan `completion_status=completed` dan `redo_until` yang belum lewat. Untuk shared box, seluruh box yang direstore dipindahkan ke pallet konsolidasi baru; pallet asal yang masih memiliki box tersimpan, termasuk expired, tetap mempertahankan lokasi. Ringkasan kuantitas aktif tetap mengikuti eligibility; hanya pallet yang tidak lagi menyimpan box fisik dilepas dari lokasi dan stock location-nya. Session assignment pending yang tersisa dibatalkan saat redo.

### 15. Merge palet

Warehouse Operator, Admin Warehouse, dan Admin dapat menggabungkan minimal dua palet.

Alur:

- hanya palet dengan box aktif yang dapat dipilih;
- sistem membuat nomor palet baru;
- seluruh box aktif dipindahkan ke palet baru;
- box assigned delivery, not-full, dan sesi picking `pending` boleh ikut; merge ditolak jika
  box yang terhubung ke pallet berada dalam sesi `scanning`, `blocked`, atau `approved`;
- ringkasan `pallet_items` digabung;
- referensi transaksi stock input diarahkan ke palet baru;
- request Box Not Full `pending` yang menunjuk palet sumber diarahkan ke palet baru;
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
- pallet wajib memiliki box aktif dan lokasi master yang valid; box assigned delivery
  atau not-full tetap boleh ikut dipindahkan;
- sesi picking `pending` boleh ada; box terhubung dalam sesi `scanning`, `blocked`, atau
  `approved` menolak pemindahan, termasuk jika box sudah tidak terhitung sebagai stok aktif;
- lokasi tujuan wajib berasal dari Master Location dan tersedia;
- backend mengunci pallet serta lokasi sumber/tujuan dalam transaksi;
- occupancy lokasi sumber dilepas, lokasi tujuan diklaim atomik, dan `stock_locations` diperbarui;
- kegagalan validasi atau konflik lokasi membatalkan seluruh transaksi;
- pemindahan dicatat sebagai audit type `pallet_location_moved`.

File utama:

- `app/Http/Controllers/MovePalletController.php`
- `resources/views/operator/move-pallet/index.blade.php`

### 17. Expired box, QA, dan penggantian ID melalui packing (23 Oktober 2026)

Catatan historis: aturan penerimaan wajib sekaligus, pasangan label fisik,
pengantar packing, transaksi campuran dan deadline sejak penerbitan di bawah
digantikan revisi 5 November 2026 di awal memo. Riwayat lama tetap dipertahankan.

Implementasi branch `codex/expired-qa-packing`; migration belum diterapkan pada database kerja saat pengembangan. QA dan packing tetap di luar sistem. Hak akses seluruh endpoint mengikuti fitur `expired_box` (default Supervisi/Admin, dapat diatur Admin Utama).

Umur dan FIFO menggunakan `boxes.created_at` sebagai tanggal masuk asli. Box pengganti mewarisi tanggal tersebut; penerimaan hasil packing dan penerbitan ID dicatat terpisah. Umur normal: active <9 bulan, warning 9–<12 bulan, expired >=12 bulan. Warning tetap eligible FIFO dan tidak dapat di-Handle.

Alur:

1. Handle pada expired berarti barang sudah diserahkan ke QA. Dibuat `expired_box_handlings` dengan referensi HND, snapshot identitas/tanggal/asal, petugas, dan status `at_qa`. Box lama menjadi `expired_status=handled` dan tetap nonaktif; tidak mendapat tambahan masa aktif. Setelah ringkasan disinkronkan, relasi pallet dilepas karena fisik sudah ke QA; identitas pallet dan mapping asal disimpan dalam snapshot agar operasi pallet berikutnya tidak mengubah box dalam penanganan.
2. Petugas mencatat `passed` atau `rejected`. Lolos sekaligus mengonfirmasi barang kembali ke warehouse (`awaiting_packing`). Penolakan wajib alasan dan konfirmasi; box di-force-delete. Snapshot histori, mapping input/picking, withdrawal, issue, request not-full, expiry report, pallet, serta audit disimpan dalam penanganan sebelum cascade/null FK. Header stock input/withdrawal/order tidak dihapus. Snapshot lengkap dapat diunduh oleh pengguna berizin.
3. Setelah lolos QA, tersedia surat pengantar cetak yang memuat nomor penanganan, ID lama, part, PCS, dan kolom ID baru/waktu penerbitan. Dokumen mengikuti box secara fisik. Warehouse mencatat penyerahan packing (`at_packing`). Packing tidak memerlukan akun atau akses aplikasi.
4. Penerimaan wajib scan ID baru delapan digit, konfirmasi fisik, part dan PCS sama persis, serta waktu penerbitan ID sejak penyerahan packing sampai sekarang. Satu box lama selalu menjadi satu box baru. Tidak ada penerimaan bertahap atau perubahan PCS. Jika pasangan ID belum pasti, jangan mencatat penerimaan.
5. Penerimaan membuat box baru, mewarisi part/PCS/atribut not-full dan tanggal asli. Batas `reactivated_until` = waktu penerbitan ID + satu bulan kalender (`addMonthNoOverflow`), bukan waktu penerimaan. Status active jika deadline belum lewat; bila terlambat diterima, status expired dan tidak eligible delivery. Lokasi tujuan wajib tersedia; pallet existing mempertahankan lokasi dan tunduk lock delivery. ID lama tetap handled, transaksi menjadi completed, dan hubungan ID lama-baru dapat ditelusuri antar siklus.
6. Penerimaan packing dicatat di ledger penanganan dan audit, tidak membuat stock input produksi atau withdrawal customer. Karena itu throughput inbound tidak menghitung barang yang sama lagi. Sejak perbaikan 16 November 2026, penerimaan expired tetap menahan lokasi pallet selama box fisik tersimpan; ringkasan aktif kosong tidak melepas lokasi. Lokasi penerimaan juga tersimpan di penanganan dan expired report.

Seluruh transisi berjalan dalam transaksi dengan row lock. State tidak boleh dilewati; pengulangan keputusan/penyerahan/penerimaan yang sama tidak menggandakan transaksi atau memperpanjang deadline. Box assigned/pick-locked atau pallet dengan sesi scanning/blocked/approved menolak mutasi. Hapus/koreksi sumber melalui Stock View diblokir jika sudah memiliki penanganan.

Penanganan per transaksi (revisi UX 23 Oktober 2026):

- `expired_box_transactions` adalah header permanen bernomor `EXP-YYYYMMDD-000001`; `expired_box_handlings.transaction_id` menghubungkan setiap detail box ke satu header. Memilih 3 box pada penyerahan awal membuat tepat satu transaksi berisi 3 detail; satu box juga menjadi satu transaksi.
- Daftar utama menampilkan satu baris per transaksi (tanggal, jumlah box/PCS, tahap, hasil, dan tindakan berikutnya). Proses belum selesai didahulukan. Pencarian nomor transaksi, ID lama/baru, atau part tetap mengembalikan transaksi beserta seluruh anggotanya.
- Revisi tampilan 23 Oktober 2026: daftar box expired tampil langsung di halaman utama sebelum daftar transaksi, tanpa membuka Buat transaksi. Section warning juga selalu terbuka. Keduanya menampilkan umur kalender dalam bulan/hari, total hari sejak masuk asli, tanggal masuk, part/PCS, dan pallet/lokasi. Umur juga tampil saat memilih box untuk transaksi baru. Box expired dengan `reactivated_until` menampilkan waktu batas aktif berakhir secara terpisah; tidak mengubah dasar umur/FIFO atau aturan expiry. Tabel lebar dapat digulir pada ponsel.
- Poles UI 23 Oktober 2026: box warning ditempatkan tepat setelah daftar expired, dengan tautan lompat ke expired/warning/transaksi. Pada pemilihan awal, pencarian langsung menyaring daftar tanpa memuat ulang halaman atau menghilangkan box yang sudah dicentang; "Pilih semua yang tampil" hanya menambah hasil yang terlihat sampai batas 50. Form QA memberikan umpan balik penerapan hasil bersama dan menandai baris ditolak; tahap packing memakai ringkasan box, sementara lokasi pallet baru hanya ditampilkan saat membuat pallet baru. Ini perubahan presentasi; status, izin, payload, dan aturan transaksi tetap sama.
- Tombol Buat transaksi membuka pemilihan box expired, maksimal 50 box, lalu tinjauan dan konfirmasi penyerahan QA. Pemilihan hanya terjadi di awal. Keanggotaan tidak bisa digabung dengan transaksi lain atau dikurangi lewat payload tahap berikutnya; start yang tumpang tindih ditolak tanpa meninggalkan header kosong.
- Halaman transaksi menampilkan progres dan form tahap saat ini: hasil QA → penyerahan packing → penerimaan → selesai. Operator tidak memilih ulang box pada setiap tahap. Detail box tersendiri menjadi halaman jejak; endpoint per-box lama menolak mutasi sebagian untuk transaksi dengan beberapa anggota.
- Keputusan QA dapat berbeda per box; tersedia pengisian hasil/catatan bersama dan alasan wajib untuk penolakan. Box ditolak dihapus permanen dari inventory, tetapi detailnya tetap anggota transaksi. Hanya anggota lolos yang diteruskan bersama ke packing/penerimaan. Jika seluruh box ditolak, transaksi selesai tanpa tahap packing.
- Semua perubahan satu tahap disimpan dalam satu database transaction. Kegagalan satu baris atau audit membatalkan seluruh mutasi tahap, termasuk penghapusan permanen, registrasi ID baru, pallet, dan okupansi lokasi. Sinkronisasi expiry sebelum penyerahan QA tetap housekeeping tersendiri.
- Semua box lolos dalam transaksi diterima bersama pada satu pallet existing atau satu pallet baru di lokasi kosong. Setiap ID lama tetap dipasangkan dengan satu ID baru dan part/PCS yang sama. Waktu penerbitan dapat diisi bersama atau berbeda; Enter scanner memindahkan fokus ke ID berikutnya tanpa mengirim form. Ringkasan pallet dihitung setelah semua baris terbentuk agar baris expired tidak melepas lokasi sebelum baris aktif diterima.
- Pengulangan permintaan yang sama tidak membuat ulang header, box, pallet, atau audit. Server mengunci header dan detail serta memeriksa ulang seluruh anggota; ID dari transaksi lain atau penerimaan sebagian ditolak.
- Surat pengantar mencantumkan nomor transaksi dan satu baris per anggota lolos. QA/packing tetap tidak memerlukan akun; jejak petugas, waktu, snapshot, dan pasangan ID tersedia per detail.
- Migration tambahan `2026_10_23_000002_create_expired_box_transactions` menambah header dan FK tanpa mengubah stok/deadline. Untuk riwayat sebelum pengelompokan permanen, setiap detail lama dibackfill menjadi satu transaksi tersendiri dengan tanggal/petugas asal; kedekatan timestamp atau pilihan massal pada tahap lain tidak dipakai untuk menebak kelompok. Backfill dapat dilanjutkan untuk detail yang masih null. Rollback ditolak bila sudah ada header agar pengelompokan tidak hilang. Database kerja belum dimigrasikan.

`box_identities` menyimpan semua nomor yang pernah dipakai, termasuk soft-delete dan penolakan QA permanen. Sejak 15 November, registry menjadi pengunci global dan penghitung penggunaan. Pembuatan melalui model Box mengklaim penggunaan atomik bersama box, pemeriksaan pemilik aktif serta unique nomor/penggunaan mencegah duplikasi. Stock Input, direct Delivery Assign, dan pengajuan Not Full memeriksa pemilik/proses aktif. Jangan membuat box melalui raw insert yang melewati model.

Transisi data lama (revisi sesuai keputusan pengguna 24 November 2026): migration `2026_10_23_000001_create_expired_box_handlings` menyimpan deadline lama pada `legacy_reactivated_until` untuk box non-withdrawn/non-deleted tanpa mengubah deadline atau status existing. Masa reaktivasi valid tetap berlaku sampai batas asal, bukan dipotong saat deploy; deadline habis tetap tidak eligible. Riwayat `expired_box_reports` lama tetap dipisahkan dari proses QA baru. Tidak ada transaksi QA fiktif yang dibuat. Data handled legacy tetap nonaktif. Server yang sudah menjalankan versi lama tidak otomatis pulih hanya dengan perubahan file migration.

Scheduler `expired-box:sync-statuses` memperbarui Lokal dan Ekspor setiap menit. Ringkasan harian `expired-box:send-summary` pukul 07.00 WIB mengirim satu email dengan bagian Lokal dan Ekspor terpisah. Penerima default adalah akun Supervisi. `EXPIRED_BOX_SUMMARY_TO` dapat mengarahkan ringkasan ke satu alamat uji di development. Query expiry mencakup box orphan; status handled tidak diaktifkan ulang oleh scheduler. Cek deadline pada query stok/FIFO/scan tetap berlaku sebelum scheduler berjalan. Setelah masa satu bulan habis, ID pengganti masuk siklus QA baru.

File utama:

- `app/Services/ExpiredBoxService.php`
- `app/Services/ExpiredBoxHandlingService.php`
- `app/Services/ExpiredBoxBulkService.php`
- `app/Models/ExpiredBoxHandling.php`
- `app/Models/ExpiredBoxTransaction.php`
- `app/Models/Box.php`
- `app/Http/Controllers/ExpiredBoxController.php`
- `app/Http/Controllers/ExpiredBoxTransactionController.php`
- `resources/views/warehouse/expired-box/{index,show,print}.blade.php`
- `resources/views/warehouse/expired-box/{create,transaction,transaction-form,bulk-review,bulk-print}.blade.php`
- `public/js/expired-box-bulk.js`
- `tests/Feature/ExpiredBoxReactivationTest.php`
- `tests/Feature/ExpiredBoxBulkHandlingTest.php`

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
- `app/Services/OperationalDashboardService.php`
- `app/Services/OperationalDashboardSources.php`
- `app/Exports/OperationalDashboardExport.php`
- `app/Services/AuditService.php`

## Audio Scan (diverifikasi 13 Oktober 2026)

- Stock Input, Picking Verification, dan Scan Final Delivery menggunakan pemutar bersama `public/js/scan-feedback.js`, dimuat melalui `resources/views/shared/partials/scan-feedback.blade.php`.
- Audio berasal dari `public/audio/OK.mp3` untuk input/scan berhasil dan `public/audio/NG.mp3` untuk input/scan gagal. File disertakan dalam repository dan diakses melalui URL `asset()`, sehingga deployment tidak bergantung pada folder Downloads komputer development.
- Stock Input memutar OK setelah server menerima ID Box atau nomor part; seluruh pemanggilan suara error sebelumnya memakai NG. Pesan sedang memproses tidak memutar OK.
- Picking Verification dan Scan Final Delivery memutar NG untuk validasi input gagal, penolakan server, dan kegagalan koneksi. Scan ulang box yang sudah diverifikasi tetap memakai OK sesuai perilaku sukses yang sudah ada.
- Beep sintetis dan text-to-speech sebelumnya dihapus. Pemutar menyiapkan audio hanya saat interaksi keyboard/pointer pada kontrol scan (diverifikasi 20 Oktober 2026), bukan saat navigasi/status; suara sebelumnya dihentikan saat hasil baru datang, dan scan tetap berjalan jika audio tidak tersedia.
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
- ID Box bersifat unik dan umumnya harus delapan digit.
- `stock_input_boxes` adalah sumber mapping audit transaksi input yang paling akurat.
- Box aktif adalah sumber utama perhitungan stok.
- `pallet_items` merupakan ringkasan/fallback legacy, bukan satu-satunya sumber kebenaran.
- Status lokasi kosong ditentukan dari box fisik tersimpan (termasuk expired, mengecualikan withdrawn/deleted/handled); `pallet_items` hanya dipakai untuk pallet legacy yang tidak pernah memiliki histori box. Eligibility FIFO/delivery tetap memakai box aktif.
- Box yang sama harus dihitung satu kali walaupun memiliki relasi ke lebih dari satu palet.
- Canonical pallet box shared adalah relasi lokasi valid paling baru.
- Box dalam sesi picking aktif tidak boleh dialokasikan ke order lain.
- Box dalam sesi picking aktif tidak boleh diedit atau dihapus.
- Box assigned ke delivery tidak boleh diedit, dihapus, atau ditangani sebagai expired.
  Move/Merge tetap boleh selama belum ada sesi delivery aktif; sesi `pending` tidak mengunci.
- Pallet dengan box dalam sesi `scanning`, `blocked`, atau `approved` tidak boleh diubah
  melalui Move/Merge, Stock Input, Delivery Assign, approval Box Not Full, Stock View,
  atau Handle expired. Sinkronisasi expiry otomatis dan penyelesaian delivery tetap berjalan.
- Semua lokasi baru dari Stock Input dan Merge Pallet wajib berasal dari Master Location.
- Semua request box not full pending memblokir fulfillment; supplement hanya boleh menutup kebutuhan order yang benar-benar masih tersisa.
- Completion delivery wajib exact dan membatalkan transaksi jika snapshot box, status, quantity, assignment, atau lokasi telah berubah.
- Lokasi hanya boleh ditempati satu palet aktif.
- Database menjamin satu `stock_locations` per pallet dan satu relasi `master_location_id` aktif melalui unique constraint.
- Undo withdrawal memulihkan pallet soft-deleted dan `stock_locations`; undo ditolak jika lokasi asal sudah ditempati pallet lain.
- Soft delete digunakan untuk box, palet, stock input, withdrawal, dan delivery agar histori tetap dapat diaudit.
- User dinonaktifkan melalui `is_active`, bukan dihapus, sehingga foreign key dan histori tetap utuh.
- Nomor Master Part yang pernah dipakai tidak boleh diubah. Master Part dapat
  diarsipkan dari katalog aktif bila hanya tersisa histori. Stok dan proses aktif
  tetap memblokir pengarsipan, dan seluruh rujukan histori dipertahankan.
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
- Warning tetap dapat masuk FIFO dan tidak dapat di-Handle. Handle expired berarti penyerahan QA. QA ditolak menghapus box permanen dengan snapshot histori; QA lolos dikembalikan ke warehouse lalu packing menerbitkan ID baru. Sejak revisi 5 November 2026, stok pengganti aktif satu bulan sejak penerimaan, dengan umur/FIFO mengikuti tanggal asli; deadline historis tidak diubah. Assignment dan picking lock tetap dilindungi.

## Status Schema dan Validasi Terakhir

- MySQL lokal `db_stock`: enum `delivery_orders.status` sudah memuat `partial` (20 Oktober 2026).
  Deployment lain dengan skema lama memerlukan migration perbaikan sebelum Split Delivery digunakan.
- Seluruh migration sampai `2026_06_19_000005_make_audit_log_type_extensible` telah dijalankan pada MySQL lokal tanggal 19 Juni 2026.
- Backup sebelum migration terbaru tersimpan di `storage/app/backups/db_stock_before_integrity_hardening_2026-06-19_082641.sql`.
- Audit data aktif tidak menemukan duplicate stock location, lokasi tanpa master link, mismatch `pallet_items`, box aktif tanpa lokasi, session pending yatim, assignment invalid, lebih dari satu session picking aktif, maupun mismatch PCS Master Part tanpa approval not-full.
- Full regression suite branch QA/packing diverifikasi 23 Oktober 2026 menggunakan SQLite in-memory: 272 test lulus (2.076 assertions), termasuk 22 test expiry/QA/packing dan 18 test proses beberapa box/pengelompokan transaksi. Cakupan tambahan: 3 box menjadi 1 header, siklus lengkap dengan hasil QA campuran, seluruh anggota ditolak, penolakan payload sebagian/lintas transaksi, start tumpang tindih, permission, dan backfill yang dijalankan ulang. Daftar satu baris per transaksi, navigasi langsung ke tahap QA, pilihan awal, hasil QA berbeda, tahap packing/selesai, dan simulasi viewport 390px diperiksa di browser dengan HTML hasil render Laravel dari database test. Ini bukan pengujian perangkat scanner fisik atau rehearsal MySQL production. Database kerja MySQL belum dimigrasikan dalam pengerjaan ini.
- Migration QA/packing belum dinyatakan siap production. Revisi 24 November menghapus pemotongan deadline sesuai keputusan pengguna; tes populated baseline SQLite mempertahankan deadline dan relasi picking. Masih perlu rehearsal salinan MySQL/MariaDB production, backup/restore teruji, penghentian semua write/worker/scheduler dan prosedur pemulihan DDL sebagian. `down()` menolak rollback jika penanganan sudah ada. Lihat docs/PRODUCTION_READINESS_2026-11-24.md untuk hasil dan prasyarat terbaru.
- Verifikasi UI umur setelah revisi di atas: 40 tes expiry/transaksi lulus (384 assertions). Render dengan data contoh memastikan umur 13 bulan 5 hari / 10 bulan 3 hari, batas aktif terpisah, serta hasil pencarian kosong; daftar expired/warning diperiksa pada desktop dan simulasi lebar 390px. Revisi tampilan ini tidak menambah migration.
- Verifikasi poles UI 23 Oktober 2026: 40 tes expiry/transaksi lulus (384 assertions), sintaks controller dan JavaScript valid, serta pratinjau browser desktop memeriksa filter yang mempertahankan pilihan lintas pencarian, pemilihan massal hasil tampil, feedback QA, urutan daftar utama, dan ringkasan packing. Preview memakai HTML dari SQLite test; belum diuji dengan perangkat scanner fisik atau browser ponsel nyata. Tidak ada migration tambahan.
- Kolom `audit_logs.type` menggunakan string terindeks, bukan enum tertutup, sehingga audit fitur baru seperti `delivery_assignment` dapat disimpan tanpa melanggar constraint.

## Catatan yang Belum Diputuskan

Alur QA/packing memakai pencatatan internal; revisi 5 November 2026 tidak mewajibkan pengantar fisik ke packing. Metode penyampaian hasil QA tetap perlu diverifikasi operasional. Keputusan 24 November mempertahankan deadline Handle lama saat upgrade; migration QA dan penerimaan bertahap tidak memotong deadline existing. Rincian keputusan ada pada bagian 17 dan revisi di awal memo.

## Panduan Agent Saat Menerima Tugas

- Untuk pertanyaan umum tentang sistem, jawab berdasarkan memo ini.
- Untuk perubahan fitur, baca memo ini lalu inspeksi controller, model, migration, view, dan test yang terkait saja.
- Untuk diagnosis bug, jangan berasumsi memo selalu mutakhir; verifikasi jalur kode yang terlibat.
- Jangan melakukan full-repository exploration kecuali:
  - user secara eksplisit memintanya;
  - tugas lintas domain memang membutuhkannya;
  - implementasi yang ditemukan bertentangan dengan memo.
- Setelah perubahan proses bisnis yang signifikan, perbarui bagian terkait dan tanggal verifikasi memo.
