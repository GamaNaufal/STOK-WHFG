<section class="{{ ($inModal ?? false) ? 'receiving-box-detail' : 'plan-panel' }}" id="receiving-instruction" aria-labelledby="receiving-instruction-title"><div class="{{ ($inModal ?? false) ? 'visually-hidden' : 'plan-panel-heading' }}"><h2 id="receiving-instruction-title">Instruksi penempatan</h2></div><div class="{{ ($inModal ?? false) ? '' : 'plan-panel-body' }}">
    @if($selected)
        @php
            $commitment = $selected->fulfillment;
            $planned = $commitment?->plannedItem->pallet;
            $delivery = $planned?->palletPlan->deliveryPlan;
            $needsPlacement = $selected->state === 'received' || ($commitment && !$commitment->placed_at);
            $canMoveSpare = $selected->state === 'stored' && !$commitment;
        @endphp
        <div class="receiving-box-heading"><div><span class="plan-eyebrow">Box ekspor</span><h3>{{ $selected->box_number }}</h3><p>{{ $selected->part_number }} · {{ number_format($selected->pcs_quantity, 0, ',', '.') }} PCS</p></div>@include('export.receiving.box-status', ['box' => $selected])</div>
        @if($selected->currentPallet)<p class="plan-help mb-3">Posisi saat ini: {{ $selected->currentPallet->display_name }} · lokasi {{ $selected->currentPallet->location_code }}.</p>@endif
        @if($commitment && $needsPlacement)
            <div class="receiving-target"><span>Letakkan box ke</span><strong>Pallet {{ $planned->position }} · {{ $planned->location_code }}</strong><p>{{ $delivery?->plan_code ?? 'Rencana delivery' }} · {{ $delivery?->customer_name }}@if($delivery) · Tenggat {{ $delivery->delivery_deadline->format('d/m/Y') }}@endif</p></div>
            @if($selectedLoad ?? null)<div class="mb-3"><p class="plan-help mb-2">Isi pallet {{ $selectedLoad['current'] }} box + {{ $selectedLoad['incoming'] }} box masuk. Total setelah penempatan:</p><x-export-pallet-load :boxes="$selectedLoad['total']" /></div>@endif
            <p class="plan-help mb-3">Tujuan sudah direservasi. Konfirmasikan setelah box benar-benar berada di lokasi ini.</p>
        @elseif($needsPlacement)
            <p class="plan-help mb-3">Box ini belum memiliki reservasi kebutuhan. Pilih lokasi kosong atau pallet ekspor yang sudah ada untuk stok cadangan.</p>
        @else
            <p class="receiving-complete">Penempatan sudah dikonfirmasi.</p>
        @endif
        @if($needsPlacement || $canMoveSpare)
            @if($canMoveSpare)<details class="receiving-relocate"><summary>Ubah penempatan stok cadangan</summary>@endif
            <form method="POST" action="{{ route('export.receiving.place', $selected) }}" id="export-place-form">
                @csrf<input type="hidden" name="revision" value="{{ $selected->revision }}"><input type="hidden" name="request_token" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                @unless($commitment)
                    <label class="form-label" for="receiving-destination">Penempatan stok cadangan</label><select class="form-select mb-3" id="receiving-destination" name="destination" required data-pallet-load-select data-incoming-count="1"><option value="">Pilih lokasi atau pallet ekspor</option><optgroup label="Lokasi kosong">@foreach($locations as $loc)<option value="location:{{ $loc->id }}" data-box-count="0">{{ $loc->code }} · pallet cadangan baru</option>@endforeach</optgroup><optgroup label="Pallet ekspor existing">@foreach($pallets as $physical)@if($physical->id !== $selected->current_pallet_id)<option value="pallet:{{ $physical->id }}" data-box-count="{{ $physical->boxes_count }}">{{ $physical->display_name }} · {{ $physical->location_code }} · {{ $physical->boxes_count }} box</option>@endif @endforeach</optgroup></select>
                    @include('export.receiving.destination-load')
                @endunless
                <button class="btn btn-primary w-100" type="submit" id="confirm-placement">Konfirmasi box sudah ditempatkan</button>
            </form>
            @if($canMoveSpare)</details>@endif
        @endif
        @if($delivery && auth()->user()->canAccess('export_fulfillment'))<a class="plan-back mt-3 mb-0" href="{{ route('export.fulfillment.show', $delivery) }}">Lihat pemenuhan permintaan</a>@endif
    @else
        <div class="receiving-empty"><i class="bi bi-upc-scan" aria-hidden="true"></i><strong>Mulai dengan scan box</strong><p>Sistem mencari kebutuhan bertenggat terdekat dan nomor pallet terkecil. Stok yang lebih lama diprioritaskan sesuai FIFO.</p></div>
    @endif
</div></section>
