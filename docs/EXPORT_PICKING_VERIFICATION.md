# Verifikasi pengambilan box ekspor

Terakhir diverifikasi: 30 Oktober 2026.

## Dua tahap scan

Menu **Picking Verification Ekspor** menyediakan alat bantu mencari box FIFO
yang sudah direkomendasikan saat finalisasi rencana pallet. Hanya tujuan yang
memiliki pengambilan rekomendasi tersebut dari pallet campuran yang masuk daftar
ini, urut tenggat delivery, ID delivery, lalu nomor
pallet. Scan salah menampilkan NG dan operator dapat
langsung mencoba lagi. Pencarian tidak menerbitkan sertifikat pengambilan,
membuat issue, mengubah reservasi, atau memindahkan inventory.

**Verifikasi pengambilan wajib** dibuka dari kelompok penempatan yang memerlukan
scan. Setiap box rekomendasi dari pallet asal campuran harus cocok dengan nomor box pada
reservasi, termasuk jika box lain memiliki part yang sama. Setelah scan benar,
konfirmasi penempatan fisik tetap diperlukan pada transaksi penerimaan asal.
Penerimaan/input box baru cukup mengikuti scan input dan konfirmasi penempatan,
tanpa verifikasi pengambilan tambahan. Alokasi saat penerimaan berikutnya juga
dikecualikan walaupun sumber atau tujuannya berisi beberapa jenis part.

Komposisi diperiksa dari seluruh isi fisik `current_pallet_id`, bukan hanya
part yang dibutuhkan atau tujuan reservasinya. Pallet satu jenis part dapat
menyuplai sebagian isi tanpa scan satuan, misalnya 36 dari 72 box. Sistem tetap
menggunakan identitas 36 box yang telah direservasi menurut FIFO.

Asal rekomendasi ditentukan pada backend melalui `allocation_notice_id` yang
terisi untuk alokasi finalisasi rencana pallet. Flag `picking_required` saja
tidak cukup mewajibkan verifikasi pada alokasi tanpa notice. Flag wajib scan
dicatat untuk rekomendasi ini saat reservasi dari pallet campuran dan ketika
source menjadi campuran. Flag tetap melekat pada reservasi tersebut walaupun
part lain kemudian dipindahkan. Bukti scan terikat ke box, revision, dan pallet
asalnya; pencarian atau perubahan data dari browser tidak bisa menggantikannya.

## Kesalahan dan pengembalian

Nomor box harus tepat delapan digit ASCII. Leading zero dipertahankan. Input
seperti `123`, huruf, angka lebih panjang, atau digit full-width mendapat NG
tanpa issue atau blokir baru. Input tidak dipotong atau ditambah nol agar cocok.

Scan salah dengan nomor delapan digit pada tahap wajib membuat issue ekspor
yang tersimpan di database. Refresh, logout, retry, atau POST langsung ke
penempatan tidak membuka blokir. Pengambilan terkait, operator pelapor, dan
box yang salah dilindungi dari pemindahan sampai penanganan selesai. Bukti
verifikasi lama milik box yang salah dibatalkan agar tidak digunakan kembali.

| Status issue | Tindakan berikutnya | Kondisi blokir |
| --- | --- | --- |
| `pending` | Admin Warehouse meninjau dan menyetujui arahan | Tetap aktif |
| `awaiting_return` | Operator pelapor menempatkan box sesuai arahan, lalu mengonfirmasi | Tetap aktif |
| `returned` | Proses pengambilan dapat dilanjutkan jika tidak ada issue terbuka lain | Issue ini dilepas |

Approval memakai halaman **Scan Issues dan Approval** yang sama dengan lokal.
Setiap baris pending/riwayat memiliki label **Ekspor** atau **Domestik / Lokal**.
Alur approval domestik tetap seperti sebelumnya.
Indikator issue pending pada sidebar dan dashboard Admin Warehouse menghitung
approval domestik dan ekspor yang masih menunggu keputusan.

- Box diketahui dan lokasi asal valid: tujuan tetap pallet/lokasi asal.
- Box diketahui tetapi lokasi asal belum tersedia: admin wajib memilih lokasi
  kosong atau pallet ekspor existing. Part, PCS, dan tanggal pertama diterima
  tetap dipertahankan.
- Box belum tercatat pada stok ekspor: admin wajib memilih nomor part dari
  master ekspor dan tujuan pengembalian. PCS mengikuti kapasitas master dan
  disimpan sebagai snapshot approval. Data domestik dengan nomor box sama
  tidak dipakai untuk membuat atau mengoreksi stok ekspor.

