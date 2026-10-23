# Review segmen ringkasan dashboard — 16 Oktober 2026

## Iterasi Current Handling dan Active Pallet — 17 Oktober 2026

Segmen selesai pada dashboard SPV/Admin Utama. Route laporan lainnya tetap
menggunakan tabel sebelumnya. ID `current-handling` dan `active-pallets` tetap
menjadi tujuan tautan ringkasan. Query, urutan, grouping pallet, serta data tetap.

| Before | After | Why |
| --- | --- | --- |
| Batas dataset tidak terlihat | Keterangan maksimal 200 baris dan jumlah baris/pallet yang ditampilkan | Tidak memberi kesan bahwa ringkasan mencakup seluruh gudang |
| Tabel panjang kehilangan konteks saat digulir | Header dan kolom Box/Pallet tetap terlihat dalam wadah gulir | Menjaga orientasi pada data panjang dan layar kecil |
| Angka rata kiri tanpa pemisah ribuan | Angka rata kanan dengan pemisah ribuan dan tabular numerals | Mempermudah perbandingan PCS/box |
| Kode panjang melebar, kondisi kosong masih berupa tabel | Teks membungkus; pesan kosong tanpa tabel lebar | Mencegah overflow halaman dan menjelaskan kondisi kosong |

Keterangan batas mengacu source `OperationalReportService::buildCurrentHandling`:
query dibatasi 200 baris, diurutkan `boxes.created_at` ascending, tidak memakai
filter periode. `activePallets` berasal dari grouping koleksi tersebut di view.
Tidak ada perubahan aturan bisnis dalam iterasi ini.

File: `resources/views/shared/partials/dashboard-stock-tables.blade.php`,
`public/css/dashboard-stock-tables.css`,
`resources/views/operator/reports/operational.blade.php`, dan dokumen ini.

Validasi:

- 2 tes render existing / 56 assertion lulus; preview sementara 1 tes / 16
  assertion lulus untuk kedua role. Data contoh menghasilkan 40 baris dan 10
  pallet, termasuk nilai 999,999,999 PCS serta agregat 1,000,113,999 PCS.
- Data contoh hanya pada render view, menggunakan SQLite in-memory. Lokasi null
  tetap tampil `Unknown`; grouping dan seluruh field sumber dipertahankan.
- Browser pada 1440, 768, 390px, kedua role, kondisi kosong/berisi: tidak ada
  overflow panel atau sel numerik. Tabel lebar digulir di dalam wadahnya.
- Page Down mengubah scrollTop menjadi 364px dengan posisi header tetap sama
  dengan atas wadah. Arrow Right mengubah scrollLeft 40px pada kedua tabel,
  sementara kolom pertama tetap sejajar dengan sisi kiri wadah.
- Fokus keyboard 3px terlihat. Teks part/lokasi panjang membungkus; tampilan
  desktop, ponsel, dan pesan kosong diperiksa. Tidak ada error browser tercatat.
- `git diff --check` lulus. Belum diuji pada perangkat fisik/pembaca layar.

Usulan berikutnya: tabel Inbound vs Outbound Matching dan Schedule Fulfillment.
Berhenti untuk review pengguna; laporan tanpa screenshot.

## Iterasi rencana/aktual delivery dan scan mismatch — 17 Oktober 2026

Dilanjutkan atas arahan pengguna setelah pemeriksaan grafik aktivitas stok.
Cakupan: satu bagian dashboard SPV/Admin Utama yang berisi grafik fulfillment
dan ringkasan mismatch. Halaman laporan non-dashboard tetap memakai presentasi
sebelumnya; data, status, filtering, dan perhitungan tidak berubah.

| Before | After | Why |
| --- | --- | --- |
| Grafik Highcharts gelap di halaman terang | Latar putih, teks gelap, grid halus ditetapkan pada dashboard | Kontras konsisten dengan tema aplikasi |
| Judul fulfillment tidak menjelaskan seri | Rencana dan aktual delivery, deskripsi QTY, tabel alternatif | Makna grafik dan angka dapat dibaca tanpa interaksi pointer |
| Label tanggal berdesakan pada ponsel | Jumlah label responsif dan rotasi pada grafik sempit | Seluruh titik tetap ada, label tidak bertabrakan |
| Jumlah mismatch hanya tersebar per jenis | Total mismatch memakai jumlah yang sudah dihitung view | Ringkasan lebih cepat dibaca |
| Kode scan dan tanggal saling berebut lebar | Kode membungkus, timestamp pada baris berikutnya | Kode panjang tetap terbaca pada ponsel |

