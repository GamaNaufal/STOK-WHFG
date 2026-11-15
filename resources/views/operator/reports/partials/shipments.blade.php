@php
    $scopeLabel = $filters['scope'] === 'export' ? 'Ekspor' : 'Lokal';
    $params = array_filter($filters, fn ($value, $key) => $key === 'status' || $value !== '', ARRAY_FILTER_USE_BOTH);
    $activeFilters = collect($filters)->except(['scope','kind','status'])->filter(fn ($v) => filled($v))->count();
@endphp
<div class="shipment-report">
    <header class="withdrawal-report-header">
        <div class="withdrawal-report-intro"><span class="withdrawal-report-eyebrow">Laporan {{ $scopeLabel }}</span><h1>Laporan Pengiriman</h1><p>Telusuri setiap pengiriman selesai, rincian part dan box, serta histori pengembalian atau Redo.</p></div>
        <div class="withdrawal-report-export"><span>Excel memuat seluruh delivery sesuai filter dalam tiga sheet.</span><a data-report-export class="btn btn-outline-primary" href="{{ route('reports.withdrawal.export', $params) }}"><i class="bi bi-download" aria-hidden="true"></i> Export Excel</a></div>
    </header>
    @include('operator.reports.scope-nav')
    <section class="shipment-overview" aria-labelledby="shipment-overview-title">
        <div class="withdrawal-report-overview-heading"><div><span class="withdrawal-report-eyebrow">Ringkasan {{ $scopeLabel }}</span><h2 id="shipment-overview-title">Seluruh histori pengiriman</h2></div><p>Ringkasan seluruh histori, tabel dan Excel mengikuti filter.</p></div>
        <div class="shipment-metrics">
            <dl><dt>Delivery selesai</dt><dd>{{ number_format($summary->completed_deliveries) }}<small>kejadian pengiriman</small></dd></dl>
            <dl><dt>PCS terkirim netto</dt><dd>{{ number_format($summary->shipped_pcs) }}<small>PCS</small></dd></dl>
            <dl><dt>Box terkirim netto</dt><dd>{{ number_format($summary->shipped_boxes, 2, ',', '.') }}<small>box</small></dd></dl>
            <dl><dt>Dikembalikan / Redo</dt><dd>{{ number_format($summary->redo_events) }}<small>kejadian terpisah</small></dd></dl>
        </div>
        <p class="plan-help mt-2">Satu baris mewakili satu kejadian pengiriman. Redo tidak menambah jumlah delivery selesai atau barang terkirim netto.</p>
        @if($summary->legacy_events)<p class="shipment-history-note"><i class="bi bi-info-circle" aria-hidden="true"></i> {{ number_format($summary->legacy_events) }} catatan/batch lama belum memiliki tautan delivery. Jumlah barangnya tetap diperhitungkan sesuai status, jumlah delivery selesai hanya menghitung pengiriman dengan referensi delivery.</p>@endif
    </section>
    <nav class="shipment-tabs" aria-label="Jenis histori pengiriman">
        @foreach(['completed'=>'Pengiriman selesai', 'reversed'=>'Histori Redo / pengembalian', ''=>'Semua catatan'] as $value=>$label)
            <a data-report-reset href="{{ route('reports.withdrawal', array_replace($params, ['status'=>$value])) }}" @if($filters['status']===$value) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <section class="card withdrawal-report-filters mb-4" aria-labelledby="shipment-filter-title">
        <div class="withdrawal-report-filter-heading"><div><h2 id="shipment-filter-title">Filter pengiriman</h2><p>Pencarian part, box, lokasi, atau operator memilih seluruh delivery terkait. Jumlah dan Excel tetap mencakup seluruh isinya.</p></div><span>{{ $activeFilters }} filter aktif</span></div>
        <form data-report-filter-form method="GET" action="{{ route('reports.withdrawal') }}" class="card-body shipment-filter-grid">
            <input type="hidden" name="scope" value="{{ $filters['scope'] }}"><input type="hidden" name="kind" value="shipments">
            @foreach(['start_date'=>'Dari tanggal dikirim','end_date'=>'Sampai tanggal dikirim','q'=>'Delivery / customer / ID Box / catatan','customer_name'=>'Customer','part_number'=>'Nomor part','warehouse_location'=>'Lokasi tercatat'] as $key=>$label)
                <div><label class="form-label" for="shipment-{{ $key }}">{{ $label }}</label><input class="form-control" id="shipment-{{ $key }}" name="{{ $key }}" type="{{ str_ends_with($key,'date')?'date':'search' }}" value="{{ $filters[$key] }}" maxlength="{{ $key==='customer_name'?150:100 }}" autocomplete="off"></div>
            @endforeach
            <div><label class="form-label" for="shipment-status">Status</label><select class="form-select" id="shipment-status" name="status"><option value="">Semua status</option>@foreach($statuses as $value=>$label)<option value="{{ $value }}" @selected($filters['status']===$value)>{{ $label }}</option>@endforeach</select></div>
            <div><label class="form-label" for="shipment-operator">Operator tercatat</label><select class="form-select" id="shipment-operator" name="user_id"><option value="">Semua operator</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected((string)$filters['user_id']===(string)$user->id)>{{ $user->name }}</option>@endforeach</select></div>
            <div class="plan-inline-actions"><button type="submit" class="btn btn-primary">Terapkan</button><a data-report-reset class="btn btn-outline-secondary" href="{{ route('reports.withdrawal', ['scope'=>$filters['scope']]) }}">Atur ulang</a></div>
        </form>
    </section>
    <section class="plan-panel" aria-labelledby="shipment-results-title">
        <div class="plan-panel-heading"><h2 id="shipment-results-title">{{ $filters['status']==='reversed'?'Histori Redo / pengembalian':'Daftar pengiriman' }}</h2><span>{{ number_format($rows->total()) }} kejadian sesuai filter</span></div>
        <div class="plan-table-scroll shipment-table" role="region" tabindex="0" aria-label="Daftar pengiriman dan akses rincian"><table class="table plan-table"><thead><tr><th scope="col">Delivery / customer</th><th scope="col">Selesai dikirim</th><th scope="col">Isi pengiriman</th><th scope="col">Status</th><th scope="col">Operator tercatat</th><th scope="col">Rincian</th></tr></thead><tbody>
            @forelse($rows as $row)
                <tr><th scope="row"><strong>{{ \App\Services\ShipmentReportService::reference($row) }}</strong><span>{{ $row->customer_name ?: 'Tanpa referensi customer' }}</span><small>{{ $row->legacy?'Catatan lama tanpa tautan delivery':($filters['scope']==='export'?'Planning #':'Sesi pengiriman #').$row->session_id }}</small>@if($row->notes)<p class="shipment-notes">{{ $row->notes }}</p>@endif</th>
                    <td>{{ $row->occurred_at ? \Carbon\Carbon::parse($row->occurred_at)->format('d/m/Y H:i') : 'Waktu belum tercatat' }}@if($row->changed_at)<small>Redo/perubahan {{ \Carbon\Carbon::parse($row->changed_at)->format('d/m/Y H:i') }}</small>@endif</td>
                    <td><strong>{{ number_format($row->box_quantity,2,',','.') }} box · {{ number_format($row->pcs_quantity) }} PCS</strong><small>{{ $row->part_count }} part · {{ $row->pallet_count }} pallet tercatat</small></td>
                    <td><span class="shipment-status is-{{ $row->status }}">{{ $statuses[$row->status] ?? $row->status }}</span></td><td>{{ $row->operator_name ?: 'Belum tercatat' }}</td>
                    <td><a class="btn btn-sm btn-outline-primary" href="{{ route('reports.shipments.show', ['shipment'=>$row->event_key, 'list_page'=>$rows->currentPage()]+$params) }}">Lihat seluruh keterangan</a></td></tr>
            @empty<tr><td colspan="6" class="plan-empty"><strong>Belum ada pengiriman sesuai filter.</strong><span>Pilih rentang tanggal lain atau buka Semua catatan untuk melihat histori yang tersedia.</span></td></tr>@endforelse
        </tbody></table></div><div class="plan-panel-body">{{ $rows->links('pagination::bootstrap-5') }}</div>
    </section>
</div>
