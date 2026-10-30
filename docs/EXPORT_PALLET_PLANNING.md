# Perencanaan Pallet Ekspor

Verifikasi terakhir 29 Oktober 2026 pada branch aktif Export-plan.

## Editor dua panel

- Pada desktop, daftar kebutuhan tetap di kiri dan hanya satu pallet aktif
  ditampilkan di kanan. Pilih nomor pallet atau gunakan tombol sebelumnya/berikutnya.
  Mengganti pallet tidak menghapus isian pada pallet lain.
- Cari nomor part atau aktifkan Hanya belum selesai. Filter juga menampilkan
  alokasi berlebih yang masih perlu dikoreksi. Bersihkan mereset pencarian/filter
  tanpa mengubah pembagian. Enter pada pencarian memfokuskan hasil pertama.
- Klik nomor part untuk menambahkannya ke pallet aktif dan fokus ke jumlah box;
  jika sudah ada, baris yang sama difokuskan. Pemilihan manual tetap tersedia.
- Kebutuhan, total alokasi lintas pallet, dan sisa muncul di dekat jumlah box.
  Alokasikan sisa mengisi jatah yang tersedia untuk baris itu. Contoh kebutuhan
  10 box, baris ini 3 dan pallet lain 4: tombol menjadikan baris ini 6 box,
  sehingga alokasi pallet lain tetap 4. Tombol tidak mengisi part yang dihapus
  dari revisi delivery atau baris part duplikat.
- Simpan draft dan Finalisasi tetap terlihat. Pada layar sempit panel bertumpuk
  dan ruang bawah disediakan agar bilah tombol tidak menutup input terakhir.
- Semua pallet dikirim melalui kontrak pallets_json yang sama, termasuk pallet
  yang tidak sedang ditampilkan. Tidak ada perubahan schema atau aturan bisnis.

Verifikasi UI 29 Oktober: build dan 55 tes frontend lulus; 32 tes backend terkait
lulus (323 assertions). Browser Chromium menguji 59 part/20 pallet, simpan dan
baca ulang, alokasi sisa/koreksi kelebihan, pencarian/filter/keyboard, lokasi
duplikat dan finalisasi. Tampilan diperiksa pada 1440x900, 1366x768 serta simulasi
390x844, menggunakan database preview SQLite terpisah dari database operasional.

## Tahap yang sudah tersedia

- Menu Pengiriman Ekspor / Perencanaan Pallet menggunakan izin
  `export_pallet_plans`. Bawaan Operator Export, dapat diatur melalui Hak Akses.
- Satu rencana delivery mempunyai satu perencanaan yang dapat direvisi.
  Nomor pallet berurutan 1..N di dalam rencana, terpisah dari nomor pallet fisik.
- Operator menentukan isi manual, boleh mencampur part dalam satu pallet.
  Jumlah box per pallet tidak dibatasi oleh kapasitas pallet otomatis.
- Isi dan kapasitas PCS/box mengacu ke snapshot part pada rencana delivery.
  Ringkasan menunjukkan kebutuhan, teralokasi, kekurangan dan kelebihan.
- Draft boleh belum lengkap, termasuk pallet kosong atau lokasi belum dipilih.
  Alokasi berlebih akibat revisi juga dapat disimpan sebagai draft untuk dikoreksi.
- Finalisasi oleh Operator Export mensyaratkan setiap part tepat sesuai delivery,
  semua pallet terisi, dan lokasi ekspor valid. Part yang dihapus dari delivery
  harus dikeluarkan dari perencanaan sebelum finalisasi ulang.
- Satu lokasi untuk satu pallet aktif. Draft baru belum mereservasi lokasi.
  Finalisasi mereservasi lokasi secara atomik; constraint database mencegah
  lokasi yang sama diklaim perencanaan lain. Reservasi tidak mengubah occupancy
  stok fisik pada master atau membuat box/pallet pada inventory domestik.
- Buka revisi mengubah status final menjadi draft dengan reservasi tetap ditahan.
  Saat draft disimpan, lokasi yang dikeluarkan dari susunan dilepas; lokasi baru
  direservasi setelah finalisasi. Pembatalan mempertahankan pembagian sebagai
  riwayat dan melepas seluruh reservasi. Susunan dapat digunakan kembali setelah
  memeriksa ketersediaan lokasi.
- Revisi delivery otomatis mengembalikan perencanaan aktif ke draft, menaikkan
  revision, dan mempertahankan baris, ID, serta reservasinya. Tidak ada penghapusan
  alokasi diam-diam ketika kebutuhan turun atau part dihapus.
- Delivery dengan perencanaan aktif harus dibatalkan perencanaannya sebelum
  diarsipkan. Identitas master part/lokasi yang tercatat pada perencanaan dilindungi.
- Simpan, finalisasi, buka revisi, pembatalan dan invalidasi oleh revisi delivery
  mempunyai audit dalam transaksi mutasi. Stale revision ditolak. Lock selalu
  dimulai dari header delivery, lalu header perencanaan dan lokasi berurutan ID.
