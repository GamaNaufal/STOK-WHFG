# Panduan uji seluruh proses bisnis STOK WHFG

Untuk menjalankan alur ekspor sendiri dari membuat delivery sampai selesai,
gunakan `EXPORT_MANUAL_TEST_GUIDE.md` (14 rangkaian kasus). Checklist di bawah
tetap menjadi referensi rinci lintas fitur domestik/ekspor.

Paket `UAT26` untuk source yang diverifikasi 31 Oktober 2026. Mencakup domestik,
ekspor, Box Not Full ekspor terbaru, pelaporan, dashboard, dan hak akses.
Ini panduan UAT manual: SQL menyiapkan kondisi awal, penguji menjalankan tindakan
melalui aplikasi dan mencatat hasilnya. Hasil validasi paket ada di
`database/uat/validation.json`; itu bukan pernyataan bahwa seluruh checklist UI
di bawah sudah dijalankan oleh agent.

## 1. Cara memasukkan data uji

1. Gunakan database **development** yang migrasinya sudah terbaru, termasuk
   `2026_10_31_000001_support_export_not_full_boxes`. Buat backup sebelum impor.
   Untuk pengujian bebas dan mudah mengulang, gunakan salinan database development.
2. Hentikan sementara aktivitas operator dan scheduler yang memutasi inventory
   selama impor. Selesaikan/batalkan sesi picking domestik scanning/blocked
   melalui aplikasi; SQL menolak impor jika masih ada sesi tersebut.
3. Pilih database tujuan di MySQL Workbench/phpMyAdmin. Buka dan jalankan seluruh
   `database/uat/01_seed_business_uat.sql` sebagai **SQL script**, bukan per baris.
   Default database `stock`, serta hostname di header, mengikuti development lokal
   saat paket dibuat. Jika memakai salinan development dengan nama berbeda,
   ubah `@uat_expected_database` ke nama salinan tersebut. Periksa juga
   `SELECT DATABASE(), @@hostname;` sebelum menjalankan.
4. Akun DB membutuhkan SELECT/INSERT/UPDATE serta CREATE ROUTINE/ALTER ROUTINE/
   EXECUTE untuk procedure sementara. Jangan menghapus `DELIMITER`, handler
   rollback, atau pemeriksaan namespace untuk melewati error.
5. Jalankan `database/uat/02_verify_business_uat.sql`. Bagian integritas harus
   menghasilkan **nol baris bermasalah**. Simpan hasil baseline sebelum UAT.
6. Login menggunakan akun di bagian berikut. Jika login lama sudah terbuka,
   logout dahulu agar session draft scan/cache role tidak terbawa.
7. SQL memakai tanggal impor sebagai D0, Asia/Jakarta. Tenggat D+2 berarti dua
   hari setelah impor. Gunakan tanggal sebenarnya dari query verifikasi ketika
   membuat dokumen uji atau membandingkan duplikasi.

SQL menambahkan data, tidak mengganti master/user/akses existing. Nama memakai
`UAT26`, box baseline memakai `9800xxxx`, dan ID input berikutnya `9801xxxx`.
ID numerik tabel disesuaikan dengan data yang sudah ada; jangan mengandalkan
nomor primary key dari contoh atau `manifest.json` sebagai ID URL.
Cari berdasarkan nama customer, kode rencana, part, lokasi, atau ID Box.

Impor kedua **sengaja ditolak**, termasuk setelah sebagian data uji selesai atau
dihapus melalui aplikasi. Ledger ID Box mempertahankan identitas historis.
Untuk mengulang dari awal, restore backup/salinan sebelum impor. Tidak disediakan
SQL hapus massal karena dapat merusak histori, reservasi, dan relasi audit.

Paket membutuhkan MySQL yang mendukung SIGNAL, JSON dan procedure. Sudah diuji
pada versi yang tertulis di `validation.json`; kompatibilitas MariaDB belum
diverifikasi. SQL tidak membaca `APP_ENV` Laravel: pemilihan DB development tetap
harus diperiksa oleh penguji.

## 2. Akun dan pembagian penguji

Password semua akun baru: **`UatWHFG!2026`**.

| Email | Role | Fokus |
| --- | --- | --- |
| `uat26.admin@whfg.test` | Admin Utama | Semua menu, user, akses, lock, approval |
| `uat26.supervisi@whfg.test` | Supervisi | Approval Not Full, expired, laporan, dashboard, audit |
| `uat26.admin_warehouse@whfg.test` | Admin Warehouse | Master domestik, operasi gudang, issue, penyiapan Not Full |
| `uat26.warehouse_operator@whfg.test` | Operator Warehouse | Input/picking domestik, pengajuan Not Full |
| `uat26.warehouse_operator2@whfg.test` | Operator Warehouse | Konflik kepemilikan sesi dan konkurensi |
| `uat26.export_operator@whfg.test` | Operator Export | Master dan operasi ekspor |
| `uat26.sales@whfg.test` | Sales | Permintaan domestik dan koreksi |
| `uat26.ppc@whfg.test` | PPC | Review/approval domestik |
| `uat26.inactive@whfg.test` | Sales nonaktif | Login harus ditolak |

Gunakan profil Chrome berbeda/incognito untuk dua role bersamaan. Akses mengikuti
konfigurasi role development Anda; SQL tidak menimpa `role_feature_access`.
Jika menu berbeda dari akses default, periksa Hak Akses sebagai Admin Utama.
Uji perubahan permission hanya pada salinan development karena berlaku untuk
semua user dalam role yang sama.

## 3. Data acuan dan urutan

Daftar lengkap part, kapasitas, ID Box, umur penerimaan, rencana, dan akun ada
di `database/uat/manifest.json`. Lokasi tersedia `UAT26-D01`–`D50` untuk domestik
dan `UAT26-E01`–`E50` untuk ekspor. Lokasi yang sudah ditempati/direservasi tidak
boleh dipilih sebagai lokasi kosong.

