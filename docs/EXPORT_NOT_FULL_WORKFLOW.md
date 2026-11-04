# Box Not Full ekspor

Diverifikasi 31 Oktober 2026, branch `Export-plan`.

Kebutuhan 100 PCS dengan master 80 PCS/box menjadi **1 box penuh 80 PCS dan
1 Not Full 20 PCS**, total **2 box**. Kapasitas master tidak diubah. Aturan ini
berlaku untuk input manual dan Excel.

## Dari dokumen sampai pemenuhan

1. Pratinjau menandai sisa PCS dan menghitung box fisik dengan pembulatan ke atas.
   Membaca file belum membuat request/stok.
2. Saat rencana disimpan, satu request wajib dicatat untuk setiap part bersisa.
   Alert menjelaskan kebutuhan dan meminta operator menghubungi admin. Retry
   token atau edit rencana yang sama mempertahankan satu kebutuhan; rencana
   berbeda memiliki request tersendiri. Perubahan kebutuhan ditelusuri di audit.
3. Perencanaan memisahkan pilihan part berdasarkan kapasitas: contoh `99 · 80
   PCS/box` dan `99 · Not Full 20 PCS/box`. Keduanya boleh dalam satu pallet,
   dengan alokasi exact. Setiap Not Full tetap satu box pada total/warning 36/72.
4. Finalisasi mengalokasikan stok sesuai part+PCS melalui FIFO existing.
   Box penuh memenuhi slot penuh; stok Not Full 20 PCS memenuhi slot 20 PCS.
   Stok yang direservasi mengikuti antrean dan konfirmasi penempatan existing.
5. Jika kebutuhan Not Full belum memiliki stok, Admin Warehouse/Admin Utama
   menyiapkan data setelah pallet final. Tujuan berasal dari rencana, bukan
   pilihan lokasi bebas. Tersedia dua opsi:
   - **Datang sudah dikemas Not Full:** ID baru berisi jumlah sisa yang tepat.
   - **Ambil dari box penuh di stok:** sistem memilih full box tertua yang masih
     bebas setelah reservasi kebutuhan penuh, pada part/kapasitas yang sesuai.
6. Admin Utama/Supervisi menyetujui dengan konfirmasi fisik. Approval langsung
   mencatat stok dan penempatan ke pallet rencana. Box hasil tidak perlu diterima
   atau dipindai ulang melalui Penerimaan Transfer Box Ekspor.
7. Permintaan hanya dapat selesai setelah setiap pallet berisi tepat kebutuhan
   dan sudah dikonfirmasi siap. Rejection tidak menghilangkan kebutuhan Not Full.

## Pemecahan dan FIFO

Untuk sumber 80 PCS diambil 20 PCS: hasil 20 memakai ID baru, sisa 60 memakai ID
dan lokasi sumber. Keduanya mewarisi tanggal penerimaan awal. Total PCS tetap 80.
Hanya box penuh yang dipilih pada opsi pemecahan; stok Not Full yang cocok dapat
langsung memenuhi kebutuhan melalui alokasi FIFO, tanpa membuat box baru.

Penyiapan data tidak menahan sumber dari reservasi penuh yang datang kemudian.
Saat approval, sistem memeriksa ulang sumber tertua, revision, status bebas,
issue, lokasi dan tujuan final. Jika berubah, data wajib disiapkan ulang.
Sumber yang terreservasi atau pallet ready tidak boleh dipecah. Jika tidak ada
sumber bebas, request menunggu stok atau menggunakan opsi datang sudah dikemas.

Mutasi sumber, pencatatan anak, penempatan, status dan audit berada dalam satu
transaksi/mutex inventory. Kegagalan penempatan membatalkan seluruh perubahan.
Versi request menolak tinjauan lama dan retry approval tidak memecah ulang.
ID sumber/hasil tetap terpisah dari identitas lokal.

## Akses, histori dan revisi

- Menu Box Not Full existing mempunyai pilihan Lokal/Ekspor. Badge menunjukkan
  kebutuhan ekspor belum terpenuhi, termasuk yang menunggu planning/approval.
  Kebutuhan terbuka tampil sebelum histori, diurutkan dari tenggat terdekat.
- Admin Warehouse/Admin Utama menyiapkan; Admin Utama/Supervisi approve/reject.
  Izin fitur existing dan guard role server tetap berlaku. Operator melihat
  status pada rencana delivery, perencanaan pallet dan pemenuhan.
- Request menyimpan snapshot penyiapan; audit mempertahankan perubahan sumber,
  tujuan, jumlah dan keputusan. Mengubah rencana tidak mengubah stok fisik diam-diam.
- Revisi memakai jumlah PCS aktual per varian. Menghapus slot 20 dari 100 menyisakan
  80; menghapus slot 80 menyisakan 20. Box yang sudah ditempatkan mengikuti alur
  pengembalian, dan kebutuhan yang hilang membatalkan request. FIFO tidak direset.
- `origin=split` tidak menjadi penerimaan eksternal baru. Snapshot penerimaan
  sumber tetap 80 walaupun stok kini 60. Box datang sudah Not Full merupakan
  inbound eksternal. Dashboard/laporan memakai aturan yang sama.
  Pemecahan menambah jumlah kemasan fisik dengan total PCS yang tetap; audit
  menelusuri perubahan jumlah box dari aktivitas internal tersebut.

## Implementasi dan deployment

File utama: `ExportNotFullService`, `ExportBoxRequirements`,
`ExportNotFullController`, `ExportNotFullRequest`, perubahan delivery/planning,
receiving/revision/report service, dan view `export/not-full`.

Migration `2026_10_31_000001_support_export_not_full_boxes.php` menambah request
ekspor, metadata box dan sisa PCS delivery, serta unique pallet+part+kapasitas.
Data lama tidak dibulatkan atau diubah; default sisa 0 cocok dengan rencana lama
yang wajib kelipatan. Tidak ada dependency atau seeder baru.

Migration sudah diterapkan ke **MySQL development lokal stock/127.0.0.1** setelah
backup `storage/app/backups/stock_export_not_full_20261031_191724_0955cd.json`.
Kolom/data lama pada 15 tabel identik, 4 rencana/8 stok lama tetap; request baru
lokal 0. Production belum diubah. Deployment production memerlukan migration,
build aset dan pembaruan cache konfigurasi/route sesuai prosedur deployment.

Validasi: regresi penuh **518 backend / 4.731 assertions**; setelah guard akhir,
**25 tes terkait / 173 assertions**; **88 frontend** dan Vite build lulus.
Browser SQLite khusus memeriksa Excel, alert, planning 80+20, FIFO penuh,
penyiapan Admin Warehouse, approval Supervisi, hasil 20 dan sisa 60, serta
desktop 1366px/ponsel 390px. Tidak ada overflow halaman/error konsol tak terduga.
Scanner/printer fisik dan konkurensi production tidak diuji.
