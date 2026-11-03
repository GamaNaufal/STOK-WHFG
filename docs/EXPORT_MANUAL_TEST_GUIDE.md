# Uji manual alur ekspor dengan data yang sudah diimpor

Gunakan panduan ini untuk menjalankan sendiri proses melalui aplikasi. Tidak
perlu mengimpor SQL lagi. Langkah di bawah tidak dijalankan oleh agent; penguji
yang membuat rencana, memindai box, mengonfirmasi fisik, dan menyelesaikan delivery.

Pemeriksaan **baca saja** pada database development lokal tanggal 1 November 2026
menemukan data UAT26 masih dalam kondisi awal: 18 part yang saat itu berada di master ekspor, 50 lokasi,
123 box aktif dan 4 box histori. Sebagian box sudah direservasi untuk rencana
contoh final. Semua ID baru berawalan `9811` yang dipakai di panduan ini belum
tercatat saat pemeriksaan. Kondisi dapat berubah setelah Anda menjalankan tes.

> Pembaruan 3 November 2026: jalankan migrasi penyatuan master terlebih dahulu.
> Jangan impor ulang fixture ke database yang sudah memuat UAT26. Data uji lama
> boleh diteruskan jika `inventory:unification-check` lulus; jika ada konflik,
> selesaikan sesuai [panduan migrasi](SHARED_INVENTORY_MIGRATION.md).

## Persiapan

- Operator: `uat26.export_operator@whfg.test`.
- Penyiapan Not Full/approval salah scan: `uat26.admin_warehouse@whfg.test`.
- Approval Not Full: `uat26.supervisi@whfg.test` atau `uat26.admin@whfg.test`.
- Password akun uji: `UatWHFG!2026`.
- H berarti hari Anda menjalankan tes. H+7 berarti tujuh hari sesudahnya.
- ID Box harus dipindai/diketik lengkap 8 digit. Pada tes development,
  ketik nomor tersebut sebagai simulasi label box.
- Semua part ditulis lengkap. Singkatan lokasi E41 berarti `UAT26-E41`.
- Jalankan berurutan. Untuk mencoba ulang, gunakan kode rencana/ID Box baru
  dan lokasi yang masih kosong. Jangan menghapus reservasi melalui SQL.

Sebagian rencana contoh sudah final agar kasus lanjutan dapat langsung diuji.
Status header delivery dapat masih bertuliskan draft ketika **perencanaan
palletnya sudah final**; periksa status perencanaan di rinciannya.

| Kasus | Titik mulai |
| --- | --- |
| 1–5, 7, 12–13 | Buat rencana baru sendiri |
| 6, 8–11 | Gunakan rencana contoh yang sudah tersedia |
| 14 | Uji master dan tinjau hasil seluruh proses |

## Kasus 1 — Dari membuat delivery sampai keluar gudang

Tujuan: menguji alur utama dengan box yang baru diterima, tanpa stok sebelumnya.

1. Login sebagai Operator Export, buka **Rencana Delivery → Buat**.
2. Isi kode `TES-EXP-01`, customer `Customer Uji 01`, tenggat H+7.
3. Tambahkan `UAT26-E-RECEIVE`, jumlah **240 PCS**, lalu simpan.
   Master part ini 80 PCS/box: hasil harus **3 box**, tanpa Not Full.
4. Buka **Perencanaan Pallet** untuk rencana yang baru Anda buat. Isi:

   | Pallet | Lokasi | Part | Jumlah box |
   | --- | --- | --- | ---: |
   | 1 | `UAT26-E41` | `UAT26-E-RECEIVE` | 2 |
   | 2 | `UAT26-E42` | `UAT26-E-RECEIVE` | 1 |

5. Coba simpan draft saat belum lengkap, lalu lengkapi dan **finalkan**.
   Total alokasi wajib tepat 3 box; stok part ini belum tersedia.