Approval hanya menyimpan arahan. Box baru didaftarkan dan posisi fisik box yang
belum memiliki lokasi baru dicatat ketika operator mengonfirmasi penempatan.
Tanggal pertama untuk box baru menggunakan waktu pertama box tersebut terdeteksi
pada issue. Pengembalian ke posisi asal yang sudah benar tidak menambah stok
atau mengubah FIFO. Komitmen monitoring legacy, jika ada, harus sesuai part/PCS
sebelum dikaitkan dengan penerimaan inventory.

Jika lokasi menjadi terisi atau kapasitas part berubah setelah approval,
konfirmasi ditolak dan admin dapat memperbarui arahan. Operator harus memakai
revision terbaru. Blokir baru dilepas setelah konfirmasi penempatan yang valid;
request token melindungi retry agar tidak membuat box atau movement ganda.

## Integrasi dan pengamanan

- `ExportPickingService`: daftar rekomendasi, pencarian, scan wajib, bukti scan,
  dan pemeriksaan blokir sebelum penempatan satuan/bulk.
- `ExportPickingIssueService`: approval serta konfirmasi pengembalian atomik.
- `ExportInventoryService`: mutex ekspor yang sama dengan reservasi, penerimaan,
  dan penempatan. FIFO tidak dialokasikan dari box yang sedang ditahan issue.
- `ExportStockViewService`: instruksi verifikasi/pengembalian pada transaksi
  existing; tombol konfirmasi penempatan muncul setelah prasyarat terpenuhi.
- `DeliveryPickController::issues` dan partial `export-issue-row`: approval
  lokal/ekspor bersama dengan endpoint mutasi yang terpisah.
- Izin `export_picking_verification` memerlukan `export_stock_input`. Scan wajib
  dan konfirmasi pengembalian memakai izin penerimaan existing; approval memakai
  `delivery_issues`. Hanya operator pelapor yang mengonfirmasi pengembalian.
- Semua perubahan penting dicatat pada audit. Rencana dengan issue aktif tidak
  dapat direvisi untuk melewati penanganan.

## Migration dan deployment

Migration `2026_10_30_000002_create_export_picking_verification` menambah metadata
bukti scan pada `export_fulfillment_boxes` serta tabel `export_picking_issues`
dan `export_picking_scans`. Migration menandai reservasi existing dari source
campuran tanpa mengubah identitas atau posisi box; backend tetap mensyaratkan
asal rekomendasi finalisasi (`allocation_notice_id`) sebelum mewajibkan scan.
Pembatasan asal rekomendasi tidak memerlukan migration tambahan. Jika pengaturan izin
operator ekspor tersimpan dan masih memiliki izin penerimaan, menu baru
ditambahkan dengan audit; role yang izinnya kosong tetap tidak mendapat akses.

Jalankan migration dan build aplikasi pada deployment:

```sh
php artisan migrate --force
npm run build
```

Perbarui cache konfigurasi/route sesuai prosedur deployment proyek. Tidak ada
dependency, layanan background, atau konfigurasi Chrome/PC baru. Rollback otomatis
ditahan karena issue, bukti scan, dan pengembalian harus direkonsiliasi dahulu.

## Hasil verifikasi

- Regresi penuh: 422 tes backend, 3.802 assertions; 80 tes frontend; Vite build.
- Koreksi lingkup scan: 17 tes picking, 194 assertions. Penerimaan bulk baru
  setelah finalisasi serta alokasi penerimaan berikutnya dari source campuran
  dapat dikonfirmasi tanpa scan tambahan, termasuk jika flag lama masih terisi.
  Browser preview mengonfirmasi penempatan dua box input langsung tanpa
  verifikasi tambahan; tugas rekomendasi campuran tetap tersedia, konsol bersih.
- Setelah penyempurnaan detail box selesai dan indikator approval: 36 tes
  terkait lulus, 494 assertions. Box selesai tidak diminta verifikasi ulang.
  Penyesuaian urutan tenggat dilanjutkan 15 tes picking (173 assertions) dan
  pemeriksaan query tasks secara baca saja pada MySQL lokal.
- Browser memeriksa helper NG tanpa blokir, input 123, salah box FIFO, refresh,
  approval Admin Warehouse, konfirmasi pengembalian, registrasi box baru,
  kapasitas/posisi hasilnya, scan benar, serta penempatan satu part tanpa scan.
  Contoh hanya database SQLite `export-picking-preview.sqlite`; bukan uji
  scanner fisik.
- Migration diterapkan pada MySQL lokal `stock`, host `127.0.0.1`, APP_ENV local.
  Backup `storage/app/backups/stock_export_picking_20261030_173847_0c056a.json`.
  Hash seluruh kolom existing dan jumlah baris inventory lama tetap sama,
  termasuk 12 box domestik, 8 box ekspor dan 8 komitmen. Tabel issue/scan baru
  kosong; tidak ada contoh data di MySQL atau perubahan production remote.
