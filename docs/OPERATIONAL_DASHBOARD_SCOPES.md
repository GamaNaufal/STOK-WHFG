# Dashboard Admin Utama dan Supervisi

Diverifikasi 31 Oktober 2026 pada branch `Export-plan`.

Admin Utama dan Supervisi memakai satu dashboard dengan filter **Semua**
(default), **Domestik**, atau **Ekspor**. Grafik domestik memakai PCS, grafik
ekspor memakai box. Masing-masing tetap menampilkan jumlah box dan PCS pada
ringkasan arus, tanpa menggabungkan kedua satuan di satu grafik.

## Definisi angka

| Bagian | Sumber dan aturan |
| --- | --- |
| Stok saat ini | Domestik: box aktif yang memiliki lokasi valid, ditambah fallback pallet_items jika pallet sama sekali tidak punya histori pallet_boxes. Ekspor: export_stock_boxes belum diarsipkan, state received/stored. Tidak mengikuti periode. |
| Pallet berisi stok | Pallet fisik yang memiliki stok aktif. Rencana kosong belum dihitung. Box diterima tetapi belum ditempatkan termasuk stok, dengan indikator belum memiliki penempatan. |
| Masuk domestik | stock_inputs yang belum dihapus, berdasarkan stored_at; grafik PCS. |
| Keluar domestik | stock_withdrawals completed yang belum dihapus, berdasarkan withdrawn_at; grafik PCS. |
| Masuk ekspor | first_received_at, termasuk box yang kemudian diarsipkan. Pengembalian cadangan/pemindahan tidak menjadi penerimaan baru. Grafik box. |
| Keluar ekspor | Pemenuhan dari permintaan yang sudah diselesaikan, berdasarkan completed_at. Reservasi, penempatan dan pallet siap belum menjadi pengiriman. Grafik box. |
| Pemenuhan domestik | Realisasi item order approved/processing/partial/completed. Parent split yang memiliki child aktif tidak menggandakan kebutuhan child. |
| Pemenuhan ekspor | overview/itemMetrics dari ExportFulfillmentService: pencocokan part/PCS per box serta konfirmasi penempatan yang valid. Reservasi belum terpenuhi. Draft yang belum pernah final dan pembatalan dikecualikan, pengiriman selesai tetap tampil. |
| Periode pemenuhan | Tenggat delivery, bukan tanggal pembuatan. Grafik kebutuhan/realisasi tetap menunjukkan semua status; filter Penuh/Belum penuh berlaku pada KPI dan tabel pemenuhan. |
| KPI penuh | Jumlah permintaan penuh / seluruh permintaan sesuai filter. Menggabungkan jumlah permintaan, bukan menjumlahkan kuantitas PCS dan box. |
| Durasi | Domestik: mulai picking hingga selesai dan rentang scan sesi. Ekspor: finalisasi terakhir hingga Selesaikan permintaan. Fase diberi label, tidak digabung menjadi satu rata-rata. |
| Issue scan | created_at sesuai periode. Histori returned/input_error tetap tercatat, tetapi keduanya sudah tertutup. Indikator terbuka merupakan subset issue pada periode itu. |
| Audit | created_at, mengikuti lingkup dan filter jenis/aksi/petugas. Umum hanya pada Semua; aktivitas ekspor dikenali dari awalan type export_, nama model Export, atau snapshot scope. |

Nomor box/ID pallet/order yang sama antarlingkup tetap dua identitas. Box domestik
yang tercatat di beberapa pallet dihitung satu kali pada total stok, diberi label
**Periksa posisi ganda**, dan kedua tautan tetap terlihat pada daftar pallet.
Jumlah per pallet dari data seperti ini tidak boleh dianggap sebagai total unik.
Data lama tidak diperbaiki otomatis oleh dashboard.

## Filter, grafik dan Excel

- Select dan tanggal langsung diterapkan memakai AJAX existing. URL, judul,
  rincian dan tautan Excel diperbarui bersama hasil; Back/Forward, reset dan
  pagination audit tetap berfungsi. GET biasa tetap didukung.
