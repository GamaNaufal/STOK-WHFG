# Penerimaan Transfer Box Ekspor

Verifikasi 30 Oktober 2026 pada branch aktif Export-plan.

## Keputusan dan alur operator

- Scan nomor box delapan digit, lalu nomor part. PCS diambil ulang di server
  dari master part ekspor; tidak ada input PCS manual atau box tidak penuh.
  Kolom ID box hanya menerima angka 0–9, maksimal delapan digit, dengan angka
  nol di awal tetap dipertahankan. Validasi server tetap mewajibkan tepat delapan.
- Identitas dipisahkan menurut lingkup. Delapan digit yang sama boleh ada pada
  domestik dan ekspor. Nomor yang sudah diterima di ekspor tidak dapat diterima
  lagi. Registry `box_identities` domestik tidak dipakai/diubah oleh modul ini.
- Enter pada nomor box memeriksa duplikat dan memfokuskan nomor part. Enter
  setelah nomor part menambahkan baris ke daftar scan; fokus kembali ke nomor
  box untuk pasangan berikutnya. Tersedia tombol untuk input manual.
- Scan memberi indikator OK/NG dan memakai audio OK.mp3/NG.mp3 yang sama dengan
  Stock Input domestik. Part tidak dikenal, format salah, dan duplikat mendapat
  NG tanpa menambah baris. Chrome HTTP intranet mempunyai fallback UUID aman
  melalui crypto.getRandomValues jika randomUUID belum tersedia.
- Daftar maksimal 1.000 box tersimpan sebagai draft milik operator di database,
  tetap ada setelah refresh. Baris dapat dihapus atau seluruh daftar dibatalkan
  sebelum diproses. Scan belum membuat stok, reservasi atau tujuan penempatan.
- Tombol Proses penerimaan menerima seluruh daftar secara atomik, memvalidasi
  ulang master/duplikat, lalu menghitung reservasi FIFO sekali untuk seluruh
  box. Kapasitas master yang berubah mengharuskan baris dihapus dan di-scan ulang.
  Waktu scan dari server dipertahankan sebagai tanggal penerimaan pertama.
- Hasil dikelompokkan per pallet rencana/lokasi dan stok cadangan, dengan nomor
  box, part dan PCS pada halaman transaksi tersendiri. Tugas stok lama tetap
  tersedia melalui transaksi asalnya yang diprioritaskan FIFO pada antrean.
  Operator mengonfirmasi per kelompok setelah semua box benar-benar ditempatkan.
  Konfirmasi kelompok atomik; satu baris stale membatalkan seluruh kelompok.
  Baru pada tahap ini box berstatus stored dan pemenuhan bertambah.
- Jika tidak ada kebutuhan yang tersedia/cocok kapasitas, operator memilih
  lokasi ekspor kosong untuk pallet cadangan baru atau pallet ekspor existing.
  Part campuran diperbolehkan. Stok cadangan dapat dipindahkan lewat detail box;
  tanggal penerimaan pertama tidak berubah.
- Antrean di bawah area scan menampilkan satu baris per transaksi: 10 box dalam
  satu Proses penerimaan menghasilkan satu transaksi. Baris memuat kode, waktu,
  operator, total box, sudah ditempatkan dan menunggu. Atur penempatan membuka
  halaman baru berisi anggota transaksi yang dikelompokkan per tujuan pallet.
  Pagination 20 transaksi kembali ke anchor receiving-pending-queue.
- FIFO memakai tanggal penerimaan pertama box yang masih menunggu lalu ID.
  Penempatan boleh bertahap per kelompok. Transaksi keluar antrean bila semua
  selesai, dan dapat kembali bila stok cadangannya perlu dipindahkan karena
  reservasi baru. Header tetap processed; progres dihitung dari box.
- Penerimaan lama tanpa batch tetap tersedia sebagai transaksi satu box, tanpa
  menggabungkan histori berdasarkan tanggal atau mengubah data existing.
