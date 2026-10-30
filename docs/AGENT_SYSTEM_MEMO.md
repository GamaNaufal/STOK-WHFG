# Memo Sistem STOK WHFG untuk Agent

Terakhir diverifikasi dari source code: 30 Oktober 2026 (pembaruan inkremental).

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
- Laporan domestik/ekspor dan rincian kini memakai public/js/report-browser.js.
  Lingkup/jenis/select/tanggal langsung diterapkan; teks debounce 300 ms. GET
  biasa tetap berfungsi; tombol Terapkan/Tampilkan disembunyikan saat JS aktif.
- ReportPageResponse mengirim JSON html/title/url berisi partial tanpa layout
  untuk Accept application/json; HTML biasa tetap view existing. Cache private/
  no-store dan Vary Accept. Fokus/caret/scroll dipertahankan; URL, judul,
  ringkasan serta tautan Excel sinkron. Pagination/reset dan Back/Forward AJAX.
- AbortController/versi mengabaikan respons lama sejak awal edit/debounce.
  Gagal/422 mempertahankan hasil, menampilkan Coba lagi dan menahan export/
  pagination hingga filter berhasil. Pilihan operator/status tidak hilang pada
  hasil kosong. Export pengambilan domestik kini mengikuti filter part/operator
  juga. Izin, pencatatan inventory dan dashboard tidak berubah.
- 454 tes backend lulus (4.191 assertions), 87 tes frontend lulus. Browser
  memeriksa pencarian domestik, perpindahan ekspor, 27 hasil/pagination, Back,
  satu box, reset/retry tanggal salah, Excel URL, fokus dan viewport 390px tanpa
  overflow halaman atau exception JS. Fixture hanya SQLite preview khusus;
  tidak ada migration/dependency/build baru atau perubahan MySQL/production.
  Detail: docs/WAREHOUSE_SCOPED_REPORTS.md.

Verifikasi Laporan Gabungan Domestik/Ekspor 30 Oktober 2026:
- Fitur Laporan dan Export Operasional existing memiliki pemilih scope
  domestic/export dan jenis penerimaan, pengiriman, stok aktif/cadangan,
  pemenuhan serta revisi/pengembalian. Tanpa scope tetap domestik. Halaman dan
  export penerimaan/pengambilan domestik dipertahankan, dengan pemilih tambahan.
- Default reports untuk Admin Warehouse/Supervisi; Admin utama tetap akses
  existing. Izin role tersimpan/revokasi tetap dihormati; operator ekspor tidak
  diberi akses default. Rincian report baca sendiri tanpa izin operasi ekspor.
- WarehouseReportService memakai query sama untuk tabel/ringkasan/Excel baru.
  Histori penerimaan per box termasuk arsip dan lokasi movement pertama;
  pengiriman hanya completed_at; stok hanya aktif dan posisi fisik. Pemenuhan
  mencakup arsip selesai (overview includeArchived opsional, default tetap
  aktif), terpisah dari metrik PCS domestik. Retur ekspor berasal dari snapshot
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
  nomor box reservasi FIFO. Penerimaan/input baru dan alokasi penerimaan berikutnya
  tanpa notice tidak memerlukan verifikasi tambahan, termasuk pallet campuran;
  scan input dan konfirmasi penempatan tetap berlaku. Campuran ditentukan dari
  seluruh part di pallet fisik asal; flag hanya diwajibkan untuk rekomendasi
  tersebut saat reservasi/ketika source menjadi campuran. Pallet satu part tetap boleh
  menyuplai sebagian isi tanpa scan (teruji 36 dari 72 box). Bukti terikat
  source dan revision box; penempatan satuan/bulk memeriksanya pada backend.
- Input selain tepat 8 digit ASCII (termasuk 123) ditolak tanpa issue. Salah
  box delapan digit membuat ExportPickingIssue pending yang persisten, menahan
  operator/tujuan terkait dan box salah. Refresh/POST langsung tidak melepasnya.
