# Hak Akses Lokal dan Ekspor

Diverifikasi: 13 November 2026. Konfigurasi sumber: `config/feature_access.php` dan
`config/feature_actions.php` (141 izin, termasuk 100 tindakan rinci).

## Pemakaian oleh Admin Utama

1. Buka **Hak Akses**, pilih peran, lalu cari menu/tindakan (contoh: `revisi pallet`).
2. Izin menu/baca membuka halaman; tindakan seperti tambah, simpan, hapus, cetak,
   approval, dan konfirmasi dipilih terpisah. Prasyarat tindakan dicentang otomatis.
   Mencabut prasyarat ikut melepas pilihan tindakan turunannya.
3. Simpan dan konfirmasi perubahan. Berlaku pada request berikutnya untuk semua
   akun pada peran tersebut, termasuk akun yang sudah login. Transaksi tetap
   memeriksa status, pemilik, versi, posisi fisik, FIFO dan syarat bisnis lainnya.
4. Pencarian hanya menyaring tampilan, tidak mengubah pilihan. **Pilih semua** dan
   **Kosongkan** berlaku untuk seluruh izin yang bisa diatur pada peran terpilih.

Admin Utama selalu memiliki akses penuh. Kelola User, Hak Akses dan Lock
Management tetap khusus Admin Utama. Dashboard utama dan profil tersedia untuk
semua akun aktif; rincian dashboard memiliki izin tersendiri.

**Hapus delivery ekspor** dan **Batalkan seluruh permintaan ekspor** hanya dapat
diberikan kepada Admin Warehouse dan Supervisi. Batas role ini diperiksa kembali
oleh server; mencentang izin lain atau mengirim request langsung tidak melonggarkannya.
Revisi pallet dapat diberikan/dicabut untuk semua peran. Revisi menjadi nol pada
pallet terakhir juga memerlukan izin hapus dan pembatalan seluruh permintaan.

Menghapus rencana dengan reservasi lokasi memerlukan izin batalkan perencanaan;
pelepasan box memerlukan izin batalkan seluruh permintaan. Dua tahap konfirmasi,
daftar dampak, dan kewajiban pengembalian fisik tetap berlaku. Mencabut izin hapus
juga menonaktifkan pembatalan seluruh permintaan karena izin tersebut menjadi
prasyaratnya. Izin finalisasi dapat membuka form planning tanpa harus memberi
izin Simpan draft.

## Menjaga akses existing dan penerapan

- Data role lama (versi 1) dikembangkan menjadi tindakan yang sebelumnya efektif
  melalui menu tersebut. Hak yang sudah dicabut sebelumnya tidak diberikan kembali.
  Pembatasan role dan seluruh prasyarat tetap diperhitungkan.
- Migration `2026_11_12_000002_version_granular_feature_permissions.php` menambah
  kolom `permissions_version` pada `role_feature_access`, mengonversi grant lama,
  dan membuat audit konversi. Tidak menulis ulang stok, reservasi, transaksi,
  delivery, atau akun. Aman dijalankan ulang: pilihan versi 2 dilewati.
- Sesudah konversi/simpan, versi 2 menyimpan pilihan tindakan secara eksplisit.
  Membiarkan izin menu tetap aktif tidak mengaktifkan kembali tindakan yang dicabut.
  Tindakan baru pada rilis berikutnya harus ditinjau Admin Utama untuk role yang
  sudah memiliki pengaturan tersimpan; tidak otomatis diwariskan lagi.
- Sebelum migration dijalankan, pembacaan row lama tetap memakai konversi saat
  request. Menyimpan pengaturan rinci membutuhkan kolom versi tersebut.
- Jalankan migration ini pada proses deployment sesudah backup database dan
  pengujian staging. Bersihkan/bangun ulang cache konfigurasi/route/view sesuai
  prosedur deployment, dan sertakan aset build serta `public/js/action-permissions.mjs`.
  Migration belum dijalankan pada database operasional oleh perubahan kode ini.
- Jangan menurunkan versi aplikasi/izin secara otomatis setelah izin tindakan
  dicabut: kode lama masih mengartikan izin menu sebagai seluruh tindakan.
  `down()` migration sengaja menolak downgrade. Rollback memerlukan rekonsiliasi
  hak akses dan backup yang sesuai, bukan hanya menghapus kolom versi.
- Client/API admin lama yang mengirim daftar izin tanpa `permissions_version`
  diperlakukan sebagai grant menu lama dan dikonversi satu kali. Form baru selalu
  mengirim versi 2. Integrasi admin baru harus mengirim `permissions_version: 2`.