| Skenario domestik | Customer / part | Kondisi awal |
| --- | --- | --- |
| FIFO | `UAT26 DOM FIFO` / `UAT26-D-FIFO` | Minta 160; 3 box ×80: `98000101`–`103`, urut tertua |
| Input langsung | `UAT26 DOM DIRECT` / `UAT26-D-DIRECT` | Minta 60 PCS; standar 30 PCS/box; belum ada stok |
| Salah scan | `UAT26 DOM ISSUE` / `UAT26-D-ISSUE` | Minta 80 PCS; box 40 PCS: `98000201`–`203` |
| Lock | `UAT26 DOM LOCK` / `UAT26-D-LOCK` | Minta 40 PCS; box 20 PCS: `98000301`, `302` |
| Split | `UAT26 DOM SPLIT` / `UAT26-D-SPLIT` | Minta 80 PCS; box 20 PCS: `98000401`–`404` |
| Not Full supplement | `UAT26 DOM NF-SUPPLEMENT` / `UAT26-D-NF` | Minta 100 PCS; stok 80 PCS; request 20 PCS `98010071` pending |
| Not Full additional | `UAT26 DOM NF-ADDITIONAL` / `UAT26-D-NF-ADD` | Minta 80 PCS; stok 80 PCS; request tambahan 20 PCS `98010073` pending |
| Pindah/gabung/edit | `UAT26-D-MOVE`, `D-MERGE`, `D-EDIT` | Stok bebas di D07–D10 |
| Expired | `UAT26-D-EXPIRED` | `98000601`–`604` umur 400 hari; sumber `98000606` sudah di packing sejak D−60 |
| Warning | `UAT26-D-WARNING` | `98000611`, `612`, umur 305 hari |
| Redo/laporan | `UAT26 DOM HISTORY-REDO` | Pengiriman 100 PCS selesai D−1; box `98001101`, `102` |
| PPC | customer `CORRECTION`, `REJECT`, `PRIORITY-LATER` | Masih pending; PRIORITY-FIRST approved lebih awal |

Rentang ID Box pada tabel adalah singkatan delapan digit, bukan barcode
yang dipindai literal. Contoh `98000101`–`103` berarti `98000101`, `98000102`,
`98000103`. Selalu scan delapan digit lengkap. Singkatan lokasi D23 berarti
`UAT26-D23`, E17 berarti `UAT26-E17`. Singkatan part D-FREE berarti
`UAT26-D-FREE`. Angka kapasitas dalam kurung pada tabel berikut bukan bagian
dari nomor part.

| Kode ekspor (`UAT26-EX-…`) | Part (awalan `UAT26-`) | Kondisi awal / tujuan uji |
| --- | --- | --- |
| MIX | E-MIX-A (80), E-MIX-B (40) | Draft 160+40 PCS; 3 box direkomendasikan dari pallet campuran E01 |
| MONO | E-MONO (20) | Draft 720 PCS = 36 box; stok 71 box pada satu pallet E03 |
| RECEIVE | E-RECEIVE (80) | Draft 240 PCS = 3 box; bulk penerimaan → penempatan → selesai |
| EARLY / LATER | E-DEADLINE (80) | Final masing-masing 1 box di E13/E14; D+2/D+3 |
| NF-EXT | E-NF-EXT (80) | Final 100 = 80+20 PCS, E31; buat NF datang sudah dikemas |
| NF-DRAFT | E-NF-DRAFT (80) | Draft 100 PCS; penyiapan NF harus ditolak |
| NF-SPLIT | E-NF-SPLIT (80) | Final 100 PCS E32; stok `98002261`–`263`; tertua `98002261` sudah reserved full |
| NF-SHORT | E-NF-SHORT (80) | Final 100 PCS E33; satu full `98002271` sudah reserved, tidak bisa dipecah |
| NF-EXIST | E-NF-EXIST (80) | Draft 100 PCS; spare NF 20 PCS `98002401` di E12 tersedia |
| REV | E-REV (50) | Final 200 PCS = 4 box E35; stok 3 box `98002301`–`303`; belum ditempatkan |
| CANCEL | E-CANCEL (80) | Final 160 PCS E37; stok 2 box `98002311`, `312` reserved |
| WARN | E-WARN (10) | Draft 720 PCS = 72 box; warning rencana 36/72 |
| DUP / ARCHIVE | E-DUP (80) | Pembanding draft aktif dan draft arsip |
| HISTORY | E-HIST (20) | Selesai D−1, 4 box = 80 PCS, tersedia pada histori/arsip |

Jalankan berurutan: akses/master → domestik → ekspor → laporan/dashboard →
konkurensi. Selesaikan atau batalkan sesi domestik sebelum skenario berikutnya.
Untuk cabang yang saling menghabiskan data (misalnya NF approve vs reject),
buat rencana/request baru dengan kode dan ID baru, atau ulangi pada salinan awal.

Catatan tiap kasus: **Lulus/Gagal, tanggal, penguji, ID transaksi, screenshot,
hasil aktual**. Kasus negatif lulus jika ditolak dengan pesan jelas dan data
tidak berubah; muncul pesan sukses saja belum cukup.

## 4. Login, akses, profil dan master

| ID | Langkah | Hasil yang harus terlihat |
| --- | --- | --- |
| A01 | Login setiap akun aktif; logout; coba password salah dan akun inactive. | Role/menu sesuai; kredensial salah/nonaktif ditolak; halaman privat setelah logout tidak bisa digunakan. |
| A02 | Operator domestik buka URL ekspor dari profil export; export buka approval/admin user; Warehouse coba approve NF ekspor. | Akses ditolak meskipun URL diketahui. Admin Warehouse hanya menyiapkan NF ekspor; Admin/Supervisi approve. |
| A03 | Admin buat user `UAT26 Tester`, edit role, nonaktifkan; coba login lagi. | Validasi email duplikat; perubahan berlaku dan audit dapat ditelusuri. |
| A04 | Pada salinan DB, admin ubah Hak Akses satu role lalu login ulang. Uji dependensi picking/split/redo ke jadwal, edit/hapus ke stok. | UI dan endpoint mengikuti akses; dependensi dijaga; admin tetap dapat mengelola akses. Pulihkan konfigurasi setelah uji. |
| A05 | Ubah profil/password akun uji jika tersedia; coba field tidak valid. | Validasi jelas, perubahan tersimpan, password lama tidak berlaku setelah diganti. Catat password baru. |
| M01 | Cari `UAT26-SHARED` pada master bersama. | Satu master 100 PCS/box dipakai kedua alur. Box domestik `98009999` dan ekspor `98009998` berbeda; mencoba ID lintas lingkup harus ditolak. |
| M02 | Tambah part `UAT26-CRUD` kapasitas 25 di master bersama; coba duplikat dari role lain, nol/negatif. | Part unik global; kedua alur melihat kapasitas yang sama. Kapasitas tidak valid ditolak. |
| M03 | Edit/hapus master yang belum dipakai; lalu coba master bersama E-NF-DRAFT atau E-MIX-A yang dipakai rencana aktif/draft. | Master bebas dapat dikelola; perubahan kapasitas part dengan rencana aktif ditolak, data rencana/box lama tetap. |
| M04 | Kelola lokasi baru `UAT26-CRUD-LOC`; coba duplikat, ubah/hapus lokasi occupied/reserved. | Validasi per lingkup; lokasi dipakai tetap dilindungi. Lokasi domestik tidak muncul pada pilihan ekspor. |