6. Setelah final, uji **cetak rencana delivery dan kartu pallet**. Draft belum
   boleh dicetak. Periksa part, box, lokasi dan ukuran A4 pada print preview.
7. Buka **Penerimaan transfer box ekspor**. Scan tiga box berikut, semuanya
   part `UAT26-E-RECEIVE`: `98110001`, `98110002`, `98110003`.
8. Klik **Proses penerimaan** satu kali. Ketiganya harus menjadi **1 transaksi**.
9. Buka **Atur penempatan** transaksi tersebut. Periksa tujuan 2 box ke
   Pallet 1 E41, 1 box ke Pallet 2 E42. Cetak guide penempatannya.
10. Konfirmasi penempatan satu box dulu. Buka **Pemenuhan Permintaan**:
    reservasi belum dianggap terpenuhi, baru penempatan yang dikonfirmasi.
11. Lengkapi penempatan semua box. Box baru dari penerimaan ini tidak perlu
    verifikasi picking untuk pengambilan dari stok lama.
12. Konfirmasi Pallet 1 siap di lokasi semula E41. Konfirmasi Pallet 2 siap,
    pindahkan lokasi ke E43 yang kosong. Rincian dan stok harus ikut E43.
13. Saat semua pallet siap, klik **Selesaikan permintaan**. Baca alert seluruh
    box sudah keluar gudang; coba batalkan popup dulu lalu konfirmasikan.

**Lulus jika:** selesai hanya sekali; 3 box/240 PCS tidak lagi di stok aktif,
lokasi yang ditinggalkan tersedia kembali, histori/laporan/audit tetap ada.
Klik ulang/refresh tidak membuat pengiriman kedua.

Rencana `UAT26-EX-RECEIVE` hasil impor boleh tetap sebagai draft. Jangan ikut
finalkan rencana tersebut selama menjalankan kasus ini agar kebutuhan tidak dobel.

## Kasus 2 — Rekomendasi FIFO dari pallet campuran dan salah ambil

Stok siap: `98002101`, `98002102` part A, serta `98002103` part B di E01;
`98002104` part A yang lebih baru ada di E02. Semuanya belum direservasi.

1. Buat delivery baru `TES-EXP-02`, customer `Customer Uji 02`, tenggat H+7.
2. Isi `UAT26-E-MIX-A` **160 PCS** dan `UAT26-E-MIX-B` **40 PCS**.
3. Buat 1 pallet di E44: 2 box A (80 PCS/box), 1 box B (40 PCS/box). Finalkan.
4. Popup pencarian stok harus merekomendasikan **98002101, 98002102, 98002103**.
   Box 98002104 tidak dipilih karena lebih baru. Klik tombol masukkan antrean.
5. Buka **Picking Verification ekspor**. Sumbernya pallet campuran sehingga
   wajib scan setiap box rekomendasi sebelum penempatan.
6. Pada scan mandatory, coba box salah `98002104`. Harus NG dan blocked;
   petunjuk mengembalikan ke pallet asal di E02 harus terlihat.
7. Login Admin Warehouse, buka **Approval Scan Salah**, pilih lingkup Ekspor
   dan issue box tersebut, lalu approve. Operator mencoba lanjut: masih blocked.
8. Operator konfirmasi box salah sudah dikembalikan ke asal. Baru setelah itu
   blokir boleh terbuka. Approval saja belum cukup.
9. **Sebelum menuntaskan scan benar**, jalankan variasi berikut satu per satu:

   | Percobaan | Hasil yang diperiksa |
   | --- | --- |
   | Ketik `123` pada scan mandatory | Format ditolak, tidak meminta pengembalian box fiktif |
   | Scan `98119999` sebagai salah ketik tanpa box fisik | Admin menutup sebagai Salah input dengan alasan; tidak membuat stok |
   | Scan ID sudah dikirim `98002901` sebagai salah input tanpa box fisik | Riwayat lama tidak didaftarkan ulang; tutup dengan alasan |
   | Scan `98119998` sebagai box fisik tidak dikenal | Admin mengisi part `UAT26-E-SPARE`, tujuan E48; operator wajib konfirmasi penempatan setelah approval |
   | Terima `98110031` part A tetapi jangan tempatkan; lalu salah scan di picking | Box dikenal tanpa posisi meminta tujuan, misalnya E49; konfirmasi penempatan tetap wajib |