- Scan Issues dan Approval existing kini memuat Lokal/Domestik dan Ekspor,
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
  melindungi form yang baru dimuat. Modal memuat nomor box, part/PCS, status,
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
  transaksi, membuat stock boxes, lalu alokasi FIFO sekali. Tanggal scan server
  menjadi first_received_at. Hasil berkelompok per pallet/lokasi dan cadangan;
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
  /export/receiving. Sesuai jawaban pengguna: scan nomor box lalu nomor part,
  PCS otomatis dari master ekspor; nomor delapan digit boleh sama dengan domestik.
- Penerimaan mencatat export_stock_boxes sebagai received dan melakukan alokasi
  otomatis, tanpa menulis boxes/box_identities domestik. Konfirmasi penempatan
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
  current_pallet_id master yang merupakan FK pallet domestik.
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
  hanya pada SQLite preview, tidak mengubah inventory domestik/production remote.

Verifikasi Pemenuhan Permintaan Ekspor 29 Oktober 2026:
- Detail implementasi/tahap berikutnya ada di docs/EXPORT_FULFILLMENT.md. Menu
  /export/fulfillment memakai izin export_fulfillment, bawaan Operator Export,
  independen dari hak mengubah perencanaan. Halaman daftar dan detail bersifat
  pemantauan; belum ada input/scan, penerimaan inventory ekspor, FIFO alokasi
  otomatis, atau alur pengembalian. Jangan menghitung stok domestik di sini.
- Finalisasi mengisi entered_fulfillment pada export_pallet_plans. Rencana yang
  pernah final tetap muncul saat revisi, meski seluruh lokasi sudah dilepas.
  Draft awal tidak ditampilkan; cancelled/arsip dikeluarkan dari daftar aktif.
  Migration 2026_10_29_000001 melakukan backfill penanda dari final/klaim/audit.
- export_fulfillment_boxes menyimpan komitmen aktif unik per nomor box beserta
  snapshot penerimaan pertama, part/PCS, aktor/waktu reservasi dan konfirmasi
  penempatan/lokasi. Ini fondasi pencatatan, bukan penerimaan inventory fisik.
  Belum ada endpoint write. Catatan baru tidak dibuat oleh GET/finalisasi.
- Terpenuhi hanya dari konfirmasi dengan part/PCS sesuai item dan lokasi tujuan
  benar. Reservasi belum ditempatkan dipisah. Catatan tidak sesuai dan kelebihan
  ditandai; kekurangan/progres dihitung per item agar kelebihan part lain tidak
  menutupi kebutuhan. Daftar box urut penerimaan pertama lalu nomor box.
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
domestik, kapasitas, ID dan occupancy lokasi, diverifikasi tetap utuh. Import
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
- Input box, stok ekspor/cadangan, reservasi nomor box, FIFO dan pengembalian
  fisik merupakan tahap Pemenuhan Permintaan berikutnya. Jangan mengambil stok
  domestik otomatis. Keputusan pengguna: FIFO tanggal penerimaan pertama,
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
  server. Jumlah wajib kelipatan kapasitas, tidak boleh part duplikat atau box
  tidak penuh. Tidak ada input jumlah pallet.
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
Master ekspor dimulai kosong; migration/seeder tidak menyalin master domestik.

Model PartSetting/MasterLocation memakai scope domestik secara otomatis;
ExportPartSetting/ExportMasterLocation memakai scope ekspor pada tabel yang sama.
Lingkup tetap ditentukan oleh model dan nama route, bukan role/login atau input
request. Endpoint ekspor terpisah di /export/part-settings dan /export/locations;
CRUD, pencarian, validasi unik dan akses ID semuanya dibatasi sesuai lingkup.
API/validasi lokasi pada transaksi domestik dan LocationAssignmentService tetap
hanya menerima master domestik. Master ekspor belum terhubung ke inventory fisik.
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
pada transaksi domestik, dan pengaturan ulang hak akses. Browser diperiksa pada
viewport desktop 1440x900 dan ponsel 390x844, kondisi kosong/berisi, pencarian dan
navigasi keyboard. Database preview terpisah dipakai untuk data uji antarmuka;
database operasional/production tidak diubah. Cabang MySQL migration belum
dijalankan pada database production.