## 5. Domestik: penerimaan dan stok

| ID | Langkah | Hasil yang harus terlihat |
| --- | --- | --- |
| D01 | Operator buka Stock Input. Scan box `98010001` dan part `UAT26-D-FREE`; tambah `98010002`. Simpan ke lokasi kosong D23. | Scan OK; PCS otomatis 10, transaksi 2 box 20 PCS; keduanya satu pallet/lokasi; riwayat penerimaan bertambah sekali. |
| D02 | Sebelum simpan, scan box yang sama dua kali, part tidak dikenal, angka 123, huruf, lebih dari 8 digit. Hapus baris salah/reset draft bila perlu. | NG/validasi; antrean tidak menggandakan box; input tidak valid tidak menjadi stok. |
| D03 | Input box baru `98010003` part D-FREE ke pallet D23 yang dibuat D01. | Box bergabung di pallet tersebut; jumlah 3 box 30 PCS, bukan pallet baru tanpa lokasi. |
| D04 | Cari box `98000101`; filter part/pallet/lokasi; buka detail; unduh Excel stok. | Hasil sesuai filter, detail isi benar, ID Box delapan digit utuh; ekspor tidak ikut. |
| D05 | Admin Warehouse edit data box bebas `98000901`; coba perubahan tidak valid; hapus `98000902` dengan alasan jika diminta. | Total/pallet sinkron; audit edit/hapus ada; ID terhapus tidak boleh dipakai lagi. Jangan edit box skenario FIFO/issue. |
| D06 | Coba edit/hapus box yang sedang dipilih/terikat delivery; lalu hapus pallet yang tidak eligible. | Guard bisnis mencegah merusak picking/reservasi; tidak ada relasi yatim. |
| D07 | Move pallet D07 ke D24 kosong; coba pindahkan ke lokasi occupied. | Semua isi mengikuti; D07 kosong, D24 occupied; tujuan occupied ditolak. |
| D08 | Merge dua pallet D08/D09 part D-MERGE; tentukan tujuan yang valid. | Total 4 box 40 PCS, identitas/umur asli tetap, lokasi sumber yang kosong dilepas; histori perpindahan tercatat. |
| D09 | Ulangi input ID `98000902` setelah dihapus dan ID `98001101` yang sudah dikirim. | Ditolak berdasarkan histori/ledger, bukan hanya daftar stok aktif. |

## 6. Domestik: permintaan sampai keluar gudang

| ID | Langkah | Hasil yang harus terlihat |
| --- | --- | --- |
| D10 | Sales buat permintaan `UAT26 DOM NEW`, part `UAT26-D-FREE`, jumlah 20 PCS, tenggat D+7. Coba quantity kosong/negatif/part tidak dikenal. | Request valid pending, item benar; input tidak valid ditolak. |
| D11 | PPC proses customer CORRECTION dengan koreksi, Sales perbaiki dan ajukan lagi. PPC proses REJECT dengan reject. | Status dan catatan terlihat untuk Sales; hanya pending yang dapat diputuskan ulang. |
| D12 | PPC approve UAT26 DOM NEW; kosongkan catatan lebih dahulu. Review PRIORITY-LATER saat stok 50 PCS tetapi dua order masing-masing 40 PCS. | Catatan approval wajib; informasi ketersediaan/impact ke order lain konsisten dengan tenggat. Jangan mengasumsikan approved berarti stok sudah keluar. |
| D13 | Buka FIFO dan mulai picking/verifikasi; cetak daftar rekomendasi. | Rekomendasi `98000101`, `98000102` = 160 PCS, bukan `98000103`; lokasi D01 terlihat. |
| D14 | Pada **Picking Verification domestik**, scan `98000103` yang tidak direkomendasikan, lalu dua ID benar. | NG untuk pencarian yang salah, tidak membuat issue blocking; OK sesuai daftar. Tidak ada stock withdrawal sebelum selesai pengiriman. |
| D15 | Lanjutkan scan pengiriman FIFO, scan `98000101` dan `98000102`; coba selesai saat baru satu box, lalu setelah lengkap; klik/retry selesai lagi. | Belum lengkap ditolak; lengkap selesai 160 PCS sekali; dua box tidak di stok aktif, box `98000103` tetap; laporan keluar dan audit ada, retry tidak menggandakan. |
| D16 | Admin Warehouse buka HISTORY-REDO pada riwayat selesai D−1; tinjau opsi redo dan lakukan satu redo dengan tujuan yang valid. | Pilihan box/lokasi jelas; stok, pemenuhan dan histori dikoreksi sesuai redo, bukan membuat penerimaan produksi palsu. Uji redo setelah jendela5hari menggunakan salinan yang waktunya dikendalikan atau tes otomatis, bukan mengubah jam PC utama. |
| D17 | Untuk DIRECT 60 PCS, input langsung box baru `98010011`, `98010012` part D-DIRECT lewat alur assignment/input delivery yang tersedia; tinjau dan konfirmasi. | Dua box 30 PCS memenuhi 60 PCS. Coba box ketiga `98010013`: kelebihan kebutuhan tidak boleh diam-diam masuk assignment. Gunakan opsi stok normal/cadangan yang disediakan. |
| D18 | Pada ISSUE 80 PCS, scan final `98000203` padahal rekomendasi `98000201` dan `98000202`. | NG, issue pending dan sesi blocked; scan benar/selesai tetap tertahan. |
| D19 | Admin Warehouse buka Approval Scan Salah, tinjau box/lokasi, lakukan approval yang sah; operator lanjut sesuai arahan. | Issue domestik berlabel Domestik; unblock mengikuti alur domestik existing, tidak membuat issue ekspor. Selesaikan/cancel sesi sebelum D20. |
| D20 | Pada SPLIT 80 PCS, Admin Warehouse split menjadi 40 dan 40 PCS; uji quantity tidak valid; tinjau anak/parent dan cetak. Gunakan restore split sebelum dipicking jika tersedia. | Jumlah anak tetap 80 PCS; parent tidak dihitung ganda; restore eligible menjaga kebutuhan dan stok. Setelah ada picking, tindakan yang merusak histori ditolak. |
| D21 | Operator 1 mulai LOCK. Operator 2 mencoba order/sesi sama dan order lain. Coba batalkan/mulai ulang dengan pemilik. | Sesi pemilik lain dilindungi; hanya satu scanning/blocked domestik secara global; cancel melepas lock/reservasi yang sah. |
| D22 | Mulai verifikasi lalu hentikan aktivitas browser lebih dari 10 menit; jalankan scheduler/command `php artisan delivery:expire-verifications` pada DB uji. Muat ulang. | Sesi kedaluwarsa tidak dapat scan/complete memakai halaman lama; harus mulai ulang. Heartbeat/scan aktif mencegah expired keliru. |
| D23 | Admin Utama buka Pengelolaan Lock, tinjau sesi LOCK, terminate dengan alasan jika diperlukan. | Lock lepas secara teraudit, halaman lama operator tidak dapat melanjutkan sesi yang dibatalkan. Role selain admin tidak bisa memaksa terminate. |
| D24 | Admin Utama buat lalu hapus delivery percobaan yang belum memiliki picking/mutasi. Coba hapus order dengan sesi aktif/completed. | Delivery bebas mengikuti aturan hapus; delivery berhistori/aktif tidak boleh meninggalkan stok atau relasi picking yatim. Role tanpa delivery_delete ditolak. |