10. Setelah tiap issue terselesaikan, scan ketiga ID rekomendasi yang benar,
    kemudian konfirmasi penempatannya di E44. Coba scan duplikat satu kali.
11. Pada fitur pencarian/lookup picking, uji box salah: NG untuk pencarian
    tidak boleh membuat blokir seperti scan mandatory.
12. Setelah lengkap, konfirmasi pallet siap dan selesaikan delivery.

**Lulus jika:** FIFO dan mandatory scan berlaku, salah ambil fisik baru unblock
setelah return, salah input tanpa fisik tidak menambah stok, dan penempatan
tetap menuju pallet rencana. Rencana impor `UAT26-EX-MIX` dibiarkan draft.

## Kasus 3 — Warning stok 36/72 dan pengambilan dari pallet satu part

1. Di penerimaan, scan `98110021` part `UAT26-E-MONO` dan `98110022` part
   `UAT26-E-LOAD36`. Proses keduanya sebagai satu transaksi.
2. Tempatkan 98110021 pada **pallet existing di E03**. Jumlah awal 71 box
   menjadi **72: merah**. Tempatkan 98110022 pada **pallet existing di E04**:
   jumlah awal 35 menjadi **36: kuning**. Pilih pallet existing, bukan membuat
   pallet baru pada lokasi yang occupied.
3. Buat delivery baru `TES-EXP-03`, customer `Customer Uji 03`, tenggat H+7,
   part `UAT26-E-MONO`, **720 PCS = 36 box**.
4. Buat 1 pallet berisi 36 box di E45, finalkan dan tinjau popup FIFO.
5. Harus dipilih box tertua **98002001 sampai 98002036**, bukan box baru 98110021.
6. Masukkan ke antrean. Sumber hanya satu part sehingga pengambilan 36 dari
   72 box tersebut **tidak wajib scan picking satuan**.
7. Konfirmasi penempatan 36 box, lalu konfirmasi siap dan selesaikan delivery.

**Lulus jika:** warning tampil sesuai jumlah, sumber mono tidak diperlakukan
sebagai campuran, dan box yang lebih baru tetap di stok sumber.

## Kasus 4 — Warning saat planning, alokasi salah, dan cetak

1. Buat `TES-EXP-04`, customer `Customer Uji 04`, tenggat H+7,
   part `UAT26-E-WARN`, **720 PCS = 72 box**.
2. Coba 1 pallet 36 box: label kuning. Simpan draft boleh, final belum boleh
   karena masih kurang 36 box.
3. Ubah menjadi 72 box pada 1 pallet: label merah. Coba 73: final ditolak
   karena melebihi kebutuhan. Warning merah sendiri bukan larangan kapasitas.
4. Akhirnya buat 2 pallet masing-masing 36 box di E46 dan E47, lalu finalkan.
5. Coba lokasi sama pada kedua pallet atau lokasi occupied/reserved:
   harus ditolak. Buka dua tab dan simpan tab lama setelah tab lain berubah:
   harus meminta muat ulang, bukan menimpa revisi baru.
6. Cetak delivery dan kartu pallet. Belum ada stok part ini, sehingga pemenuhan
   tetap menunggu. Tidak perlu menerima 72 box untuk menyelesaikan tes planning.

**Lulus jika:** jumlah per part tepat, konflik lokasi/revisi terlindungi,
warning 36/72 konsisten, kartu tiap pallet benar dan cetak hanya setelah final.

## Kasus 5 — Buat permintaan Not Full dari awal, datang sudah dikemas