Verifikasi tambahan 28 Oktober 2026 (Operator Export): role `export_operator`
tersedia pada tambah/edit/filter user dan pengaturan Hak Akses. Akses fitur
saat ini mencakup Rencana Delivery, Perencanaan Pallet dan dua master ekspor; login, profil, logout,
dan dashboard ekspor tetap tersedia. Dashboard role ini tidak memuat statistik atau transaksi domestik.
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

Redo hanya berlaku satu kali untuk session `completed` dengan `completion_status=completed` dan `redo_until` yang belum lewat. Untuk shared box, seluruh box yang direstore dipindahkan ke pallet konsolidasi baru; pallet asal yang masih memiliki box aktif tetap mempertahankan lokasi dan ringkasannya, sedangkan pallet asal yang kosong dilepas dari lokasi dan stock location-nya. Session assignment pending yang tersisa dibatalkan saat redo.

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

Implementasi branch `codex/expired-qa-packing`; migration belum diterapkan pada database kerja saat pengembangan. QA dan packing tetap di luar sistem. Hak akses seluruh endpoint mengikuti fitur `expired_box` (default Supervisi/Admin, dapat diatur Admin Utama).

Umur dan FIFO menggunakan `boxes.created_at` sebagai tanggal masuk asli. Box pengganti mewarisi tanggal tersebut; penerimaan hasil packing dan penerbitan ID dicatat terpisah. Umur normal: active <9 bulan, warning 9–<12 bulan, expired >=12 bulan. Warning tetap eligible FIFO dan tidak dapat di-Handle.

Alur:

1. Handle pada expired berarti barang sudah diserahkan ke QA. Dibuat `expired_box_handlings` dengan referensi HND, snapshot identitas/tanggal/asal, petugas, dan status `at_qa`. Box lama menjadi `expired_status=handled` dan tetap nonaktif; tidak mendapat tambahan masa aktif. Setelah ringkasan disinkronkan, relasi pallet dilepas karena fisik sudah ke QA; identitas pallet dan mapping asal disimpan dalam snapshot agar operasi pallet berikutnya tidak mengubah box dalam penanganan.
2. Petugas mencatat `passed` atau `rejected`. Lolos sekaligus mengonfirmasi barang kembali ke warehouse (`awaiting_packing`). Penolakan wajib alasan dan konfirmasi; box di-force-delete. Snapshot histori, mapping input/picking, withdrawal, issue, request not-full, expiry report, pallet, serta audit disimpan dalam penanganan sebelum cascade/null FK. Header stock input/withdrawal/order tidak dihapus. Snapshot lengkap dapat diunduh oleh pengguna berizin.
3. Setelah lolos QA, tersedia surat pengantar cetak yang memuat nomor penanganan, ID lama, part, PCS, dan kolom ID baru/waktu penerbitan. Dokumen mengikuti box secara fisik. Warehouse mencatat penyerahan packing (`at_packing`). Packing tidak memerlukan akun atau akses aplikasi.
4. Penerimaan wajib scan ID baru delapan digit, konfirmasi fisik, part dan PCS sama persis, serta waktu penerbitan ID sejak penyerahan packing sampai sekarang. Satu box lama selalu menjadi satu box baru. Tidak ada penerimaan bertahap atau perubahan PCS. Jika pasangan ID belum pasti, jangan mencatat penerimaan.
5. Penerimaan membuat box baru, mewarisi part/PCS/atribut not-full dan tanggal asli. Batas `reactivated_until` = waktu penerbitan ID + satu bulan kalender (`addMonthNoOverflow`), bukan waktu penerimaan. Status active jika deadline belum lewat; bila terlambat diterima, status expired dan tidak eligible delivery. Lokasi tujuan wajib tersedia; pallet existing mempertahankan lokasi dan tunduk lock delivery. ID lama tetap handled, transaksi menjadi completed, dan hubungan ID lama-baru dapat ditelusuri antar siklus.
6. Penerimaan packing dicatat di ledger penanganan dan audit, tidak membuat stock input produksi atau withdrawal customer. Karena itu throughput inbound tidak menghitung barang yang sama lagi. Penerimaan expired mengikuti aturan lokasi expired yang sudah ada: ringkasan aktif kosong dapat melepas lokasi, tetapi lokasi penerimaan tetap tersimpan di penanganan dan expired report.

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