## 7. Domestik: Box Not Full dan expired

| ID | Langkah | Hasil yang harus terlihat |
| --- | --- | --- |
| N01 | Sebelum approve NF-SUPPLEMENT/ADDITIONAL, coba mulai picking/split/assignment terkait. | Pending Not Full mengunci proses delivery terkait. Pengiriman lain tetap dapat diuji setelah lock global bersih. |
| N02 | Supervisi/Admin approve supplement `98010071`, 20 PCS di D21. | Request approved, stok NF 20 PCS terbentuk dan terikat order 100 PCS; kebutuhan order tetap 100 = 80+20. Pilih stok 80 PCS tertua dan NF 20 PCS saat pemenuhan. |
| N03 | Approve additional `98010073`, 20 PCS di D22. | Kebutuhan order 80 menjadi 100 PCS; total penambahan sekali; NF 20 dan full 80 PCS tercatat. |
| N04 | Buat order uji approved baru `UAT26 DOM NF-REJECT`, part `UAT26-D-NF`, jumlah 100 PCS. Operator ajukan request ID `98010075`, 20 PCS ke D27. Coba PCS 0, PCS sama/lebih dari standar, ID existing, order completed, supplement melebihi sisa, lokasi tidak valid. Buat satu valid lalu reject dengan alasan. | Input salah ditolak tanpa stok; reject tidak menambah stok; jenis supplement/additional tidak tertukar. Permintaan baru saat picking aktif ditolak. |
| X01 | Supervisi buka Expired Box. Cari `98000601`–`98000604`, `98000611`, `98000612`. | Umur 400 hari expired, 305 hari warning. Aturan aktual expired ≥12 bulan, warning ≥9 bulan. Expired tidak tersedia untuk picking; warning masih mengikuti aturan usable aplikasi. |
| X02 | Buat transaksi expired dengan `98000601` dan `98000602`. Tinjau dan konfirmasi penarikan; coba QA sebelum penarikan. | Status berurutan; tidak ada pengambilan stok baru/double input; QA terlalu awal ditolak. |
| X03 | QA `98000601` passed, `98000602` rejected. Kosongkan alasan reject lalu isi alasan dan konfirmasi fisik. | Alasan/konfirmasi wajib; box rejected tidak tersedia sebagai stok, identitas dan snapshot histori tetap; box passed dapat lanjut packing. |
| X04 | Cetak transaksi/label serah packing `98000601`, kirim packing, terima ID baru `98010601`, part D-EXPIRED, 10 PCS, tanggal label hari ini, lokasi kosong D25. | ID baru terhubung ID lama, FIFO sumber diwarisi; masa aktif satu bulan kalender dari tanggal label; tidak dihitung penerimaan produksi baru. |
| X05 | Sebelum penerimaan X04, coba ID lama, ID existing, part/PCS berbeda, label sebelum serah packing atau masa depan. Pada transaksi multi-box coba satu baris salah. | Ditolak; satu baris gagal membatalkan seluruh batch. Tidak ada penggantian parsial yang tidak terlacak. |
| X06 | Buka sumber packing lama `98000606`; terima sebagai `98010606`, part D-EXPIRED, 10 PCS, label D−35, lokasi D26. | Label setelah serah D−60 tetapi usia label lebih satu bulan: box hasil tetap expired, tidak aktif satu bulan dari tanggal penerimaan. |
| X07 | Gunakan `98000603` dan `98000604` untuk transaksi bulk kedua; cek pilih semua, cetak, QA/packing/terima semua baris yang diwajibkan; coba pilih satu anggota transaksi di tahap lanjut. | Keanggotaan transaksi dan limit 50 dijaga; hasil QA campuran benar; trace memperlihatkan rantai ID lama→baru dan petugas/waktu. |

## 8. Ekspor: rencana delivery, Excel dan kode unik

Ada dua layout untuk diuji. Pada **template sederhana yang diunduh dari aplikasi**,
isi customer di B1, tenggat `YYYY-MM-DD` di B2, kode di B3; part mulai A5 dan
jumlah PCS mulai B5. Pada **layout dokumen produksi yang sebelumnya dilampirkan**,
jumlah permintaan mengikuti kolom C, bukan Qty/Bx kolom G. Pertahankan header dan
struktur masing-masing layout. Satu file mewakili satu customer. Kapasitas hasil
selalu mengikuti master part bersama.

