# Titik lanjut paket UAT26

Status: paket selesai disiapkan setelah dilanjutkan, 31 Oktober 2026.

Pekerjaan tersimpan di branch existing `Export-plan`. Tidak membuat branch,
commit atau push. Perubahan Not Full ekspor dari pekerjaan sebelumnya tetap ada.

Sudah tersedia:

- `database/uat/01_seed_business_uat.sql`: SQL aditif development, tanggal relatif,
  guard database/hostname/InnoDB/namespace, rollback atomik, impor ulang ditolak.
- `database/uat/02_verify_business_uat.sql`: 22 query ringkasan/integritas baca saja.
- `database/uat/manifest.json`: akun, master, barcode dan skenario awal.
- `database/uat/README.md`: urutan penggunaan dan peta cakupan 32 fitur konfigurasi.
- `database/uat/validation.json`: bukti validasi terakhir pada MySQL 8.4.3 sementara.
- `docs/UAT_BUSINESS_GUIDE.md`: 116 kasus manual, role, input dan hasil yang diharapkan.
- `docs/UAT_RESULT_TEMPLATE.md`: lembar pencatatan seluruh 116 kasus, belum diuji.
- `storage/app/uat/STOK-WHFG-UAT26.zip`: paket SQL/panduan/manifest/bukti validasi,
  dengan struktur folder dan CHECKSUMS.sha256; file ZIP diabaikan Git.
- `scripts/uat/`: builder SQLite, exporter MySQL, validator dan runtime checks.
- `docs/AGENT_SYSTEM_MEMO.md`: sudah mencatat paket dan batas validasinya.

Validasi terakhir lulus: impor dengan ID target berbeda dan allocator meloncat,
rollback kegagalan di tengah impor, penolakan impor kedua, data sentinel existing
tetap, hash login, ledger/pivot/ringkasan/reservasi, 22 query, serta service FIFO
lokal/ekspor, mixed picking issue→approval→return, placement→ready→shipment,
split Not Full menjaga PCS, redo histori lokal dan penerimaan packing terlambat.
Semua skrip PHP sudah lint; peta 32 fitur/116 kasus dan manifest diperiksa;
`git diff --check` tidak menemukan whitespace error. Checklist UI manual belum
dijalankan seluruhnya dan tidak diklaim lulus.

Database aplikasi `stock` hanya dibaca skemanya selama tugas ini. **Fixture belum
diimpor ke database pengguna.** Database MySQL sementara validator sudah dihapus.
Isi awal: 9 akun, 35 part, 100 lokasi, 37 box lokal, 127 box ekspor,
12 delivery lokal dan 17 rencana ekspor, termasuk histori/arsip.

Penyelesaian setelah dilanjutkan:

1. Panduan dirapikan: nama part dipisah dari kapasitas, contoh scan delapan digit,
   singkatan lokasi dijelaskan, tahap percobaan/draft dipertegas.
2. Lembar hasil disiapkan untuk 116 kasus dengan status Belum diuji.
3. `scripts/uat/package.py` memeriksa cakupan 32 fitur/manifest/validasi lalu
   membuat ZIP dan membandingkan kembali seluruh isi arsip terhadap sumber.
4. SQL hanya berubah pada komentar judul agar tidak mengklaim dukungan MariaDB
   yang belum diuji; statement SQL tetap sesuai hasil validasi sebelumnya.
5. Database pengguna belum diisi. Langkah berikutnya dilakukan penguji:
   baca panduan, impor 01_seed_business_uat.sql, jalankan 02_verify_business_uat.sql,
   lalu jalankan checklist dan isi lembar hasil. Jangan impor otomatis kecuali diminta.

Catatan lingkungan: cwd yang benar ialah
`C:/Users/ygi/OneDrive/ドキュメント/WHFG/STOK-WHFG`, bukan path worktree lama.
Drive sementara W: sebelumnya dipakai untuk menghindari masalah path Unicode PHP;
pengemasan memakai Python standar tanpa drive tersebut. Tidak ada server preview
yang dijalankan untuk tugas ini.
Fixture SQLite scratch berada di `storage/framework/testing/uat26`, diabaikan Git;
`last-fixture.txt` menunjuk fixture terakhir untuk `build.php --export-existing`.
Tidak perlu menjalankan ulang validasi kecuali ada perubahan yang relevan.