## Penegakan

`FeatureAccessService` menentukan izin menu, endpoint dan tindakan. Endpoint yang
berbagi URL tetap membedakan `action`/`status` (draft vs finalisasi, buka revisi vs
batal, setuju vs tolak, dan tahap QA/packing/penerimaan). Input box baru saat
assignment juga diperiksa terpisah. Endpoint pencarian/pratinjau yang hanya membaca
data tetap mengikuti modul pemakainya. Service planning dan pemenuhan memeriksa
lagi tindakan sensitif pada pemanggilan langsung. Riwayat yang tertanam pada API
detail pallet juga disaring bila izin riwayat stok dicabut.

Tombol/link/form biasa menggunakan kebijakan route dari server; kontrol scanner,
modal dan aksi JavaScript menggunakan penanda izin. Kontrol yang tidak diizinkan
tersembunyi, termasuk yang muncul melalui AJAX. Server tetap menjadi penentu akhir.
Perubahan izin dan audit disimpan dalam satu transaksi; audit gagal membatalkan
perubahan izin.

## Katalog izin

Default di bawah adalah default konfigurasi untuk peran yang belum memiliki
pengaturan tersimpan. Grant existing tetap dipertahankan sesuai konversi di atas.
Admin Utama selalu diizinkan dan tidak diulang pada kolom default. Pengecualian
debug: master part dan tindakan turunannya tersedia bagi Operator Export hanya
pada lingkungan local/testing, mengikuti perilaku sebelumnya.

### Warehouse