- Halaman Stok Ekspor (/export/stock) terpisah dari penerimaan. Memuat stok,
  pencarian/filter, jumlah menunggu dan detail/relokasi setiap box. Tabel antrean
  sudah dipindah ke penerimaan pada 29 Oktober. Tombol Atur penempatan pada
  antrean membuka halaman transaksi. Lihat box pada tabel stok memuat pop-up
  detail saat klik tanpa meninggalkan daftar atau mengubah filter. Deep link
  ?box=ID dan redirect penempatan membuka modal otomatis. Draft scan belum
  muncul sebagai stok.
- Label lokasi fisik mengikuti pallet yang benar-benar ditempati: Pallet 1 · A1
  sesuai nomor perencanaan, atau Pallet cadangan · A1 bila belum terkait rencana.
  Target reservasi ditampilkan sebagai instruksi tersendiri sampai dipindahkan.

## Peringatan jumlah box per pallet

Sejak 30 Oktober 2026, nama menu/halaman menjadi Penerimaan Transfer Box Ekspor.
Route dan izin export_stock_input tetap sama. Jumlah 36–71 box mendapat label
kuning **Peringatan**; mulai 72 box mendapat label merah **Perlu perhatian**.
Label tidak memblokir penerimaan, simpan/finalisasi perencanaan atau penempatan.

Saat memilih pallet existing, panel menampilkan jumlah fisik saat halaman
dimuat ditambah box pending yang akan ditempatkan melalui kelompok tersebut.
Contoh: 34 box + 2 masuk = 36 (kuning), 70 + 2 = 72 (merah).
Detail satu box yang dimuat pada pop-up juga menghitung satu box masuk,
termasuk relokasi stok cadangan. Pilihan lokasi kosong mulai dari nol.

Tujuan pallet rencana memakai seluruh stok fisik pada pallet tersebut,
termasuk penerimaan dari transaksi lain, lalu menambah box pending kelompok
yang belum berada di sana. Box yang sudah ditempatkan tidak dihitung ulang.
Perhitungan merupakan snapshot untuk kelompok yang dipilih; muat ulang untuk
mengambil angka terbaru. Pemenuhan, reservasi dan FIFO tetap mengikuti aturan
existing. Panduan cetak juga memuat teks peringatan saat proyeksinya mencapai
threshold, sehingga tetap dapat dibaca pada cetakan hitam-putih.

Verifikasi: 38 tes backend (434 assertions), 67 tes frontend dan build lulus.
Browser Chromium desktop memeriksa label berubah saat jumlah/pilihan diganti,
hasil simpan, proyeksi bulk, detail modal dan cetak; tanpa error konsol.
Seluruh contoh memakai SQLite preview, bukan inventory operasional.

## Sorotan tujuan dan cetak panduan per transaksi

Di halaman Atur penempatan, setiap kelompok mempunyai blok **Antarkan box ke**
dengan nomor pallet dan lokasi berukuran besar. Untuk cadangan, memilih lokasi
atau pallet memperbarui sorotan dan tujuan pada tautan **Cetak panduan**.
Pilihan ini belum mengubah stok; konfirmasi tetap dilakukan setelah peletakan.

Tombol **Cetak** di antrean atau **Cetak panduan** di halaman transaksi membuka
panduan tersendiri. Pilih **Cetak / simpan PDF** untuk mencetak melalui browser
Chrome atau menyimpan PDF. Layout A4 memuat kode/waktu transaksi, operator,
jumlah box, kelompok pallet/lokasi, nomor box, part, PCS dan posisi saat dicetak.
Kotak centang dipakai di kertas setelah peletakan; box selesai ditandai **Sudah**.
Tujuan serta judul kolom diulang saat tabel berlanjut ke halaman berikutnya,
dan garis/teks tetap jelas tanpa pencetakan warna latar.