- Batas formulir: 500 pallet, 500 baris per pallet, 5.000 alokasi per perencanaan.
  Payload editor dikirim sebagai JSON agar tidak terpotong max_input_vars PHP.

## Peringatan jumlah box per pallet (30 Oktober 2026)

Jumlah seluruh part pada satu pallet dijumlahkan: 0–35 box tanpa peringatan,
36–71 box label kuning **Peringatan**, dan mulai 72 box label merah **Perlu
perhatian**. Ini penanda untuk operator, bukan batas kapasitas. Simpan/finalisasi
tetap diizinkan jika seluruh validasi alokasi dan lokasi existing terpenuhi.

Label berada di bagian atas pallet aktif dan berubah langsung saat part/jumlah
diubah; navigator menyertakan status teks. Hasil perencanaan yang disimpan
memakai threshold yang sama. Aturan juga berlaku pada Penerimaan Transfer Box
Ekspor dengan proyeksi isi fisik + box yang akan masuk; detail di
docs/EXPORT_STOCK_RECEIVING.md. Threshold bersumber dari ExportPalletLoad dan
diteruskan ke JS melalui komponen Blade, tanpa perubahan skema/izin.

38 tes backend terkait (434 assertions), 67 tes frontend dan build lulus.
Browser Chromium desktop memeriksa 35/36, 71/72 dan simpan draft dua pallet
dengan part campuran. Data contoh hanya pada SQLite preview.

## Kesepakatan untuk tahap Pemenuhan Permintaan

Pemantauan serta penerimaan/reservasi FIFO tersedia sejak 29 Oktober 2026; lihat
docs/EXPORT_FULFILLMENT.md dan docs/EXPORT_STOCK_RECEIVING.md. Finalisasi memasukkan
rencana ke pemantauan dan mereservasi stok ekspor tersedia. Revisi tetap terlihat
meskipun seluruh lokasi dilepas, tetapi rencana dengan komitmen/stock fisik masih
dilindungi sampai alur pelepasan dan pengembalian karena revisi terhubung.
Kesepakatan penerimaan dan tahap berikutnya:

- Scan nomor box dan part; PCS dari master ekspor. Penerimaan menambah catatan
  received, lalu konfirmasi menempatkan stok fisik di lokasi yang ditentukan.
- Operator tidak memilih delivery; kebutuhan diprioritaskan menurut tenggat
  pengiriman terdekat, lalu nomor pallet terkecil dalam delivery tersebut.
- Box tanpa kebutuhan yang cocok boleh diterima sebagai stok cadangan dengan
  memilih lokasi/pallet yang tersedia.
- Pemeriksaan stok ekspor memakai FIFO dan reservasi unik per box. Inventory
  ekspor tersendiri; jangan memakai stok domestik sebagai stok ekspor otomatis.
- FIFO memakai tanggal pertama box diterima gudang. Pemindahan/pengembalian
  mempertahankan tanggal asal. Jika kebutuhan turun, keluarkan box terbaru dahulu
  agar box tertua tetap diprioritaskan untuk pengiriman.
- Pengembalian fisik memerlukan pemilihan lokasi/pallet tujuan. Saat modul stok
  terhubung, revisi/pembatalan harus menangani penempatan/pengembalian yang masih
  berlangsung sebelum melepas reservasi. Konfirmasi penempatan (tombol atau scan
  label lokasi) dibahas saat tahap penerimaan dibuat.

## Deployment dan verifikasi

Implementasi utama: app/Services/ExportPalletPlanningService.php,
app/Http/Controllers/ExportPalletPlanController.php, model ExportPalletPlan /
ExportPlannedPallet / ExportPlannedPalletItem / ExportPalletLocationReservation,
resources/views/export/pallet-plans, serta resources/js/export-pallet-planning.js.

Migration `2026_10_28_000006_create_export_pallet_planning` membuat empat tabel
perencanaan/reservasi dan menambahkan izin pada pengaturan role existing beserta
audit. Jalankan migration yang telah ditinjau dan build dengan npm ci / npm run
build. Deployment aset memakai public/build. Tidak perlu OCR atau layanan baru.
Migration rollback otomatis ditolak untuk melindungi histori dan reservasi.

Regresi lengkap: 324 tes backend (2.685 assertions) lulus. Setelah penambahan
notifikasi arsip dan satu tes perpindahan reservasi, 32 tes Rencana Delivery /
Perencanaan Pallet (323 assertions) lulus, termasuk 14 tes khusus perencanaan.
Seluruh 53 tes frontend dan build lulus. Browser Chromium memverifikasi draft,
dua pallet dengan part campuran, penolakan kelebihan, finalisasi A1/A2, revisi
delivery yang mempertahankan alokasi/reservasi, dan finalisasi ulang.

Database pengujian menggunakan SQLite in-memory dan preview terpisah. Migration
belum dijalankan pada database operasional/production; rehearsal MySQL production
belum dilakukan. Migration QA/packing pada branch ini tetap memerlukan peninjauan
cutover sesuai memo sistem. File fixture/preview berada di storage yang diabaikan Git.