| Izin / kode | Kegunaan | Prasyarat | Default selain Admin Utama | Batas peran |
|---|---|---|---|---|
| Jalankan Stock Opname Lokal<br>`stock_opname_local_manage` | Izin dasar yang menjadi prasyarat tindakan terkait. | `stock_opname_local` | Warehouse Operator, Admin Warehouse | Dapat diatur per peran |
| Mulai sesi Stock Opname Lokal<br>`stock_opname_local_start` | Menjalankan tindakan Mulai sesi pada sesi opname Lokal. | `stock_opname_local_manage` | Warehouse Operator, Admin Warehouse | Dapat diatur per peran |
| Scan box Stock Opname Lokal<br>`stock_opname_local_scan` | Menjalankan tindakan Scan box pada sesi opname Lokal. | `stock_opname_local_manage` | Warehouse Operator, Admin Warehouse | Dapat diatur per peran |
| Perbarui acuan sesi Stock Opname Lokal<br>`stock_opname_local_refresh` | Menjalankan tindakan Perbarui acuan sesi pada sesi opname Lokal. | `stock_opname_local_manage` | Warehouse Operator, Admin Warehouse | Dapat diatur per peran |
| Selesaikan sesi Stock Opname Lokal<br>`stock_opname_local_finish` | Menjalankan tindakan Selesaikan sesi pada sesi opname Lokal. | `stock_opname_local_manage` | Warehouse Operator, Admin Warehouse | Dapat diatur per peran |
| Stock Opname Lokal<br>`stock_opname_local` | Menu / akses dasar modul. | — | Warehouse Operator, Admin Warehouse, Supervisi | Dapat diatur per peran |
| Unduh hasil Stock Opname Lokal<br>`stock_opname_local_download` | Mengunduh snapshot hasil sesi yang selesai. | `stock_opname_local` | Warehouse Operator, Admin Warehouse, Supervisi | Dapat diatur per peran |
| Input Stok<br>`stock_input` | Menu / akses dasar modul. | — | Warehouse Operator, Admin Warehouse | Dapat diatur per peran |
| Scan dan susun penerimaan lokal<br>`stock_input_scan` | Menambah hasil scan dan memilih pallet pada draft penerimaan. | `stock_input` | Warehouse Operator, Admin Warehouse | Dapat diatur per peran |
| Simpan penerimaan lokal<br>`stock_input_save` | Membuat stok fisik dari draft penerimaan. | `stock_input` | Warehouse Operator, Admin Warehouse | Dapat diatur per peran |
| Kosongkan draft penerimaan lokal<br>`stock_input_clear` | Membuang daftar scan yang belum disimpan. | `stock_input` | Warehouse Operator, Admin Warehouse | Dapat diatur per peran |
| Cetak keterangan pallet lokal<br>`stock_input_print` | Mencetak susunan pallet penerimaan pada halaman input. | `stock_input` | Warehouse Operator, Admin Warehouse | Dapat diatur per peran |
| Request Box Not Full<br>`not_full_request` | Menu / akses dasar modul. | — | Warehouse Operator, Admin Warehouse | Dapat diatur per peran |
| Ajukan Box Not Full lokal<br>`not_full_create` | Menyimpan pengajuan box di bawah kapasitas standar. | `not_full_request` | Warehouse Operator, Admin Warehouse | Dapat diatur per peran |
| Siapkan Box Not Full ekspor<br>`not_full_prepare_export` | Menentukan ID baru, asal box dan sumber pecah. | `not_full_request` | Admin Warehouse | Admin Warehouse |
| Approval Box Not Full<br>`not_full_approval` | Menu / akses dasar modul. | — | Supervisi | Dapat diatur per peran |
| Setujui Box Not Full lokal<br>`not_full_approve_local` | Menyetujui pengajuan lokal sesuai pemeriksaan sumber. | `not_full_approval` | Supervisi | Dapat diatur per peran |
| Tolak Box Not Full lokal<br>`not_full_reject_local` | Menolak pengajuan lokal dengan catatan. | `not_full_approval` | Supervisi | Dapat diatur per peran |
| Setujui Box Not Full ekspor<br>`not_full_approve_export` | Membuat hasil Not Full setelah penyiapan diverifikasi. | `not_full_approval` | Supervisi | Supervisi |
| Tolak Box Not Full ekspor<br>`not_full_reject_export` | Menolak penyiapan Not Full ekspor. | `not_full_approval` | Supervisi | Supervisi |
| Merge Palet<br>`merge_pallet` | Menu / akses dasar modul. | — | Warehouse Operator, Admin Warehouse | Dapat diatur per peran |
| Gabungkan pallet lokal<br>`merge_pallet_execute` | Menyimpan penggabungan pallet dan lokasi. | `merge_pallet` | Warehouse Operator, Admin Warehouse | Dapat diatur per peran |
| Pemindahan Pallet<br>`move_pallet` | Menu / akses dasar modul. | — | Warehouse Operator, Admin Warehouse | Dapat diatur per peran |
| Pindahkan pallet lokal<br>`move_pallet_execute` | Mengonfirmasi perpindahan pallet ke lokasi tujuan. | `move_pallet` | Warehouse Operator, Admin Warehouse | Dapat diatur per peran |
| Pemindahan Pallet Ekspor<br>`export_move_pallet` | Menu / akses dasar modul. | — | Operator Export, Admin Warehouse | Dapat diatur per peran |
| Pindahkan pallet ekspor<br>`export_move_execute` | Mengonfirmasi perpindahan fisik pallet ekspor. | `export_move_pallet` | Operator Export, Admin Warehouse | Dapat diatur per peran |
| Edit Box<br>`stock_edit` | Izin dasar yang menjadi prasyarat tindakan terkait. | `stock_view` | Admin Warehouse | Dapat diatur per peran |
| Hapus Box / Pallet<br>`stock_delete` | Izin dasar yang menjadi prasyarat tindakan terkait. | `stock_view` | Admin Warehouse, Supervisi | Dapat diatur per peran |
| Hapus box lokal<br>`stock_delete_box` | Menghapus box sesuai perlindungan stok aktif. | `stock_delete` | Admin Warehouse, Supervisi | Dapat diatur per peran |
| Hapus pallet lokal<br>`stock_delete_pallet` | Menghapus pallet dan isi yang diizinkan aturan stok. | `stock_delete` | Admin Warehouse, Supervisi | Dapat diatur per peran |
| Stok Lokal<br>`stock_view` | Menu / akses dasar modul. | — | Warehouse Operator, Admin Warehouse, PPC, Supervisi | Dapat diatur per peran |
| Unduh stok lokal<br>`stock_export` | Mengunduh stok menurut part, ID Box, atau pallet. | `stock_view` | Warehouse Operator, Admin Warehouse, PPC, Supervisi | Dapat diatur per peran |
| Lihat riwayat box dan pallet lokal<br>`stock_history` | Membuka jejak penerimaan, perpindahan dan pengeluaran. | `stock_view` | Warehouse Operator, Admin Warehouse, PPC, Supervisi | Dapat diatur per peran |
| Expired Box dan Handle<br>`expired_box` | Menu / akses dasar modul. | — | Supervisi, Admin Warehouse | Dapat diatur per peran |
| Mulai penanganan QA Lokal<br>`expired_box_start` | Menjalankan tahap Mulai penanganan QA untuk box expired Lokal. | `expired_box` | Supervisi, Admin Warehouse | Dapat diatur per peran |
| Putuskan hasil QA Lokal<br>`expired_box_qa` | Menjalankan tahap Putuskan hasil QA untuk box expired Lokal. | `expired_box` | Supervisi, Admin Warehouse | Dapat diatur per peran |
| Serahkan ke packing Lokal<br>`expired_box_packing` | Menjalankan tahap Serahkan ke packing untuk box expired Lokal. | `expired_box` | Supervisi, Admin Warehouse | Dapat diatur per peran |
| Terima hasil packing Lokal<br>`expired_box_receive` | Menjalankan tahap Terima hasil packing untuk box expired Lokal. | `expired_box` | Supervisi, Admin Warehouse | Dapat diatur per peran |
| Cetak pengantar packing Lokal<br>`expired_box_print` | Menjalankan tahap Cetak pengantar packing untuk box expired Lokal. | `expired_box` | Supervisi, Admin Warehouse | Dapat diatur per peran |
| Expired Box dan Handle Ekspor<br>`export_expired_box` | Menu / akses dasar modul. | — | Supervisi, Admin Warehouse | Dapat diatur per peran |
| Mulai penanganan QA Ekspor<br>`export_expired_box_start` | Menjalankan tahap Mulai penanganan QA untuk box expired Ekspor. | `export_expired_box` | Supervisi, Admin Warehouse | Dapat diatur per peran |
| Putuskan hasil QA Ekspor<br>`export_expired_box_qa` | Menjalankan tahap Putuskan hasil QA untuk box expired Ekspor. | `export_expired_box` | Supervisi, Admin Warehouse | Dapat diatur per peran |
| Serahkan ke packing Ekspor<br>`export_expired_box_packing` | Menjalankan tahap Serahkan ke packing untuk box expired Ekspor. | `export_expired_box` | Supervisi, Admin Warehouse | Dapat diatur per peran |
| Terima hasil packing Ekspor<br>`export_expired_box_receive` | Menjalankan tahap Terima hasil packing untuk box expired Ekspor. | `export_expired_box` | Supervisi, Admin Warehouse | Dapat diatur per peran |
| Pengumuman Delivery<br>`delivery_announcements` | Menu / akses dasar modul. | — | Admin Warehouse, Supervisi | Admin Warehouse, Supervisi |
| Buat pengumuman delivery<br>`announcement_create` | Menerbitkan instruksi dan kuota pengumuman. | `delivery_announcements` | Admin Warehouse, Supervisi | Admin Warehouse, Supervisi |
| Ubah pengumuman delivery<br>`announcement_edit` | Mengubah instruksi dan kuota sesuai identitas pengumuman. | `delivery_announcements` | Admin Warehouse, Supervisi | Admin Warehouse, Supervisi |
| Aktifkan/nonaktifkan pengumuman<br>`announcement_status` | Mengubah ketersediaan pengumuman dan alokasi kuota. | `delivery_announcements` | Admin Warehouse, Supervisi | Admin Warehouse, Supervisi |

