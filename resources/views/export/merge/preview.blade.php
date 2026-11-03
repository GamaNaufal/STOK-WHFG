@extends('shared.layouts.app')
@section('title', 'Tinjau Merge Pallet Ekspor')
@section('styles')<link rel="stylesheet" href="{{ asset('css/pages/export-delivery-plans.css') }}"><link rel="stylesheet" href="{{ asset('css/pages/export-merge.css') }}">@endsection
@section('content')
<div class="delivery-plan-page export-merge-page">
    <header class="mb-4"><p class="text-muted mb-1">Merge Pallet Ekspor</p><h1>Tinjau sebelum digabung</h1><p>{{ $preview['delivery']?->plan_code ?? 'Stok cadangan' }}@if($preview['delivery']) · {{ $preview['delivery']->customer_name }} · Revisi {{ $preview['plan']->revision }}@endif</p></header>
    <div class="merge-destination"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i><div><small>Tujuan seluruh box dari pallet terpilih</small><h2>{{ $preview['survivor'] ? 'Pallet '.$preview['survivor']->position : 'Pallet cadangan baru' }} · Lokasi {{ $preview['location']->code }}</h2><span>{{ $preview['result_boxes'] }} box dipindahkan @if($preview['plan']) · {{ $preview['required_boxes'] }} box kebutuhan setelah merge @endif</span></div><x-export-pallet-load :boxes="$preview['plan'] ? $preview['required_boxes'] : $preview['result_boxes']" /></div>
    <p class="mt-3">Sumber: @foreach($preview['sources'] as $source)<strong>{{ $source->display_name }} · {{ $source->location_code }}</strong>{{ $loop->last ? '' : ' + ' }}@endforeach</p>
    @if($preview['ready_count'])<div class="alert alert-warning"><strong>Status siap akan dibatalkan.</strong> {{ $preview['ready_count'] }} pallet sumber sudah siap. Setelah merge, pallet hasil harus dikonfirmasi siap kembali melalui Pemenuhan Permintaan.</div>@endif
    @if($preview['added_boxes'])<div class="alert alert-danger border-danger"><h2 class="fs-5"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> Rencana delivery akan berubah</h2><p class="mb-0">Ada <strong>{{ $preview['added_boxes'] }} box tambahan</strong> di luar kebutuhan semula. Jumlah PCS dan box delivery akan bertambah sesuai rincian. Pastikan perubahan ini sudah sesuai kebutuhan customer.</p></div>@endif
    @if($preview['plan'])
        <section class="plan-panel"><div class="plan-panel-heading"><h2>Perubahan kebutuhan pada pallet terpilih</h2></div><div class="plan-table-scroll" tabindex="0" role="region" aria-label="Perubahan kebutuhan"><table class="table plan-table"><thead><tr><th>Nomor part</th><th>PCS / box</th><th>Semula (box)</th><th>Cadangan masuk</th><th>Tambahan</th><th>Hasil (box)</th><th>Perubahan PCS</th></tr></thead><tbody>
        @foreach($preview['requirements'] as $r)<tr><th scope="row">{{ $r['part_number'] }}</th><td>{{ $r['pcs_per_box'] }}</td><td>{{ $r['before'] }}</td><td>{{ $r['incoming'] }}</td><td class="{{ $r['added'] ? 'text-danger fw-bold':'' }}">+{{ $r['added'] }}</td><td><strong>{{ $r['after'] }}</strong></td><td>{{ number_format($r['before']*$r['pcs_per_box']) }} → {{ number_format($r['after']*$r['pcs_per_box']) }}</td></tr>@endforeach
        </tbody></table></div></section>
        <div class="alert alert-info">Rencana tetap final dengan revisi baru. Reservasi yang belum dipindahkan mengikuti pallet hasil merge. Verifikasi picking yang wajib tetap harus diselesaikan; box tersebut belum dihitung terpenuhi sampai penempatannya dikonfirmasi. Data Not Full yang sudah disiapkan perlu ditinjau dan disiapkan ulang. Cetak ulang panduan setelah merge.</div>
    @else<div class="alert alert-info">Reservasi setiap box tetap mengikuti delivery asalnya. Merge cadangan hanya mengubah lokasi fisik sumber pengambilan.</div>@endif
    <section class="plan-panel"><div class="plan-panel-heading"><h2>ID Box yang harus dipindahkan</h2><span>{{ $preview['boxes']->count() }} box</span></div><div class="plan-table-scroll merge-box-list" tabindex="0" role="region" aria-label="Box sumber"><table class="table plan-table"><thead><tr><th>ID Box</th><th>Nomor part</th><th>PCS</th><th>Lokasi asal</th><th>Pertama diterima</th></tr></thead><tbody>
        @foreach($preview['boxes'] as $box)<tr><th scope="row">{{ $box->box_number }}</th><td>{{ $box->part_number }}</td><td>{{ $box->pcs_quantity }}</td><td>{{ $preview['sources']->firstWhere('id',$box->current_pallet_id)->location_code }}</td><td>{{ $box->first_received_at->format('d/m/Y H:i') }}</td></tr>@endforeach
    </tbody></table></div></section>
    <form method="POST" action="{{ route('export.merge.store') }}" class="plan-panel" data-merge-confirm>@csrf
        @foreach($data['pallet_ids'] as $id)<input type="hidden" name="pallet_ids[]" value="{{ $id }}">@endforeach
        <input type="hidden" name="location_id" value="{{ $data['location_id'] }}"><input type="hidden" name="spare_mode" value="{{ $data['spare_mode'] }}"><input type="hidden" name="fingerprint" value="{{ $preview['fingerprint'] }}"><input type="hidden" name="request_token" value="{{ $token }}">
        <div class="plan-panel-body"><h2 class="fs-5 mb-3">Konfirmasi fisik</h2>
            @if($preview['ready_count'])<label class="merge-confirm"><input type="checkbox" name="ready_confirmed" value="1" required><span>Saya memahami status siap dibatalkan dan pallet hasil harus dikonfirmasi ulang.</span></label>@endif
            @if($preview['added_boxes'])<label class="merge-confirm"><input type="checkbox" name="demand_confirmed" value="1" required><span>Saya menyetujui penambahan kebutuhan delivery sesuai rincian di atas.</span></label>@endif
            <label class="merge-confirm"><input type="checkbox" name="physical_confirmed" value="1" required><span>Seluruh box terpilih telah dipindahkan secara fisik ke <strong>lokasi {{ $preview['location']->code }}</strong> sesuai rincian.</span></label>
            <div class="merge-actions mt-3"><a class="btn btn-outline-secondary" href="{{ route('export.merge.index') }}">Ubah pilihan</a><button class="btn btn-primary" type="submit">Konfirmasi merge</button></div>
        </div>
    </form>
</div>
@endsection
@section('scripts')<script src="{{ asset('js/pages/export-merge.js') }}" defer></script>@endsection
