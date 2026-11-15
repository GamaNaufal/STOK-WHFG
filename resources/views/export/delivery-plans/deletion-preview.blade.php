@php($delivery = $review['delivery'])
<div class="text-start" style="max-height:60vh;overflow:auto;padding:.25rem">
    <p class="text-muted">Tahap 1 dari 2 · {{ $delivery->plan_code }} · {{ $delivery->customer_name }}</p>
    <p>Tinjau dampak berikut sebelum melanjutkan. Belum ada reservasi yang dilepas atau data yang dihapus pada tahap ini.</p>
    @if($review['reason'])<div class="alert alert-warning" role="alert">{{ $review['reason'] }}</div>@endif
    <h3 class="fs-6">Box terkait: {{ count($review['rows']) }}</h3>
    @if($review['rows'])
        <p>Box yang belum mulai picking hanya dilepas reservasinya, ID Box, jumlah PCS, posisi tercatat, dan tanggal FIFO tetap. Pastikan posisi aktual sesuai catatan berikut.</p>
        <div class="table-responsive" role="region" tabindex="0" aria-label="Box dan dampak penghapusan">
            <table class="table table-sm align-middle"><thead><tr><th>ID Box</th><th>Nomor part</th><th>PCS</th><th>Posisi tercatat</th><th>Tujuan rencana</th><th>Dampak</th></tr></thead><tbody>
            @foreach($review['rows'] as $box)
                <tr><th>{{ $box['box_number'] }}</th><td>{{ $box['part_number'] }}</td><td>{{ number_format($box['pcs'], 0, ',', '.') }}</td><td>{{ $box['source'] }} · {{ $box['source_location'] }}</td><td>{{ $box['target'] }} · {{ $box['target_location'] }}</td><td>{{ $box['physical_return'] ? 'Wajib pengembalian fisik, hapus langsung ditahan' : ($review['can_delete'] ? 'Reservasi akan dilepas, barang tetap di posisi tercatat' : 'Reservasi terkait, belum dilepas') }}</td></tr>
            @endforeach
            </tbody></table>
        </div>
    @else<p>Tidak ada box terkait yang akan dilepas reservasinya atau dipindahkan.</p>@endif
    <h3 class="fs-6 mt-3">Reservasi lokasi tujuan: {{ count($review['locations']) }}</h3>
    @if($review['locations'])
        <p>{{ $review['can_delete'] ? 'Klaim lokasi berikut akan dilepas setelah penghapusan dikonfirmasi. Ini tidak memindahkan barang dari lokasi asal.' : 'Lokasi berikut masih terkait rencana dan belum dibebaskan.' }}</p>
        <ul>@foreach($review['locations'] as $location)<li>Pallet {{ $location['pallet'] }} · Lokasi {{ $location['location'] }}</li>@endforeach</ul>
    @else<p>Tidak ada reservasi lokasi tujuan yang akan dilepas.</p>@endif
    @if($review['not_full'])
        <h3 class="fs-6 mt-3">Kebutuhan Box Not Full terkait</h3>
        <ul>@foreach($review['not_full'] as $request)<li>{{ $request['part_number'] }} · {{ $request['boxes'] }} box × {{ $request['pcs'] }} PCS @if(!empty($request['preparation']['box_number'])) · ID Box persiapan {{ $request['preparation']['box_number'] }}@endif @if(!empty($request['preparation']['source_box_number'])) · Catatan sumber persiapan: {{ $request['preparation']['source_box_number'] }} di {{ $request['preparation']['source_location'] ?? 'lokasi belum tercatat' }}@endif — {{ $review['can_delete'] ? 'kebutuhan/persiapan akan dibatalkan, stok sumber tidak dipindahkan otomatis' : 'belum dibatalkan' }}</li>@endforeach</ul>
    @endif
    @if($review['can_delete'])
        <form method="POST" action="{{ route('export.delivery-plans.destroy', $delivery) }}" data-delete-delivery-form>
            @csrf @method('DELETE')
            <input type="hidden" name="revision" value="{{ $delivery->revision }}">
            @if($delivery->palletPlan && $delivery->palletPlan->status !== 'cancelled')<input type="hidden" name="pallet_revision" value="{{ $delivery->palletPlan->revision }}">@endif
            <input type="hidden" name="review_token" value="{{ $review['token'] }}">
            @if($review['rows'])<label for="delete-delivery-reason" class="form-label">Alasan penghapusan dan pelepasan reservasi</label><textarea id="delete-delivery-reason" name="reason" class="form-control" rows="2" maxlength="500" required></textarea>@endif
        </form>
    @elseif($delivery->palletPlan?->isFinalFor($delivery) && auth()->user()->canAccess('export_fulfillment') && !$delivery->palletPlan->completed_at)
        <a class="btn btn-outline-danger" href="{{ route('export.fulfillment.cancel-form', $delivery) }}">Tinjau pembatalan dan pengembalian box</a>
    @endif
</div>