File iterasi ini: `public/css/dashboard-performance.css`,
`resources/views/shared/partials/dashboard-delivery-data.blade.php`,
`resources/views/operator/reports/operational.blade.php`, serta dokumen ini.
Warna/pola seri, legenda, tooltip, tabel detail lama, dan data grafik tetap.
Animasi grafik dashboard dimatikan; styling dibatasi dengan class dashboard.

Validasi:

- 2 tes render existing lulus / 56 assertion, termasuk dashboard keenam role.
- 1 tes preview sementara / 12 assertion lulus untuk SPV dan Admin Utama:
  kondisi kosong, rencana/aktual contoh, dan total 23 mismatch dari dua jenis.
  Data contoh diberikan pada view, menggunakan SQLite in-memory.
- Browser 1440, 768, 390px: tidak ada overflow pada panel maupun baris mismatch;
  latar SVG terverifikasi `#ffffff`. Pemeriksaan visual desktop dan ponsel selesai.
- Enter membuka tabel dengan 7 periode contoh; fokus outline 3px terlihat.
- Label ponsel yang sempat rapat diperbaiki dan diperiksa ulang; kode scan panjang
  membungkus tanpa memotong timestamp. Tidak ada error browser tercatat.
- `git diff --check` lulus. Belum diuji pada perangkat fisik/pembaca layar maupun
  seluruh kombinasi periode; tooltip/legenda tidak diuji langsung.

Usulan berikutnya: tabel Current Handling dan Active Pallet pada dashboard.
Berhenti untuk review pengguna. Screenshot tidak disertakan pada laporan.

## Iterasi grafik aktivitas stok — 17 Oktober 2026

Segmen selesai: grafik tren inbound/outbound dan aktivitas per jam pada dashboard
SPV/Admin Utama. Grafik fulfillment dan seluruh tabel laporan lama tetap.

| Before | After | Why |
| --- | --- | --- |
| Dua kartu dengan tinggi canvas berbeda | Satu bagian dengan dua area grafik setinggi 17rem, bertumpuk pada layar sempit | Perbandingan lebih mudah dibaca |
| Label jam/tanggal padat dan satuan kurang jelas | Label sumbu dibatasi, tanggal sumbu DD/MM, satuan PCS eksplisit | Mengurangi benturan label tanpa menghapus titik data; tanggal lengkap tetap ada di tooltip/tabel |
| Warna saja membedakan garis | Outbound memakai garis putus-putus dan titik berbeda | Membantu membedakan seri |
| Nol tampak seperti grafik tanpa konteks | Pesan tanpa aktivitas dan tabel angka yang dapat dibuka lewat keyboard | Kondisi kosong dan nilai dapat dipahami tanpa mengandalkan canvas |

File iterasi: `resources/views/shared/partials/dashboard-charts.blade.php`,
`public/css/dashboard-charts.css`, `resources/views/operator/reports/operational.blade.php`,
dan dokumen ini. Dataset, perhitungan, periode, route, hak akses, warna seri, serta
konfigurasi grafik halaman laporan non-dashboard tetap. Animasi dua grafik
dashboard dimatikan untuk respons langsung dan reduced motion. Legenda Chart.js
tetap menggunakan interaksi bawaannya.

Validasi:

- 2 tes render existing lulus, 56 assertion; preview sementara 1 tes / 8 assertion
  lulus untuk kedua role dengan data kosong dan data contoh pada view.
- Preview memakai SQLite in-memory. Data contoh hanya diberikan pada render view,
  tidak ditulis ke data aplikasi.
- Browser: 1440, 768, 390px tanpa overflow panel; tinggi kedua canvas 272px.
  Tampilan berisi diperiksa pada desktop dan ponsel. Label tanggal ponsel yang
  sempat rapat diperbaiki menjadi DD/MM.
- Enter membuka tabel, fokus 3px terlihat. Pembukaan satu tabel tidak lagi
  mendorong posisi grafik sebelahnya. Tabel panjang digulir dalam wadah.
- Tidak ada error browser yang tercatat pada preview berisi.
- Belum diuji: perangkat fisik, pembaca layar, interaksi tooltip/legenda langsung,
  dan rentang data sangat panjang. Penyedia script eksternal tetap seperti semula.

