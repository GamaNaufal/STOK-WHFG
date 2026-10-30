# Pencegahan duplikasi rencana delivery ekspor

Terakhir diverifikasi: 30 Oktober 2026. Branch Export-plan.

## Aturan pembanding

Rencana dianggap identik jika customer, kode rencana, tenggat pengiriman, nomor
part dan jumlah PCS sama. Huruf besar/kecil, whitespace dan urutan baris diabaikan.
Identitas part memakai ID master ekspor; nama file atau metode manual/Excel tidak
membedakan permintaan. Kode kosong dibandingkan sebagai kosong, bukan kode unik.

Pembanding mencakup rencana aktif dan yang selesai dikirim, termasuk histori yang
diarsipkan saat penyelesaian. Rencana cancelled dan draft yang diarsipkan diabaikan.
Edit mengecualikan dirinya sendiri. Yang dibandingkan ialah kebutuhan tersimpan
terkini; ini tidak mendeteksi kemiripan sebagian atau mengembalikan isi sebelum revisi.

Data lama langsung diperiksa tanpa backfill atau perubahan header/item. Tidak ada
penghapusan atau penggabungan duplikasi lama. Query dibatasi tenggat dan total PCS,
kemudian mencocokkan pasangan ID part/PCS per 100 header agar memori terbatas.

## Alur operator

1. Isi manual atau baca Excel dan periksa hasilnya seperti biasa.
2. Saat Simpan, backend memeriksa duplikasi sebelum melakukan mutasi apa pun.
3. Jika identik, formulir tetap menyimpan isian dan menampilkan rencana pembanding.
   Rencana aktif dapat dibuka di tab lain; histori selesai ditampilkan sebagai
   referensi dengan status dan jumlah box, tanpa tautan ke halaman aktif yang sudah arsip.
4. Untuk permintaan tambahan yang sengaja identik, isi alasan maksimal 500 karakter
   dan centang pernyataan sudah meninjau pembanding. Setelah itu simpan kembali.
5. Alasan, ID pembanding dan aktor dicatat pada audit created/updated yang sama.

Konfirmasi terikat ke isi, aktor, token form, target edit serta daftar/revisi
pembanding dengan HMAC aplikasi. Checkbox/alasan saja tidak melewati pemeriksaan.
Jika rencana pembanding baru muncul atau berubah, server meminta tinjauan terbaru.
Perubahan customer/kode/tanggal/baris, impor dan undo membatalkan persetujuan lama
di browser. Nama file berbeda atau urutan part dibalik tetap memunculkan peringatan.

## Retry dan formulir lama

Create/update web memerlukan UUID request_token tersembunyi. Setiap penyimpanan
berhasil mencatat hash input/aktor/target dan ID hasil pada tabel baru secara
atomik dengan header, item dan audit. Mutex inventory ekspor existing mendahului
semua pemeriksaan. Token sama dengan payload sama mengembalikan ID yang sudah
tersimpan, tanpa membuat audit atau menaikkan revision kedua kali. Ini juga berlaku
sesudah master berubah atau hasil sudah diarsipkan; redirect menuju daftar jika arsip.

Token sama untuk isi, aktor atau aksi berbeda ditolak. Form HTML mempertahankan
isian dan memberikan token baru, lalu meminta operator meninjau dan mengirim lagi.
Tidak ada penyimpanan otomatis. Token tidak dikonsumsi oleh validasi gagal,
peringatan duplikat atau audit gagal. JS mencegah submit ganda dan mengembalikan
tombol pada pageshow/Back. Perlindungan utama tetap pada server/database.

Respons JSON validasi duplikasi memakai status 422 dengan errors.duplicate dan
duplicate_review berisi matches serta token. Untuk konfirmasi sertakan
duplicate_review_token, duplicate_confirmed=1 dan duplicate_reason bersama UUID
form serta payload yang sama. Retry sukses harus menggunakan payload yang sama,
termasuk alasan dan konfirmasi. Token tidak boleh dipakai ulang untuk tindakan lain.

## Schema dan deployment

Migration baru:
2026_10_30_000004_create_export_delivery_plan_requests.php.
Hanya menambah tabel token; tidak mengubah stok, lokasi, kebutuhan lama, role atau
kapasitas. Tidak menambah dependency atau konfigurasi komputer operator. Asset
resources/js/export-delivery-plan.js perlu dibangun dengan npm run build.

Untuk environment yang sudah memiliki seluruh schema ekspor sebelumnya, migration
baru dapat diterapkan dengan path migration tersebut. Periksa daftar migration
terlebih dahulu. Jika production masih memiliki migration lain yang belum jalan,
susun urutan deployment sesuai dependensi; jangan menganggap migrate seluruh branch
sudah aman, karena cutover migration QA masih memerlukan persiapan terpisah.
Rollback tabel token ditolak setelah ada token sukses agar retry tidak membuat
permintaan ganda setelah downgrade.

MySQL lokal stock/127.0.0.1 sudah dimigrasikan setelah backup:
storage/app/backups/stock_export_delivery_dedup_20261030_231757_59e689.json.
Isi 12 tabel existing dibandingkan identik, termasuk 4 rencana dan 8 box ekspor.
Tabel token lokal tetap kosong; query duplikasi MySQL diperiksa baca saja.
Production remote tidak dimutasi. Semua data contoh browser hanya SQLite khusus.

## Verifikasi

- 488 tes backend lulus (4.489 assertions), 87 frontend dan Vite build lulus.
- Kasus mencakup retry create/update setelah arsip/master berubah, token beda
  payload/aktor/aksi, validasi/audit gagal, formulir Back dan pelestarian isian.
- Duplikasi legacy/manual/Excel, whitespace/huruf/urutan/nama file, kode kosong,
  aktif/completed/cancelled/arsip draft, edit sendiri, override dengan alasan,
  bukti palsu/usang/aktor atau form berbeda, serta audit override diuji.
- Browser memeriksa peringatan tanpa simpan, perubahan isian menonaktifkan review
  lama, alasan dan checkbox sebelum tombol aktif, penyimpanan override tepat satu
  rencana tambahan dengan audit, desktop 1366px/ponsel 390px, tanpa overflow halaman
  dan tanpa error konsol. Bukan pengujian scanner atau stress test konkurensi MySQL.