`box_identities` menyimpan semua nomor yang pernah dipakai, termasuk soft-delete dan penolakan QA permanen. Pembuatan melalui model Box mendaftarkan identitas atomik bersama box; unique constraint mencegah pemakaian bersamaan. Stock Input, direct Delivery Assign, dan pengajuan Not Full memeriksa identitas yang telah dipakai. Jangan membuat box melalui raw insert yang melewati model.

Transisi data lama: migration `2026_10_23_000001_create_expired_box_handlings` menyimpan deadline lama pada `legacy_reactivated_until` lalu mengakhiri deadline reaktivasi box non-withdrawn/non-deleted pada saat cutover. Box tersebut langsung tidak eligible delivery; sinkronisasi expiry memasukkannya ke daftar QA. Riwayat `expired_box_reports` lama tetap dipisahkan dari proses QA baru. Tidak ada transaksi QA fiktif yang dibuat. Data handled legacy yang tidak memiliki deadline tetap nonaktif.

Scheduler `expired-box:sync-statuses` tetap berjalan setiap menit dan ringkasan email setiap 07.00. Query expiry mencakup box orphan; status handled tidak diaktifkan ulang oleh scheduler. Cek deadline pada query stok/FIFO/scan tetap berlaku sebelum scheduler berjalan. Setelah masa satu bulan habis, ID pengganti masuk siklus QA baru.

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
- `app/Services/AuditService.php`

## Audio Scan (diverifikasi 13 Oktober 2026)

- Stock Input, Picking Verification, dan Scan Final Delivery menggunakan pemutar bersama `public/js/scan-feedback.js`, dimuat melalui `resources/views/shared/partials/scan-feedback.blade.php`.
- Audio berasal dari `public/audio/OK.mp3` untuk input/scan berhasil dan `public/audio/NG.mp3` untuk input/scan gagal. File disertakan dalam repository dan diakses melalui URL `asset()`, sehingga deployment tidak bergantung pada folder Downloads komputer development.
- Stock Input memutar OK setelah server menerima ID box atau nomor part; seluruh pemanggilan suara error sebelumnya memakai NG. Pesan sedang memproses tidak memutar OK.
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
- Box ID bersifat unik dan umumnya harus delapan digit.
- `stock_input_boxes` adalah sumber mapping audit transaksi input yang paling akurat.
- Box aktif adalah sumber utama perhitungan stok.
- `pallet_items` merupakan ringkasan/fallback legacy, bukan satu-satunya sumber kebenaran.
- Status lokasi kosong ditentukan dari box aktif; `pallet_items` hanya dipakai untuk pallet legacy yang tidak pernah memiliki histori box.
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
- Warning tetap dapat masuk FIFO dan tidak dapat di-Handle. Handle expired berarti penyerahan QA. QA ditolak menghapus box permanen dengan snapshot histori; QA lolos dikembalikan ke warehouse lalu packing menerbitkan ID baru. Stok pengganti aktif satu bulan sejak penerbitan ID, dengan umur/FIFO mengikuti tanggal asli. Assignment dan picking lock tetap dilindungi.

## Status Schema dan Validasi Terakhir

- MySQL lokal `db_stock`: enum `delivery_orders.status` sudah memuat `partial` (20 Oktober 2026).
  Deployment lain dengan skema lama memerlukan migration perbaikan sebelum Split Delivery digunakan.