Untuk stok cadangan, tentukan tujuan di halaman transaksi sebelum mencetak.
Jika belum dipilih atau pilihan sudah tidak tersedia, panduan memperingatkan
dan menampilkan **BELUM DITENTUKAN**, tanpa mengarang lokasi. Box cadangan
yang sudah ditempatkan tetap terpisah menurut posisi fisiknya sendiri.
Cetakan adalah snapshot instruksi; jika tujuan berubah, cetak ulang panduan
terbaru. Mencetak tidak melakukan konfirmasi, reservasi atau mutasi inventory.

## Penyimpanan dan isolasi

`export_stock_boxes` adalah sumber pencatatan penerimaan/stok ekspor, terpisah
dari `boxes`. Nomor unik, part/PCS snapshot, receive_token, aktor, tanggal pertama,
revision dan current_pallet_id disimpan di sini. Tidak ada penghapusan box atau
penimpaan tanggal FIFO lewat formulir penerimaan.

`export_stock_pallets` adalah pallet fisik ekspor dengan nomor internal EX-000001
berbasis ID. UI memakai display_name dari nomor 1..N pada rencana terkait,
atau Pallet cadangan; identifier/FK existing dipertahankan. Satu lokasi
hanya mempunyai satu pallet fisik aktif. Lokasi awal dan snapshot kode disimpan
sebagai histori; pallet kosong dilepas dari lokasi dan ditandai retired.

`export_stock_movements` menyimpan konfirmasi/pemindahan fisik beserta sumber,
tujuan, operator, waktu, token idempotensi dan snapshot kode lokasi. Semua mutasi
penerimaan/reservasi/penempatan mencatat audit pada transaksi yang sama.

`export_receiving_batches` menyimpan header draft/processed/discarded, pemilik,
revision dan waktu proses. `export_receiving_batch_items` menyimpan pasangan
scan, snapshot kapasitas, token unik dan waktu scan dari server, serta tautan
ke stock box setelah diproses. Pembatalan menyimpan header/baris sebagai histori;
penghapusan satu baris hanya tersedia untuk draft. Draft tidak mereservasi stok.

`export_fulfillment_boxes.export_stock_box_id` menghubungkan komitmen dengan
stok fisik, unik per stock box. Data monitoring existing tetap disimpan dengan
relasi null; migration tidak mengarang penerimaan/stock dari histori tersebut.

Master lokasi ekspor memakai is_occupied untuk stok fisik, tetapi current_pallet_id
tetap null karena kolom tersebut milik pallet domestik. Master lokasi menampilkan
relasi pallet ekspor tersendiri. Part/lokasi yang memiliki histori ekspor tidak
dapat diganti identitasnya/dihapus; kapasitas master tetap boleh diperbarui untuk
penerimaan baru. Stok dan rencana existing mempertahankan snapshot masing-masing.

## Reservasi dan FIFO

- Urutan kebutuhan: deadline delivery, ID delivery untuk tie, lalu posisi pallet.
- Hanya perencanaan final yang sesuai revision delivery dan mempunyai klaim
  lokasi yang valid yang dapat menerima reservasi.
- Urutan stok: first_received_at, lalu ID penerimaan untuk tie. Box received
  maupun stored dapat direservasi, selama belum mempunyai komitmen lain dan
  part/PCS sesuai snapshot kebutuhan. Reservasi existing tidak dicabut untuk
  mengisi rencana lain; tidak ada pencurian alokasi yang telah ditetapkan.
- Finalisasi perencanaan langsung memeriksa stok tersedia; penerimaan box baru
  juga melakukan pemeriksaan. Jika stok lebih lama memenuhi kebutuhan, box lama
  mendapat tugas pemindahan dan box baru dapat menjadi stok cadangan.
- Stock fisik tetap berada di sumber sampai konfirmasi pemindahan. Lokasi asal
  dibebaskan hanya setelah box terakhir benar-benar berpindah. Klaim rencana
  pada lokasi tersebut tetap dipertahankan bila masih ada.
- Jika stok yang sebelumnya sudah dikonfirmasi secara fisik memang berada di
  pallet tujuan yang sama, fakta penempatan tersebut dapat dipakai tanpa membuat
  perpindahan fiktif. Tanggal dan operator penempatan asal dipertahankan.
