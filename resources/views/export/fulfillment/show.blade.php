@extends('shared.layouts.app')
@section('title', 'Detail Pemenuhan Permintaan')
@section('styles')
<link rel="stylesheet" href="{{ asset('css/pages/export-delivery-plans.css') }}">
<link rel="stylesheet" href="{{ asset('css/pages/export-fulfillment.css') }}">
@endsection
@section('content')
<div class="delivery-plan-page fulfillment-page">
    @if($errors->any())<div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<p class="mb-1">{{ $error }}</p>@endforeach</div>@endif
    <header class="plan-header"><div><a class="plan-back" href="{{ route('export.fulfillment.index') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Pemenuhan permintaan</a><span class="plan-eyebrow">Pengiriman ekspor</span><h1>{{ $delivery->plan_code ?? 'Rencana tanpa kode' }}</h1><p>{{ $delivery->customer_name }} · Batas pengiriman {{ $delivery->delivery_deadline->format('d/m/Y') }} · {{ $overview->pallet_count }} pallet</p></div>
        <div class="plan-inline-actions">@if(auth()->user()->canAccess('export_pallet_plans'))<a class="btn btn-outline-secondary" href="{{ route('export.pallet-plans.show', $delivery) }}">Lihat perencanaan</a>@endif<a class="btn btn-outline-primary" href="{{ request()->fullUrl() }}">Perbarui tampilan</a></div>
    </header>
    @if($overview->fulfillment_status === 'paused')<div class="alert alert-warning" role="status"><strong>Perencanaan perlu diselesaikan kembali.</strong> Rencana sedang direvisi atau berubah dari acuan final. Periksa pembagian dan finalisasi ulang sebelum melanjutkan pemenuhan.</div>@endif
    @if($metrics['invalid'] || $metrics['excess'])<div class="alert alert-warning" role="alert"><strong>Periksa pencatatan box.</strong> {{ number_format($metrics['invalid'], 0, ',', '.') }} box tidak sesuai part, kapasitas, atau lokasi tujuan; {{ number_format($metrics['excess'], 0, ',', '.') }} alokasi box melebihi kebutuhan. Box yang tidak sesuai tidak dihitung terpenuhi.</div>@endif
    <section class="plan-panel fulfillment-total" aria-labelledby="fulfillment-progress-title">
        <div class="plan-panel-heading"><h2 id="fulfillment-progress-title">Progres pemenuhan</h2>@include('export.fulfillment.status', ['state' => $overview->fulfillment_status])</div>
        <div class="plan-panel-body">
            <div class="fulfillment-numbers" aria-label="Jumlah box pemenuhan"><div><span>Kebutuhan</span><strong>{{ number_format($metrics['required'], 0, ',', '.') }} <small>box</small></strong></div><div><span>Terpenuhi</span><strong>{{ number_format($metrics['fulfilled'], 0, ',', '.') }} <small>box</small></strong></div><div><span>Reservasi menunggu penempatan</span><strong>{{ number_format($metrics['reserved'], 0, ',', '.') }} <small>box</small></strong></div><div><span>Sisa belum ditempatkan</span><strong>{{ number_format($metrics['remaining'], 0, ',', '.') }} <small>box</small></strong></div></div>
            <div class="fulfillment-progress" role="progressbar" aria-label="Box dikonfirmasi di lokasi tujuan" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $metrics['percent'] }}"><span style="width:{{ $metrics['percent'] }}%"></span></div>
            <p class="plan-help">{{ $metrics['percent'] }}% ditempatkan · {{ number_format($metrics['unreserved'], 0, ',', '.') }} box belum direservasi. Sisa mencakup box yang sudah direservasi tetapi belum dikonfirmasi penempatannya.</p>
        </div>
    </section>
    <section aria-labelledby="fulfillment-pallet-title"><div class="fulfillment-section-heading"><h2 id="fulfillment-pallet-title">Kebutuhan per pallet</h2><p>Buka pallet untuk melihat rincian nomor part.</p></div>
        @foreach($pallets as $entry)
            @php($pallet = $entry['pallet'])
            @php($pm = $entry['metrics'])
            <details class="plan-panel fulfillment-pallet" @if($loop->first) open @endif>
                <summary><span><strong>Pallet {{ $pallet->position }}</strong><small>Lokasi {{ $pallet->location_code ?? 'belum ditentukan' }}</small></span><span class="fulfillment-pallet-count"><strong>{{ number_format($pm['fulfilled'], 0, ',', '.') }} / {{ number_format($pm['required'], 0, ',', '.') }} box</strong><small>Sisa {{ number_format($pm['remaining'], 0, ',', '.') }} · Reservasi {{ number_format($pm['reserved'], 0, ',', '.') }}@if($pm['excess'] || $pm['invalid']) · Perlu diperiksa @endif</small></span><i class="bi bi-chevron-down" aria-hidden="true"></i></summary>
                <div class="plan-table-scroll" tabindex="0" role="region" aria-label="Pemenuhan pallet {{ $pallet->position }}"><table class="table plan-table"><thead><tr><th scope="col">Nomor part</th><th scope="col">PCS / box</th><th scope="col">Butuh</th><th scope="col">Terpenuhi</th><th scope="col">Reservasi</th><th scope="col">Sisa</th></tr></thead><tbody>
                    @forelse($entry['items'] as $item)
                        <tr><th scope="row">{{ $item->part_number }}@if($item->metrics['invalid'] || $item->metrics['excess'])<small class="fulfillment-row-warning">{{ $item->metrics['invalid'] }} tidak sesuai · {{ $item->metrics['excess'] }} berlebih</small>@endif</th><td>{{ number_format($item->pcs_per_box, 0, ',', '.') }}</td><td>{{ number_format($item->metrics['required'], 0, ',', '.') }}</td><td>{{ number_format($item->metrics['fulfilled'], 0, ',', '.') }}</td><td>{{ number_format($item->metrics['reserved'], 0, ',', '.') }}</td><td>{{ number_format($item->metrics['remaining'], 0, ',', '.') }}</td></tr>
                    @empty<tr><td colspan="6">Pembagian pallet belum diisi.</td></tr>@endforelse
                </tbody></table></div>
                <div class="plan-panel-body d-flex flex-wrap gap-2 align-items-center">
                    @if($pallet->ready_at)<span class="fulfillment-status fulfillment-status-complete">Siap · {{ $pallet->ready_at->format('d/m/Y H:i') }} · Lokasi {{ $pallet->location_code }}</span>@endif
                    @if($overview->fulfillment_status !== 'paused' && $pm['required'] > 0 && $pm['fulfilled'] === $pm['required'] && !$pm['invalid'] && !$pm['excess'])
                        @unless($pallet->ready_at)<button type="button" class="btn btn-primary" data-ready-url="{{ route('export.fulfillment.ready-form', [$delivery, $pallet]) }}">Konfirmasi Pallet {{ $pallet->position }} siap</button>@endunless
                        <a class="btn btn-outline-secondary" href="{{ route('export.fulfillment.revise', [$delivery, $pallet]) }}">Revisi pallet {{ $pallet->position }}</a>
                    @else<span class="plan-help">Konfirmasi tersedia setelah seluruh kebutuhan pallet ditempatkan.</span>@endif
                </div>
            </details>
        @endforeach
    </section>
    <section class="plan-panel" aria-labelledby="fulfillment-boxes-title"><div class="plan-panel-heading"><h2 id="fulfillment-boxes-title">Box untuk permintaan ini</h2><span>{{ number_format($boxes->total(), 0, ',', '.') }} box</span></div>
        <div class="plan-table-scroll" tabindex="0" role="region" aria-label="Reservasi dan penempatan box"><table class="table plan-table fulfillment-box-table"><thead><tr><th scope="col">Nomor box</th><th scope="col">Nomor part / PCS</th><th scope="col">Pertama diterima</th><th scope="col">Pallet tujuan</th><th scope="col">Status penempatan</th></tr></thead><tbody>
            @forelse($boxes as $box)
                <tr>
                    <th scope="row">{{ $box->box_number }}</th>
                    <td>{{ $box->part?->part_number ?? 'Part tidak sesuai' }}
                        <small class="fulfillment-customer">{{ number_format($box->pcs_quantity, 0, ',', '.') }} PCS</small>
                        @if((int) $box->part_setting_id !== (int) $box->plannedItem->part_setting_id)
                            <small class="fulfillment-row-warning">Kebutuhan: {{ $box->plannedItem->part_number }}</small>
                        @endif
                    </td>
                    <td>{{ $box->first_received_at->format('d/m/Y H:i') }}</td>
                    <td>Pallet {{ $box->plannedItem->pallet->position }}<small class="fulfillment-customer">Tujuan {{ $box->plannedItem->pallet->location_code }}</small></td>
                    <td>
                        <span class="fulfillment-status fulfillment-status-{{ $box->display_status }}">{{ ['placed' => 'Ditempatkan', 'reserved' => 'Menunggu penempatan', 'invalid' => 'Perlu diperiksa'][$box->display_status] }}</span>
                        @if($box->placed_at)
                            <small class="fulfillment-customer">{{ $box->placed_at->format('d/m/Y H:i') }} · {{ $box->placedBy?->name ?? 'Operator belum tercatat' }}@if($box->placedLocation) · {{ $box->placedLocation->code }}@endif</small>
                        @endif
                    </td>
                </tr>
            @empty<tr><td colspan="5" class="plan-empty"><strong>Belum ada box yang tercatat</strong><span>Kebutuhan pallet tersedia. Belum ada reservasi atau konfirmasi penempatan box untuk permintaan ini.</span></td></tr>@endforelse
        </tbody></table></div>
        @if($boxes->hasPages())<div class="plan-panel-body">{{ $boxes->links('pagination::bootstrap-5') }}</div>@endif
        <p class="plan-help fulfillment-table-note">Urutan box memakai tanggal pertama diterima gudang, lalu nomor box. Data dimuat {{ now()->format('d/m/Y H:i') }}.</p>
    </section>
    @php($readyCount = $pallets->filter(fn ($entry) => (bool) $entry['pallet']->ready_at)->count())
    <section class="plan-panel"><div class="plan-panel-heading"><h2>Kesiapan pengiriman</h2><span>{{ $readyCount }} / {{ $pallets->count() }} pallet siap</span></div><div class="plan-panel-body">
        <p>Setiap pallet harus dikonfirmasi siap beserta lokasi fisiknya. Selesaikan permintaan hanya setelah seluruh box keluar gudang.</p>
        @if($overview->fulfillment_status === 'complete' && $readyCount === $pallets->count() && $readyCount > 0)
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#complete-request-modal">Selesaikan permintaan</button>
        @else<button type="button" class="btn btn-secondary" disabled>Selesaikan permintaan</button>@endif
    </div></section>
