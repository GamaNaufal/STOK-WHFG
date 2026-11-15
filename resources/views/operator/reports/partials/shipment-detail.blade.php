@php
    $params = array_filter($filters, fn ($value,$key) => $key==='status'||$value!=='', ARRAY_FILTER_USE_BOTH);
    $formatDate = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('d/m/Y H:i') : 'Belum tercatat';
@endphp
<div class="shipment-report">
    <header class="withdrawal-report-header"><div class="withdrawal-report-intro">
        <a class="plan-back" data-report-reset href="{{ route('reports.withdrawal', ['page'=>$listPage]+$params) }}">← Laporan Pengiriman</a>
        <span class="withdrawal-report-eyebrow">Rincian {{ $filters['scope']==='export'?'Ekspor':'Lokal' }}</span><h1>{{ \App\Services\ShipmentReportService::reference($row) }}</h1>
        <p>{{ $row->customer_name ?: 'Catatan lama tanpa tautan customer' }} · {{ $statuses[$row->status] ?? $row->status }}</p>
    </div><div class="withdrawal-report-export"><span>Tiga sheet khusus pengiriman ini, termasuk semua halaman rincian.</span><a data-report-export class="btn btn-outline-primary" href="{{ route('reports.shipments.export', ['shipment'=>$shipment]+$params) }}"><i class="bi bi-download" aria-hidden="true"></i> Export delivery ini</a></div></header>
    @if($row->legacy)<p class="shipment-history-note">Catatan ini belum memiliki hubungan delivery yang dapat dipastikan. ID batch/transaksi, jumlah barang, lokasi, dan catatan asli tetap ditampilkan sesuai data yang tersedia.</p>@endif
    @if($row->status==='reversed')<p class="shipment-history-note">Pengiriman ini telah dikembalikan atau di-Redo. Jumlah di bawah menunjukkan isi kejadian tersebut dan tidak menambah barang terkirim netto.</p>@endif
    <section class="plan-panel mb-4" aria-labelledby="shipment-info-title"><div class="plan-panel-heading"><h2 id="shipment-info-title">Keterangan pengiriman</h2><span class="shipment-status is-{{ $row->status }}">{{ $statuses[$row->status] ?? $row->status }}</span></div>
        <dl class="shipment-facts plan-panel-body">
            @foreach(['event_key'=>'ID pengiriman','order_id'=>'ID order / rencana','session_id'=>'Sesi / planning','operator_name'=>'Operator tercatat'] as $key=>$label)<div><dt>{{ $label }}</dt><dd>{{ $row->$key ?: 'Belum tercatat' }}</dd></div>@endforeach
            @foreach(['delivery_deadline'=>'Tenggat delivery','started_at'=>'Mulai proses','occurred_at'=>'Selesai dikirim','changed_at'=>'Redo / perubahan'] as $key=>$label)<div><dt>{{ $label }}</dt><dd>{{ $formatDate($row->$key) }}</dd></div>@endforeach
            <div><dt>Isi kejadian pengiriman</dt><dd>{{ number_format($row->box_quantity,2,',','.') }} box · {{ number_format($row->pcs_quantity) }} PCS</dd></div><div><dt>Part / pallet tercatat</dt><dd>{{ $row->part_count }} part · {{ $row->pallet_count }} pallet</dd></div>
            <div><dt>Barang terkirim netto</dt><dd>{{ number_format($row->shipped_boxes,2,',','.') }} box · {{ number_format($row->shipped_pcs) }} PCS</dd></div>
        </dl>
        <div class="shipment-note-block plan-panel-body"><h3>Catatan delivery / sumber dokumen</h3><p>{{ $row->delivery_notes ?: 'Tidak ada catatan.' }}</p><h3>Catatan approval</h3><p>{{ $row->approval_notes ?: 'Tidak ada catatan.' }}</p></div>
    </section>
    <section class="plan-panel mb-4" aria-labelledby="shipment-parts-title"><div class="plan-panel-heading"><h2 id="shipment-parts-title">Rincian part</h2><span>{{ $parts->total() }} baris</span></div>
        <div class="plan-table-scroll" role="region" tabindex="0" aria-label="Rincian part pengiriman"><table class="table plan-table"><thead><tr><th scope="col">Nomor part label</th><th scope="col">Nomor part dokumen</th><th scope="col">Box</th><th scope="col">PCS</th><th scope="col">Status</th></tr></thead><tbody>
            @forelse($parts as $part)<tr><th scope="row">{{ $part->part_number }}</th><td>{{ $part->document_number }}</td><td>{{ number_format($part->box_quantity,2,',','.') }}</td><td>{{ number_format($part->pcs_quantity) }}</td><td>{{ $statuses[$part->status] ?? $part->status }}</td></tr>
            @empty<tr><td colspan="5" class="plan-empty">Rincian part belum tersedia pada catatan ini.</td></tr>@endforelse
        </tbody></table></div><div class="plan-panel-body">{{ $parts->links('pagination::bootstrap-5') }}</div>
    </section>
    <section class="plan-panel" aria-labelledby="shipment-boxes-title"><div class="plan-panel-heading"><h2 id="shipment-boxes-title">Rincian box dan seluruh catatan</h2><span>{{ $boxes->total() }} baris</span></div>
        <div class="plan-table-scroll shipment-box-table" role="region" tabindex="0" aria-label="Rincian box pengiriman, geser untuk melihat seluruh kolom"><table class="table plan-table"><thead><tr><th scope="col">ID Box / part</th><th scope="col">Jumlah</th><th scope="col">Pallet / lokasi</th><th scope="col">Waktu dan petugas</th><th scope="col">Catatan</th></tr></thead><tbody>
            @foreach($boxes as $box)<tr><th scope="row"><strong>{{ $box->box_number ?: 'ID Box belum tercatat' }}</strong>@if($box->identity_cycle)<small>Penggunaan {{ $box->identity_cycle }}</small>@endif<span>{{ $box->part_number ?: 'Part belum tercatat' }}</span><small>Dokumen: {{ $box->document_number ?: '—' }}</small><small>{{ $statuses[$box->status] ?? $box->status }}</small></th>
                <td>{{ number_format($box->box_quantity,2,',','.') }} box<small>{{ number_format($box->pcs_quantity) }} PCS</small></td>
                <td>{{ $box->pallet_number ? 'Pallet '.$box->pallet_number : 'Pallet belum tercatat' }}<small>{{ $box->location ?: 'Lokasi belum tercatat' }}</small></td>
                <td><dl class="shipment-box-times"><dt>Operator tercatat</dt><dd>{{ $box->operator_name ?: '—' }}</dd><dt>Pertama diterima</dt><dd>{{ $formatDate($box->received_at) }}</dd><dt>Scan / verifikasi</dt><dd>{{ $formatDate($box->scanned_at) }}<small>{{ $box->scanner_name }}</small></dd><dt>Penempatan</dt><dd>{{ $formatDate($box->placed_at) }}<small>{{ $box->placer_name }}</small></dd><dt>Dikirim</dt><dd>{{ $formatDate($box->occurred_at) }}</dd>@if($box->changed_at)<dt>Redo / perubahan</dt><dd>{{ $formatDate($box->changed_at) }}</dd>@endif</dl></td>
                <td class="shipment-notes">{{ $box->notes ?: 'Tidak ada catatan transaksi.' }}@if($box->approval_notes)<p>Approval: {{ $box->approval_notes }}</p>@endif</td></tr>@endforeach
        </tbody></table></div><div class="plan-panel-body">{{ $boxes->links('pagination::bootstrap-5') }}</div>
        <p class="plan-help plan-panel-body">Lokasi merupakan lokasi yang tercatat pada kejadian pengiriman, bukan posisi stok saat ini. Keterangan yang belum tersimpan pada data lama ditampilkan sebagai belum tercatat.</p>
    </section>
</div>