- Kapasitas master yang berubah tidak memaksa box baru ke rencana berkapasitas
  lama. PCS harus persis cocok; kapasitas berbeda tetap menjadi stok cadangan.

## Transaksi dan batas proses

Mutasi ekspor memakai satu row lock `export_inventory_locks` sebelum lock parent,
part, stok atau lokasi. Ini sengaja menserialkan penerimaan, penempatan, perubahan
delivery dan finalisasi agar prioritas FIFO tidak berlomba; sesuai volume awal
yang kecil. Mutasi domestik tidak memakai lock ini. Perubahan future harus
mempertahankan urutan lock dan memanggil alokasi setelah availability berubah.

Receive token dan request token penempatan unik mencegah penggandaan akibat
retry/double submit. Replay harus sama data dan aktor; optimistic revision
menolak konfirmasi dari instruksi lama. Tujuan reservasi ditentukan server dan
tidak dapat ditimpa dengan pilihan stok cadangan. Audit failure membatalkan
stok, occupancy, pergerakan dan pemenuhan pada transaksi yang sama.

Scan token mencegah baris ganda ketika respons scan diulang. Revision draft
mencegah tab lama mengembalikan baris yang sudah dihapus; data terbaru dimuat
saat konflik. Draft hanya dapat dibaca/diubah/diproses pembuatnya. Jika dua draft
berisi nomor yang sama, yang diproses kemudian ditolak seluruhnya. Konfirmasi
cadangan satu kelompok dapat membuat satu pallet baru untuk semua anggotanya.

Revisi/pembatalan rencana dengan komitmen atau box fisik pada pallet rencana
tetap dilindungi. Pengurangan kebutuhan, pelepasan komitmen, pengembalian box
terbaru terlebih dahulu, withdrawal/pengiriman dan alih peruntukan lintas lingkup
belum disediakan. Itu tahap lanjutan; jangan menghapus komitmen atau box melalui
perubahan planning sebagai pengganti pengembalian. Pemindahan stok cadangan
tanpa komitmen sudah tersedia dan menjaga FIFO.

## Akses, deployment, dan verifikasi

Izin `export_stock_input` bawaan Operator Export, dapat dicabut oleh Admin.
Penerimaan dan halaman stok memakai izin existing yang sama, tanpa memperluas
hak mengedit master/perencanaan atau monitoring. Endpoint:

- GET `/export/receiving`: draft scan dan antrean per transaksi; ?batch=ID
  untuk batch processed dialihkan ke halaman transaksi.
- GET `/export/receiving/transactions/{batch}`: halaman penempatan anggota
  batch processed, tersedia untuk operator berizin. Draft tetap milik pembuat.
- GET `/export/receiving/transactions/box/{stockBox}`: halaman penerimaan lama
  yang belum mempunyai batch.
- GET `/export/receiving/transactions/{batch}/print` dan
  `/export/receiving/transactions/box/{stockBox}/print`: panduan cetak per transaksi,
  dengan izin existing, private/no-store dan penolakan draft. Parameter opsional
  spare_destination memakai pilihan ekspor yang masih tersedia saat dibuka.
- GET `/export/stock`: stok, filter dan detail melalui ?box=ID.
- GET `/export/stock/{stockBox}`: JSON HTML detail untuk pop-up, dengan izin
  existing dan respons private/no-store. Konfirmasi/relokasi tetap memakai POST
  penempatan existing; error validasi HTML kembali ke pop-up box yang sama.
- POST `/export/receiving/batches`: membuka/melanjutkan draft milik operator.
- GET `/export/receiving/batches/{batch}`: state draft untuk pemulihan konflik.
- POST `/export/receiving/batches/{batch}/scan`: menambahkan pasangan scan.
- DELETE `/export/receiving/batches/{batch}/items/{item}`: menghapus baris draft.
- DELETE `/export/receiving/batches/{batch}`: membatalkan seluruh draft.
- POST `/export/receiving/batches/{batch}/process`: menerima seluruh daftar,
  lalu membuka halaman penempatan transaksi.
