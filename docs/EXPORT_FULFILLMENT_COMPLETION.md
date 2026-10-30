# Kesiapan pallet, pengembalian FIFO, dan penyelesaian ekspor

Terakhir diverifikasi: 30 Oktober 2026.

## Konfirmasi pallet siap

Setiap pallet pada Pemenuhan Permintaan memiliki tombol konfirmasi setelah semua
kebutuhan part/PCS/box telah ditempatkan tepat. Pop-up menampilkan rincian part,
kapasitas dan total box/PCS. Operator wajib memilih lokasi fisik, boleh lokasi
semula atau lokasi ekspor kosong, serta mengonfirmasi pallet sudah berada di sana.
Pallet terisi lain dan lokasi yang direservasi tidak dapat dipakai sebagai lokasi baru.

Backend memeriksa seluruh isi fisik, bukan hanya angka progres. Komitmen legacy
tanpa penerimaan fisik, box tambahan di luar permintaan, posisi tidak konsisten,
dan issue picking terbuka menghalangi konfirmasi. Perpindahan seluruh pallet
memperbarui lokasi fisik/rencana/reservasi/konfirmasi box dan movement, membebaskan
lokasi asal, serta mempertahankan tanggal pertama diterima untuk FIFO.

Status siap mengunci penambahan/pengurangan box melalui penempatan existing dan
alokasi otomatis. Pengembalian koreksi picking juga tidak dapat menambah isi pallet
siap. Mengembalikan box yang memang sudah tercatat pada posisi asal tidak mengubah isi.

## Revisi dan pengembalian

Revisi pallet menyediakan pembukaan ulang kesiapan untuk koreksi lokasi tanpa
mengubah jumlah. Pallet harus dikonfirmasi siap lagi sebelum permintaan selesai.

Untuk pengurangan, operator mengisi jumlah box yang tetap diperlukan per part.
Tahap ini hanya mendukung pengurangan dan menyisakan minimal satu box pada pallet.
Tinjauan menampilkan nomor box yang harus diambil menurut first_received_at lalu
ID stok, khusus dari part dan pallet tersebut. Membuka tinjauan tidak mengubah data.

Operator wajib memilih satu pallet ekspor existing yang belum siap (selain pallet
asal) atau lokasi ekspor kosong, kemudian mengonfirmasi seluruh box yang ditunjuk
sudah dikembalikan di sana. Lokasi kosong menjadi pallet cadangan baru. Pilihan
tersebut diperiksa ulang saat simpan; lokasi terisi/direservasi atau fingerprint
box/revision yang berubah ditolak atomik.

Setelah konfirmasi, sistem melepas reservasi box terpilih, mencatat movement
pengembalian, mengurangi kebutuhan part pada pallet dan jumlah rencana delivery,
memperbarui total PCS/box serta revision delivery/planning, dan membuka ulang
kesiapan pallet tersebut. Pallet lain yang siap tetap siap. Box hasil pengembalian
tetap stok cadangan dengan waktu FIFO semula; tidak langsung dialokasikan ulang
oleh aksi revisi. Part yang habis pada pallet dihapus dari alokasinya; rincian
delivery bernilai nol tetap menjadi snapshot, tanpa menghapus master part.

## Selesaikan permintaan

Tombol selesai aktif setelah seluruh pallet siap. Alert menegaskan **seluruh box
permintaan sudah keluar gudang**, disertai jumlah pallet/box dan checkbox wajib.
Backend memeriksa kembali kesiapan, isi, lokasi, reservasi dan issue picking.

Penyelesaian mengarsipkan delivery dan box terkait (soft delete), menandai box
shipped, melepaskan posisi aktif, meretir pallet fisik, membebaskan lokasi dan
melepas reservasi. Permintaan selesai hilang dari daftar aktif delivery/planning,
pemenuhan, stok, antrean dan Excel stok. Stok cadangan serta master tidak dihapus.
Identitas/hubungan histori tetap disimpan; registrasi ulang nomor historis tidak
dibuat otomatis. Rincian tetap tersedia melalui audit trail dengan izin existing.

Audit export_fulfillment menyimpan kode/customer, kebutuhan, pallet/lokasi/
kesiapan dan semua box/part/PCS/tanggal FIFO sebelum penyelesaian, berikut aktor
dan waktu aksi. Filter audit mempunyai jenis Pemenuhan Permintaan Ekspor dan aksi
siap, pengurangan, buka revisi serta selesai. Rincian memakai snapshot, sehingga
tidak bergantung pada stok yang masih aktif.

Semua mutasi memakai mutex ExportInventoryService yang sama, revision dan token
request unik. Retry tidak menggandakan movement/pengurangan/audit. Audit gagal
membatalkan seluruh mutasi termasuk arsip dan pembebasan lokasi. Izin tetap
export_fulfillment; tidak ada hak akses, seeder atau layanan baru. Pengecualian
scan penerimaan baru tetap mengikuti aturan Picking Verification existing.

## Migration dan verifikasi

Migration 2026_10_30_000003 menambah metadata kesiapan pada planned pallet,
metadata selesai pada pallet plan, deleted_at pada stok box, dan tabel token
export_fulfillment_actions. Deployment memerlukan `php artisan migrate --force`.
JavaScript pop-up berada di public/js/export-fulfillment.js; tidak ada entry
Vite/dependency/config PC tambahan. Rollback schema ditahan agar riwayat selesai
direkonsiliasi dahulu.

Migration sudah diterapkan ke MySQL lokal stock (APP_ENV local, 127.0.0.1), setelah
backup storage/app/backups/stock_export_fulfillment_20261030_193906_977c63.json.
Hash seluruh kolom lama pada 24 tabel yang diperiksa tetap sama, termasuk 12 box
domestik/8 ekspor, 26 planned pallet dan 16 reservasi; tabel aksi baru kosong.
Tidak ada contoh data pada MySQL atau perubahan production remote.

Regresi backend akhir: 438 tes lulus (3.969 assertions). Verifikasi khusus
penyelesaian: 15 tes (143 assertions), termasuk kasus tambahan pallet campuran,
part dikurangi sampai nol, dan tiga box dikembalikan ke satu lokasi kosong.
Browser menguji popup, lokasi kosong/semula, revisi 3 menjadi 2 box dengan FIFO,
pengembalian cadangan, konfirmasi ulang, alert selesai, dan hasil stok tersisa.
Filter serta pop-up audit masih menampilkan empat box, dua pallet dan kebutuhan
setelah arsip selesai. Viewport 390px dan konsol halaman fitur tanpa error juga
diperiksa. Semua mutasi contoh hanya pada
database SQLite export-fulfillment-preview.sqlite; bukan scanner/printer fisik.