### Pengiriman Ekspor

| Izin / kode | Kegunaan | Prasyarat | Default selain Admin Utama | Batas peran |
|---|---|---|---|---|
| Jalankan Stock Opname Ekspor<br>`stock_opname_export_manage` | Izin dasar yang menjadi prasyarat tindakan terkait. | `stock_opname_export` | Operator Export, Admin Warehouse | Dapat diatur per peran |
| Mulai sesi Stock Opname Ekspor<br>`stock_opname_export_start` | Menjalankan tindakan Mulai sesi pada sesi opname Ekspor. | `stock_opname_export_manage` | Operator Export, Admin Warehouse | Dapat diatur per peran |
| Scan box Stock Opname Ekspor<br>`stock_opname_export_scan` | Menjalankan tindakan Scan box pada sesi opname Ekspor. | `stock_opname_export_manage` | Operator Export, Admin Warehouse | Dapat diatur per peran |
| Perbarui acuan sesi Stock Opname Ekspor<br>`stock_opname_export_refresh` | Menjalankan tindakan Perbarui acuan sesi pada sesi opname Ekspor. | `stock_opname_export_manage` | Operator Export, Admin Warehouse | Dapat diatur per peran |
| Selesaikan sesi Stock Opname Ekspor<br>`stock_opname_export_finish` | Menjalankan tindakan Selesaikan sesi pada sesi opname Ekspor. | `stock_opname_export_manage` | Operator Export, Admin Warehouse | Dapat diatur per peran |
| Stock Opname Ekspor<br>`stock_opname_export` | Menu / akses dasar modul. | — | Operator Export, Admin Warehouse, Supervisi | Dapat diatur per peran |
| Unduh hasil Stock Opname Ekspor<br>`stock_opname_export_download` | Mengunduh snapshot hasil sesi yang selesai. | `stock_opname_export` | Operator Export, Admin Warehouse, Supervisi | Dapat diatur per peran |
| Rencana Delivery Ekspor<br>`export_delivery_plans` | Menu / akses dasar modul. | — | Operator Export | Dapat diatur per peran |
| Buat rencana delivery ekspor<br>`export_delivery_create` | Membuat rencana melalui input manual. | `export_delivery_plans` | Operator Export | Dapat diatur per peran |
| Impor Excel rencana delivery<br>`export_delivery_import` | Membaca Excel dan mengunduh template, menyimpan tetap memerlukan izin buat/ubah. | `export_delivery_plans` | Operator Export | Dapat diatur per peran |
| Ubah rencana delivery ekspor<br>`export_delivery_edit` | Mengubah kebutuhan draft sesuai perlindungan reservasi. | `export_delivery_plans` | Operator Export | Dapat diatur per peran |
| Cetak rencana delivery ekspor<br>`export_delivery_print` | Mencetak rencana yang planning-nya sudah final. | `export_delivery_plans` | Operator Export | Dapat diatur per peran |
| Hapus rencana delivery ekspor<br>`export_delivery_delete` | Meninjau dampak dan menghapus dari daftar aktif, kode/histori tetap disimpan. | `export_delivery_plans` | — | Admin Warehouse, Supervisi |
| Perencanaan Pallet Ekspor<br>`export_pallet_plans` | Menu / akses dasar modul. | — | Operator Export | Dapat diatur per peran |
| Susun dan simpan draft pallet<br>`export_pallet_edit` | Menyusun part, jumlah dan lokasi pada draft planning. | `export_pallet_plans` | Operator Export | Dapat diatur per peran |
| Finalisasi perencanaan pallet<br>`export_pallet_finalize` | Memfinalisasi pembagian dan mereservasi lokasi tujuan. | `export_pallet_plans` | Operator Export | Dapat diatur per peran |
| Buka revisi perencanaan pallet<br>`export_pallet_reopen` | Membuka planning final menjadi draft dengan perlindungan komitmen. | `export_pallet_plans` | Operator Export | Dapat diatur per peran |
| Batalkan perencanaan pallet<br>`export_pallet_cancel` | Membatalkan planning kosong dan melepas klaim lokasi. | `export_pallet_plans` | Operator Export | Dapat diatur per peran |
| Aktifkan rekomendasi FIFO<br>`export_fifo_acknowledge` | Mengonfirmasi box rekomendasi agar tugas masuk antrean. | `export_pallet_plans` | Operator Export | Dapat diatur per peran |
| Cetak rincian pallet ekspor<br>`export_pallet_print` | Mencetak seluruh atau satu pallet final. | `export_pallet_plans` | Operator Export | Dapat diatur per peran |
| Akses pengelolaan stok ekspor<br>`export_stock_manage` | Izin dasar yang menjadi prasyarat tindakan terkait. | `export_stock_view` | Admin Warehouse, Supervisi | Admin Warehouse, Supervisi |
| Koreksi box ekspor<br>`export_stock_edit` | Mengubah part, PCS atau tanggal FIFO sesuai perlindungan box. | `export_stock_manage` | Admin Warehouse, Supervisi | Admin Warehouse, Supervisi |
| Hapus box ekspor<br>`export_stock_delete_box` | Menghapus box cadangan yang layak dengan alasan dan audit. | `export_stock_manage` | Admin Warehouse, Supervisi | Admin Warehouse, Supervisi |
| Ubah batas kapasitas pallet ekspor<br>`export_stock_pallet_limit` | Mengatur batas jumlah box pada pallet cadangan. | `export_stock_manage` | Admin Warehouse, Supervisi | Admin Warehouse, Supervisi |
| Hapus pallet ekspor<br>`export_stock_delete_pallet` | Menghapus pallet cadangan beserta isi yang diizinkan. | `export_stock_manage` | Admin Warehouse, Supervisi | Admin Warehouse, Supervisi |
| Lihat riwayat box ekspor<br>`export_stock_history` | Membaca pergerakan, reservasi dan penanganan box. | `export_stock_manage` | Admin Warehouse, Supervisi | Admin Warehouse, Supervisi |
| Penerimaan Transfer Box Ekspor<br>`export_stock_input` | Menu / akses dasar modul. | — | Operator Export | Dapat diatur per peran |
| Scan dan susun penerimaan ekspor<br>`export_receiving_scan` | Membuat draft dan menambah/menghapus hasil scan. | `export_stock_input` | Operator Export | Dapat diatur per peran |
| Batalkan draft penerimaan ekspor<br>`export_receiving_clear` | Membuang draft yang belum diproses. | `export_stock_input` | Operator Export | Dapat diatur per peran |
| Proses penerimaan ekspor<br>`export_receiving_process` | Membuat stok dari penerimaan yang telah diperiksa. | `export_stock_input` | Operator Export | Dapat diatur per peran |
| Konfirmasi penempatan box ekspor<br>`export_receiving_place` | Menetapkan lokasi fisik box penerimaan atau rekomendasi. | `export_stock_input` | Operator Export | Dapat diatur per peran |
| Cetak panduan penempatan ekspor<br>`export_receiving_print` | Mencetak tujuan box, pemeriksaan tujuan tetap berlaku. | `export_stock_input` | Operator Export | Dapat diatur per peran |
| Mulai picking rekomendasi ekspor<br>`export_picking_start` | Mengunci sumber dan memulai tugas pengambilan stok. | `export_stock_input` | Operator Export | Dapat diatur per peran |
| Scan picking rekomendasi ekspor<br>`export_picking_scan` | Memverifikasi ID Box yang diambil dari sumber. | `export_stock_input` | Operator Export | Dapat diatur per peran |
| Konfirmasi pengembalian issue ekspor<br>`export_picking_return` | Menyelesaikan penempatan pengembalian setelah approval. | `export_stock_input` | Operator Export | Dapat diatur per peran |
| Merge Pallet Ekspor<br>`export_merge_pallet` | Menu / akses dasar modul. | — | Operator Export, Admin Warehouse | Dapat diatur per peran |
| Gabungkan pallet ekspor<br>`export_merge_execute` | Meninjau dan menyimpan merge cadangan atau pallet rencana. | `export_merge_pallet` | Operator Export, Admin Warehouse | Dapat diatur per peran |
| Picking Verification Ekspor<br>`export_picking_verification` | Menu / akses dasar modul. | `export_stock_input` | Operator Export | Dapat diatur per peran |
| Cari tugas picking melalui scan<br>`export_picking_seek` | Menemukan instruksi picking dengan ID Box, scan pengambilan memerlukan izin tersendiri. | `export_picking_verification` | Operator Export | Dapat diatur per peran |
| Delivery Schedule Ekspor<br>`export_fulfillment` | Menu / akses dasar modul. | — | Operator Export | Dapat diatur per peran |
| Revisi part dan jumlah pallet<br>`export_fulfillment_revise` | Menambah/mengurangi kebutuhan serta meninjau pelepasan/pengembalian, dapat diatur per role. | `export_fulfillment` | Operator Export | Dapat diatur per peran |
| Konfirmasi pallet siap kirim<br>`export_fulfillment_ready` | Memastikan isi dan lokasi tepat lalu mengunci kesiapan pallet. | `export_fulfillment` | Operator Export | Dapat diatur per peran |
| Buka ulang kesiapan pallet<br>`export_fulfillment_reopen` | Membuka kunci kesiapan agar lokasi/isi dapat diperiksa kembali. | `export_fulfillment` | Operator Export | Dapat diatur per peran |
| Konfirmasi pengiriman ekspor<br>`export_fulfillment_complete` | Menyatakan seluruh barang benar-benar keluar gudang. | `export_fulfillment` | Operator Export | Dapat diatur per peran |
| Batalkan seluruh permintaan ekspor<br>`export_fulfillment_cancel` | Meninjau pengembalian, melepas reservasi dan mengarsipkan delivery. | `export_fulfillment`, `export_delivery_delete` | — | Admin Warehouse, Supervisi |
| Lihat stok ekspor<br>`export_stock_view` | Menu / akses dasar modul. | — | Operator Export, Admin Warehouse, Supervisi | Dapat diatur per peran |
| Unduh stok ekspor<br>`export_stock_export` | Mengunduh stok ekspor sesuai izin baca. | `export_stock_view` | Operator Export, Admin Warehouse, Supervisi | Dapat diatur per peran |

