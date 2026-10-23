<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Pengantar packing · {{ $records->count() }} box</title>
<style>body{font:14px/1.5 system-ui,sans-serif;color:#172b2a;max-width:1100px;margin:24px auto;padding:16px}h1{font-size:24px}table{width:100%;border-collapse:collapse;margin:20px 0}th,td{border:1px solid #777;padding:10px;text-align:left;vertical-align:top}small{display:block}td.blank{min-width:110px}button{padding:12px 20px}tr{break-inside:avoid}thead{display:table-header-group}.signatures{display:flex;gap:40px;margin-top:30px}.signatures p{width:45%;padding-bottom:60px;border-bottom:1px solid}@page{size:A4 landscape;margin:12mm}@media print{body{margin:0;padding:0;font-size:10px}h1{font-size:20px}th,td{padding:8px}.no-print{display:none}.signatures{break-inside:avoid}}</style>
</head><body>
<button class="no-print" onclick="window.print()">Cetak pengantar bersama</button>
<h1>Surat Pengantar Packing · {{ $records->count() }} box</h1>
<p>Setiap baris mewakili satu box lama dan satu box pengganti. Hasil QA seluruh box pada daftar ini: <strong>Lolos</strong>.</p>
<p>Pengantar menyertai barang sampai kembali ke warehouse. Petugas warehouse mengisi ID baru sesuai label packing dan mencocokkan setiap pasangan. Jangan menggabungkan identitas atau PCS antarbaris.</p>
<table><thead><tr><th>No.</th><th>Referensi / ID lama</th><th>Part / PCS</th><th>Masuk asli</th><th>Penyerahan packing</th><th>ID baru</th><th>Waktu ID diterbitkan</th><th>Penerima / waktu diterima</th></tr></thead><tbody>
@foreach($records as $record)<tr><td>{{ $loop->iteration }}</td><td><strong>{{ $record->source_box_number }}</strong><small>{{ $record->reference }}</small></td><td>{{ $record->part_number }}<small>{{ $record->pcs_quantity }} PCS</small></td><td>{{ $record->original_stored_at->format('d M Y H:i') }}</td><td>{{ $record->sent_packing_at?->format('d M Y H:i') ?? '________________' }}</td><td class="blank">{{ $record->replacement_box_number }}</td><td class="blank">{{ $record->label_issued_at?->format('d M Y H:i:s') }}</td><td class="blank">{{ $record->received_at?->format('d M Y H:i:s') }}</td></tr>@endforeach
</tbody></table>
<p>Jumlah: {{ $records->count() }} box / {{ number_format($records->sum('pcs_quantity')) }} PCS. Part dan PCS hasil harus sama dengan baris asal. Bila pasangan ID belum pasti, tahan pencatatan penerimaan untuk klarifikasi.</p>
<div class="signatures"><p>Petugas warehouse yang menyerahkan</p><p>Petugas warehouse yang menerima</p></div>
</body></html>
