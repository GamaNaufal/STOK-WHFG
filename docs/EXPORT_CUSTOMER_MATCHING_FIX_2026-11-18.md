# Perbaikan pencocokan customer rencana delivery Ekspor

Terverifikasi 18 November 2026 pada checkout pengguna. Perangkat lain yang
menjalankan aplikasi melalui tunnel Cloudflare belum diperbarui oleh agent.

## Diagnosis

- Database lokal checkout, MySQL `db_stock`, sudah mempunyai schema customer,
  tetapi belum berisi master customer saat diagnosis baca-saja. Pengguna
  mengonfirmasi masalah terjadi pada aplikasi lokal di perangkat lain.
- Browser pada alamat tunnel yang diberikan pengguna berhasil memeriksa master
  customer aktif, nama resmi dan alias tersimpan. Pada form rencana baru,
  katalog JSON customer juga lengkap. Mengetik nama resmi maupun alias tidak
  mengisi hidden customer_id atau mengubah status pencocokan, sementara tabel
  part berhasil diinisialisasi. Tidak ada error JavaScript yang tercatat.
- Server tersebut memuat `export-delivery-plan-COzZa8g1.js`, berbeda dari bundle
  checkout sebelum patch, `export-delivery-plan-DFVQsZfS.js`. Ini konsisten dengan
  source/form customer dan hasil build yang tidak selaras. Isi source bundle
  remote tidak diambil, pemeriksaan dilakukan melalui form dan DOM yang dimuat.
- Backend juga terbukti menolak nama/alias yang benar ketika customer_id kosong.
  Tes baru mereproduksi pesan pengguna pada nama resmi, alias Excel dan edit
  snapshot yang master-nya sudah berubah. Impor Excel sendiri dapat menemukan
  master, tetapi penyimpanan bergantung pada ID yang diisi browser.
- Pemeriksaan pada perangkat lain tidak membuat customer, rencana, reservasi
  atau stok contoh. Penyimpanan browser hanya dilakukan pada SQLite uji terpisah.

## Perbaikan

- `ExportDeliveryPlanService` mencocokkan nama/kode/alias secara pasti di server
  jika form customer versi 1 mengirim ID kosong atau tidak menyertakannya.
  Customer tidak dikenal atau nonaktif tetap ditolak. Tidak membuat master baru
  otomatis dan tidak mengubah payload integrasi lama menjadi pemilik customer.
- Bila hidden ID masih menunjuk customer sebelumnya tetapi nama/alias sudah
  cocok dengan customer lain, server menolak ketidaksesuaian tersebut. ID yang
  stale tidak boleh menyimpan rencana atas customer yang berbeda dari isian.
- Pemeriksaan token retry tetap dilakukan sebelum membaca master saat ini.
  Retry yang sama mengembalikan hasil awal walaupun alias kemudian berubah.
  Hash payload lama tidak ditulis ulang. Identitas customer existing dipertahankan
  ketika nama snapshot sama, termasuk master yang sudah rename/nonaktif.
- Nama resmi disimpan sebagai nama rencana baru, alias yang diketik tetap
  disimpan sebagai source_name. Field source kosong kini memakai nama input,
  sehingga alias manual tidak hilang hanya karena hidden source masih kosong.
- Modul `customer-reference.mjs` menyelaraskan nama dan ID saat inisialisasi,
  input/change, pageshow, sebelum submit, impor dan undo impor. Snapshot existing
  tetap terkait ID asalnya. Membuka rencana historis yang belum dipetakan tidak
  memetakan customer otomatis, pilihan eksplisit tetap diperlukan.
- Nama yang belum ada di katalog awal diperiksa melalui lookup existing,
  `export.customers.options`. Tidak menambah izin. Debounce 180 ms hanya untuk
  lookup yang belum cocok, change/submit langsung. Request sebelumnya dibatalkan
  dan respons lambat tidak boleh menimpa nama yang lebih baru. Error koneksi
  mempertahankan teks dan ID tetap belum terkonfirmasi.