### Delivery

| Izin / kode | Kegunaan | Prasyarat | Default selain Admin Utama | Batas peran |
|---|---|---|---|---|
| Jadwal Delivery<br>`delivery_schedule` | Menu / akses dasar modul. | — | Warehouse Operator, Admin Warehouse, PPC | Dapat diatur per peran |
| Sales Input dan Koreksi Order<br>`delivery_order` | Menu / akses dasar modul. | — | Sales | Dapat diatur per peran |
| Buat order delivery lokal<br>`delivery_order_create` | Menyimpan order baru untuk diperiksa PPC. | `delivery_order` | Sales | Dapat diatur per peran |
| Koreksi order delivery lokal<br>`delivery_order_edit` | Mengoreksi order milik sendiri pada status yang diizinkan. | `delivery_order` | Sales | Dapat diatur per peran |
| Approval Delivery (PPC)<br>`delivery_approval` | Menu / akses dasar modul. | — | PPC | Dapat diatur per peran |
| Setujui order delivery lokal<br>`delivery_approve` | Mengubah keputusan PPC menjadi disetujui. | `delivery_approval` | PPC | Dapat diatur per peran |
| Tolak order delivery lokal<br>`delivery_reject` | Mengubah keputusan PPC menjadi ditolak. | `delivery_approval` | PPC | Dapat diatur per peran |
| Kembalikan order untuk koreksi<br>`delivery_correction` | Meminta Sales memperbaiki order. | `delivery_approval` | PPC | Dapat diatur per peran |
| Assign Delivery<br>`delivery_assign` | Menu / akses dasar modul. | — | Warehouse Operator, Admin Warehouse | Dapat diatur per peran |
| Alokasikan box ke delivery lokal<br>`delivery_assign_execute` | Meninjau dan menetapkan stok pada order. | `delivery_assign` | Warehouse Operator, Admin Warehouse | Dapat diatur per peran |
| Input box baru saat assignment<br>`delivery_assign_new_box` | Membuat stok baru langsung dalam assignment, kapasitas tetap diperiksa. | `delivery_assign` | Warehouse Operator, Admin Warehouse | Dapat diatur per peran |
| Split dan Restore Delivery<br>`delivery_split` | Izin dasar yang menjadi prasyarat tindakan terkait. | `delivery_schedule` | Admin Warehouse | Dapat diatur per peran |
| Split delivery lokal<br>`delivery_split_execute` | Memecah kebutuhan order sesuai perlindungan assignment. | `delivery_split` | Admin Warehouse | Dapat diatur per peran |
| Pulihkan split delivery lokal<br>`delivery_split_restore` | Menggabungkan kembali hasil split yang memenuhi aturan transaksi. | `delivery_split` | Admin Warehouse | Dapat diatur per peran |
| Hapus Delivery<br>`delivery_delete` | Izin dasar yang menjadi prasyarat tindakan terkait. | `delivery_schedule` | — | Dapat diatur per peran |
| Scan Issues dan Approval<br>`delivery_issues` | Menu / akses dasar modul. | — | Admin Warehouse | Dapat diatur per peran |
| Setujui penanganan issue lokal<br>`delivery_issue_approve` | Memberi keputusan terhadap salah scan lokal. | `delivery_issues` | Admin Warehouse | Dapat diatur per peran |
| Setujui pengembalian issue ekspor<br>`export_issue_approve` | Menyetujui instruksi pengembalian salah ambil ekspor. | `delivery_issues` | Admin Warehouse | Dapat diatur per peran |
| Tutup issue ekspor sebagai salah input<br>`export_issue_input_error` | Hanya untuk salah input tanpa box fisik diambil. | `delivery_issues` | Admin Warehouse | Admin Warehouse |
| Redo Delivery<br>`delivery_redo` | Izin dasar yang menjadi prasyarat tindakan terkait. | `delivery_schedule` | Admin Warehouse | Dapat diatur per peran |
| Picking, Verifikasi dan Pengambilan<br>`delivery_pick` | Menu / akses dasar modul. | `delivery_schedule` | Warehouse Operator, Admin Warehouse | Dapat diatur per peran |
| Verifikasi picking lokal<br>`delivery_verify` | Memulai verifikasi dan memindai box yang dipilih. | `delivery_pick` | Warehouse Operator, Admin Warehouse | Dapat diatur per peran |
| Scan pengiriman lokal<br>`delivery_ship_scan` | Memulai dan melakukan scan pengambilan/pengiriman. | `delivery_pick` | Warehouse Operator, Admin Warehouse | Dapat diatur per peran |
| Selesaikan pengiriman lokal<br>`delivery_pick_complete` | Mengonfirmasi pengeluaran stok dengan jumlah tepat. | `delivery_pick` | Warehouse Operator, Admin Warehouse | Dapat diatur per peran |
| Batalkan sesi picking lokal<br>`delivery_pick_cancel` | Menghentikan sesi sesuai kepemilikan dan statusnya. | `delivery_pick` | Warehouse Operator, Admin Warehouse | Dapat diatur per peran |
| Cetak picklist lokal<br>`delivery_pick_print` | Membaca/cetak picklist, pembatasan pemilik sesi tetap berlaku. | `delivery_pick` | Warehouse Operator, Admin Warehouse | Dapat diatur per peran |