1. Buat `TES-EXP-05`, customer `Customer Uji 05`, tenggat H+7,
   part `UAT26-E-NF-DRAFT`, jumlah **100 PCS**. Standar part 80 PCS/box.
2. Setelah simpan, periksa hasil **1 full 80 + 1 Not Full 20 PCS = 2 box**,
   alert hubungi admin, dan request wajib Not Full pada rencana ini.
3. Simpan/edit lagi tanpa mengubah jumlah: request yang sama tidak boleh dobel.
4. Admin Warehouse mencoba menyiapkan sebelum planning: harus ditolak.
5. Operator buat 1 pallet E21 berisi **1 box varian 80 dan 1 box varian 20**,
   lalu finalkan. Jangan isi 2 box varian 80.
6. Admin Warehouse buka request **TES-EXP-05**, pilih datang sudah Not Full,
   ID baru `98111001`. Uji bahwa Warehouse tidak bisa approve sendiri.
7. Supervisi reject dengan alasan kemasan belum sesuai. Kebutuhan 20 tetap ada.
8. Warehouse siapkan ulang dengan ID baru `98111002`. Supervisi/Admin approve
   dengan konfirmasi fisik. Box 20 PCS harus langsung placed di E21,
   **tidak perlu melalui penerimaan/scan ulang**.
9. Terima full `98110041`, part yang sama, lalu tempatkan di E21.
10. Rencana tepat 100 PCS/2 box; konfirmasi siap dan selesaikan.

**Lulus jika:** request otomatis dan wajib, hanya approval role yang sah,
stok Not Full langsung di tujuan, master tetap 80, tidak ada penerimaan dobel.
Rencana impor NF-DRAFT tetap draft; pilih request kode TES-EXP-05 saat approval.

## Kasus 6 — Not Full dengan memecah stok penuh secara FIFO

Untuk kasus ini, **lanjutkan `UAT26-EX-NF-SPLIT` yang planning-nya sudah final**.
Tidak perlu membuat rencana baru dengan part yang sama.

1. Buka pemenuhan rencana tersebut: kebutuhan 100 PCS, tujuan E32.
2. Tinjau stok `UAT26-E-NF-SPLIT`: 98002261, 98002262, 98002263, masing-masing 80 PCS.
   **98002261 sudah reserved untuk kebutuhan full** pada rencana ini.
3. Admin Warehouse pilih request rencana tersebut, mode pecah stok penuh,
   ID hasil baru `98111003`. Sumber harus **98002262**, sumber bebas tertua.
4. Supervisi approve dengan konfirmasi fisik. Periksa:

   | Box | Hasil |
   | --- | --- |
   | 98002261 | Tetap 80, reserved untuk kebutuhan full |
   | 98002262 | Sisa 60, tetap di asal E06 |
   | 98002263 | Tetap 80, lebih baru dan belum dipakai |
   | 98111003 | Not Full 20, placed di E32, umur FIFO mewarisi sumber |

5. Sebelum shipment, total part ini harus tetap **240 PCS**, sekarang 4 box.
6. Tinjau/acknowledge antrean stok FIFO, pindahkan full 98002261 ke E32.
   Setelah tepat 100 PCS, konfirmasi siap dan selesaikan.

**Lulus jika:** reserved full tidak dipecah, sumber bebas mengikuti FIFO,
ID hasil baru, sisa tetap terlacak, dan split tidak menambah inbound PCS.

## Kasus 7 — Not Full yang tepat sudah tersedia di stok

1. Buat `TES-EXP-07`, customer `Customer Uji 07`, tenggat H+7,
   part `UAT26-E-NF-EXIST`, **100 PCS**.
2. Buat pallet E23: 1 full 80 dan 1 Not Full 20, lalu finalkan.
3. Popup harus merekomendasikan **98002401**, box Not Full 20 PCS yang sudah ada
   di E12. Masukkan ke antrean dan konfirmasi pemindahan ke E23.
