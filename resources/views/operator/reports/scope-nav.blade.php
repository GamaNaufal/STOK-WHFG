<form method="GET" action="{{ route('reports.records') }}" class="card mb-4" data-report-scope-form aria-label="Pilih lingkup dan jenis laporan"><div class="card-body d-flex flex-wrap gap-3 align-items-end">
    <div><label class="form-label" for="report-scope">Lingkup laporan</label><select class="form-select" id="report-scope" name="scope"><option value="domestic" @selected(($filters['scope']??'domestic')==='domestic')>Domestik / Lokal</option><option value="export" @selected(($filters['scope']??'domestic')==='export')>Ekspor</option></select></div>
    <div><label class="form-label" for="report-kind">Jenis laporan</label><select class="form-select" id="report-kind" name="kind">@foreach(\App\Services\WarehouseReportService::KINDS as $key=>$label)<option value="{{ $key }}" @selected(($kind??$filters['kind']??'receipts')===$key)>{{ $label }}</option>@endforeach</select></div>
    <button class="btn btn-primary" type="submit">Tampilkan laporan</button>
    <p class="small text-muted mb-0">Data setiap lingkup ditampilkan terpisah. Pilihan baru mengatur ulang filter catatan.</p>
</div></form>