| Part | PCS (B pada template / C pada dokumen produksi) | Qty/Bx pembanding pada dokumen produksi | Harapan |
| --- | --- | --- | --- |
| `UAT26-E-RECEIVE` | 160 | 999 | Master 80 PCS → 2 box; nilai 999 pada dokumen tidak dipakai |
| `UAT26-E-NF-DRAFT` | 100 | 80 | 1 full 80 + 1 NF 20 PCS → 2 box |
| `UAT26-E-WARN` | 0 | 10 | Diabaikan dengan peringatan |
| `UAT26-E-SPARE` | kosong | 40 | Diabaikan dengan peringatan |

Hapus/sesuaikan baris impor percobaan sebelum finalisasi agar tidak mengambil
stok skenario lain. Gunakan rencana impor sebagai draft dan arsipkan setelah
uji, atau buat part khusus `UAT26-IMPORT` untuk pengujian lanjutan.

| ID | Langkah | Hasil yang harus terlihat |
| --- | --- | --- |
| E01 | Operator Export buat delivery manual baru kode UAT26-EX-MANUAL; customer, tenggat, part `UAT26-E-RECEIVE`, jumlah 160 PCS. | Kode tersimpan; standar 80 PCS; total 2 box otomatis; tidak ada total pallet manual. |
| E02 | Impor Excel layout tetap sesuai contoh di atas, lihat pratinjau sebelum simpan. | Header/kode/customer/tanggal/baris terbaca; zero/kosong diabaikan dengan warning; NF ditandai. Stok/request belum dibuat sebelum simpan. |
| E03 | Ubah hasil review sebelum simpan; coba part tidak dikenal, PCS negatif/desimal tidak valid, file bukan Excel, file rusak/kosong. | Koreksi dapat dilakukan; input salah tidak menjadi rencana; pesan jelas. Deteksi foto/OCR tidak tersedia. Bulk multi-customer belum dianggap fitur siap pakai. |
| E04 | Edit rencana NF-DRAFT yang sudah ada dan simpan tetap 100 PCS. Pada rencana percobaan baru, uji penyimpanan pertama 100 PCS part yang sama. Periksa alert, kebutuhan 20 PCS, dan antrean NF pada admin. | Request wajib otomatis satu per item; master tetap 80 PCS; operator dihimbau hubungi admin. Save ulang/edit tidak membuat request yang sama ganda. Arsipkan rencana percobaan setelah uji. |
| E05 | Coba kode ` uat26-ex-dup `, lalu ubah customer/part/jumlahnya. Coba kode kosong. | Kode wajib dan unik setelah trim/case; semua percobaan kode terpakai ditolak tanpa membuat header baru. |
| E06 | Buat isi sama dengan kode baru. Rename kode draft, lalu coba kode lamanya pada rencana lain. | Kode baru boleh; kode lama tetap dikunci sepanjang histori. Override duplikat sudah dihapus. |
| E07 | Coba kode HISTORY, ARCHIVE dan permintaan cancelled, dengan isi sama maupun berbeda. | Seluruh kode tetap tidak dapat digunakan ulang setelah selesai, dibatalkan atau diarsipkan. |
| E08 | Klik simpan cepat dua kali/retry request yang sama pada rencana percobaan. | Satu header, satu kebutuhan NF, tidak menggandakan stok/audit mutasi; tombol pulih setelah Back/error. |

## 9. Ekspor: perencanaan, FIFO dan cetak

| ID | Langkah | Hasil yang harus terlihat |
| --- | --- | --- |
| P01 | Buka RECEIVE; buat 2 pallet: pallet 1 E17 berisi 2 box part E-RECEIVE (80 PCS/box), pallet 2 E18 berisi 1 box. Simpan draft belum lengkap dulu. | Draft boleh belum tepat; sisa alokasi terlihat dekat area input, nomor pallet urut per delivery. |
| P02 | Coba final kurang/lebih; lokasi sama dua pallet; lokasi domestic/occupied/reserved. Kemudian final alokasi2+1 benar. | Final wajib tepat setiap part/varian; lokasi konflik ditolak atomik. Planning final muncul di Pemenuhan Permintaan. |
| P03 | Final MIX pada satu pallet E19 dengan 2 box E-MIX-A (80 PCS/box) + 1 box E-MIX-B (40 PCS/box). | Mixed part boleh; otomatis reserve `98002101`, `98002102`, `98002103` dari E01 FIFO; bukan `98002104` yang lebih baru di E02. |
| P04 | Saat popup FIFO muncul, tinjau asal/tujuan dan tombol mandatory masukkan antrean. Refresh sebelum/selepas klik. | Reservasi langsung ada saat final, tidak direbut rencana lain; satu tombol acknowledgment mengaktifkan antrean, dapat ditinjau lagi, tidak menggandakan tugas. |
| P05 | Final MONO 36 box pada E20. Isi sumber di E03 seluruhnya satu part, walau 71/72 box. | Reserve tertua `98002001`–`98002036`; pengambilan sebagian 36 box dari sumber mono tidak wajib scan picking. |
| P06 | WARN 72 box: coba 1 pallet berisi 36, 72, lalu 73 box; final boleh 2 pallet masing-masing 36 box di E21/E22. | 36 kuning, 72 merah untuk perhatian; 73 juga melampaui kebutuhan 72 sehingga final ditolak. Warning 36/72 sendiri bukan larangan kapasitas. |
| P07 | Pada NF-EXT/SPLIT buka rincian final. | Satu part memiliki varian full 80 dan NF 20 PCS; keduanya satu box, tidak menjadi dua full. Total 100 PCS, 2 box. |
| P08 | Cetak delivery dan kartu pallet pada final RECEIVE/MIX/NF. Coba cetak draft NF-DRAFT. | Keduanya hanya setelah final; A4, beberapa kartu sesuai jumlah part, nomor pallet/lokasi/part/jumlah benar, NF jelas; preview tidak terpotong. |
| P09 | Buka planning yang sama dua tab; revisi di tab 1 lalu simpan tab 2 lama. | Revision conflict meminta muat ulang; perubahan tab 1 tidak tertimpa. Jangan mereset revision lewat SQL. |

## 10. Ekspor: bulk penerimaan, penempatan dan stok