Usulan segmen berikutnya: grafik fulfillment dan ringkasan scan mismatch.
Berhenti untuk review pengguna; laporan tanpa screenshot.

## Iterasi toolbar filter — 17 Oktober 2026

Segmen selesai: toolbar filter pada route dashboard SPV dan Admin Utama.
Halaman laporan operasional di route lain mempertahankan form sebelumnya.

| Before | After | Why |
| --- | --- | --- |
| Petunjuk di bawah satu kolom membuat input tidak sejajar | Periode cepat serta kedua tanggal dikelompokkan dengan petunjuk bersama | Hubungan dan prioritas filter tanggal lebih jelas |
| Kelompok data dan fulfillment bercampur dengan tanggal | Fieldset Tampilan data terpisah dari Periode laporan | Pengguna dapat memindai fungsi setiap kelompok |
| Label tidak terhubung ke kontrol | ID, label for, legend, dan aria-describedby | Nama kontrol serta bantuan dapat diakses keyboard dan teknologi bantu |
| Tombol terbungkus mengikuti grid yang melebihi 12 kolom | Terapkan/Reset sejajar pada desktop dan mengikuti alur form pada ponsel | Posisi aksi dapat diprediksi |

File iterasi ini:

- `resources/views/shared/partials/dashboard-filters.blade.php`: form GET dengan
  nama parameter, nilai opsi, action dan tujuan Reset yang sama.
- `public/css/dashboard-filters.css`: tata letak khusus filter dashboard,
  kontrol 44px, fokus 3px, penyesuaian tablet/ponsel.
- `resources/views/operator/reports/operational.blade.php`: memuat partial dan
  stylesheet hanya untuk route dashboard.
- Dokumen review ini.

Validasi:

- 14 tes existing lulus / 279 assertion (render halaman, dashboard seluruh role,
  dan hak akses).
- Preview sementara: 1 tes / 52 assertion lulus untuk Admin Utama dan SPV,
  meliputi nilai default, opsi periode/pengelompokan/fulfillment, tanggal custom,
  serta tanggal yang diterima view setelah request. SQLite in-memory digunakan.
- Pemeriksaan browser kedua role pada 1440, 768, dan 390px: form tidak overflow,
  semua input/select/tombol/Reset setinggi 44px, label terhubung ke input.
- Mengganti pengelompokan melalui select berhasil; Tab menuju Fulfillment dengan
  outline fokus 3px. Pemeriksaan visual desktop dan ponsel selesai.
- `git diff --check` lulus. Tidak ada perubahan controller, service, route,
  otorisasi, database, atau JavaScript dalam iterasi ini.

Batas pemeriksaan: submit diuji lewat request Laravel; preview browser bersifat
statis. Perangkat fisik dan pembaca layar belum diuji. Tidak ada animasi baru.
Usulan berikutnya: bagian grafik tren dan jam sibuk dashboard SPV/Admin Utama.
Berhenti pada toolbar filter untuk review pengguna; laporan tanpa screenshot.

## Iterasi akses cepat — 17 Oktober 2026

Segmen selesai: akses cepat dashboard pada Warehouse Operator, Sales, PPC, dan
Admin Warehouse. Admin Utama serta Supervisi tetap memakai dashboard operasional
hasil iterasi sebelumnya. Cabang fallback pada template bersama memakai komponen
yang sama tanpa mengubah pemilihan dashboard dari controller.

| Before | After | Why |
| --- | --- | --- |
| Kartu besar berulang, ikon di atas, tombol terpisah jauh dari judul | Satu daftar dengan ikon, judul, deskripsi, dan aksi sejajar | Fitur lebih cepat dipindai dan penggunaan ruang lebih efisien |
| Fitur tanpa izin tetap tampil tanpa tombol maupun penjelasan | Keterangan akses tidak tersedia menggantikan tautan | Menjelaskan keadaan tanpa menawarkan aksi yang tidak diizinkan |
| Semua tombol berwarna penuh | Aksi navigasi memakai aksen teal di atas latar lembut | Menjaga hierarki dengan ringkasan dan mengurangi dominasi tombol |
| Markup kartu diulang pada setiap role | Komponen Blade untuk setiap baris akses cepat | Menyamakan struktur, fokus, dan ukuran target |

Implementasi iterasi ini:

- `resources/views/shared/dashboard.blade.php`: daftar akses cepat; seluruh nama,
  urutan, deskripsi, serta tujuan route dipertahankan.
- `resources/views/components/dashboard-action.blade.php`: baris akses cepat,
  pemeriksaan `canAccessRoute` yang sama, serta keadaan tanpa izin.
- `public/css/dashboard.css`: tata letak daftar, responsif, target minimal 44px,
  fokus keyboard 3px dan feedback tekan langsung tanpa animasi/pergeseran.
- Dokumen review ini.

Validasi:

- 14 tes existing lulus, 279 assertion (`UiReviewPreviewTest` dan
  `FeatureAccessManagementTest`), termasuk render dashboard keenam role.
- Pemeriksaan preview sementara lulus 1 tes / 19 assertion: urutan dan tujuan
  tautan empat role, serta tidak adanya tautan Sales setelah izin dicabut.
  Preview memakai SQLite in-memory; tidak mengubah data aplikasi.
- Empat role dan kondisi Sales tanpa izin diperiksa pada 1440, 768, dan 390px.
  Tidak ada overflow horizontal pada halaman maupun baris akses cepat.
- Navigasi Tab berpindah sesuai urutan; fokus terlihat 3px dan tinggi seluruh
  tautan 44px. Kontras tautan sekitar 5,2:1; deskripsi sekitar 6,6:1.
- CSS tidak menambahkan motion; reduced motion tetap mendapat feedback warna
  yang sama. Segmen hanya berisi navigasi sehingga tidak memiliki loading,
  error transaksi, atau form submission tersendiri.
- Apple menjadi acuan utama; Emil dipakai untuk feedback langsung. Taste v2
  secara eksplisit mengecualikan dashboard/app screens, sehingga workflow situs
  pemasaran dan inspector-nya tidak diterapkan.

Belum diuji: perangkat fisik dan pembaca layar. Pemeriksaan visual dilakukan di
browser; screenshot tidak disertakan dalam laporan sesuai preferensi pengguna.
Usulan segmen berikutnya: toolbar filter dashboard operasional SPV/Admin Utama.
Berhenti setelah akses cepat untuk review pengguna.

## Tindak lanjut: popover dan Admin Utama — selesai 17 Oktober 2026

Atas permintaan lanjutan pengguna, Admin Utama kini memakai dashboard laporan
operasional yang sama dengan Supervisi. Statistik serta sinkronisasi expiry pada
controller tetap dipertahankan. Admin Warehouse dan tiga role lainnya tidak berubah.

Popover laporan kini memakai permukaan solid, muncul dari bawah/atas pemicu,
dan dibatasi ke area konten melalui Popper. Layer berada di atas sidebar tetapi
di bawah dialog. Tabel preview dapat digulir, animasi kemunculan dihilangkan,
dan Escape menutup preview. Ini memperbaiki benturan sidebar yang dilaporkan.
Teks panjang pada sel membungkus dan padding tabel dipadatkan agar preview tetap
terbaca pada layar kecil.

| Sebelum | Sesudah |
| --- | --- |
| Popover membuka ke kiri dan bertabrakan dengan sidebar | Popover membuka di bawah/atas pemicu, dibatasi area konten, dengan latar solid |
| Nama customer panjang membuat tabel preview melebar pada ponsel | Teks membungkus; seluruh kolom contoh terlihat pada lebar 390px |
| Admin Utama memakai ringkasan terpisah | Admin Utama memakai dashboard operasional Supervisi; role lain tetap |

Validasi tindak lanjut:

- Suite regresi lengkap pada 16 Oktober: 204 tes lulus, 1.293 assertion.
- Preview setelah penyesuaian CSS ponsel pada 17 Oktober: 1 tes lulus,
  4 assertion, memakai data SQLite in-memory untuk Admin Utama dan Supervisi.
- Desktop 1440px: popup berada pada x=332–852, di dalam area konten
  x=270,5–1402,5; tidak menimpa sidebar.
- Ponsel 390px: popup berada pada x=12–370; lebar konten dan wadah sama-sama
  356px, tanpa overflow horizontal pada tabel contoh dengan nama panjang.
- Preview dibuka melalui fokus keyboard (konfigurasi popup yang sama dengan
  hover), dan Escape menutupnya. Pointer hover, perangkat fisik, serta pembaca
  layar belum diuji langsung.
