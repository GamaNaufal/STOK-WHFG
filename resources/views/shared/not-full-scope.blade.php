<nav class="d-flex flex-wrap gap-2 mb-3" aria-label="Lingkup Box Not Full">
    @php($scopeRoute = ($approval ?? false) ? 'box-not-full.approvals' : 'box-not-full.create')
    <a class="btn {{ request('scope') !== 'export' ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route($scopeRoute) }}">Domestik</a>
    <a class="btn {{ request('scope') === 'export' ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route($scopeRoute, ['scope'=>'export']) }}">Ekspor · {{ app(\App\Services\ExportNotFullService::class)->pending()->count() }} wajib dipenuhi</a>
</nav>