- Tanggal pilihan menggantikan periode cepat. Rentang inklusif, satu sisi kosong
  boleh. Pengelompokan grafik harian/mingguan/bulanan tersedia pada semua periode.
  Seluruh periode/rentang panjang memakai bucket aktivitas aktual agar tidak
  membuat deretan kosong bertahun-tahun.
- Respons gagal/422 mempertahankan hasil sebelumnya, menyediakan Coba lagi dan
  menahan unduhan/pagination sampai filter berhasil diterapkan.
- Grafik menggunakan SVG lokal, tooltip angka dan tabel rincian yang dapat
  dibuka dengan keyboard. Tidak ada library grafik/CDN baru atau layanan gambar
  eksternal. Grafik ekspor tidak dicampur dengan PCS domestik.
- Ringkasan memakai seluruh data. Tabel stok/pallet/permintaan/durasi menampilkan
  maksimal 200 baris; issue 50 terbaru; audit 20 per halaman. Excel mencakup
  seluruh hasil sesuai filter, tanpa batas preview/pagination.
- Workbook memuat Ringkasan, Filter, Stok, Pallet, Pemenuhan, Durasi, Issue Scan,
  Audit, dan tiga sheet grafik per lingkup. Filter audit ikut dicatat. Header
  dibekukan, kolom menyesuaikan, filter Excel tersedia. Grafik Excel native;
  nomor box nol awal dan teks mirip formula tetap teks.

## Akses dan implementasi

Dashboard tetap melalui route `dashboard` untuk role `admin` dan `supervisi`.
Role lain tetap menggunakan dashboard masing-masing. Tautan rincian/Excel
mengikuti izin `reports` existing; mencabut izin laporan tidak memberikan akses
ke endpoint Excel. Tidak ada izin mutasi ekspor tambahan untuk Supervisi.

File utama:

- `app/Services/OperationalDashboardService.php`: validasi filter, agregasi,
  grafik, ringkasan global, audit dan pagination.
- `app/Services/OperationalDashboardSources.php`: sumber baca domestik/ekspor
  dengan kolom seragam dan identitas lingkup.
- `app/Http/Controllers/DashboardController.php`: respons halaman/JSON. AJAX
  hanya membaca; load HTML Admin tetap menjalankan sinkronisasi expiry existing.
- `resources/views/shared/operational-dashboard.blade.php` dan partial
  `operational-dashboard-*`: halaman, grafik dan tabel.
- `public/css/operational-dashboard.css`, `public/js/report-browser.js` existing:
  tampilan responsif dan interaksi AJAX.
- `app/Exports/OperationalDashboardExport.php`, `app/Exports/Sheets/Dashboard*.php`:
  Excel baru melalui endpoint laporan operasional jika parameter scope diberikan.
  Jalur legacy tanpa scope tetap tersedia.

Tidak ada migration/seeder/dependency baru untuk dashboard ini. Tabel dan
migration fitur ekspor sebelumnya tetap menjadi prasyarat. Query ini membaca
data/histori existing tanpa backfill. Dashboard tidak mengubah reservasi, FIFO,
konfirmasi penempatan atau prosedur penyelesaian permintaan.

## Verifikasi

- Regresi penuh: **501 tes backend, 4.609 assertions**. Setelah pembaruan akhir
  label/grafik/Excel: **58 tes terkait, 607 assertions**. **87 tes frontend** lulus.
- OperationalDashboardScopeTest memeriksa benturan ID lintas lingkup, stok lebih
  dari 200 baris, legacy/posisi ganda, draft, arsip selesai, pembatalan/pengembalian,
  reservasi vs penempatan, pallet siap vs pengiriman, split delivery, unit/grafik,
  batas tanggal, audit/pagination, validasi, izin dan struktur XLSX native.
- Query Semua/Domestik/Ekspor berhasil pada MySQL lokal `stock` dalam transaksi
  **READ ONLY**, tanpa menulis data. Production remote tidak diakses.
- Browser memakai SQLite preview khusus, bukan MySQL: desktop 1366px dan ponsel
  390px; filter AJAX, audit/pagination/Back, tanggal 422/pemulihan, Excel download,
  keyboard/fokus, grafik/tabel dan tautan rincian laporan ekspor berhasil. Tidak
  ada overflow halaman atau error konsol tak terduga.
- Belum merupakan pengukuran beban atau stress test pada data production.