| ID | Langkah | Hasil yang harus terlihat |
| --- | --- | --- |
| R01 | Setelah RECEIVE final, scan `98110001`, `98110002`, `98110003`, semuanya E-RECEIVE. Klik Proses penerimaan satu kali. Coba juga box domestik D01. | Satu transaksi 3 box, standar 80 PCS, rincian penempatan berupa halaman. ID domestik yang sudah tercatat ditolak dengan NG. |
| R02 | Coba 123, huruf, 9 digit, ID duplikat satu draft/yang tersimpan, part tidak dikenal. | Input ID dibatasi 8 angka, NG/validasi; tidak ada stok ganda. PCS bukan input bebas. |
| R03 | Buka transaksi R01; tinjau tujuan dan cetak guide per transaksi. Konfirmasi satu box dahulu, refresh, kemudian sisanya. | Tujuan Pallet 1 E17 untuk dua box, Pallet 2 E18 satu box; highlight jelas, jumlah pending turun, refresh tidak menempatkan ulang. Box penerimaan baru tidak perlu scan picking campuran. |
| R04 | Terima `98010011`,`98010012` part E-DEADLINE dalam satu proses. | Otomatis satu ke EARLY E13 D+2, kemudian LATER E14 D+3. Pada delivery yang sama, pallet nomor terkecil yang masih butuh diprioritaskan. |
| R05 | Terima `98010021` part E-SPARE tanpa kebutuhan. Pilih pallet E05 atau lokasi kosong E23. | Diterima sebagai stok cadangan; tujuan wajib valid; stok tidak diklaim sebagai pemenuhan rencana lain. |
| R06 | Terima `98010202` part E-LOAD36 ke pallet E04 berisi 35 box. Terima `98010201` part E-MONO ke pallet E03 berisi 71 box sebelum sumber dipindah. | Preview/konfirmasi menunjukkan 36 kuning dan 72 merah, konsisten dengan aturan planning. Lakukan sebelum pemindahan 36 box MONO dari P05. |
| R07 | Stok Ekspor: ganti tampilan box/pallet; cari nomor pallet, lokasi E03, part, status; ketik cepat, reset dan pagination. | Filter/search AJAX langsung; hasil terbaru yang tampil, tanpa klik Cari; daftar pallet tidak autoexpand; Lihat detail membuka modal isi. |
| R08 | Buka Lihat box dari daftar; tinjau lokasi fisik; unduh Excel dengan filter. | Modal, bukan rincian muncul di atas halaman; label Pallet sesuai planning lalu lokasi, tidak hanya kode EX internal. Excel sesuai seluruh hasil filter, identitas dan PCS terjaga. |
| R09 | Coba konfirmasi penempatan dari tab lama, tujuan reserved orang lain, tombol doubleclick/retry. | Revisi/konflik ditolak atau replay aman; satu box satu posisi, satu riwayat mutasi per aksi sukses. |

## 11. Ekspor: Picking Verification dan salah ambil

Gunakan MIX setelah P03/P04, **sebelum memindahkan tiga box rekomendasi**.
Jangan menghabiskan ketiga scan benar sebelum menyelesaikan cabang salah scan.

| ID | Langkah | Hasil yang harus terlihat |
| --- | --- | --- |
| V01 | Buka Picking Verification ekspor, tinjau MIX vs MONO; gunakan fitur pencarian/lookup dengan box salah. | MIX dari sumber campuran wajib scan box; MONO tidak. Lookup/pencarian NG tidak memblokir proses mandatory. |
| V02 | Dalam scan mandatory MIX, scan salah `98002104` dari E02. | NG dan blocked; arahan kembalikan ke pallet asal E02 tampil. Scan benar/penempatan tidak dapat melewati issue. |
| V03 | Admin Warehouse buka Approval Scan Salah bersama domestik, filter Ekspor, approve issue `98002104`. Operator coba lanjut sebelum konfirmasi pengembalian; kemudian konfirmasi fisik di tujuan. | Approval saja belum unblock; baru konfirmasi penempatan asal membuka blokir. Tidak membuat penerimaan baru. |
| V04 | Scan ID tidak dikenal 8 digit `98019998` sebagai box fisik salah ambil. Admin isi part E-SPARE dan tujuan cadangan kosong E24. Coba approve tanpa part/tujuan. | Data wajib; setelah approval masih blocked; konfirmasi pengembalian baru mendaftarkan box ke stok yang benar dan unblock. Jangan memakai aksi Salah input untuk box fisik yang benar-benar ada. |
| V05 | Scan `98019999` sebagai typo tanpa box fisik; Admin Warehouse tutup sebagai Salah input dengan alasan wajib. Ulangi menggunakan ID sudah dikirim `98002901`. | Tidak membuat stok atau pengembalian fiktif; histori issue/alasan ada; proses unblock setelah resolusi sah. |
| V06 | Coba 123 pada mandatory scan, bukan lookup. | Ditolak sebagai format human error; tidak membuat stok/issue 8 digit fiktif atau meminta lokasi untuk angka 123. |
| V07 | Untuk missing location, terima box baru E-MIX-A `98010031` sebagai stok cadangan tetapi jangan konfirmasi penempatan; scan itu di mandatory MIX, lalu admin lengkapi tujuan. | Box dikenal tetapi belum punya posisi meminta lokasi/pallet; part tidak perlu ditebak ulang. Harus konfirmasi pengembalian/penempatan untuk unblock. |
| V08 | Setelah seluruh issue selesai, scan `98002101`, `98002102`, `98002103` benar; coba salah satu ulang; lalu tempatkan sesuai rekomendasi. | OK, tujuan E19 jelas, duplikat tidak menggandakan; picking_verified belum dianggap placed. Penempatan valid baru menaikkan KPI pemenuhan. |
| V09 | Perubahan posisi/isi sumber setelah verifikasi lewat aksi yang diizinkan; coba konfirmasi dari halaman lama. | Revision/source check mencegah verifikasi lama dipakai untuk box/posisi berubah; tinjau ulang. Lakukan pada salinan agar tidak mengubah skenario utama. |

## 12. Ekspor: Box Not Full wajib