- Hak akses, proses transaksi, dan dependensi tidak diubah. Perubahan controller
  hanya memilih tampilan operasional Admin Utama sambil mempertahankan statistik
  serta sinkronisasi expiry yang sebelumnya berjalan.

## Catatan iterasi sebelumnya — 16 Oktober 2026

Bagian berikut merekam iterasi ringkasan sebelumnya; pilihan dashboard Admin
Utama di atas menggantikan rancangan ringkasannya pada iterasi tersebut.

Cakupan: ringkasan dashboard Warehouse Operator, Sales, PPC, Admin Warehouse,
Admin Utama, dan Supervisi. Iterasi sebelumnya untuk Warehouse Operator diperluas
ke seluruh role atas permintaan pengguna. Akses cepat bukan bagian iterasi ini.

| Before | After | Why |
| --- | --- | --- |
| Deretan kartu KPI dengan bobot visual seragam | Angka utama dan metrik pendukung dalam satu panel | Hierarki lebih jelas tanpa kartu berulang |
| Penjelasan angka disembunyikan dalam tooltip | Definisi tampil langsung | Dapat dibaca tanpa hover |
| Ringkasan Supervisi berupa empat kartu | Fulfillment sebagai angka utama, tiga metrik sebagai daftar bertautan | Mempertahankan akses detail serta preview popover |
| Struktur KPI berulang di setiap role | Komponen ringkasan dan stylesheet khusus | Konsistensi tanpa mengubah tema global |

## Penerapan per role

- Warehouse Operator: Total Pallet, Pallet Tersimpan, Pending Input, Total Lokasi,
  Picking Aktif.
- Sales: Total Order dan Order Hari Ini, dilengkapi pending/approved/completed.
- PPC: Menunggu Persetujuan dan Order Disetujui, dilengkapi total order dan stok.
- Admin Warehouse: pallet serta PCS, dilengkapi lokasi, scan issues, dan part.
- Admin Utama: pallet dan box, dilengkapi lokasi, part, PCS, order; catatan Hari Ini
  dan Pending tetap ada.
- Supervisi: halaman aktual berasal dari `operator.reports.operational`.
  Fulfillment Rate, Active Boxes, Active Pallets, Scan Issues tetap memakai nilai
  semula. Tema ringkasan dibatasi pada route dashboard; route laporan lain tetap
  memakai panel sebelumnya.

## File implementasi

- `resources/views/shared/dashboard.blade.php`
- `resources/views/shared/partials/operator-summary.blade.php`
- `resources/views/shared/partials/role-summary.blade.php`
- `resources/views/shared/partials/supervisor-summary.blade.php`
- `resources/views/components/dashboard-summary.blade.php`
- `resources/views/operator/reports/operational.blade.php`
- `public/css/dashboard.css`
- `public/css/dashboard-summary.css`
- `tests/Feature/UiReviewPreviewTest.php`

## Validasi

- 17 tes terkait lulus, 288 assertion: ringkasan stok, hak akses, render halaman,
  seluruh role dashboard, tautan detail Supervisi, serta pilihan Order Partial.
- Preview menggunakan SQLite in-memory dan data contoh; tidak menulis data produksi.
- Pemeriksaan desktop 1440px untuk seluruh role. Pada 390px, lima role diuji dengan
  angka sembilan digit, Supervisi dengan data contoh; tidak ada overflow pada panel
  atau teks metrik. Pada 768px seluruh role diperiksa dengan data kosong.
- Keyboard Supervisi: fokus 3px terlihat, popover Active Boxes tampil dengan data,
  Enter menuju `#current-handling`.
- Pemeriksaan source memastikan seluruh ekspresi angka dipertahankan; akses cepat,
  kondisi role, isi detail laporan, dan JavaScript laporan tidak berubah.
- Pint dan `git diff --check` lulus. Tidak ada perubahan backend atau dependensi.

Taste v2 berfokus pada halaman publik; aturan gambar, motion pemasaran, dan
inspector khusus halaman publik tidak diterapkan ke WMS. Pemeriksaan visual
dilakukan melalui browser. Prinsip Emil dipakai untuk respons keyboard langsung;
tidak ditambahkan animasi pada angka statis.

Belum diuji: perangkat fisik, pembaca layar, dan semua kombinasi data operasional.
Segmen berikutnya yang disarankan: akses cepat dashboard. Tunggu review pengguna.
