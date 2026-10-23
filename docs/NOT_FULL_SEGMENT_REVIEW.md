# Request dan Approval Box Not Full

Audit dan implementasi: 18 Oktober 2026.

## Acuan

- `apple-design`: hierarki, feedback langsung, fokus, dan adaptasi ukuran layar.
- `emil-design-eng`: validasi, konfirmasi, pemulihan, serta detail keyboard.
- `design-taste-frontend`: evaluasi jarak, kesejajaran, warna, keterbacaan, dan
  komposisi ponsel. Skill versi ini secara eksplisit ditujukan untuk halaman
  publik, bukan app/dashboard; alur random direction, hero, foto, dan inspector
  marketing tidak diterapkan ke form operasional. Preferensi Apple, Bootstrap,
  font sistem, teal, dan warna status bisnis tetap menjadi baseline.

## Temuan dan perbaikan

| Before | After | Why |
| --- | --- | --- |
| Form memakai banner/dekorasi kuat, kelompok field kurang jelas | Judul tenang, bagian detail box/delivery dan tujuan penyimpanan | Hierarki mengikuti pekerjaan operator |
| Label tidak terhubung ke field | Label, petunjuk, aria-invalid, dan pesan field terhubung | Keyboard dan pembaca layar mendapat konteks |
| Batas kapasitas tidak terlihat setelah memilih part | Kapasitas dan rentang PCS diperbarui dari master yang sudah dirender | Operator melihat batas sebelum mengirim |
| Tujuan kosong, alasan pendek, dan PCS di luar batas baru ditolak server | Validasi inline sebelum konfirmasi; tujuan aktif wajib dipilih | Mengurangi perjalanan form yang gagal |
| Listener memakai form pertama di dokumen | Listener terikat ke form request secara eksplisit | Tidak bergantung pada posisi form layout |
| Konfirmasi request hanya menyebut part, PCS, dan jenis tujuan | ID box, delivery, tipe, serta pallet/lokasi aktual diperlihatkan | Pengguna memeriksa identitas transaksi |
| Konfirmasi approval generik, alasan dan pelanggan dipotong | Teks lengkap; konfirmasi menyebut identitas dan dampak kuantitas order | Keputusan menggunakan konteks yang cukup |
| Tombol aksi mengecil dan label hilang di ponsel | Label Setujui/Tolak tetap terlihat, target minimal 44px | Aksi mudah dikenali dan disentuh |
| Tabel pending sangat lebar pada ponsel | Rincian per request menjadi susunan label/nilai; desktop tetap tabel | Alasan dan tujuan tidak tersembunyi saat mengambil keputusan |
| Klik kirim/keputusan dapat diulang | Guard saat konfirmasi/proses dan tombol Memproses | Menghindari submit berulang dari UI |
| Feedback server hanya toast sementara | Pesan sukses/error menetap di halaman | Pesan masih tersedia setelah toast hilang |
| CSS approval memakai stack styles yang tidak dirender layout | Stylesheet dimuat melalui section styles yang didukung | Tampilan halaman benar-benar menerima style khususnya |

## Batas perubahan

Tidak mengubah controller, route, payload POST, izin fitur, status request,
approval, assignment stok, atau perhitungan kuantitas order. Tidak menambahkan
alasan penolakan yang tidak didukung backend. Requester tetap melihat riwayat
approved/rejected sesuai query yang ada. Endpoint server tetap menjadi sumber
validasi akhir untuk kapasitas delivery, lock picking, dan konflik stok/lokasi.

## Verifikasi

- 20 tes backend / 279 assertions lulus: `StockInputAndNotFullWorkflowTest`
  dan `FeatureAccessManagementTest`. Termasuk integritas approval, target lokasi,
  dan pembatasan akses/transaksi yang sudah tersedia.
- Render fixture Blade dalam SQLite memory: 20 assertions, mencakup halaman
  berisi/kosong, flash sukses/error, serta akses default enam role.
- Browser fixture: form kosong, PCS sama dengan kapasitas, alasan dua karakter,
  pemilihan master part, target lokasi baru dan pallet existing, konfirmasi
  kembali, request gagal dengan input dipertahankan, dan request sukses.
- Approval: konfirmasi Setujui/Tolak menyebut dampak yang sesuai, pembatalan,
  respons gagal, respons sukses, daftar pending dan riwayat kosong.
- Desktop dan 390 × 844 diperiksa. Kontrol part/input sama-sama 44px. Rincian
  pending ponsel tidak overflow horizontal; tabel riwayat tetap dalam wadah
  gulir. Nama pelanggan dan alasan panjang dipakai dalam fixture.
- Sintaks JavaScript dan `git diff --check` diperiksa. Tidak ada error console
  pada alur browser yang diuji.
- Browser menggunakan respons POST simulasi; transaksi aktual diuji melalui
  Laravel dengan database testing. Database operasional tidak dimutasi.
  Scanner/perangkat sentuh fisik dan seluruh variasi gangguan jaringan belum
  diuji. Screenshot tidak disertakan dalam laporan pengguna.