- Seluruh migration sampai `2026_06_19_000005_make_audit_log_type_extensible` telah dijalankan pada MySQL lokal tanggal 19 Juni 2026.
- Backup sebelum migration terbaru tersimpan di `storage/app/backups/db_stock_before_integrity_hardening_2026-06-19_082641.sql`.
- Audit data aktif tidak menemukan duplicate stock location, lokasi tanpa master link, mismatch `pallet_items`, box aktif tanpa lokasi, session pending yatim, assignment invalid, lebih dari satu session picking aktif, maupun mismatch PCS Master Part tanpa approval not-full.
- Full regression suite branch QA/packing diverifikasi 23 Oktober 2026 menggunakan SQLite in-memory: 272 test lulus (2.076 assertions), termasuk 22 test expiry/QA/packing dan 18 test proses beberapa box/pengelompokan transaksi. Cakupan tambahan: 3 box menjadi 1 header, siklus lengkap dengan hasil QA campuran, seluruh anggota ditolak, penolakan payload sebagian/lintas transaksi, start tumpang tindih, permission, dan backfill yang dijalankan ulang. Daftar satu baris per transaksi, navigasi langsung ke tahap QA, pilihan awal, hasil QA berbeda, tahap packing/selesai, dan simulasi viewport 390px diperiksa di browser dengan HTML hasil render Laravel dari database test. Ini bukan pengujian perangkat scanner fisik atau rehearsal MySQL production. Database kerja MySQL belum dimigrasikan dalam pengerjaan ini.
- Migration QA/packing belum dinyatakan siap production: cutover deadline masih perlu preflight box assigned/picking, pemisahan perubahan schema dan cutover, serta prosedur pemulihan jika DDL terhenti sebagian. `down()` menolak rollback jika penanganan sudah ada dan bukan pemulihan deadline lama. Perlu rehearsal pada salinan MySQL production, backup/restore teruji, serta penghentian sementara write/worker/scheduler ketika backfill dan cutover. Penambahan proses massal tidak menyelesaikan risiko migration tersebut.
- Verifikasi UI umur setelah revisi di atas: 40 tes expiry/transaksi lulus (384 assertions). Render dengan data contoh memastikan umur 13 bulan 5 hari / 10 bulan 3 hari, batas aktif terpisah, serta hasil pencarian kosong; daftar expired/warning diperiksa pada desktop dan simulasi lebar 390px. Revisi tampilan ini tidak menambah migration.
- Verifikasi poles UI 23 Oktober 2026: 40 tes expiry/transaksi lulus (384 assertions), sintaks controller dan JavaScript valid, serta pratinjau browser desktop memeriksa filter yang mempertahankan pilihan lintas pencarian, pemilihan massal hasil tampil, feedback QA, urutan daftar utama, dan ringkasan packing. Preview memakai HTML dari SQLite test; belum diuji dengan perangkat scanner fisik atau browser ponsel nyata. Tidak ada migration tambahan.
- Kolom `audit_logs.type` menggunakan string terindeks, bukan enum tertutup, sehingga audit fitur baru seperti `delivery_assignment` dapat disimpan tanpa melanggar constraint.

## Catatan yang Belum Diputuskan

Alur QA/packing memakai pencatatan internal dan pengantar fisik; metode informasi hasil QA serta keterjagaan pengantar perlu diverifikasi operasional. Jalankan migration hanya saat cutover karena masa aktif Handle lama dihentikan. Rincian keputusan ada pada bagian 17.

## Panduan Agent Saat Menerima Tugas

- Untuk pertanyaan umum tentang sistem, jawab berdasarkan memo ini.
- Untuk perubahan fitur, baca memo ini lalu inspeksi controller, model, migration, view, dan test yang terkait saja.
- Untuk diagnosis bug, jangan berasumsi memo selalu mutakhir; verifikasi jalur kode yang terlibat.
- Jangan melakukan full-repository exploration kecuali:
  - user secara eksplisit memintanya;
  - tugas lintas domain memang membutuhkannya;
  - implementasi yang ditemukan bertentangan dengan memo.
- Setelah perubahan proses bisnis yang signifikan, perbarui bagian terkait dan tanggal verifikasi memo.
