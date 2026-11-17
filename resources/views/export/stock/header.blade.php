<header class="plan-header stock-header export-stock-view-header">
    <div><span class="plan-eyebrow">Pengiriman ekspor</span><h1>Stok ekspor</h1><p>Pantau stok yang tersedia dan kesiapan delivery per customer.</p></div>
    <div class="plan-inline-actions">
        @if(auth()->user()->canAccess('export_stock_manage'))
            <a class="btn btn-outline-primary" href="{{ route('export.stock.box-history') }}"><i class="bi bi-clock-history" aria-hidden="true"></i> Riwayat box</a>
        @endif
        @if(auth()->user()->canAccessRoute('export.receiving.index'))
            <a class="btn btn-outline-primary" href="{{ route('export.receiving.index') }}"><i class="bi bi-upc-scan" aria-hidden="true"></i> Penerimaan Transfer Box Ekspor</a>
        @endif
    </div>
</header>
@if(app(\App\Services\ExportCustomerStockPolicy::class)->ready())
    <nav class="export-stock-view-switch" data-stock-view="{{ $stockView }}" aria-label="Tampilan stok ekspor">
        <span class="export-stock-view-thumb" aria-hidden="true"></span>
        <a href="{{ route('export.stock.index') }}" data-stock-view-link="stock" @if($stockView === 'stock') aria-current="page" @endif>Daftar stok</a>
        <a href="{{ route('export.stock.index', ['customer_view' => 'reservations']) }}" data-stock-view-link="reservations" @if($stockView === 'reservations') aria-current="page" @endif>Reservasi customer</a>
    </nav>
@endif
