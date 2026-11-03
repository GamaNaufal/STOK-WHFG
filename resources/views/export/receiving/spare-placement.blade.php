@php
    $placementTokens=collect($group['placements'])->keyBy('id');
    $spareBoxes=$group['actionable']->map(fn($box)=>['id'=>$box->id,'revision'=>$box->revision,
        'request_token'=>$placementTokens[$box->id]['request_token'],'box_number'=>$box->box_number,'part_number'=>$box->part_number])->values();
@endphp
<div class="receiving-spare-placement" data-spare-placement data-part="{{ $group['part_number'] }}" data-key="{{ $group['key'] }}" data-warning-at="{{ \App\Support\ExportPalletLoad::WARNING_AT }}" data-danger-at="{{ \App\Support\ExportPalletLoad::DANGER_AT }}">
    <div class="receiving-split-heading"><div><label class="form-label" for="group-destination-{{ $group['key'] }}">Tujuan {{ $group['pending']->count() }} box · {{ $group['part_number'] }}</label><p class="plan-help mb-0">Urutan box mengikuti penerimaan pertama. Konfirmasi setelah peletakan fisik sesuai pembagian.</p></div><button type="button" class="btn btn-outline-primary" data-split-toggle hidden aria-expanded="false">Bagi ke beberapa tujuan</button></div>
    <div data-spare-single>
        <select class="form-select mb-3" id="group-destination-{{ $group['key'] }}" name="destination" required data-incoming-count="{{ $group['pending']->count() }}" data-spare-single-select><option value="">Pilih lokasi kosong atau pallet ekspor</option>@include('export.receiving.destination-options')</select>
    </div>
    <div data-spare-split hidden><div data-spare-allocation-rows></div><button type="button" class="btn btn-outline-primary mb-3" data-add-spare-destination>Tambah tujuan</button></div>
    <div class="receiving-load-preview" data-spare-loads aria-live="polite" hidden></div>
    <p class="plan-help" data-spare-allocation-status role="status"></p>
    <details class="receiving-spare-preview" data-spare-preview hidden><summary>Rincian box dan tujuan pilihan</summary><div class="plan-table-scroll" role="region" tabindex="0" aria-label="Pembagian box {{ $group['part_number'] }}"><table class="table plan-table"><thead><tr><th>Nomor box</th><th>Nomor part</th><th>Pallet / lokasi tujuan</th></tr></thead><tbody data-spare-preview-rows></tbody></table></div></details>
    <template data-spare-allocation-template><div class="receiving-spare-allocation"><div><label data-allocation-qty-label>Jumlah box</label><input class="form-control" type="number" min="1" step="1" inputmode="numeric" data-spare-quantity></div><div><label data-allocation-target-label>Lokasi / pallet tujuan</label><select class="form-select" data-spare-target></select></div><button type="button" class="btn btn-outline-danger" data-remove-spare-destination aria-label="Hapus tujuan">Hapus</button></div></template>
    <script type="application/json" data-spare-boxes>{!! json_encode($spareBoxes,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
</div>
