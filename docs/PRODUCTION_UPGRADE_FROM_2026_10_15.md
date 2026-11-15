# Upgrade production dari migration 15 Oktober 2026

Disusun 15 November 2026 berdasarkan source checkout saat ini dan log pengguna.
Dokumen ini panduan untuk pengelola server, bukan catatan bahwa deploy telah dilakukan.

## Acuan dan status kesiapan

- Pengguna memilih seluruh fitur terbaru beserta perbaikan keamanan.
- Daftar status terbaru yang disampaikan: migration sampai
  `2026_10_15_000001_create_role_feature_access_table` sudah Ran.
- Log pull lebih lama: `6fb73cf` menuju `9451407`, branch main, container aplikasi
  `fifowhfg_app`. Pada log lama tersebut tiga migration terakhir masih Pending.
  Daftar terbaru menjadi acuan sementara, bukan bukti kondisi server saat deploy.
- Dibanding acuan 15 Oktober, checkout ini mempunyai **29 migration tambahan**.
  Laravel hanya menjalankan migration yang belum tercatat; tidak perlu membuat
  ulang database atau menjalankan kembali migration yang sudah Ran.
- OS, versi PHP/DB aktual, nama container database, Compose/Dockerfile dan pola
  mount/image production belum diverifikasi. File Dockerfile/Compose production
  tidak tersedia pada checkout yang diperiksa. Perintah rebuild tidak boleh ditebak.
- Perbaikan keamanan terbaru lulus pemeriksaan sintaks, tetapi tes aplikasi dan
  rehearsal upgrade MySQL/MariaDB salinan production belum dilakukan pada sesi ini.
  Belum ada dasar untuk menyatakan rilis siap langsung dijalankan di production.

## 1. Pengelola mencatat kondisi server, tanpa mengubah data

Jalankan dari terminal HOST pada folder repository production, bukan dari prompt
`www-data@...` di dalam container. Tambahkan sudo bila memang diperlukan oleh
konfigurasi server. Jangan menambahkan sudo pada Artisan di dalam container tanpa
kebutuhan; pertahankan user/permission aplikasi existing.

```sh
git rev-parse --short HEAD
git status --short
docker ps --format 'table {{.Names}}\t{{.Image}}'
docker exec fifowhfg_app php -v
docker exec fifowhfg_app php artisan migrate:status
docker inspect fifowhfg_app --format '{{range .Mounts}}{{println .Type .Source "->" .Destination}}{{end}}'
```

Catat versi database melalui administrasi DB, image/tag aplikasi, versi Composer,
serta apakah worker/scheduler berjalan dalam container lain, cron host, atau supervisor.
Jangan membagikan output inspect lengkap/Compose config/.env karena dapat memuat secret.
Jika terminal sudah di dalam container, gunakan `php artisan ...` langsung. Docker
di dalam container tidak diperlukan.

Tanda `git:(main) ✗` pada log lama menunjukkan ada kondisi working tree yang perlu
diperiksa, bukan alasan untuk `git reset --hard`. Simpan dan tinjau perubahan server
sebelum update. Perbaikan lokal yang belum masuk commit/release tidak akan ikut git pull.

## 2. Backup dan salinan pengujian

Pengelola membuat backup database lengkap yang dapat dipulihkan, code/image rilis
lama, lockfile, konfigurasi deploy, .env/APP_KEY dan file persistent di storage.
Simpan backup di luar filesystem sementara container; simpan salinan terpisah dari
server. Metode dump disesuaikan engine/versi database dan akun yang tersedia.
Menyimpan volume name atau menyalin file database yang masih aktif saja bukan
pengganti backup konsisten yang telah diuji restore.

Restore backup ke database/server/container TERPISAH untuk rehearsal. Gunakan engine,
versi dan collation yang sama dengan production. Akun staging tidak boleh mempunyai
izin menulis database production. Cegah staging mengirim email, menjalankan scheduler,
atau memakai layanan production. Backup penting tidak perlu dikirim ke chat.