- POST `/export/receiving/place-bulk`: konfirmasi kelompok; return_batch atau
  return_box memvalidasi anggota transaksi dan kembali ke halaman yang sama.
- POST `/export/receiving/check-box`: pemeriksaan nomor tanpa mutasi stok.
- GET `/export/receiving/part`: lookup tepat master ekspor.
- POST `/export/receiving`: API penerimaan satu box existing dipertahankan.
- POST `/export/receiving/{stockBox}/place`: konfirmasi/relokasi stok cadangan.

Migration `2026_10_29_000002_create_export_stock_receiving.php` membuat empat tabel,
menambah FK nullable ke komitmen, dan menambahkan izin ke pengaturan operator
existing beserta audit. Tidak menyalin stok domestik atau mengubah master.
Jalankan setelah migration pemenuhan, build aset, serta sertakan CSS halaman baru
dan public/build saat deployment. Tidak perlu library, layanan atau konfigurasi
scanner PC tambahan selain scanner yang mengirim teks dan Enter. Rollback
otomatis ditolak untuk menjaga stok dan histori. Jangan menjalankan cutover
QA/packing yang belum ditinjau hanya untuk mengaktifkan modul ini.

Pembaruan bulk memerlukan migration tambahan
`2026_10_29_000003_create_export_receiving_batches.php` setelah 000002. Hanya
menambah dua tabel draft; tidak mengubah izin atau stok existing. Build ulang
aset dan sertakan perubahan public/js/scan-feedback.js serta CSS. Tidak ada
dependency, layanan OCR atau konfigurasi scanner baru.

Regresi lengkap: 366 tes backend lulus (3.092 assertions), 57 tes frontend lulus,
dan Vite build lulus. Sesudah perbaikan respons awal draft, 13 tes batch lulus
(133 assertions), termasuk status/revision pada respons pembuatan pertama.
Cakupan batch: draft tanpa stok, pemulihan, duplikat/retry, konflik tab, kapasitas
berubah, rollback seluruh penerimaan/konfirmasi, satu pallet cadangan per grup,
FIFO stok lama, isolasi operator, akses halaman stok, dan batas 1.000 box.
Browser Chromium desktop memverifikasi lima pasangan scan, NG part salah dan
duplikat, refresh draft, tidak ada stok sebelum proses, pembagian dua pallet
dan cadangan, konfirmasi ketiga kelompok, serta lima box pada halaman stok
terpisah. Daftar baru berhasil langsung pada scan pertama. Tidak ada error
konsol. Scanner fisik belum diuji.

MySQL lokal stock pada 29 Oktober: agent menerapkan hanya migration 000003;
kedua tabel baru kosong. Jumlah existing dipertahankan: 12 boxes, 12 identities,
16 pallets dan 3 stock_locations domestik; 276 part, 27 lokasi, 2 delivery dan
2 planning ekspor, 2 stock box/komitmen ekspor, 0 pallet/pergerakan ekspor.
Tidak menambahkan data uji pada MySQL. Seluruh transaksi uji browser memakai
SQLite preview; production remote tidak diubah. Preflight/receipt, helper,
fixture dan log tes berada di storage yang diabaikan Git.

Pembaruan cetak 30 Oktober: empat tes backend panduan (35 assertions), 64 tes
frontend dan build lulus. Browser Chromium memeriksa sorotan tujuan, tautan
cetak dan pilihan cadangan tanpa error konsol; tidak mengonfirmasi box.
QA cetakan A4 memakai Dompdf existing pada fixture SQLite: 10 box dua pallet
satu halaman, 60 box dengan part panjang empat halaman, header berulang dan
seluruh halaman diperiksa. Printer fisik/hasil mesin cetak Chrome belum diuji.
Fitur pengguna tetap memakai cetak HTML browser, tanpa layanan PDF tambahan.
