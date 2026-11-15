# Perbaikan keamanan terarah, 15 November 2026

Status: perubahan source di checkout pengguna, belum dideploy. Perbaikan ini tidak
menambah migration, tidak mengakses database operasional, dan tidak menulis ulang
stok, user, transaksi, reservasi, atau history lama. Perubahan lain yang sudah ada
di checkout sebelum pekerjaan ini tetap dipertahankan.

## Kandidat dari audit sebelumnya

Kandidat audit adalah dugaan berbasis source, bukan bukti bahwa serangan telah
terjadi. Berikut penanganan pada titik penggunaan data dan keputusan server:

| Jalur | Perubahan source |
| --- | --- |
| Withdrawal langsung | Parent delivery dikunci sebelum item/stock. Status wajib approved, processing, atau partial. Item tetap terikat pada order/part, kuantitas exact dan hold Not Full tetap diperiksa. Withdrawal tanpa referensi delivery yang memang sudah didukung tetap tersedia. |
| Split delivery | Server menolak split bila terdapat Not Full pending, sama dengan pembatasan UI. Penolakan terjadi sebelum quantity atau child dibuat. |
| Redo | Key pallet pada payload relokasi legacy tidak lagi menentukan objek yang diubah. Kode lokasi tetap diterima untuk kompatibilitas, target relokasi adalah pallet konsolidasi yang dibuat server. Box shared legacy tetap berada pada pallet konsolidasi, ringkasan pallet asal disesuaikan dengan box aktif yang benar-benar tersisa, sesuai alur satu pallet baru pada UI/memo. Tidak mengubah hasil Redo yang sudah tercatat. |
| Bootstrap stok | JSON dalam elemen script memakai JSON_HEX_TAG/APOS/AMP/QUOT. Nilai asli tetap tersedia setelah JSON.parse, tetapi tidak dapat menutup elemen script dari teks pencarian/master. |
| Lokasi/part di browser | Picker, status pallet, daftar existing box dan FIFO meng-escape nilai dinamis. Identitas dan teks di database tidak diubah. |
| Nama petugas dalam notifikasi | Toast memakai textContent. Fallback error SweetAlert memakai text/titleText. Pesan error tidak ditafsirkan sebagai HTML. Dialog konfirmasi terformat yang sudah ada tidak dirombak. |
| Excel lama | StockInput, AuditTrail dan tiga StockView exporter memakai TextSafeExport. Sel string selalu teks, termasuk formula-shaped text dan ID dengan leading zero. Quantity yang dikirim sebagai angka tetap numerik. Binder global dipulihkan di finally, termasuk bila download gagal. |
| Email expired | Penerima default Supervisi hanya akun aktif. Alamat override yang dipilih lewat konfigurasi tetap mengikuti konfigurasi existing. |
| Seeder demo | UserSeeder dan ExportOperatorSeeder tidak membuat akun contoh saat APP_ENV=production. Akun dan password existing tidak diubah otomatis. Status akun contoh yang mungkin sudah ada perlu diperiksa Admin Utama melalui pengelolaan user biasa. |
| Rentang laporan lama | Format/urutan tanggal divalidasi. Grafik rentang panjang memakai tanggal yang memiliki aktivitas, sehingga tidak membuat baris kosong untuk setiap hari selama puluhan tahun. Seluruh data aktivitas dan agregat tetap dibaca dalam rentang yang dipilih; bukan pembatasan akses history. |

## Tiga jalur yang sebelumnya tertunda

1. **Teks master ke audit Excel**: PartSettingController menyimpan part_number dan
   MasterLocationController menyimpan location pada snapshot new_values audit.
   AuditTrailExport membaca snapshot ini melalui extractDetail, termasuk lokasi
   ekspor. TextSafeExport melindungi semua sel string, sehingga tidak bergantung
   pada apakah teks berasal dari nama operator, master, atau stock input. Audit
   penghapusan yang hanya mempunyai old_values tidak dibuat menjadi catatan baru.
2. **Simpan delivery Ekspor**: controller memvalidasi bentuk input/izin route.
   Service mengikat hash retry ke actor, target, revision, dan payload. Retry sukses
   mengembalikan record yang sama, termasuk arsip, tanpa mengaktifkan atau menulis
   ulang data. Mutasi baru memakai parent aktif berstatus draft dan revision yang
   cocok, registry kode permanen, mapping part/snapshot kapasitas, mutex inventory,
   dan invalidasi planning dengan pemeriksaan committed box. Tidak ditemukan
   kebutuhan perubahan dari penelusuran source ini.
3. **Simpan/finalisasi pallet dan acknowledge FIFO**: source mengikat ID pallet
   pada planning, lokasi pada master ekspor, serta part/kapasitas pada catalog
   kebutuhan. Finalisasi memeriksa alokasi exact. Reservasi dibangun ulang di
   transaksi inventory setelah pemeriksaan revision dan committed box. Notice
   acknowledgment harus milik planning/delivery dan revision final yang sama.
   Tidak ditemukan bypass tambahan dari jalur yang ditelusuri.

Ini bukan klaim bahwa seluruh kondisi runtime atau deployment telah diuji.
Penyesuaian angka instruksi announcement untuk box number yang dipakai ulang
tetap merupakan tindak lanjut konsistensi laporan, bukan bypass inventory/approval
yang terbukti; perhitungan historisnya tidak diubah dalam perbaikan keamanan ini.

## Verifikasi dan penerapan

- Pemeriksaan sintaks menggunakan `php -n -l` dan `node --check`, tanpa menjalankan
  aplikasi, mengakses .env, atau membuka koneksi database.
  Hasil akhir: 22 file PHP dan 4 file JavaScript lulus pemeriksaan sintaks,
  `git diff --check` lulus. Template Blade diperiksa melalui perubahan source,
  belum melalui render aplikasi/browser.
- Tes regresi ditambahkan untuk penolakan withdrawal tanpa approval/arsip,
  penolakan split saat Not Full pending, relokasi Redo dengan key pallet lain,
  penerima email nonaktif, seeder production, tipe sel Excel/binder isolation,
  grafik rentang panjang, dan kontrak teks notifikasi.
- Tes aplikasi/frontend **belum dijalankan**. Panduan security-audit yang dipakai
  mensyaratkan sandbox OS dengan isolasi network/environment/filesystem/resource
  untuk target-controlled tests; semua kontrol itu belum tersedia pada sesi ini.
  Syntax PASS tidak sama dengan hasil PHPUnit, browser, atau transaksi PASS.
- Uji regresi harus memakai database sementara, dummy data dan mail palsu. Jangan
  memakai konfigurasi database production untuk menjalankan tes.
- Tidak ada kebutuhan migration dari perubahan ini. Checkout juga memuat migration
  dan perubahan dari pekerjaan sebelumnya; penerapan patch keamanan tidak berarti
  persetujuan untuk menjalankan seluruh migration atau men-deploy semuanya sekaligus.
- Seeder guard mencegah pembuatan akun berikutnya, bukan mengganti credential lama.
  Tidak ada mass reset, penghapusan akun, pemindahan fisik barang, atau perubahan
  hak akses existing sebagai bagian patch ini.

Audit run-2 sebelumnya tetap berstatus incomplete karena dihentikan pengguna.
Dokumen ini mencatat remediation source berikutnya; tidak mengganti bukti audit
lama menjadi temuan terkonfirmasi atau hasil uji yang tidak pernah dijalankan.