4. Tidak perlu membuat/approve box Not Full baru untuk kebutuhan 20 PCS tersebut.
5. Terima full `98110044` part yang sama; tempatkan, konfirmasi siap, selesaikan.

**Lulus jika:** stok dengan PCS tepat dimanfaatkan dahulu, tidak muncul stok
Not Full baru/ganda, dan penerimaan full tetap mengikuti master 80 PCS.

## Kasus 8 — Tidak ada sumber penuh yang boleh dipecah

1. Lanjutkan rencana impor **`UAT26-EX-NF-SHORT`**, final di E33.
2. Stok satu-satunya, `98002271` 80 PCS, sudah reserved untuk kebutuhan full.
3. Warehouse coba mode pecah stok: harus ditolak/tidak tersedia.
4. Pilih datang sudah Not Full dengan ID `98111004`, lalu approve sebagai
   Supervisi/Admin setelah konfirmasi fisik. Hasil 20 PCS langsung di E33.
5. Tinjau antrean reserved full 98002271, pindahkan ke E33 dan selesaikan normal.

**Lulus jika:** sistem tidak mengambil sumber yang sudah terikat kebutuhan full
dan memberi pilihan external/menunggu stok secara jelas.

## Kasus 9 — Pengurangan pallet terisi sebagian, box terbaru dikembalikan

1. Lanjutkan **`UAT26-EX-REV`**, kebutuhan 4 box × 50 = 200 PCS, final di E35.
2. Tinjau/acknowledge stok FIFO. Konfirmasi penempatan **98002301 dan 98002302**
   saja; 98002303 tetap reserved di sumber E09. Box keempat memang belum ada.
3. Revisi kebutuhan pallet menjadi **1 box**. Preview harus memperlihatkan:
   98002301 tertua dipertahankan, 98002302 dikembalikan, 98002303 dilepas.
4. Coba simpan tanpa lokasi/pallet return atau tanpa konfirmasi fisik: ditolak.
5. Pilih lokasi kosong E36, konfirmasi pengembalian, simpan.
6. Kebutuhan menjadi 50 PCS; 98002302 cadangan E36, 98002303 cadangan asal E09. Tidak ada
   pengembalian fiktif untuk box keempat. FIFO tanggal asli tetap.
7. Konfirmasi pallet siap, lalu uji opsi revisi pallet siap: kesiapan harus
   dibuka lewat alur revisi sebelum perubahan. Konfirmasi kembali bila sesuai.

**Lulus jika:** yang lebih baru dikembalikan, reserved yang belum dipindah
dilepas, penempatan return wajib, dan jumlah rencana berubah sesuai PCS aktual.

## Kasus 10 — Membatalkan permintaan yang sebagian sudah ditempatkan

1. Lanjutkan **`UAT26-EX-CANCEL`**, final E37, kebutuhan 2 box × 80 PCS.
2. Tinjau/acknowledge antrean dan tempatkan 98002311 saja. 98002312 masih reserved.
3. Batalkan permintaan. Isi alasan `Uji pembatalan customer`, tujuan return E38,
   dan konfirmasi bahwa box sudah dikembalikan secara fisik.
4. Periksa 98002311 menjadi cadangan E38; 98002312 dilepas di asal E10.
5. Request menjadi cancelled/arsip. Tidak boleh tercatat shipment keluar 160 PCS.

**Lulus jika:** placed wajib return, reservasi bebas dilepas, histori/alasan
tetap ada, tidak ada stok hilang atau pengiriman palsu. Untuk uji hapus seluruh
isi satu pallet, ulangi pola ini melalui revisi jumlah 0 pada rencana uji baru.

## Kasus 11 — Prioritas tenggat dan nomor pallet

1. Buka rencana impor **`UAT26-EX-EARLY`** (E13) dan **`UAT26-EX-LATER`** (E14).
   Masing-masing membutuhkan 1 box `UAT26-E-DEADLINE`; planning sudah final.
