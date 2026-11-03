# Lembar hasil pengujian UAT26

Isi identitas pengujian dan hasil setiap kasus sesuai `UAT_BUSINESS_GUIDE.md`.
Seluruh baris dimulai sebagai **Belum diuji**; hasil validasi SQL/service tidak
menggantikan pemeriksaan UI oleh penguji.

- Nama database development:
- Tanggal impor / D0:
- Versi aplikasi / commit yang diuji:
- Penguji dan role:
- Browser / perangkat scanner:
- Lokasi screenshot / bukti:

Pilihan hasil: **Lulus / Gagal / Belum diuji / Tidak berlaku**.
Jika Tidak berlaku, tulis alasan. Untuk Gagal, cantumkan hasil aktual, ID/kode
transaksi, pesan error dan bukti. Langkah lengkap dan hasil yang diharapkan
berada pada kasus dengan ID yang sama dalam panduan.

| ID | Ringkasan langkah | Hasil | Penguji / tanggal | ID transaksi / hasil aktual / bukti |
| --- | --- | --- | --- | --- |
| A01 | Login setiap akun aktif; logout; coba password salah dan akun inactive. | Belum diuji | | |
| A02 | Operator domestik buka URL ekspor dari profil export; export buka approval/admin user; Warehouse coba approve NF ekspor. | Belum diuji | | |
| A03 | Admin buat user `UAT26 Tester`, edit role, nonaktifkan; coba login lagi. | Belum diuji | | |
| A04 | Pada salinan DB, admin ubah Hak Akses satu role lalu login ulang. Uji dependensi picking/split/redo ke jadwal, edit/hapus ke stok. | Belum diuji | | |
| A05 | Ubah profil/password akun uji jika tersedia; coba field tidak valid. | Belum diuji | | |
| M01 | Master part bersama dan identitas box global | Belum diuji | | |
| M02 | CRUD master bersama dan validasi kapasitas | Belum diuji | | |
| M03 | Edit/hapus master yang belum dipakai; lalu coba master ekspor E-NF-DRAFT atau E-MIX-A yang dipakai rencana aktif/draft. | Belum diuji | | |
| M04 | Kelola lokasi baru `UAT26-CRUD-LOC`; coba duplikat, ubah/hapus lokasi occupied/reserved. | Belum diuji | | |
| D01 | Operator buka Stock Input. Scan box `98010001` dan part `UAT26-D-FREE`; tambah `98010002`. Simpan ke lokasi kosong D23. | Belum diuji | | |
| D02 | Sebelum simpan, scan box yang sama dua kali, part tidak dikenal, angka 123, huruf, lebih dari 8 digit. Hapus baris salah/reset draft bila perlu. | Belum diuji | | |
| D03 | Input box baru `98010003` part D-FREE ke pallet D23 yang dibuat D01. | Belum diuji | | |
| D04 | Cari box `98000101`; filter part/pallet/lokasi; buka detail; unduh Excel stok. | Belum diuji | | |
| D05 | Admin Warehouse edit data box bebas `98000901`; coba perubahan tidak valid; hapus `98000902` dengan alasan jika diminta. | Belum diuji | | |
| D06 | Coba edit/hapus box yang sedang dipilih/terikat delivery; lalu hapus pallet yang tidak eligible. | Belum diuji | | |
| D07 | Move pallet D07 ke D24 kosong; coba pindahkan ke lokasi occupied. | Belum diuji | | |
| D08 | Merge dua pallet D08/D09 part D-MERGE; tentukan tujuan yang valid. | Belum diuji | | |
| D09 | Ulangi input ID `98000902` setelah dihapus dan ID `98001101` yang sudah dikirim. | Belum diuji | | |
| D10 | Sales buat permintaan `UAT26 DOM NEW`, part `UAT26-D-FREE`, jumlah 20 PCS, tenggat D+7. Coba quantity kosong/negatif/part tidak dikenal. | Belum diuji | | |
| D11 | PPC proses customer CORRECTION dengan koreksi, Sales perbaiki dan ajukan lagi. PPC proses REJECT dengan reject. | Belum diuji | | |
| D12 | PPC approve UAT26 DOM NEW; kosongkan catatan lebih dahulu. Review PRIORITY-LATER saat stok 50 PCS tetapi dua order masing-masing 40 PCS. | Belum diuji | | |
| D13 | Buka FIFO dan mulai picking/verifikasi; cetak daftar rekomendasi. | Belum diuji | | |
| D14 | Pada **Picking Verification domestik**, scan `98000103` yang tidak direkomendasikan, lalu dua ID benar. | Belum diuji | | |
| D15 | Lanjutkan scan pengiriman FIFO, scan `98000101` dan `98000102`; coba selesai saat baru satu box, lalu setelah lengkap; klik/retry selesai lagi. | Belum diuji | | |
| D16 | Admin Warehouse buka HISTORY-REDO pada riwayat selesai D−1; tinjau opsi redo dan lakukan satu redo dengan tujuan yang valid. | Belum diuji | | |
| D17 | Untuk DIRECT 60 PCS, input langsung box baru `98010011`, `98010012` part D-DIRECT lewat alur assignment/input delivery yang tersedia; tinjau dan konfirmasi. | Belum diuji | | |
| D18 | Pada ISSUE 80 PCS, scan final `98000203` padahal rekomendasi `98000201` dan `98000202`. | Belum diuji | | |
| D19 | Admin Warehouse buka Approval Scan Salah, tinjau box/lokasi, lakukan approval yang sah; operator lanjut sesuai arahan. | Belum diuji | | |
| D20 | Pada SPLIT 80 PCS, Admin Warehouse split menjadi 40 dan 40 PCS; uji quantity tidak valid; tinjau anak/parent dan cetak. Gunakan restore split sebelum dipicking jika tersedia. | Belum diuji | | |
| D21 | Operator 1 mulai LOCK. Operator 2 mencoba order/sesi sama dan order lain. Coba batalkan/mulai ulang dengan pemilik. | Belum diuji | | |
| D22 | Mulai verifikasi lalu hentikan aktivitas browser lebih dari 10 menit; jalankan scheduler/command `php artisan delivery:expire-verifications` pada DB uji. Muat ulang. | Belum diuji | | |
| D23 | Admin Utama buka Pengelolaan Lock, tinjau sesi LOCK, terminate dengan alasan jika diperlukan. | Belum diuji | | |
| D24 | Admin Utama buat lalu hapus delivery percobaan yang belum memiliki picking/mutasi. Coba hapus order dengan sesi aktif/completed. | Belum diuji | | |
| N01 | Sebelum approve NF-SUPPLEMENT/ADDITIONAL, coba mulai picking/split/assignment terkait. | Belum diuji | | |
| N02 | Supervisi/Admin approve supplement `98010071`, 20 PCS di D21. | Belum diuji | | |
| N03 | Approve additional `98010073`, 20 PCS di D22. | Belum diuji | | |
| N04 | Buat order uji approved baru `UAT26 DOM NF-REJECT`, part `UAT26-D-NF`, jumlah 100 PCS. Operator ajukan request ID `98010075`, 20 PCS ke D27. Coba PCS 0, PCS sama/lebih dari standar, ID existing, order completed, supplement melebihi sisa, lokasi tidak valid. Buat satu valid lalu reject dengan alasan. | Belum diuji | | |
| X01 | Supervisi buka Expired Box. Cari `98000601`–`98000604`, `98000611`, `98000612`. | Belum diuji | | |
| X02 | Buat transaksi expired dengan `98000601` dan `98000602`. Tinjau dan konfirmasi penarikan; coba QA sebelum penarikan. | Belum diuji | | |
| X03 | QA `98000601` passed, `98000602` rejected. Kosongkan alasan reject lalu isi alasan dan konfirmasi fisik. | Belum diuji | | |
| X04 | Cetak transaksi/label serah packing `98000601`, kirim packing, terima ID baru `98010601`, part D-EXPIRED, 10 PCS, tanggal label hari ini, lokasi kosong D25. | Belum diuji | | |
| X05 | Sebelum penerimaan X04, coba ID lama, ID existing, part/PCS berbeda, label sebelum serah packing atau masa depan. Pada transaksi multi-box coba satu baris salah. | Belum diuji | | |
| X06 | Buka sumber packing lama `98000606`; terima sebagai `98010606`, part D-EXPIRED, 10 PCS, label D−35, lokasi D26. | Belum diuji | | |
| X07 | Gunakan `98000603` dan `98000604` untuk transaksi bulk kedua; cek pilih semua, cetak, QA/packing/terima semua baris yang diwajibkan; coba pilih satu anggota transaksi di tahap lanjut. | Belum diuji | | |
| E01 | Operator Export buat delivery manual baru kode UAT26-EX-MANUAL; customer, tenggat, part `UAT26-E-RECEIVE`, jumlah 160 PCS. | Belum diuji | | |
| E02 | Impor Excel layout tetap sesuai contoh di atas, lihat pratinjau sebelum simpan. | Belum diuji | | |
| E03 | Ubah hasil review sebelum simpan; coba part tidak dikenal, PCS negatif/desimal tidak valid, file bukan Excel, file rusak/kosong. | Belum diuji | | |
| E04 | Edit rencana NF-DRAFT yang sudah ada dan simpan tetap 100 PCS. Pada rencana percobaan baru, uji penyimpanan pertama 100 PCS part yang sama. Periksa alert, kebutuhan 20 PCS, dan antrean NF pada admin. | Belum diuji | | |
| E05 | Kode wajib unik setelah trim dan normalisasi kapital | Belum diuji | | |
| E06 | Kode baru untuk isi sama; kode lama tetap dikunci setelah rename | Belum diuji | | |
| E07 | Kode histori selesai/arsip/batal tidak dapat digunakan ulang | Belum diuji | | |
| E08 | Klik simpan cepat dua kali/retry request yang sama pada rencana percobaan. | Belum diuji | | |
| P01 | Buka RECEIVE; buat 2 pallet: pallet 1 E17 berisi 2 box part E-RECEIVE (80 PCS/box), pallet 2 E18 berisi 1 box. Simpan draft belum lengkap dulu. | Belum diuji | | |
| P02 | Coba final kurang/lebih; lokasi sama dua pallet; lokasi domestic/occupied/reserved. Kemudian final alokasi2+1 benar. | Belum diuji | | |
| P03 | Final MIX pada satu pallet E19 dengan 2 box E-MIX-A (80 PCS/box) + 1 box E-MIX-B (40 PCS/box). | Belum diuji | | |
| P04 | Saat popup FIFO muncul, tinjau asal/tujuan dan tombol mandatory masukkan antrean. Refresh sebelum/selepas klik. | Belum diuji | | |
| P05 | Final MONO 36 box pada E20. Isi sumber di E03 seluruhnya satu part, walau 71/72 box. | Belum diuji | | |
| P06 | WARN 72 box: coba 1 pallet berisi 36, 72, lalu 73 box; final boleh 2 pallet masing-masing 36 box di E21/E22. | Belum diuji | | |
| P07 | Pada NF-EXT/SPLIT buka rincian final. | Belum diuji | | |
| P08 | Cetak delivery dan kartu pallet pada final RECEIVE/MIX/NF. Coba cetak draft NF-DRAFT. | Belum diuji | | |
| P09 | Buka planning yang sama dua tab; revisi di tab 1 lalu simpan tab 2 lama. | Belum diuji | | |
| R01 | Setelah RECEIVE final, buka Penerimaan transfer box ekspor. Scan `98010001`, `98010002`, `98010003`, semuanya E-RECEIVE. Klik Proses penerimaan satu kali. | Belum diuji | | |
| R02 | Coba 123, huruf, 9 digit, ID duplikat satu draft/yang tersimpan, part tidak dikenal. | Belum diuji | | |
| R03 | Buka transaksi R01; tinjau tujuan dan cetak guide per transaksi. Konfirmasi satu box dahulu, refresh, kemudian sisanya. | Belum diuji | | |
| R04 | Terima `98010011`,`98010012` part E-DEADLINE dalam satu proses. | Belum diuji | | |
| R05 | Terima `98010021` part E-SPARE tanpa kebutuhan. Pilih pallet E05 atau lokasi kosong E23. | Belum diuji | | |
| R06 | Terima `98010202` part E-LOAD36 ke pallet E04 berisi 35 box. Terima `98010201` part E-MONO ke pallet E03 berisi 71 box sebelum sumber dipindah. | Belum diuji | | |
| R07 | Stok Ekspor: ganti tampilan box/pallet; cari nomor pallet, lokasi E03, part, status; ketik cepat, reset dan pagination. | Belum diuji | | |
| R08 | Buka Lihat box dari daftar; tinjau lokasi fisik; unduh Excel dengan filter. | Belum diuji | | |
| R09 | Coba konfirmasi penempatan dari tab lama, tujuan reserved orang lain, tombol doubleclick/retry. | Belum diuji | | |
| V01 | Buka Picking Verification ekspor, tinjau MIX vs MONO; gunakan fitur pencarian/lookup dengan box salah. | Belum diuji | | |
| V02 | Dalam scan mandatory MIX, scan salah `98002104` dari E02. | Belum diuji | | |
| V03 | Admin Warehouse buka Approval Scan Salah bersama domestik, filter Ekspor, approve issue `98002104`. Operator coba lanjut sebelum konfirmasi pengembalian; kemudian konfirmasi fisik di tujuan. | Belum diuji | | |
| V04 | Scan ID tidak dikenal 8 digit `98019998` sebagai box fisik salah ambil. Admin isi part E-SPARE dan tujuan cadangan kosong E24. Coba approve tanpa part/tujuan. | Belum diuji | | |
| V05 | Scan `98019999` sebagai typo tanpa box fisik; Admin Warehouse tutup sebagai Salah input dengan alasan wajib. Ulangi menggunakan ID sudah dikirim `98002901`. | Belum diuji | | |
| V06 | Coba 123 pada mandatory scan, bukan lookup. | Belum diuji | | |
| V07 | Untuk missing location, terima box baru E-MIX-A `98010031` sebagai stok cadangan tetapi jangan konfirmasi penempatan; scan itu di mandatory MIX, lalu admin lengkapi tujuan. | Belum diuji | | |
| V08 | Setelah seluruh issue selesai, scan `98002101`, `98002102`, `98002103` benar; coba salah satu ulang; lalu tempatkan sesuai rekomendasi. | Belum diuji | | |
| V09 | Perubahan posisi/isi sumber setelah verifikasi lewat aksi yang diizinkan; coba konfirmasi dari halaman lama. | Belum diuji | | |
| NF01 | Warehouse buka NF-DRAFT dan coba siapkan ID `98011009`. | Belum diuji | | |
| NF02 | Warehouse siapkan NF-EXT mode Datang sudah Not Full, ID baru `98011001`. Supervisi approve setelah konfirmasi fisik. | Belum diuji | | |
| NF03 | Terima full `98010041` part E-NF-EXT, 80 PCS, lalu tempatkan E31. | Belum diuji | | |
| NF04 | NF-SPLIT: tinjau full `98002261` reserved, siapkan split ID baru `98011002`; approve sebagai Supervisi. | Belum diuji | | |
| NF05 | NF-SHORT: pilih mode split. | Belum diuji | | |
| NF06 | Final NF-EXIST pada E34 dan acknowledge rekomendasi. | Belum diuji | | |
| NF07 | Pada request baru yang prepared, reject sebagai Supervisi dengan alasan; periksa kebutuhan. Siapkan lagi dengan ID baru lalu approve. | Belum diuji | | |
| NF08 | Siapkan split pada salinan NF-SPLIT sebelum approve, lalu buat dan finalkan rencana full 80 PCS baru part sama supaya kandidat sumber `98002262` terreservasi. Coba approve persiapan lama. | Belum diuji | | |
| NF09 | Request prepared punya ID baru; coba input ID itu di penerimaan normal. Approve dua kali dari tab lama. | Belum diuji | | |
| NF10 | Revisi/hapus kebutuhan NF sebelum approve, atau ubah planning/lokasi. Coba approve tab lama. | Belum diuji | | |
| NF11 | Cek laporan setelah split dan external. | Belum diuji | | |
| F01 | Buka RECEIVE setelah baru satu pallet terpenuhi; coba konfirmasi pallet belum lengkap dan selesaikan seluruh permintaan. | Belum diuji | | |
| F02 | Pallet 1 penuh 2 box: klik Konfirmasi, lihat rincian, kosongkan lokasi lalu pilih lokasi semula E17. Pallet 2 penuh 1 box: konfirmasi lokasi kosong E25. | Belum diuji | | |
| F03 | Coba edit/pindah/menambah ke pallet yang ready; gunakan opsi Revisi untuk membuka kesiapan bila perlu. | Belum diuji | | |
| F04 | Pada REV, acknowledge lalu tempatkan 98002301 dan 98002302 saja; 98002303 masih reserved, kebutuhan 4. Kurangi menjadi 1 box, tujuan return E36 kosong; jangan konfirmasi return dulu. | Belum diuji | | |
| F05 | Konfirmasi return 98002302 di E36 dan simpan revisi F04. | Belum diuji | | |
| F06 | Pada cabang/salinan lain kurangi semua isi satu pallet atau seluruh permintaan, termasuk terisi sebagian. | Belum diuji | | |
| F07 | Pada CANCEL, acknowledge, tempatkan 98002311 saja, 98002312 masih reserved. Batalkan dengan alasan, tujuan return E38, konfirmasi fisik. | Belum diuji | | |
| F08 | RECEIVE semua pallet ready: klik Selesaikan permintaan. Baca alert bahwa seluruh box telah keluar gudang; batalkan popup dulu, lalu konfirmasi. | Belum diuji | | |
| F09 | Refresh/retry selesai, buka URL lama, coba scan ID yang sudah shipped. | Belum diuji | | |
| F10 | Coba selesai/reduce/cancel saat issue picking terkait masih open. | Belum diuji | | |
| L01 | Warehouse/Supervisi/Admin buka laporan existing; pilih Domestik/Ekspor, rentang tanggal, part, lokasi, user, search dan pagination yang tersedia. | Belum diuji | | |
| L02 | Unduh Excel/CSV laporan dan stok setelah filter. Bandingkan detail dan total, termasuk histori D−1. | Belum diuji | | |
| L03 | Audit: filter transaksi UAT26, user, aksi, tanggal, scope; rincian before/after dan export. | Belum diuji | | |
| L04 | Admin Utama/Supervisi: dashboard default Semua; ubah Domestik/Ekspor dan tanggal via AJAX, Back/Forward, reset. | Belum diuji | | |
| L05 | Bandingkan NF-SPLIT/REV yang baru reserved dengan placed; RECEIVE ready dengan complete. | Belum diuji | | |
| L06 | Lihat stok/pallet aktif sambil mengganti periode, lalu issue/histori durasi. | Belum diuji | | |
| L07 | Unduh dashboard Excel; buka sheet/grafik/rincian audit, bandingkan angka web. | Belum diuji | | |
| L08 | Coba rentang tanggal terbalik, hasil kosong, mengetik search cepat, koneksi lambat/error lalu retry. | Belum diuji | | |
| UI01 | Chrome desktop, layar sempit, keyboard Tab/Enter, scanner USB dengan Enter; tutup modal Esc, cetak A4. | Belum diuji | | |
| UI02 | DevTools Network: filter/search/pagination, navigasi keluar/masuk, doubleclick simpan. | Belum diuji | | |
| C01 | Dua sesi operator ekspor menyimpan ID box sama dengan token berbeda. | Belum diuji | | |
| C02 | Dua finalisasi memakai lokasi kosong sama. | Belum diuji | | |
| C03 | Dua rencana membutuhkan spare box yang sama. | Belum diuji | | |
| C04 | Place/reduce/approve NF memakai revision lama dari tab kedua. | Belum diuji | | |
| C05 | Finish dikirim dua kali atau saat proses lain revisi. | Belum diuji | | |
| C06 | Role dicabut aksesnya lalu submit halaman lama. | Belum diuji | | |
