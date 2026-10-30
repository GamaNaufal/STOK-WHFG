# Penanganan pengecualian ekspor

Terakhir diverifikasi: 30 Oktober 2026. Branch Export-plan.

## Revisi dan pembatalan

Pada Pemenuhan Permintaan, tombol Revisi pallet tersedia sejak perencanaan final,
termasuk pallet kosong atau terisi sebagian. Isi jumlah yang tetap dibutuhkan.
Tinjauan membedakan box wajib pengembalian dari reservasi yang cukup dilepas.
Box terbaru dipilih menurut tanggal pertama diterima menurun dan ID stok terbesar;
box tertua dipertahankan untuk pengiriman FIFO. Pengurangan tidak menambah kebutuhan.

- Kebutuhan belum terisi: jumlah dikurangi tanpa membuat movement/pengembalian.
- Reservasi belum diambil: dilepas pada posisi semula, revision box dinaikkan.
- Sudah ditempatkan atau diverifikasi diambil: wajib memilih pallet existing
  belum siap atau lokasi kosong dan mengonfirmasi pengembalian fisik.
- Semua part nol: pallet dihapus setelah box dikembalikan dan klaim lokasi dilepas.
  Nomor pallet lain tidak berubah. Jika pallet terakhir dihapus, request dibatalkan.
- Batalkan permintaan: tinjau semua pallet, isi alasan dan kembalikan box ke tujuan
  cadangan di luar request. Delivery cancelled diarsipkan; box tetap stok cadangan.

Reservasi, sumber, kesiapan, fingerprint, revision dan tujuan diperiksa ulang saat
konfirmasi. Tinjauan lama setelah penerimaan baru ditolak. Issue picking terbuka
tetap memblokir. Token retry tidak menggandakan aksi meskipun parent sudah arsip.
Audit gagal membatalkan seluruh transaksi. Konfirmasi siap dan pengiriman tetap
memerlukan seluruh isi sesuai tepat, tidak dilonggarkan oleh revisi parsial.

Laporan revisi/pengembalian memakai jumlah box/PCS fisik yang dikembalikan.
Pengurangan tanpa pengembalian fisik mendapat status tersendiri; daftar reservasi
yang dilepas tetap ada pada snapshot audit meskipun komitmennya sudah dihapus.

## Salah input scan tanpa box fisik

Admin/Admin Warehouse membuka Scan Issues dan Approval, lalu pilihan Salah input
tanpa box fisik pada baris ekspor. Isi alasan dan pernyataan tidak ada box fisik
diambil. Issue menjadi input_error dan berhenti memblokir, tanpa membuat box atau
movement maupun menandai penempatan dikonfirmasi. ID historis tetap arsip.

Jika box fisik benar-benar diambil, gunakan persetujuan tujuan pengembalian existing;
blokir baru dibuka sesudah operator pelapor mengonfirmasi penempatan. Revokasi izin,
role, revision, token dan audit tetap diperiksa pada backend.

## Kapasitas master

Perubahan PCS per box master ekspor ditolak jika part memiliki kebutuhan positif
pada rencana delivery aktif, termasuk draft. Simpan nilai yang sama tetap boleh.
Setelah semua request terkait selesai, dibatalkan atau diarsipkan, kapasitas baru
boleh disimpan. Snapshot histori/rencana terdahulu tetap. Master domestik independen.
Mutex ekspor mendahului lock part agar perubahan tidak berlomba dengan rencana baru.

## Verifikasi dan deployment

Perubahan kode tidak menambah migration, dependency, seeder atau konfigurasi PC.
Pengujian memakai SQLite in-memory dan browser memakai database SQLite preview
khusus. MySQL lokal dan production tidak dimutasi. Tidak mencakup poin review
duplikasi impor maupun cutover migration QA, yang berada di luar perbaikan 1–3.

Regresi penuh: 468 backend lulus (4.314 assertions), 87 frontend lulus.
Verifikasi akhir penyajian audit dan alur terkait: 82 tes lulus (896 assertions).
Browser preview memeriksa revisi parsial memilih box terbaru, halaman pembatalan
desktop 1366px dan ponsel 390px tanpa overflow halaman, serta formulir alasan dan
pernyataan salah input admin. Tidak ada error konsol. Tidak menguji perangkat
scanner/printer fisik atau melakukan deployment production.
