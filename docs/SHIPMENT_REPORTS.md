# Laporan Pengiriman Lokal dan Ekspor

Pembaruan 15 November 2026: rincian box pada halaman detail dan sheet Rincian Box
memuat nomor penggunaan ID Box. Nomor yang dipakai ulang memiliki record stok
baru, sehingga pengiriman lama tetap membaca penggunaan aslinya. Kebijakan:
[Penggunaan ulang ID Box](BOX_NUMBER_REUSE.md).

Diverifikasi 15 November 2026. Menu **Laporan Pengiriman** menggantikan nama
Laporan Pengambilan. URL lama `/reports/withdrawal` dan endpoint Excel-nya tetap
berlaku. Pilihan jenis laporan Pengiriman pada laporan lintas lingkup membuka
tampilan pengiriman yang sama.

## Satuan dan perhitungan

- Lokal: satu kejadian pengiriman per `delivery_pick_sessions.id`. Beberapa
  pengiriman untuk order yang sama tetap menjadi baris terpisah. Baris box pada
  `stock_withdrawals` tidak dihitung sebagai sejumlah delivery yang berbeda.
- Ekspor: satu kejadian pengiriman per planning yang memiliki `completed_at`.
  Pallet siap dan kebutuhan terpenuhi belum dihitung sebagai selesai dikirim.
  Rencana serta box yang sudah diarsipkan tetap dibaca untuk laporan ini.
- Histori Redo/pengembalian Lokal ditampilkan terpisah dari pengiriman selesai.
  Kejadian dan kuantitas historisnya tetap terlihat, barang yang berstatus Redo
  tidak menambah barang terkirim netto. Campuran status dalam satu kejadian
  ditandai Perlu diperiksa.
- Transaksi lama tanpa hubungan sesi tidak ditebak identitas delivery/customer-nya.
  Baris dikelompokkan menurut batch jika ada, atau per catatan asli. Jumlah barang
  tetap diperhitungkan sesuai status. Catatan tanpa referensi ini tidak menambah
  metrik Delivery selesai.
- Sesi selesai yang belum memiliki withdrawal masih dapat dibaca melalui rincian
  picking tersimpan. Jika rincian tidak tersedia, waktu/identitas yang belum ada
  ditandai belum tercatat, bukan diisi dari posisi stok saat ini.
- Nomor part yang berbeda huruf besar/kecil tetap terpisah pada agregasi part.
  Nomor dokumen ekspor memakai snapshot delivery, bukan pemetaan master terbaru.

## Layar dan filter

Ringkasan menampilkan seluruh histori pada lingkup terpilih: delivery selesai,
PCS dan box terkirim netto, serta kejadian Redo. Tabel dimulai dari pengiriman
selesai dan dapat beralih ke histori Redo atau semua catatan.

Tanggal mengikuti waktu pengiriman selesai, bukan tenggat delivery. Customer,
nomor part, lokasi, operator, ID Box, referensi delivery dan catatan dapat dicari.
Pencarian part/box/lokasi/operator memilih seluruh kejadian terkait, tidak
memotong isi delivery. Tabel dan Excel mengikuti filter, ringkasan seluruh
histori tetap menunjukkan seluruh lingkup.

Tombol **Lihat seluruh keterangan** membuka identitas delivery/sesi, customer,
tenggat, waktu proses/pengiriman/Redo, kuantitas, catatan delivery dan approval,
agregasi part serta setiap box dengan pallet/lokasi tercatat, scan/verifikasi,
penempatan dan petugas yang tersedia. Rincian part dan box memiliki pagination
terpisah. Riwayat tidak memerlukan akses ke halaman operasi gudang.

CSS laporan pengiriman dimuat melalui `operator/reports/ajax-styles` bersama
gaya laporan lain. Pergantian jenis/lingkup melalui AJAX tetap memperoleh kartu,
filter dan tabel yang sama seperti saat halaman dibuka langsung.

## Excel dan hak akses

Excel memiliki tepat tiga sheet:

1. Ringkasan Delivery, satu baris per kejadian sesuai filter.
2. Rincian Part, agregasi part/status untuk kejadian yang sama.
3. Rincian Box, seluruh baris beserta waktu, petugas dan catatan.

Unduhan daftar mencakup semua halaman hasil filter. **Export delivery ini**
menghasilkan ketiga sheet hanya untuk kejadian yang dibuka. ID dan nomor part
disimpan sebagai teks, termasuk nol depan dan teks yang menyerupai formula.
Kuantitas disimpan numerik tanpa pembulatan box pecahan ke atas, tanggal sebagai
tanggal Excel. Sheet memiliki header tetap, filter dan pembungkusan catatan.
Pembuatan workbook berlangsung dalam transaksi baca, tanpa mutex inventory.

Izin `reports` membuka daftar/rincian. Izin `reports_download` beserta prasyaratnya
diperlukan untuk unduhan daftar maupun delivery tunggal. Mengubah filter atau
membuka laporan tidak menulis stok, transaksi maupun audit.

## Retensi dan deployment

`delivery:purge-completions` dipertahankan untuk kompatibilitas scheduler,
tetapi kini tidak menghapus sesi atau item delivery selesai. Masa Redo tetap
mengikuti pemeriksaan operasional existing. Data yang sudah dihapus oleh perintah
lama tidak dapat dipulihkan oleh laporan, catatan withdrawal yang tersisa tampil
sebagai batch/catatan lama sesuai hubungan yang masih tersedia.

Perubahan laporan ini tidak menambah migration. Terapkan controller/service,
kelas export, view, CSS, route dan konfigurasi navigasi yang berubah bersama-sama,
bersihkan atau bangun ulang cache route/view sesuai proses deployment.
Prasyarat skema dari fitur master dokumen dan hak akses sebelumnya tetap berlaku.
Tidak ada migration maupun perubahan data operasional yang dijalankan untuk
penerapan laporan ini pada sesi pengembangan.

File utama: `ShipmentReportService`, `ShipmentReportController`,
`ShipmentReportExport`/`ShipmentReportSheet`, view `operator/reports/shipments`
dan `shipment-detail`, serta `public/css/pages/report-shipments.css`.
