# Master part bersama dan identitas global

Perubahan 3 November 2026. Berlaku pada branch aktif tanpa membuat branch baru.

- `part_settings` menjadi satu master untuk domestik dan ekspor. Jika nomor sama
  memiliki dua master, ID dan kapasitas domestik menjadi acuan. Part yang hanya
  ada pada ekspor tetap dipertahankan. Seluruh foreign key ekspor dipindahkan ke
  ID acuan; PCS transaksi, kapasitas snapshot, tanggal FIFO, stok dan audit lama
  tidak ditulis ulang. Lokasi tetap memiliki scope masing-masing.
- Menu/route/model master part khusus ekspor dihapus. Operator Ekspor memakai
  menu bersama pada development/testing. Pada production, izin `parts` tetap
  dikelola melalui Hak Akses; default debug tidak menambah izin production.
  Izin eksplisit yang telah dicabut tetap dihormati. `export_parts` dibersihkan.
- `box_identities` menjadi registri ID delapan digit global. `origin_scope`
  mencatat asal, bukan namespace keunikan. Penerimaan, Not Full dan registrasi
  koreksi picking memeriksa identitas lintas lingkup. Histori yang dihapus tetap
  mengunci ID. Identitas box tidak dapat diganti setelah tercatat. Tabel stok dan
  seluruh alur operasional domestik/ekspor tetap terpisah.
- Kode delivery wajib, dinormalisasi trim/huruf besar, dan unik sepanjang histori.
  Indeks database menjaga kode aktif; `export_delivery_plan_codes` juga menjaga
  kode lama setelah rename, arsip atau penghapusan. Migrasi membaca alias dari
  audit lama. Alur override duplikat dengan alasan dihapus. Token penyimpanan
  tetap idempoten, termasuk hash request lama yang pernah berhasil.

## Urutan deployment

1. Backup database dan pastikan restore dapat dilakukan. Uji pada salinan data
   production dengan versi MySQL yang sama terlebih dahulu.
2. Hentikan transaksi baru dengan maintenance mode; hentikan sementara worker
   dan scheduler yang menulis inventory sesuai cara deployment aplikasi.
3. Deploy source terbaru dan jalankan pemeriksaan baca saja:

   ```sh
   php artisan inventory:unification-check
   ```

4. Jika ditemukan konflik, migrasi berhenti sebelum DML/DDL. Jangan mengubah
   ID Box, menggabungkan kuantitas atau menebak kode kosong secara otomatis.
   Tinjau identitas fisik dan histori bersama pemilik data. Part dengan kapasitas
   berbeda pada rencana aktif perlu diselesaikan/dibatalkan dahulu lewat alur
   pengembalian yang ada. Benturan relasi item perlu ditinjau per rencana.
   Isi kode lama yang kosong dan selesaikan kode yang pernah digunakan oleh
   beberapa rencana berdasarkan dokumen asli, dengan catatan koreksi/audit.
5. Jika pemeriksaan lulus:

   ```sh
   php artisan migrate --force
   php artisan optimize:clear
   npm ci
   npm run build
   php artisan inventory:unification-check
   ```

6. Periksa menu master bersama, pencarian part pada kedua alur, scan box lintas
   lingkup (harus NG), delivery kode sama (harus ditolak), serta lokasi yang tetap
   terpisah. Aktifkan kembali aplikasi/worker/scheduler setelah pengecekan.

Migration baru `2026_11_03_000001_unify_part_master_and_box_identity` tidak
memecah master/identitas kembali pada `down`. DDL MySQL melakukan implicit
commit; gunakan maintenance dan backup, bukan mengandalkan rollback DDL.
Jangan menjalankan `migrate:fresh`, fixture atau seeder UAT pada production.

## Data uji

Paket UAT diperbarui menjadi 34 master bersama. `UAT26-SHARED` memakai 100
PCS/box pada fixture baru, box domestik `98009999` dan ekspor `98009998`.
Data lama yang hanya memiliki salah satu master mempertahankan kapasitasnya.
SQL import master lama tetap tersedia pada nama file sebelumnya tetapi kini
hanya menambah part bersama yang belum ada, tanpa menimpa kapasitas existing;
20 lokasi yang ditambahkan tetap khusus ekspor. Jangan impor ulang UAT26 pada
database yang sudah memuat namespace itu.