Catat data pembanding sebelum upgrade: ID/jumlah box, PCS aktif per part/lokasi,
jumlah dan isi transaksi/history, delivery/sesi/Not Full pending, tanggal reaktivasi,
penempatan pallet, user/role dan permission. Counts master dapat berubah bila ada
penggabungan yang memang direncanakan; simpan pemetaan ID dan alasan perubahannya.

## 3. Tinjauan perubahan data yang wajib selesai sebelum deploy

| Migration | Dampak yang harus diperiksa pada salinan database |
| --- | --- |
| 2026_10_23_000001_create_expired_box_handlings | Membuat registry dan QA. Untuk box aktif, tidak terhapus, yang reactivated_until-nya terisi, source menyalin deadline lama ke legacy_reactivated_until lalu mengubah reactivated_until menjadi waktu migration. Box terkait dapat kehilangan masa reaktivasi yang masih tersisa dan memerlukan QA. Ini perubahan operasional, bukan sekadar tambah tabel. |
| 2026_11_03_000001_unify_part_master_and_box_identity | Menyatukan master Lokal/Ekspor, memindahkan FK ke ID acuan dan menghapus master duplikat setelah pemeriksaan, menormalkan kode delivery serta membangun registry. Tinjau konflik dan pasangan part dengan perbedaan huruf besar/kecil sesuai identitas yang benar. Jangan menyamakan dua part berbeda secara manual. |
| 2026_11_12_000001_add_part_document_numbers | Menambah tabel mapping nomor dokumen dan kolom snapshot. Tidak otomatis mengimpor file master_product atau menebak pasangan part_no/part_no_label. Mapping harus disiapkan/divalidasi melalui fitur master jika belum terdaftar. |
| Migration penambahan fitur dan 2026_11_12_000002_version_granular_feature_permissions | Beberapa migration menambah grant fitur Ekspor/QA/opname ke role tertentu, lalu mengonversi izin menu menjadi tindakan. Bandingkan izin efektif sebelum/sesudah; jangan hanya mengecek apakah admin dapat login. |
| 2026_11_15_000001_allow_box_number_reuse | Menambah nomor penggunaan, mengubah unique/index serta backfill draft Ekspor. History menggunakan PK lama, tetapi aturan label/scan dan Redo berubah. Uji generated column dan indeks pada engine production; beberapa migration tidak mendukung downgrade otomatis. |

Jika bisnis mengharuskan masa reaktivasi yang sudah diberikan tetap berlaku,
perlakuan migration QA di atas perlu diputuskan dan disesuaikan SEBELUM deploy.
Jangan menjalankan seluruh migration lalu baru memutuskan setelah box terblokir.
Jangan mengubah tanggal/quantity/history production sebagai cara melewati konflik.

## 4. Rehearsal seluruh upgrade dan persiapan release

Gunakan code/release final yang sama dengan yang akan dipasang, termasuk file baru
dan perubahan keamanan. Instal dependency dari lockfile, bukan composer update atau
npm update. composer.json mengharuskan PHP ^8.2; lockfile Vite 7.3.7 mensyaratkan
Node ^20.19.0 atau >=22.12.0 pada mesin build. Node tidak harus dipasang pada server
production bila public/build dihasilkan di mesin build dan disertakan pada release.

Rehearsal menjalankan 29 migration dari baseline salinan production, bukan dari
database kosong saja. Catat durasi dan log setiap migration. `--pretend` bukan
pengganti rehearsal: beberapa migration membaca tabel/data yang dibuat migration
sebelumnya sehingga simulasi pada schema lama tidak membuktikan kelulusan upgrade.

Command `inventory:unification-check` saat ini mengasumsikan tabel registry dan
Ekspor sudah ada. Jangan menjalankannya pada schema 15 Oktober yang belum memiliki
tabel tersebut. Migration penyatuan 3 November memanggil preflight itu sendiri
pada urutan yang tepat. Pemeriksaan mandiri dapat dilakukan setelah prerequisite
sampai 31 Oktober tersedia, dan diulang setelah semua migration selesai.

