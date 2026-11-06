@if($errors->any())
    <div class="alert alert-danger" role="alert"><strong>Penempatan belum disimpan.</strong><ul class="mb-0">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
@endif
@include('export.receiving.placement', ['inModal' => true, 'stockContext' => true])
<dl class="receiving-detail-facts mt-3 mb-0">
    <div><dt>Pertama diterima</dt><dd>{{ $selected->first_received_at->format('d/m/Y H:i') }}</dd></div>
    <div><dt>Penempatan terakhir</dt><dd>{{ $selected->stored_at?->format('d/m/Y H:i') ?? 'Belum ditempatkan' }}</dd></div>
</dl>
@if(auth()->user()->canAccess('export_stock_manage'))
    <div class="plan-inline-actions mt-3">
        <a class="btn btn-outline-primary" href="{{ route('export.stock.box-manage', $selected) }}">Kelola box</a>
        <a class="btn btn-outline-primary" href="{{ route('export.stock.box-history', ['box_number' => $selected->box_number]) }}"><i class="bi bi-clock-history" aria-hidden="true"></i> Riwayat box</a>
    </div>
@endif
