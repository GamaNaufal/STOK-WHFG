# Retensi lokasi pallet dengan box expired

Perubahan aturan: 16 November 2026, atas instruksi pengguna.

Lokasi adalah panduan penyimpanan fisik. Perubahan status menjadi expired atau
habisnya masa reaktivasi tidak memindahkan barang dan tidak boleh mengosongkan
`stock_locations` ataupun okupansi `master_locations`.

## Perilaku

- Seluruh box expired: lokasi tetap tersimpan dan terisi.
- Pallet campuran: pengambilan/penghapusan box aktif terakhir tidak menghapus
  lokasi selama box expired masih di sana.
- Expired tetap tidak eligible FIFO, scan dan completion delivery. `activeBoxes`
  serta ringkasan kuantitas aktif tidak diubah untuk memasukkan expired.
- Penyerahan sebagian box ke QA mempertahankan lokasi untuk anggota tersisa.
  Setelah box fisik terakhir keluar, lokasi dilepas sesuai proses existing.
- Klaim lokasi oleh pallet lain menolak pallet yang masih berisi expired, bahkan
  bila flag master location tertinggal sebagai kosong tetapi stock location ada.
- Merge memindahkan box eligible. Bila masih ada expired di sumber, pallet sumber
  dan lokasi/input history-nya dipertahankan. Memilih lokasi sumber yang masih
  ditempati expired sebagai tujuan akan membatalkan seluruh merge.

## Implementasi

`Pallet::storedBoxes()` membaca box yang masih terhubung ke pallet, belum withdrawn,
belum soft-deleted dan belum handled. Status expired dan deadline tidak menyaring
okupansi. Handled berarti penyerahan ke QA dan relasinya dilepas oleh proses QA.

`Pallet::hasStoredInventory()` mempertahankan fallback ringkasan untuk pallet legacy
yang tidak mempunyai histori box. Bila histori box tersedia, ringkasan lama tidak
boleh menahan lokasi untuk box yang sudah keluar/dihapus.

Pemeriksaan ini dipakai oleh MasterLocation, ExpiredBoxService,
LocationAssignmentService, pembersihan completion/withdrawal/Redo dan merge.
Penghapusan box Stock View menghitung `storedBoxes` yang masih tersisa.

## Batas perubahan

Tidak menambah migration, memulihkan lokasi lama secara otomatis, mengubah UI,
atau menerapkan perubahan ke produksi. Lokasi yang sudah hilang perlu dicocokkan
dengan posisi fisik serta konflik okupansi sebelum diperbaiki; histori lokasi
lama tidak otomatis membuktikan posisi saat ini.

## Verifikasi

- Sembilan tes baru `ExpiredPalletLocationRetentionTest`: 107 assertions lulus pada
  SQLite in-memory. Mencakup expiry berulang, deadline reaktivasi, QA sebagian/akhir,
  penolakan klaim, hapus box, withdrawal, completion/Redo, merge dan rollback tujuan.
- Regresi awal expiry/QA/packing, MasterLocation dan snapshot audit withdrawal:
  68 tes / 696 assertions lulus pada SQLite in-memory.
- Tes existing yang sebelumnya mengharapkan lokasi dilepas hanya karena expired
  (`ExpiredBoxReactivationTest`, `RemainingIntegrityRegressionTest`) diperbarui
  untuk memastikan lokasi tetap tersimpan dan delivery tetap ditolak.
- Pemeriksaan konflik Redo juga memakai okupansi fisik; Redo ke lokasi asal yang
  masih ditempati expired ditolak sebelum mutasi, dan relokasi ke lokasi kosong
  berhasil tanpa melepas lokasi expired yang tertinggal.
- Verifikasi akhir seluruh backend: 733 tes / 7.810 assertions, 726 lulus,
  tujuh gagal, tanpa error. Semua tes retensi lokasi, expiry/QA/packing,
  MasterLocation, withdrawal, delivery/Redo, merge/move dan snapshot audit lulus.
- Tujuh kegagalan di luar perubahan okupansi:
  - `OperationalReportRangeTest::test_short_range_still_includes_days_without_activity`:
    rentang tanggal mendapat satu hari tambahan.
  - Kedua tes `ReportTextSafetyTest`: tipe numerik Excel dan pemulihan binder.
  - `SystemIntegrityRegressionTest::test_demo_seeders_in_production_preserve_existing_users_and_create_none`:
    perbandingan array atribut user mentah (urutan/key/tipe is_active).
  - Tiga tes dashboard `UiReviewPreviewTest` yang telah tercatat pada baseline memo:
    render role/link detail, durasi nol, serta rincian issue/catatan.
  Empat kegagalan pertama belum dicatat sebagai baseline sebelumnya; source laporan,
  exporter/binder dan seeder tersebut tidak diubah dalam perbaikan ini. Hasil suite
  penuh tidak dinyatakan hijau dan kegagalan tersebut tidak diperbaiki dalam tugas ini.

Perintah pengujian akhir di PowerShell:

```powershell
$env:APP_ENV = 'testing'
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = ':memory:'
php vendor/phpunit/phpunit/phpunit --configuration phpunit.xml --do-not-cache-result
```

`git diff --check` lulus. Tidak melakukan pengujian browser visual karena tidak
ada perubahan UI, atau menjalankan migration pada database operasional.