- Konflik kode/nama dari Excel tetap memblokir penyimpanan sampai operator
  mengoreksi customer. Submit/pageshow tidak boleh mengubah konflik impor menjadi
  cocok secara diam-diam. Undo mempertahankan status konflik dan nama sumber.
- Form menyertakan URL lookup bila fitur customer tersedia. Layout, route,
  mekanisme review Excel, hak akses, kapasitas part, inventory dan FIFO tetap.

## Verifikasi

- 78 tes backend / 663 assertions lulus pada SQLite in-memory. Meliputi sembilan
  tes baru, rencana delivery, identity/retry, penghapusan, reservasi customer,
  nama resmi/kode/alias tanpa hidden ID, XLSX nyata, nama tidak dikenal/nonaktif,
  integrasi legacy, snapshot rename, hidden ID yang stale dan lookup untuk
  operator tanpa izin kelola master customer.
- Seluruh 180 tes frontend lulus, termasuk delapan kasus reference customer,
  pemulihan nama, pergantian identitas, lookup master baru, respons terbalik,
  konflik Excel, undo, snapshot lama dan kegagalan koneksi.
- Vite build lulus, bundle baru `export-delivery-plan-BjxHXmMC.js`. Sintaks PHP,
  Pint file tes baru dan git diff --check lulus. Tidak mengulang suite backend
  penuh dalam patch ini atau mengadopsi hitungan lama sebagai hasil patch baru.
- Browser Laravel nyata memakai database `customer-match-preview.sqlite`,
  cookie/session terpisah dan akun fiktif. Tiga rencana tersimpan melalui form,
  manual dari alias, impor XLSX dari alias, serta lookup customer yang ditambahkan
  setelah form dibuka. Pemeriksaan database mengonfirmasi customer_id 1/1/3,
  source_name alias asli dan jumlah PCS 10/20/10. Tidak memfinalisasi rencana
  atau memproses stok. Nama resmi juga cocok pada viewport 390 px tanpa overflow,
  console error kosong. Back diuji tanpa menyimpan ulang rencana.
- Server uji dihentikan sesudah pemeriksaan. Tidak menjalankan migration pada
  database operasional, mengubah .env atau men-deploy perangkat lain.

## Paket untuk perangkat lain

Artefak lokal: `storage/app/private/customer-match-fix.zip`.

1. Ekstrak ZIP pada perangkat yang menjalankan aplikasi/tunnel.
2. Dari folder hasil ekstraksi, jalankan `php apply.php "F:\lokasi-project\STOK-WHFG"`,
   sesuaikan dengan path project yang mempunyai file artisan.
3. Setelah berhasil, refresh browser dengan Ctrl+F5. Saat mengetik nama/alias,
   status harus menjadi Terhubung ke nama customer yang benar.

Paket berisi empat source yang berubah dan dua aset build yang diperlukan
editor customer. Installer menggabungkan dua entri manifest customer/dependency,
mempertahankan entri dan aset modul lain, serta tidak menghapus aset lama.
Checksum, direktori target, baseline fitur customer dan symlink diperiksa.
Backup dibuat sebelum penulisan source, manifest diganti terakhir, kegagalan
penulisan memulihkan file yang sudah diganti. Tidak memerlukan npm atau migration
pada perangkat tujuan. Bila OPcache tidak memeriksa perubahan file, layanan PHP
yang digunakan perlu direstart agar membaca source baru.

Installer diuji pada direktori project probe, source dan manifest customer baru
terpasang, entri manifest lain serta sentinel .env/database tetap identik, dan
backup source awal tersedia. Ini bukan verifikasi pemasangan pada perangkat
lain. Alternatif developer, sinkronkan source patch lalu jalankan npm run build
di perangkat tersebut dan sertakan manifest beserta seluruh aset hasil build.

Fixture, log tes, bukti browser, hasil build dan paket berada di
`storage/app/private/customer-match-*` dan `customer-matching-*`, diabaikan Git.