| ID | Langkah | Hasil yang harus terlihat |
| --- | --- | --- |
| NF01 | Warehouse buka NF-DRAFT dan coba siapkan ID `98011009`. | Ditolak sampai planning final/lokasi tersedia. Tidak ada stok baru, request tetap wajib. |
| NF02 | Warehouse siapkan NF-EXT mode Datang sudah Not Full, ID baru `98011001`. Supervisi approve setelah konfirmasi fisik. | 20 PCS langsung stok dan placed di Pallet 1 E31; tidak perlu input/scan ulang. Warehouse tidak dapat approve sendiri. |
| NF03 | Terima full `98010041` part E-NF-EXT, 80 PCS, lalu tempatkan E31. | Rencana terpenuhi tepat 100 PCS, 2 box. Menerima ulang ID 98011001 ditolak agar NF tidak berubah menjadi 80. |
| NF04 | NF-SPLIT: tinjau full `98002261` reserved, siapkan split ID baru `98011002`; approve sebagai Supervisi. | Memilih sumber bebas tertua 98002262, bukan 98002261 reserved atau 98002263 lebih baru. Hasil 20 di E32, sumber menjadi 60 di E06; total PCS tetap 240. Tanggal FIFO keduanya mewarisi sumber. |
| NF05 | NF-SHORT: pilih mode split. | Tidak tersedia: satu full `98002271` dipakai kebutuhan penuh. Operator dapat menunggu stok atau pilih external NF 20 PCS `98011003`; bukan mengambil reserved full. |
| NF06 | Final NF-EXIST pada E34 dan acknowledge rekomendasi. | Spare `98002401`, 20 PCS, diprioritaskan melalui FIFO; masuk antrean penempatan biasa. Tidak membuat box NF 20 PCS baru; ikuti task/picking bila sumber campuran. Terima full `98010044` untuk melengkapi 80 PCS. |
| NF07 | Pada request baru yang prepared, reject sebagai Supervisi dengan alasan; periksa kebutuhan. Siapkan lagi dengan ID baru lalu approve. | Rejection tidak menghapus kebutuhan 20 PCS; tetap mandatory sampai stok sesuai tersedia/permintaan direvisi sah. |
| NF08 | Siapkan split pada salinan NF-SPLIT sebelum approve, lalu buat dan finalkan rencana full 80 PCS baru part sama supaya kandidat sumber `98002262` terreservasi. Coba approve persiapan lama. | Preparation tidak menahan sumber dari kebutuhan full; approval stale ditolak, harus siapkan ulang sumber FIFO bebas atau external. Tidak ada pengurangan dua kali. |
| NF09 | Request prepared punya ID baru; coba input ID itu di penerimaan normal. Approve dua kali dari tab lama. | ID prepared ditolak di receipt; retry approval tidak membuat stok atau split kedua. |
| NF10 | Revisi/hapus kebutuhan NF sebelum approve, atau ubah planning/lokasi. Coba approve tab lama. | Request tidak dibutuhkan dibatalkan; revisi stale meminta tinjauan baru; tidak membuat NF pada lokasi yang sudah berubah. |
| NF11 | Cek laporan setelah split dan external. | External NF 20 menambah inbound 20; split internal 80→60+20 tidak menambah inbound PCS. Riwayat receipt sumber tetap 80 walau stok kini 60; identitas NF jelas. |

## 13. Ekspor: pemenuhan, revisi, pembatalan dan keluar

| ID | Langkah | Hasil yang harus terlihat |
| --- | --- | --- |
| F01 | Buka RECEIVE setelah baru satu pallet terpenuhi; coba konfirmasi pallet belum lengkap dan selesaikan seluruh permintaan. | Pallet belum tepat ditolak; selesai belum tersedia/ditolak sampai seluruh pallet siap. Reserved saja belum cukup. |
| F02 | Pallet 1 penuh 2 box: klik Konfirmasi, lihat rincian, kosongkan lokasi lalu pilih lokasi semula E17. Pallet 2 penuh 1 box: konfirmasi lokasi kosong E25. | Lokasi wajib; semula boleh, lokasi kosong boleh; occupied/reserved pallet lain ditolak. Semua isi/lokasi stock/reservasi ikut pindah secara konsisten. |
| F03 | Coba edit/pindah/menambah ke pallet yang ready; gunakan opsi Revisi untuk membuka kesiapan bila perlu. | Pallet ready terkunci; revisi sah membatalkan kesiapan dan perlu konfirmasi lagi. Stok masih ada sebelum seluruh permintaan selesai. |
| F04 | Pada REV, acknowledge lalu tempatkan 98002301 dan 98002302 saja; 98002303 masih reserved, kebutuhan 4. Kurangi menjadi 1 box, tujuan return E36 kosong; jangan konfirmasi return dulu. | Preview mempertahankan 301 tertua, return 302 yang sudah placed, release 303 reserved; kekurangan box keempat tidak membuat return fiktif. Simpan tanpa tujuan/konfirmasi fisik ditolak. |
| F05 | Konfirmasi return 98002302 di E36 dan simpan revisi F04. | Kebutuhan 200→50 PCS, 1 box; 301 tetap, 302 jadi cadangan di E36, 303 cadangan asal E09. Umur FIFO asli tetap; bukan 301 yang dikembalikan. |
| F06 | Pada cabang/salinan lain kurangi semua isi satu pallet atau seluruh permintaan, termasuk terisi sebagian. | Reserved belum dipindah dilepas; placed/yang sudah diambil terverifikasi wajib return fisik; lokasi/pallet cadangan tujuan wajib valid. Pengurangan varian NF memakai PCS aktual. |
| F07 | Pada CANCEL, acknowledge, tempatkan 98002311 saja, 98002312 masih reserved. Batalkan dengan alasan, tujuan return E38, konfirmasi fisik. | 311 cadangan E38, 312 dilepas di asal E10; request cancelled/arsip, tidak ada pengiriman keluar palsu; lokasi request dilepas bila kosong. |
| F08 | RECEIVE semua pallet ready: klik Selesaikan permintaan. Baca alert bahwa seluruh box telah keluar gudang; batalkan popup dulu, lalu konfirmasi. | Membatalkan tidak mengubah stok. Konfirmasi sah mengarsipkan request/box, melepas lokasi dan meninggalkan history/audit; total 3 box 240 PCS keluar sekali. |
| F09 | Refresh/retry selesai, buka URL lama, coba scan ID yang sudah shipped. | Tidak menggandakan shipment; arsip masih bisa ditinjau melalui riwayat/laporan. Penerimaan normal menolak ID ekspor yang sudah pernah tercatat. |
| F10 | Coba selesai/reduce/cancel saat issue picking terkait masih open. | Mutasi kritis ditolak sampai issue dan pengembalian terselesaikan. |

## 14. Laporan, audit, dashboard dan antarmuka