2. Periksa tenggat EARLY lebih awal daripada LATER.
3. Terima `98110011` dan `98110012`, part yang sama, dalam 1 transaksi.
4. Atur penempatan: box pertama dialokasikan ke EARLY, berikutnya LATER.
   Konfirmasi penempatan dan periksa pemenuhan masing-masing.
5. Pada kasus 1, penempatan 2+1 box menguji urutan pallet 1 sebelum 2 dalam delivery.
   Untuk menguji antrean prioritas sekali lagi, buat part baru lewat master
   bersama lalu dua rencana baru dengan tenggat berbeda; jangan menambah rencana
   part ini tanpa memperhitungkan permintaan impor yang masih aktif.

**Lulus jika:** sistem memilih permintaan sendiri berdasarkan tenggat dan
nomor pallet, tanpa meminta operator memilih rencana saat penerimaan.

## Kasus 12 — Buat delivery melalui Excel

1. Unduh template pada halaman buat Rencana Delivery. Isi B1 `Customer Uji 12`,
   B2 tanggal H+7 dalam format YYYY-MM-DD, B3 `TES-EXP-12`.
2. Isi baris mulai A5/B5:

   | Sel part | Nilai part | Sel jumlah | PCS |
   | --- | --- | --- | ---: |
   | A5 | UAT26-E-WARN | B5 | 20 |
   | A6 | UAT26-E-SPARE | B6 | 0 |
   | A7 | UAT26-E-LOAD36 | B7 | kosong |

3. Upload, periksa preview: part WARN 20 PCS berarti 2 box; baris 0/kosong diabaikan
   dengan warning. Ubah satu nilai saat review lalu simpan.
4. Coba file rusak/part tidak dikenal pada dokumen percobaan: tidak boleh
   membentuk rencana salah. Arsipkan draft percobaan setelah selesai.
5. Pada format dokumen produksi lama, uji jumlah dari kolom C dan kode header;
   Qty/Bx kolom G tidak menggantikan kapasitas master. Layout ini berbeda dari
   template sederhana di atas. Foto/OCR sudah dihapus dan bukan bagian tes.

**Lulus jika:** data baru ada setelah disimpan, kode/customer/tenggat benar,
hasil review dapat dikoreksi, dan peringatan baris yang diabaikan terlihat.

## Kasus 13 — Kode delivery unik dan submit ganda

1. Buat draft `TES-EXP-13`, customer `Customer Uji 13`, H+7,
   part `UAT26-E-DUP`, 80 PCS.
2. Coba simpan lagi kode ` tes-exp-13 ` dengan customer/tanggal/jumlah berbeda:
   ditolak pada kode rencana; belum ada header baru. Kode wajib diisi.
3. Gunakan kode baru `TES-EXP-13-B`: isi yang sama boleh disimpan tanpa override.
4. Ubah kode draft pertama ke `TES-EXP-13-C`. Kode lamanya tetap tidak boleh
   digunakan oleh rencana lain. Kode milik rencana itu sendiri boleh disimpan.
5. Klik Simpan cepat dua kali/retry: token sama hanya menghasilkan satu rencana.
6. Coba kode `UAT26-EX-HISTORY`, `UAT26-EX-ARCHIVE` atau kode permintaan cancelled:
   semuanya tetap ditolak walaupun rencana asal sudah tidak aktif.

**Lulus jika:** kode unik sepanjang histori, normalisasi case/spasi berlaku,
isi serupa dengan kode baru boleh, dan retry tidak membuat data ganda.

## Kasus 14 — Master, stok, laporan, dashboard dan akses

1. Operator Export buat master `TES-EXP-PART`, kapasitas 25, dan lokasi baru
   `TES-EXP-LOC`. Uji edit master yang belum dipakai, duplikat, kapasitas 0.
   Kemudian buat draft delivery memakai part tersebut: perubahan kapasitas
   selama ada rencana aktif harus ditolak. Lokasi occupied/reserved dilindungi.
