@php
    $pickParameters = ['pallet' => $group['planned']?->id];
    if (!empty($receipt['notice_id'])) $pickParameters['notice'] = $receipt['notice_id'];
    $redirectLinks = $group['redirected']->map(function ($box) {
        $noticeId = $box->fulfillment->allocation_notice_id;
        $palletId = $box->fulfillment->plannedItem->export_planned_pallet_id;
        return ['code' => \App\Services\ExportReceivingTransactionService::code('recommendation', $noticeId, $palletId),
            'url' => route('export.receiving.transactions.recommendation', ['notice' => $noticeId, 'pallet' => $palletId])];
    })->unique('url');
@endphp
<section class="plan-panel receiving-result-group" @unless($group['planned']) data-spare-part-group @else data-planned-target="{{ $group['physical_pallet_id'] }}" data-planned-current="{{ $group['load']['current'] }}" data-planned-incoming="{{ $group['load']['incoming'] }}" @endunless>
    <div class="plan-panel-heading"><div><h3>{{ $group['planned'] ? 'Pallet '.$group['planned']->position.' · '.$group['planned']->location_code : 'Part '.$group['part_number'] }}</h3>@if($group['delivery'])<p class="plan-help">{{ $group['delivery']->plan_code ?? 'Rencana delivery' }} · {{ $group['delivery']->customer_name }} · {{ $group['delivery']->delivery_deadline->format('d/m/Y') }}</p>@else<p class="plan-help">Stok cadangan · Belum mendapat alokasi rencana</p>@endif</div><strong>{{ $group['boxes']->count() }} box</strong></div>
    <div class="receiving-destination {{ !$group['planned'] && $group['pending']->isNotEmpty() ? 'is-unassigned' : '' }}" @if(!$group['planned'] && $group['pending']->isNotEmpty()) data-spare-guide-destination aria-live="polite" @endif>
        <p>{{ $group['pending']->isNotEmpty() ? 'Antarkan box ke' : ($group['redirected']->isNotEmpty() ? 'Melalui transaksi rekomendasi' : 'Penempatan selesai') }}</p>
        <div class="receiving-destination-values"><div><span>Pallet tujuan</span><strong data-guide-pallet>{{ $group['planned'] ? 'Pallet '.$group['planned']->position : 'Pallet cadangan' }}</strong></div><div><span>Lokasi tujuan</span><strong data-guide-location>{{ $group['planned']?->location_code ?? ($group['pending']->isNotEmpty() ? 'Belum dipilih' : 'Lihat posisi masing-masing box') }}</strong></div></div>
        @if(!$group['planned'] && $group['pending']->isNotEmpty())<small>Pilih lokasi atau pallet cadangan di bawah. Pilihan ini akan disertakan saat mencetak panduan.</small>@endif
    </div>
    @if($group['load'])<div class="receiving-group-load"><p class="plan-help mb-2" data-planned-load-summary>Isi pallet {{ $group['load']['current'] }} box + {{ $group['load']['incoming'] }} box masuk. Total setelah penempatan:</p><x-export-pallet-load :boxes="$group['load']['total']" /></div>@endif
    @if($redirectLinks->isNotEmpty())<div class="alert alert-info mx-3">Lanjutkan box rekomendasi pada transaksi tersendiri:@foreach($redirectLinks as $link) <a class="d-block" href="{{ $link['url'] }}">{{ $link['code'] }}</a>@endforeach</div>@endif
    @unless($group['planned'])<details class="receiving-box-history" @if($group['pending']->isEmpty()) open @endif><summary>Status {{ $group['boxes']->count() }} box pada part ini</summary>@endunless
    <div class="plan-table-scroll" role="region" tabindex="0" aria-label="Daftar box dalam kelompok penempatan"><table class="table plan-table"><thead><tr><th>ID Box</th><th>Nomor part / PCS</th><th>Posisi saat ini</th><th>Status</th></tr></thead><tbody>@foreach($group['boxes'] as $box)<tr><th scope="row">{{ $box->box_number }}</th><td>{{ $box->part_number }}<small class="fulfillment-customer">{{ number_format($box->pcs_quantity, 0, ',', '.') }} PCS</small></td><td>{{ $box->currentPallet ? $box->currentPallet->display_name.' · '.$box->currentPallet->location_code : 'Belum ditempatkan' }}</td><td>@if($group['redirected']->contains('id', $box->id))<span class="fulfillment-status fulfillment-status-paused">Di transaksi rekomendasi FIFO</span>@else @include('export.receiving.box-status') @endif</td></tr>@endforeach</tbody></table></div>
    @unless($group['planned'])</details>@endunless
    @if($group['waiting']->isNotEmpty())<p class="alert alert-info mx-3">{{ $group['waiting']->count() }} box menunggu Oke pada tinjauan stok FIFO.@if(auth()->user()->canAccess('export_pallet_plans')) <a href="{{ route('export.pallet-plans.show', $group['delivery']) }}">Buka tinjauan perencanaan</a>.@endif</p>@endif
    @if($group['picking_issue'])
        <div class="alert alert-danger mx-3"><strong>Pengambilan diblokir oleh issue ekspor #{{ $group['picking_issue']->id }}.</strong><p>Approval admin dan konfirmasi penempatan pengembalian harus diselesaikan dahulu.</p><a class="btn btn-outline-primary" href="{{ route('export.receiving.picking.show', array_filter(['pallet'=>$group['picking_issue']->export_planned_pallet_id, 'notice'=>data_get($group['picking_issue']->context, 'allocation_notice_id')])) }}">Lihat arahan pengembalian</a></div>
    @elseif($group['picking_remaining'] > 0)
        <div class="alert alert-warning mx-3"><strong>{{ $group['picking_remaining'] }} box wajib diverifikasi.</strong><p>Scan ID Box rekomendasi sebelum konfirmasi penempatan.</p><a class="btn btn-primary" href="{{ route('export.receiving.picking.show', $pickParameters) }}">Verifikasi pengambilan</a></div>
    @endif
    @if($group['actionable']->isNotEmpty() && !$group['picking_issue'] && $group['picking_remaining'] === 0 && empty($receipt['picking_start_needed']))
        <form method="POST" action="{{ route('export.receiving.place-bulk') }}" class="plan-panel-body receiving-group-form" data-placement-form>
            @csrf
            @if(!empty($receipt['notice_id']))<input type="hidden" name="return_notice" value="{{ $receipt['notice_id'] }}"><input type="hidden" name="return_pallet" value="{{ $receipt['pallet_id'] }}">@elseif($batch)<input type="hidden" name="return_batch" value="{{ $batch->id }}">@elseif($returnBox)<input type="hidden" name="return_box" value="{{ $returnBox }}">@endif
            <input type="hidden" name="placements_json" value="{{ json_encode($group['placements'], JSON_THROW_ON_ERROR) }}">
            @unless($group['planned']) @include('export.receiving.spare-placement') @endunless
            @if($group['picking_required'] > 0)<p class="receiving-complete">Verifikasi pengambilan sudah lengkap.</p>@endif
            <p class="plan-help mb-2">Konfirmasikan hanya setelah semua box pada kelompok ini sudah ditempatkan. Penerimaan box baru tidak memerlukan verifikasi pengambilan tambahan.</p><button class="btn btn-primary" type="submit">Konfirmasi {{ $group['actionable']->count() }} box sudah ditempatkan</button>
        </form>
    @elseif($group['pending']->isEmpty() && $group['redirected']->isEmpty())<p class="plan-help receiving-table-note">Penempatan kelompok ini sudah dikonfirmasi.</p>@endif
</section>