| ID | Langkah | Hasil yang harus terlihat |
| --- | --- | --- |
| L01 | Warehouse/Supervisi/Admin buka laporan existing; pilih Domestik/Ekspor, rentang tanggal, part, lokasi, user, search dan pagination yang tersedia. | Filter AJAX langsung, scope tidak bocor; histori masuk/keluar/pindah/revisi bisa dirinci. Warehouse hanya mendapat laporan sesuai izin. |
| L02 | Unduh Excel/CSV laporan dan stok setelah filter. Bandingkan detail dan total, termasuk histori D−1. | Seluruh hasil sesuai filter, bukan hanya halaman aktif; satuan PCS/box benar, ID Box teks 8 digit, file dapat dibuka. |
| L03 | Audit: filter transaksi UAT26, user, aksi, tanggal, scope; rincian before/after dan export. | Semua mutasi penting/approval/reject/return/ship/redo/QA ada aktor, waktu, alasan; tidak ada audit sukses untuk transaksi gagal rollback. |
| L04 | Admin Utama/Supervisi: dashboard default Semua; ubah Domestik/Ekspor dan tanggal via AJAX, Back/Forward, reset. | Default Semua; label scope jelas; grafik domestik PCS, ekspor BOX terpisah; tidak dijumlahkan sebagai satuan campuran. |
| L05 | Bandingkan NF-SPLIT/REV yang baru reserved dengan placed; RECEIVE ready dengan complete. | Reservasi belum pemenuhan; placed valid menaikkan pemenuhan; ready masih stok; shipment baru setelah complete. Draft belum final/cancelled tidak masuk KPI pemenuhan. |
| L06 | Lihat stok/pallet aktif sambil mengganti periode, lalu issue/histori durasi. | Stok aktif posisi sekarang, bukan dibatasi tanggal penerimaan; periode memfilter arus/KPI terkait; durasi domestik/ekspor tidak dicampur. |
| L07 | Unduh dashboard Excel; buka sheet/grafik/rincian audit, bandingkan angka web. | Data sesuai scope/filter, grafik native dan unit benar, total tidak berhenti pada limit rincian 200 baris. |
| L08 | Coba rentang tanggal terbalik, hasil kosong, mengetik search cepat, koneksi lambat/error lalu retry. | Pesan validasi/pemulihan jelas; respons lama tidak menimpa filter baru; tidak menampilkan data lama dari scope lain sebagai hasil baru. |
| UI01 | Chrome desktop, layar sempit, keyboard Tab/Enter, scanner USB dengan Enter; tutup modal Esc, cetak A4. | Fokus scan kembali, tombol/label terbaca, tabel dapat digulir, modal tidak terselip, cetak tidak terpotong. |
| UI02 | DevTools Network: filter/search/pagination, navigasi keluar/masuk, doubleclick simpan. | Filter AJAX tidak reload penuh, loading pulih setelah error, tidak mengirim mutasi ganda. Catat waktu respons p50/p95 pada volume development sendiri; paket kecil ini bukan benchmark production. |

## 15. Uji konkurensi dan pengecekan akhir

Lakukan pada dua profil Chrome, memakai salinan/rencana percobaan agar data utama
tidak habis. Catat kedua respons dan cek DB, bukan hanya toast.

| ID | Kondisi bersamaan | Harapan |
| --- | --- | --- |
| C01 | Dua sesi operator ekspor menyimpan ID Box sama dengan token berbeda. | Satu berhasil, satunya ditolak; satu stok dan satu identitas global domestik/ekspor. |
| C02 | Dua finalisasi memakai lokasi kosong sama. | Hanya satu claim; tidak ada dua pallet aktif pada satu lokasi. |
| C03 | Dua rencana membutuhkan spare box yang sama. | Hanya satu reservasi; prioritas deadline dan FIFO konsisten. |
| C04 | Place/reduce/approve NF memakai revision lama dari tab kedua. | Conflict/replay aman; tidak ada kehilangan PCS atau box ganda. |
| C05 | Finish dikirim dua kali atau saat proses lain revisi. | Tidak ada shipment ganda; revision dan lock menjaga hasil atomik. |
| C06 | Role dicabut aksesnya lalu submit halaman lama. | Server tetap memeriksa izin; tombol disembunyikan bukan satu-satunya perlindungan. |

Setelah UAT, jalankan bagian **integritas** `02_verify_business_uat.sql` lagi.
Jumlah baseline wajar berubah, tetapi identitas yatim, lokasi ganda, stok negatif,
reservasi ganda, kapasitas reservasi tidak cocok, dan PCS NF hilang harus tetap 0.
Bandingkan dengan file hasil awal. Periksa log Laravel untuk exception baru.

Gunakan template hasil berikut, satu baris per kasus:

Untuk langsung mencatat seluruh hasil, gunakan `docs/UAT_RESULT_TEMPLATE.md`.
File tersebut sudah memuat 116 kasus dengan status awal **Belum diuji**.

| ID kasus | Lulus/Gagal/Belum diuji | Penguji/tanggal | ID/kode transaksi | Hasil aktual / bukti |
| --- | --- | --- | --- | --- |
| A01 | Belum diuji | | | |

Kriteria penerimaan: seluruh kasus kritis inventory/otorisasi/FIFO/approval/
pengiriman lulus, tidak ada selisih PCS atau identitas, hasil laporan sesuai mutasi,
dan semua bug yang ditemukan dicatat/diperbaiki sebelum rollout.

## 16. Pemeliharaan paket oleh developer

`php scripts/uat/build.php` membuat **SQLite baru** pada
`storage/framework/testing/uat26`, menjalankan migrations, membangun fixture
melalui model/service, lalu menghasilkan SQL dan manifest. Builder menolak driver
selain SQLite untuk fixture. Metadata hostname MySQL lokal hanya dibaca.

`php scripts/uat/validate-mysql.php` membaca skema DB lokal dari `.env`, membuat
DB sementara bernama `whfg_uat_verify_<acak>`, menguji SQL dan integritasnya, lalu
menghapus DB sementara itu di blok finally. Ia tidak mengimpor fixture ke `stock`.
Memerlukan izin CREATE/DROP DATABASE pada MySQL lokal. Detail hasil validasi ada
di `database/uat/validation.json`.

Jika PHP Windows tidak mengenali path Unicode, jalankan lewat path ASCII/drive
sementara yang menunjuk folder project; hal ini hanya kebutuhan tool pengembang,
bukan konfigurasi pada PC operator Chrome. Hash password fixture bcrypt baru
setiap build. Jangan gunakan akun/password uji ini di production.