2. Cari `UAT26-SHARED` pada **Master Nomor Part Bersama**: hanya satu master
   untuk kedua alur. Fixture baru memakai 100 PCS/box; pada data lama tanpa
   pasangan domestik, kapasitas existing dipertahankan. ID Box yang tercatat
   pada satu lingkup harus ditolak jika dicoba pada lingkup lain, termasuk histori.
   Lokasi tetap terpisah; akses master operator untuk debug hanya default lokal/testing.
3. Buka **Stok Ekspor**, cari box, part, lokasi dan pallet hasil kasus 1–11.
   Ganti pengelompokan box/pallet dan filter status. Filter harus AJAX tanpa
   klik Cari; Lihat detail pallet membuka popup berisi box, tidak autoexpand.
4. Unduh Excel stok dengan filter: cocokkan ID Box/PCS/lokasi/seluruh hasil.
5. Admin Warehouse/Supervisi/Admin buka laporan existing, pilih scope Ekspor,
   periode pengujian dan part. Cocokkan inbound, perpindahan, return, shipment.
   Split kasus 6 tidak menambah inbound PCS; external kasus 5 menambah 20 PCS.
6. Admin/Supervisi buka dashboard, ganti Semua/Domestik/Ekspor dengan AJAX.
   Grafik ekspor BOX terpisah dari domestik PCS. Reserved belum pemenuhan;
   placed valid baru terpenuhi; ready masih stok; complete baru berkurang.
7. Periksa audit kasus salah scan, approval, return, Not Full, revisi, cancel,
   dan shipment: aktor/waktu/alasan/rincian benar. Coba filter dan ekspor.
8. Uji URL approval langsung sebagai Operator Export: harus ditolak. Warehouse
   hanya menyiapkan Not Full; Supervisi/Admin approve. Uji role lewat profil
   Chrome berbeda agar session tidak tertukar.
9. Uji refresh/doubleclick/dua tab saat finalisasi, penempatan dan selesai:
   tidak ada stok ganda, saling menimpa revisi, atau shipment ganda. Jangan pakai
   2 pengguna untuk mengonfirmasi aksi yang sama tanpa meninjau respons conflict.

**Lulus jika:** transaksi aplikasi cocok dengan stok dan laporan, akses tetap
terjaga, data dua lingkup tidak tercampur, dan semua perubahan bisa ditelusuri.

## Mencatat hasil

Tidak perlu menjalankan query verifikasi untuk tiap langkah. Nilai hasil melalui
halaman aplikasi; query SQL verifikasi yang sudah tersedia boleh dipakai sebagai
pengecekan tambahan setelah satu rangkaian selesai.

| Kasus | Hasil (Lulus/Gagal/Belum diuji) | Kode transaksi / hasil aktual / bukti |
| --- | --- | --- |
| 1. Delivery baru → shipment | Belum diuji | |
| 2. FIFO campuran dan salah scan | Belum diuji | |
| 3. Warning stok dan sumber mono | Belum diuji | |
| 4. Planning dan cetak | Belum diuji | |
| 5. NF external dari awal | Belum diuji | |
| 6. NF split FIFO | Belum diuji | |
| 7. NF existing | Belum diuji | |
| 8. Sumber split tidak tersedia | Belum diuji | |
| 9. Pengurangan terbaru | Belum diuji | |
| 10. Pembatalan dan return | Belum diuji | |
| 11. Prioritas tenggat/pallet | Belum diuji | |
| 12. Upload Excel | Belum diuji | |
| 13. Kode unik dan retry | Belum diuji | |
| 14. Master, stok, laporan, dashboard, akses | Belum diuji | |

Checklist teknis lebih rinci tetap tersedia pada `UAT_BUSINESS_GUIDE.md`.
Paket data saat ini cukup untuk rangkaian di atas; tidak perlu reset atau
mengimpor ulang hanya untuk mulai tes manual ekspor.