</div>
<div class="modal fade" id="ready-pallet-modal" tabindex="-1" aria-labelledby="ready-pallet-title" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content" id="ready-pallet-content"></div></div></div>
<div class="modal fade" id="complete-request-modal" tabindex="-1" aria-labelledby="complete-request-title" aria-hidden="true"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="{{ route('export.fulfillment.complete', $delivery->id) }}">@csrf<input type="hidden" name="revision" value="{{ $delivery->palletPlan->revision }}"><input type="hidden" name="request_token" value="{{ (string)\Illuminate\Support\Str::uuid() }}">
    <div class="modal-header"><h2 class="modal-title fs-5" id="complete-request-title">Selesaikan permintaan?</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
    <div class="modal-body"><div class="alert alert-warning"><strong>Pastikan seluruh box permintaan sudah keluar gudang.</strong></div><p>{{ $delivery->plan_code }} · {{ $delivery->customer_name }}<br>{{ $pallets->count() }} pallet · {{ number_format($metrics['required'],0,',','.') }} box</p><p>Box dan pallet permintaan akan dikeluarkan dari daftar aktif, lokasi dibebaskan, dan rincian disimpan pada audit trail.</p><div class="form-check"><input class="form-check-input" id="shipped-confirmed" type="checkbox" name="shipped_confirmed" value="1" required><label class="form-check-label" for="shipped-confirmed">Saya memastikan seluruh box permintaan ini sudah keluar gudang.</label></div></div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Kembali</button><button type="submit" class="btn btn-primary">Ya, seluruh box sudah keluar</button></div></form>
</div></div></div>
@endsection
@section('scripts')<script src="{{ asset('js/export-fulfillment.js') }}" defer></script>@endsection
