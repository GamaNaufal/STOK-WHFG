<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Pengantar {{ $handling->reference }}</title>
<style>body{font:15px/1.5 system-ui,sans-serif;color:#172b2a;max-width:800px;margin:32px auto;padding:20px}h1{font-size:25px}table{width:100%;border-collapse:collapse;margin:24px 0}th,td{border:1px solid #777;padding:12px;text-align:left}th{width:35%}.blank{height:35px}.signatures{display:flex;justify-content:space-between;margin-top:40px;gap:24px}.signatures p{padding-bottom:65px;border-bottom:1px solid;width:45%}button{padding:12px 20px;cursor:pointer}@page{size:A4;margin:18mm}@media print{body{margin:0;padding:0;font-size:12px}h1{font-size:22px}h2{font-size:16px}table{margin:16px 0}th,td{padding:8px}.signatures{margin-top:24px}.signatures p{padding-bottom:40px}.no-print{display:none}}</style>
</head><body>
<button class="no-print" onclick="window.print()">Cetak pengantar</button>
<h1>Surat Pengantar Packing</h1><p><strong>{{ $handling->reference }}</strong><br>Hasil QA: Lolos · {{ $handling->qa_decided_at?->format('d M Y H:i') }}</p>
<table><tr><th>ID box lama</th><td>{{ $handling->source_box_number }}</td></tr><tr><th>Nomor part</th><td>{{ $handling->part_number }}</td></tr><tr><th>Jumlah tetap</th><td>{{ $handling->pcs_quantity }} PCS · 1 box</td></tr><tr><th>Tanggal masuk asli</th><td>{{ $handling->original_stored_at->format('d M Y H:i') }}</td></tr><tr><th>Diserahkan ke packing</th><td>{{ $handling->sent_packing_at?->format('d M Y H:i') ?? '________________________' }}</td></tr></table>
<p>Sertakan pengantar ini bersama box sampai kembali ke warehouse. Satu box lama menjadi satu box baru dengan part dan PCS yang sama.</p>
<h2>Diisi petugas warehouse saat penerimaan</h2>
<table><tr><th>ID baru dari packing</th><td class="blank">{{ $handling->replacement_box_number }}</td></tr><tr><th>Waktu ID baru diterbitkan</th><td class="blank">{{ $handling->label_issued_at?->format('d M Y H:i:s') }}</td></tr><tr><th>Waktu diterima warehouse</th><td class="blank">{{ $handling->received_at?->format('d M Y H:i:s') }}</td></tr><tr><th>Catatan pencocokan</th><td class="blank"></td></tr></table>
<p>Warehouse mencocokkan pengantar, ID baru, part, dan PCS sebelum mencatat penerimaan di sistem. Bila pasangan ID tidak dapat dipastikan, tahan pencatatan untuk klarifikasi.</p>
<div class="signatures"><p>Petugas warehouse yang menyerahkan</p><p>Petugas warehouse yang menerima</p></div>
</body></html>