Verifikasi pada staging: PK/jumlah/PCS history tetap sesuai, relasi input/delivery/
QA tetap tersambung, tanggal FIFO tidak berubah tidak sengaja, lokasi/reservasi
sesuai, mapping part benar, permission tiap role benar. Uji login, receipt/scan,
assignment/picking, approval, split, Redo, laporan lama/Excel, serta alur Ekspor,
QA, opname dan reuse baru dengan dummy transaksi. Fixture/transaksi uji tidak
dijalankan pada database production. Regresi terbaru perlu dijalankan sebelum rilis;
syntax PASS tidak menggantikan hasil regresi.

Setelah rehearsal lulus, siapkan vendor production dan public/build dari release
yang sama. Jika memakai Composer di lingkungan build yang sesuai, gunakan install
dengan lockfile dan opsi production; periksa platform requirement di image PHP
target. Pertahankan .env/APP_KEY server existing. Jangan memakai composer setup
untuk upgrade karena script itu juga membuat key, menjalankan migration dan build.

## 5. Window maintenance dan backup final

Pilih waktu sepi berdasarkan durasi rehearsal, tanpa menjanjikan menit yang belum
diukur. Hentikan scan/input baru, tunggu request/transaksi yang sedang menulis selesai,
aktifkan maintenance, lalu hentikan worker/scheduler penulis secara tertib sesuai
pengelola proses yang sebenarnya. Tidak harus menuntaskan seluruh delivery: record
pending/processing boleh tetap ada dan harus dibuktikan tetap bisa dilanjutkan.

Contoh dari HOST, dengan container yang sudah dikonfirmasi:

```sh
docker exec fifowhfg_app php artisan down
```

Jika container akan diganti, pengelola harus memastikan maintenance tetap berlaku
di ingress atau storage maintenance yang persistent. Maintenance satu container
lama saja belum membuktikan container baru tidak menerima traffic.

Setelah semua penulis berhenti, buat backup final konsisten dan catat cutoff.
Jangan mengambil baseline perbandingan ketika transaksi lain masih mengubah stok.

## 6. Pasang release dan jalankan migration

Pasang source/vendor/assets menurut pola deployment Docker yang sudah diverifikasi:
bind mount dan image-built mempunyai cara update berbeda. Volume database dan
storage harus tetap menunjuk data existing. Jangan menghapus volume atau membuat
database baru kosong untuk menggantikan production.

Perintah berikut dilakukan satu per satu pada container RELEASE BARU yang benar,
hanya setelah rehearsal lulus dan backup final tersedia. Bila nama container
berbeda dari fifowhfg_app, pengelola mengganti nama sesuai hasil pemeriksaan.
Setiap error berarti STOP; jangan melanjutkan ke up.

```sh
docker exec fifowhfg_app php artisan config:clear
docker exec fifowhfg_app php artisan route:clear
docker exec fifowhfg_app php artisan view:clear
```

Pastikan target database/APP_KEY/environment benar, kemudian:

```sh
docker exec fifowhfg_app php artisan migrate --force
```

Laravel mengurutkan migration pending; jangan memilih hanya tiga file terbaru
karena baseline ini juga memerlukan seluruh prerequisite fitur Ekspor/QA.
`--force` hanya melewati konfirmasi interaktif, bukan menjamin perubahan aman.
Jika migration penyatuan menolak konflik, migration yang lebih awal dapat sudah
berhasil. Batch ini bukan satu transaksi atomik MySQL. Jangan mengedit tabel
migrations agar terlihat Ran, mengulang buta, atau menghapus tabel yang bentrok.

Setelah migration berhasil:

```sh
docker exec fifowhfg_app php artisan migrate:status
docker exec fifowhfg_app php artisan inventory:unification-check
docker exec fifowhfg_app php artisan config:cache
docker exec fifowhfg_app php artisan route:cache
docker exec fifowhfg_app php artisan view:cache
```

## 7. Validasi sebelum transaksi dibuka

Bandingkan data penting dengan snapshot cutoff dan ekspektasi yang disetujui:
jumlah/ID box dan transaksi, PCS per part, lokasi, order/sesi pending, permission,
deadline reaktivasi, snapshot historis, mapping dokumen, dan history laporan.
Pastikan tabel/kolom/indeks baru ada dan seluruh migration yang dirilis sudah Ran.
Periksa log aplikasi dan akses baca memakai akun/role yang tepat.

