# Pemenuhan Permintaan Ekspor

Verifikasi 29 Oktober 2026, branch aktif Export-plan.

## Cakupan tahap ini

Halaman pemantauan dan fondasi pencatatan per nomor box sudah tersedia. Tahap ini
belum menyediakan input/scan box, penerimaan stok ekspor, pemilihan box FIFO
otomatis, pemindahan, atau pengembalian. Tidak ada penghitungan stok domestik
sebagai pemenuhan ekspor dan tidak ada tombol untuk menaikkan jumlah secara manual.

- Izin `export_fulfillment`, bawaan Operator Export dan dapat diatur melalui
  Hak Akses. Tidak bergantung pada izin mengubah delivery atau perencanaan.
- GET `/export/fulfillment` menampilkan daftar, pencarian kode/customer, filter
  status, pagination 15 rencana, dan urutan tenggat lalu ID delivery.
- GET `/export/fulfillment/{deliveryPlan}` menampilkan progres, kebutuhan tiap
  part/pallet/lokasi, dan daftar box. Rincian pallet dapat dibuka dengan keyboard.
  Daftar box dipaginasi 25 baris, urut tanggal pertama diterima lalu nomor box.
- Menu tersedia di navigasi dan dashboard ekspor. Perencanaan final mempunyai
  tautan menuju pemantauannya. Tombol Perbarui tampilan mengambil data terkini;
  tidak ada polling otomatis.

## Rencana yang masuk pemantauan

Finalisasi menandai `export_pallet_plans.entered_fulfillment = true` dan rencana
langsung tersedia tanpa salinan header/item. Draft yang belum pernah final tidak
ditampilkan. Rencana yang dibuka untuk revisi tetap terlihat sebagai Perlu revisi,
termasuk setelah semua lokasi dilepas. Finalisasi ulang menggunakan entitas yang
sama. Pembatalan dan arsip delivery mengeluarkan rencana dari daftar aktif.

Migration mengisi penanda untuk rencana existing yang final, masih mempunyai
klaim lokasi, atau mempunyai audit finalisasi. Tidak mengubah kebutuhan, status,
revision, occupancy, atau klaim lokasi yang sudah ada.

## Sumber angka pemenuhan

`export_fulfillment_boxes` adalah catatan komitmen aktif per nomor box, bukan tabel
penerimaan inventory. Satu nomor box hanya boleh mempunyai satu komitmen aktif
di tabel ini. Relasi mengarah ke item pallet, dengan part/PCS, tanggal penerimaan
pertama, waktu/aktor reservasi, serta waktu/aktor/lokasi penempatan. FK membatasi
penghapusan referensi. Nomor box dipertahankan sebagai string termasuk nol awal.

- Kebutuhan memakai item pallet dan snapshot PCS/box hasil perencanaan.
- Reservasi: part/PCS sesuai snapshot dan belum ada konfirmasi penempatan.
- Terpenuhi: part/PCS sesuai, waktu dan operator konfirmasi terisi, serta lokasi
  penempatan sama dengan lokasi pallet tujuan.
- Catatan yang tidak sesuai tidak dihitung sebagai reservasi/terpenuhi dan
  menimbulkan status Perlu diperiksa; part aktual dan acuan dapat dilihat.
- Sisa belum ditempatkan adalah penjumlahan kekurangan tiap item, termasuk
  reservasi yang belum ditempatkan. Belum direservasi juga dihitung per item.
  Kelebihan pada part/pallet lain tidak dapat menutupi kekurangan tersebut.
- Progres hanya menghitung box yang menutup kebutuhan masing-masing item,
  sehingga kelebihan pada satu part tidak menghasilkan progres 100% palsu.
- Status Terpenuhi memerlukan seluruh kebutuhan sesuai, tanpa ketidaksesuaian
  atau kelebihan. Terpenuhi pada tahap ini bukan status sudah dikirim/withdrawn.

Saat ini tabel operasional baru tetap kosong sampai modul penerimaan terhubung.
Contoh dengan angka nonnol hanya berada pada database preview dan tes.

## Perlindungan revisi dan integrasi tahap berikutnya

Selama ada catatan komitmen box, simpan ulang pembagian, buka revisi, pembatalan,
dan perubahan delivery ditolak agar alokasi/penempatan tidak terhapus diam-diam.
Perubahan delivery yang sudah mulai ditulis dibatalkan oleh transaksi yang sama
jika pemeriksaan komitmen gagal. Ini pembatasan sementara sampai alur pelepasan
reservasi/pengembalian tersedia, bukan pengganti proses pengembalian fisik.

Modul penerimaan berikutnya harus mengunci delivery lalu perencanaan sebelum
menulis komitmen, memvalidasi seluruh data, mencegah kelebihan/duplikat secara
atomik, dan mencatat audit pada transaksi yang sama. Tidak ada endpoint write
untuk catatan komitmen pada tahap pemantauan ini. Riwayat pelepasan harus diaudit
sebelum catatan komitmen aktif diubah/dilepas.

Kesepakatan berikutnya tetap: pilih delivery final bertenggat terdekat lalu nomor
pallet terkecil; FIFO berdasarkan tanggal pertama diterima; reservasi box stok
tersedia; stok cadangan memakai lokasi/pallet pilihan. Revisi harus mempertahankan
FIFO dan mengembalikan box terbaru terlebih dahulu ke lokasi/pallet pilihan.
Keunikan identitas box lintas domestik/ekspor dan format payload scanner perlu
dipastikan pada tahap input box. Pemantauan ini tidak mengalihkan peruntukan box.

## Deployment dan verifikasi

File utama: `ExportFulfillmentController`, `ExportFulfillmentService`,
`ExportFulfillmentBox`, `resources/views/export/fulfillment`, dan
`public/css/pages/export-fulfillment.css`. Tidak ada dependency atau layanan baru.

Migration `2026_10_29_000001_create_export_fulfillment_monitoring.php` menambah
penanda monitoring, tabel komitmen kosong, dan izin pada pengaturan Operator
Export existing beserta audit. Sesudah migration ekspor sebelumnya tersedia,
jalankan migration ini secara terarah sesuai prosedur deployment; jangan memakai
langkah ini untuk menjalankan cutover QA/packing yang belum ditinjau. Rollback
otomatis ditolak karena harus meninjau komitmen/penempatan terlebih dahulu.

Regresi 338 tes backend lulus (2.806 assertions). Pemeriksaan terakhir 13 tes
pemenuhan lulus (110 assertions), termasuk backfill histori final/revisi dan
preservasi izin. Cakupan meliputi isolasi inventory domestik, hak akses/revokasi,
snapshot kapasitas, reservasi vs penempatan, data tidak sesuai, kelebihan lintas
part, revisi atomik, identitas unik, pagination, serta urutan tenggat/FIFO tampilan.
Browser Chromium memeriksa pencarian/status/reset/hasil kosong, rincian pallet
dengan keyboard, detail box, desktop 1440x900 dan simulasi 390x844, tanpa error
konsol. Simulasi tidak merupakan pengujian scanner atau perangkat ponsel nyata.

MySQL lokal `stock` diperiksa baca saja pada 29 Oktober: migration dan kolom/tabel
baru sudah tersedia, izin operator aktif, query agregasi berjalan, 0 komitmen box
dan 0 permintaan masuk pemantauan. Agent tidak menjalankan ulang migration atau
menambahkan data uji pada MySQL lokal. Semua data contoh memakai SQLite preview
terpisah; database production remote tidak diubah.
