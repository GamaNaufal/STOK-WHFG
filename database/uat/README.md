# Paket data uji UAT26

Untuk **tes manual khusus ekspor**, mulai dari `docs/EXPORT_MANUAL_TEST_GUIDE.md`:
14 rangkaian kasus dengan isian delivery, pembagian pallet, ID scan dan hasil
yang diharapkan. Data yang sudah diimpor tetap dipakai; tidak perlu impor ulang.

Paket diperbarui 3 November 2026 untuk master part bersama, ID box global,
dan kode delivery wajib unik sepanjang histori. Paket lama yang sudah diimpor
tidak perlu diimpor ulang; jalankan migrasi sesuai `docs/SHARED_INVENTORY_MIGRATION.md`.

Urutan penggunaan:

1. Baca `docs/UAT_BUSINESS_GUIDE.md`, terutama persiapan database dan akun.
2. Jalankan `01_seed_business_uat.sql` pada **development**, setelah migration.
3. Jalankan `02_verify_business_uat.sql` dan simpan baseline.
4. Ikuti checklist manual pada panduan; ulangi query integritas setelah UAT.
   Catat hasil pada `docs/UAT_RESULT_TEMPLATE.md` yang sudah menyediakan
   seluruh 116 baris kasus dengan status awal Belum diuji.

Data awal: **9 akun, 34 master part bersama, 100 lokasi, 37 box domestik, 127 box ekspor,
12 delivery domestik dan 17 rencana ekspor**, termasuk histori/arsip.
Password akun uji `UatWHFG!2026`; Admin Utama `uat26.admin@whfg.test`.
Seluruh akun dan barcode tercantum di `manifest.json`.
Panduan memuat **116 skenario manual untuk 31 fitur konfigurasi**, dilengkapi
role, langkah input/scan, hasil yang diharapkan dan kasus penolakan.

SQL menambah namespace UAT26 tanpa menimpa data existing. Impor ulang ditolak.
Tanggal bergeser relatif ke tanggal impor. Database default `stock`; hostname
dibatasi ke development lokal tempat paket dibuat. Periksa header sebelum impor
ke salinan development. Mengulang skenario dari awal dilakukan lewat restore
backup/salinan DB, bukan menghapus ledger box.

`validation.json` berisi hasil pengujian paket di MySQL sementara: rollback,
impor berulang, relasi dengan ID existing berbeda, query verifikasi integritas, dan
uji service picking/pengiriman, Not Full, redo, serta expired. Database aplikasi
tidak diisi oleh proses pembuatan/validasi paket ini. Checklist UI manual masih
perlu dijalankan oleh penguji.

Peta cakupan fitur konfigurasi:

| Fitur | Kasus di panduan |
| --- | --- |
| stock_input | D01–D03, D09 |
| stock_view / stock_edit / stock_delete | D04–D06, D09 |
| move_pallet / merge_pallet | D07–D08 |
| not_full_request / not_full_approval | N01–N04, NF01–NF11 |
| expired_box | X01–X07 |
| delivery_order / delivery_approval / delivery_schedule | D10–D13, D20 |
| delivery_assign | D17 |
| delivery_pick | D13–D15, D18–D19, D21–D22 |
| delivery_split / delivery_redo / delivery_delete / delivery_locks | D16, D20–D24 |
| delivery_issues | D18–D19, V02–V07 |
| parts / locations / export_locations | M01–M04 |
| export_delivery_plans | E01–E08 |
| export_pallet_plans | P01–P09 |
| export_stock_input / export_stock_view | R01–R09 |
| export_picking_verification | V01–V09 |
| export_fulfillment | F01–F10, NF01–NF11 |
| reports / audit | L01–L03, NF11 |
| users / access_management / profil / autentikasi | A01–A05 |
| dashboard Admin Utama dan Supervisi | L04–L08 |
| interaksi UI, cetak, konkurensi | UI01–UI02, C01–C06 |

Builder yang dapat direproduksi: `scripts/uat/build.php`.
Validator terisolasi: `scripts/uat/validate-mysql.php`.
Pembuatan lembar hasil dan ZIP tanpa akses database:
`python scripts/uat/package.py`.
