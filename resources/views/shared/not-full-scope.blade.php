@php($exportPending = app(\App\Services\ExportNotFullService::class)->pending()->count())
@if($approval ?? false)
    <nav class="d-flex flex-wrap gap-2 mb-3" aria-label="Lingkup Box Not Full">
        <a class="btn {{ request('scope') !== 'export' ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('box-not-full.approvals') }}">Lokal</a>
        <a class="btn {{ request('scope') === 'export' ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('box-not-full.approvals', ['scope' => 'export']) }}">Ekspor · {{ $exportPending }} wajib dipenuhi</a>
    </nav>
@else
    @include('shared.warehouse-scope-switch', [
        'scopeLabel' => 'Lingkup Box Not Full',
        'scope' => request('scope') === 'export' ? 'export' : 'domestic',
        'scopeUrls' => [
            'domestic' => route('box-not-full.create'),
            'export' => route('box-not-full.create', ['scope' => 'export']),
        ],
    ])
@endif