### Administrasi

| Izin / kode | Kegunaan | Prasyarat | Default selain Admin Utama | Batas peran |
|---|---|---|---|---|
| Lock Management<br>`delivery_locks` | Menu / akses dasar modul. | — | — | Khusus Admin Utama |
| Kelola User<br>`users` | Menu / akses dasar modul. | — | — | Khusus Admin Utama |
| Hak Akses<br>`access_management` | Menu / akses dasar modul. | — | — | Khusus Admin Utama |

### Master Data

| Izin / kode | Kegunaan | Prasyarat | Default selain Admin Utama | Batas peran |
|---|---|---|---|---|
| Kelola Lokasi<br>`locations` | Menu / akses dasar modul. | — | Admin Warehouse | Dapat diatur per peran |
| Tambah lokasi lokal<br>`locations_create` | Mendaftarkan lokasi gudang lokal. | `locations` | Admin Warehouse | Dapat diatur per peran |
| Ubah lokasi lokal<br>`locations_edit` | Mengubah lokasi yang memenuhi aturan okupansi. | `locations` | Admin Warehouse | Dapat diatur per peran |
| Hapus lokasi lokal<br>`locations_delete` | Menghapus lokasi yang tidak digunakan. | `locations` | Admin Warehouse | Dapat diatur per peran |
| Master Nomor Part Bersama<br>`parts` | Menu / akses dasar modul. | — | Admin Warehouse | Dapat diatur per peran |
| Tambah master part<br>`parts_create` | Mendaftarkan kode label, kode dokumen dan kapasitas dalam satu simpan. | `parts` | Admin Warehouse | Dapat diatur per peran |
| Ubah master part dan pemetaan<br>`parts_edit` | Mengubah kapasitas/pemetaan, identitas yang dipakai transaksi tetap dilindungi. | `parts` | Admin Warehouse | Dapat diatur per peran |
| Hapus master part<br>`parts_delete` | Arsipkan master tanpa stok atau proses aktif. Histori dan nomor part tetap tercatat. | `parts` | Admin Warehouse | Dapat diatur per peran |

