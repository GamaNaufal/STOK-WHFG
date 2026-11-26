# Pemulihan migrasi timestamp MySQL

## Kondisi yang dilaporkan

Deploy V2.0 commit `3f5c0e2` pada 26 November 2026 berhenti di
`2026_10_29_000001_create_export_fulfillment_monitoring`. MySQL menolak
`CREATE TABLE export_fulfillment_boxes` dengan 1067 `Invalid default value for
'reserved_at'`. Sembilan migrasi sebelumnya berhasil. Pemeriksaan operator
mengonfirmasi MySQL 5.7.42, `@@SESSION.explicit_defaults_for_timestamp=0`,
dan `NO_ZERO_DATE` pada mode SQL. Kolom awal sudah ada, tabel target belum ada,
dan status migrasi menunjukkan 42 Ran serta 22 Pending.

Dengan aturan `TIMESTAMP` lama, MySQL dapat memberi default tanggal nol pada
kolom kedua yang wajib dan belum mendefinisikan default. Mode SQL yang menolak
tanggal nol menghentikan pembuatan tabel. DDL MySQL juga dapat melakukan commit
tersendiri, sehingga `export_pallet_plans.entered_fulfillment` dan backfill-nya
dapat sudah ada meskipun migrasi masih Pending.

## Pemeriksaan operator

Pertahankan maintenance dan hentikan seluruh proses yang menulis stok. Dari host,
jalankan pemeriksaan baca-saja berikut.

```sh
docker exec fifowhfg_app php artisan tinker --execute='dump(\Illuminate\Support\Facades\DB::selectOne("SELECT VERSION() AS versi, @@SESSION.explicit_defaults_for_timestamp AS default_timestamp, @@SESSION.sql_mode AS sql_mode", [], false)); dump(\Illuminate\Support\Facades\Schema::hasColumn("export_pallet_plans", "entered_fulfillment"), \Illuminate\Support\Facades\Schema::hasTable("export_fulfillment_boxes"));'
docker exec fifowhfg_app php artisan migrate:status
```

Untuk kegagalan tepat di `CREATE TABLE` seperti log ini, keadaan yang diharapkan
adalah kolom `true`, tabel `false`, dan migrasi tersebut `Pending`. Jika tabel
sudah ada atau ada migrasi sesudahnya yang telah berjalan, berhenti dan tinjau
schema aktual. Keadaan itu memerlukan pemeriksaan constraints dan data.

## Perbaikan pada kandidat rilis

- Menambahkan default `CURRENT_TIMESTAMP` pada kolom waktu wajib yang relevan,
  tanpa `ON UPDATE`. Nilai yang diberikan aplikasi tetap dipakai dan dapat
  bertahan saat kolom lain berubah. Tipe kolom `TIMESTAMP` tetap sama.
- Memeriksa keberadaan `entered_fulfillment` sebelum menambahkannya. Backfill
  hanya menyentuh nilai false yang memenuhi syarat. Migrasi sengaja berhenti
  jika tabel target telah ada saat masih Pending, agar tabel dengan constraint
  yang mungkin baru terbentuk sebagian tidak dianggap selesai.
- Memperbaiki pasangan kolom waktu serupa pada Pengumuman Delivery, dan
  memberi default eksplisit pada waktu wajib migrasi Ekspor/Opname yang masih
  pending. Tidak ada migrasi baru maupun perubahan setting global MySQL.

Pengujian MySQL 8.4.3 terisolasi memakai mode kompatibilitas `TIMESTAMP` lama
dan `NO_ZERO_DATE`. Kode asli kembali gagal 1067 pada `reserved_at`. Kolom
awal, backfill, dan 42 catatan migrasi sebelumnya tetap ada, sedangkan tabel
target belum terbentuk. Setelah patch, seluruh 22 migrasi tersisa selesai.
Relasi, indeks, backfill, dan nilai FIFO yang diisi aplikasi diperiksa.
Pengujian ini memakai data fiktif dan bukan rehearsal MySQL 5.7 produksi.

## Melanjutkan pembaruan setelah kondisi terkonfirmasi

1. Pertahankan backup cutoff sebelum deploy. Verifikasi file dan cara restore
   masih dapat digunakan. Pertahankan maintenance selama pemulihan.
2. Pasang commit perbaikan yang telah ditinjau atau patch migrasi yang cocok
   dengan checkout V2.0. Jika memakai file patch, jalankan `git apply --check`
   dari root repository produksi sebelum `git apply`. Berhenti jika pemeriksaan
   patch gagal. Uji patch pada salinan yang memakai MySQL 5.7 bila tersedia.
3. Hanya untuk keadaan sebagian yang sudah dipastikan di atas, lanjutkan
   `docker exec fifowhfg_app php artisan migrate --force`. Migrasi yang tercatat
   Ran akan dilewati, sedangkan migrasi yang gagal akan melanjutkan dengan
   schema yang sudah diperiksa.
4. Jika ada error lain, simpan log dan status lalu tahan maintenance. Jika
   berhasil, jalankan `migrate:status`, `inventory:unification-check`, dan
   `health:concurrency` dari container yang sama. Ikuti pemeriksaan data dan
   prosedur membuka aplikasi pada panduan deploy V2.0.

Hindari mengedit tabel `migrations`, menghapus kolom/tabel secara manual,
menonaktifkan mode strict, atau menjalankan rollback otomatis. Jika pilihan
operasional adalah batal deploy, pulihkan aplikasi dan database yang cocok dari
backup cutoff sesuai panduan V2.0.