Uji mutasi lengkap dan skenario error dilakukan di staging. Pemeriksaan production
sebelum buka difokuskan pada baca/konsistensi; setiap transaksi uji production harus
mempunyai dummy inventory yang disetujui dan dipisahkan, bukan mengubah barang asli.

Sesudah lulus, restart/reload proses aplikasi/worker yang hidup lama agar memakai
code baru, aktifkan kembali worker/scheduler yang tadi dihentikan, dan buka aplikasi
sesuai urutan pengelola. Contoh command untuk keluar maintenance dari HOST:

```sh
docker exec fifowhfg_app php artisan up
```

Pantau transaksi pertama dan log. Pengguna perlu memuat ulang halaman scanner/draft;
draft lama yang diminta scan ulang jangan dipaksa masuk dengan mengedit database.

## 8. Jika gagal

Pertahankan maintenance dan penulis berhenti. Simpan error dan status migration.
MySQL DDL dapat melakukan implicit commit; migration gagal dapat meninggalkan
perubahan sebagian. Beberapa down() sengaja menolak atau tidak membalik perubahan.
`migrate:rollback` bukan rencana pemulihan otomatis untuk release ini.

Sebelum transaksi dibuka kembali, pengelola dapat memilih perbaikan maju yang
ditinjau atau pemulihan database cutoff bersama code/image/config lama yang cocok.
Pemulihan backup setelah pengguna membuat transaksi baru memerlukan rekonsiliasi;
jangan menimpa transaksi baru dengan restore lama secara diam-diam.

Tidak digunakan dalam upgrade ini: migrate:fresh, migrate:refresh, migrate:reset,
db:wipe, db:seed/UAT, key:generate, docker compose down -v, atau docker volume prune.
Jangan menyalin .env.example menimpa .env existing.

## Lampiran: 29 migration setelah baseline yang disampaikan

```text
2026_10_20_000001_add_partial_to_existing_delivery_order_status
2026_10_23_000001_create_expired_box_handlings
2026_10_23_000002_create_expired_box_transactions
2026_10_28_000001_add_export_operator_role_to_users
2026_10_28_000002_add_scope_to_master_data
2026_10_28_000003_enable_export_master_features
2026_10_28_000004_create_export_delivery_plans
2026_10_28_000005_add_plan_code_to_export_delivery_plans
2026_10_28_000006_create_export_pallet_planning
2026_10_29_000001_create_export_fulfillment_monitoring
2026_10_29_000002_create_export_stock_receiving
2026_10_29_000003_create_export_receiving_batches
2026_10_30_000001_create_export_stock_allocation_notices
2026_10_30_000002_create_export_picking_verification
2026_10_30_000003_add_export_fulfillment_completion
2026_10_30_000004_create_export_delivery_plan_requests
2026_10_31_000001_support_export_not_full_boxes
2026_11_03_000001_unify_part_master_and_box_identity
2026_11_03_000002_support_export_pallet_merges
2026_11_04_000001_add_export_pallet_move_and_picking_start
2026_11_04_000002_create_stock_opname
2026_11_05_000001_add_expired_box_packing_receipts
2026_11_05_000002_support_export_expired_boxes
2026_11_05_000003_add_export_stock_pallet_box_limit
2026_11_05_000004_grant_export_stock_management
2026_11_05_000005_create_delivery_announcements
2026_11_12_000001_add_part_document_numbers
2026_11_12_000002_version_granular_feature_permissions
2026_11_15_000001_allow_box_number_reuse
```

Referensi prosedur: [Laravel migrations](https://laravel.com/docs/12.x/migrations),
[Laravel deployment](https://laravel.com/docs/12.x/deployment),
[MySQL implicit commit](https://dev.mysql.com/doc/refman/8.0/en/implicit-commit.html),
[Docker exec](https://docs.docker.com/reference/cli/docker/container/exec/) dan
[Docker volumes pada compose down](https://docs.docker.com/reference/cli/docker/compose/down/).