### Master Data Ekspor

| Izin / kode | Kegunaan | Prasyarat | Default selain Admin Utama | Batas peran |
|---|---|---|---|---|
| Master Lokasi Ekspor<br>`export_locations` | Menu / akses dasar modul. | — | Operator Export | Dapat diatur per peran |
| Tambah lokasi ekspor<br>`export_locations_create` | Mendaftarkan lokasi gudang ekspor. | `export_locations` | Operator Export | Dapat diatur per peran |
| Ubah lokasi ekspor<br>`export_locations_edit` | Mengubah lokasi ekspor yang tidak dikunci stok/planning. | `export_locations` | Operator Export | Dapat diatur per peran |
| Hapus lokasi ekspor<br>`export_locations_delete` | Menghapus lokasi ekspor yang tidak digunakan. | `export_locations` | Operator Export | Dapat diatur per peran |

### Laporan

| Izin / kode | Kegunaan | Prasyarat | Default selain Admin Utama | Batas peran |
|---|---|---|---|---|
| Laporan dan Export Operasional<br>`reports` | Menu / akses dasar modul. | — | Admin Warehouse, Supervisi | Dapat diatur per peran |
| Unduh laporan operasional<br>`reports_download` | Mengunduh Excel/CSV laporan lokal dan ekspor. | `reports` | Admin Warehouse, Supervisi | Dapat diatur per peran |
| Audit Trail<br>`audit` | Menu / akses dasar modul. | — | Supervisi | Dapat diatur per peran |
| Unduh audit trail<br>`audit_download` | Mengunduh histori aktivitas yang dapat dilihat. | `audit` | Supervisi | Dapat diatur per peran |
| Lihat rincian dashboard<br>`dashboard_details` | Membuka rincian analitik dashboard. | — | Supervisi | Supervisi |

