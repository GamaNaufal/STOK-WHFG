{{-- Presentation only: keep the controller's five operator metrics unchanged. --}}
<div class="dashboard-summary">
    <dl class="dashboard-summary__inventory">
        <div class="dashboard-summary__total">
            <dt>Total Pallet</dt>
            <dd class="dashboard-summary__figure">{{ number_format($stats['total_pallets'] ?? 0, 0, ',', '.') }}</dd>
            <dd class="dashboard-summary__description">Jumlah seluruh pallet di sistem.</dd>
        </div>
        <div class="dashboard-summary__stored">
            <dt>Pallet Tersimpan</dt>
            <dd class="dashboard-summary__count">{{ number_format($stats['pallets_with_location'] ?? 0, 0, ',', '.') }}</dd>
            <dd class="dashboard-summary__description">Memiliki stok aktif dan sudah tersimpan di lokasi.</dd>
        </div>
    </dl>
    <dl class="dashboard-summary__details">
        <div class="dashboard-summary__row">
            <dt>Pending Input</dt>
            <dd class="dashboard-summary__count">{{ number_format($stats['pallets_without_location'] ?? 0, 0, ',', '.') }}</dd>
            <dd class="dashboard-summary__description">Pallet yang belum memiliki lokasi penyimpanan.</dd>
        </div>
        <div class="dashboard-summary__row">
            <dt>Total Lokasi</dt>
            <dd class="dashboard-summary__count">{{ number_format($stats['total_locations'] ?? 0, 0, ',', '.') }}</dd>
            <dd class="dashboard-summary__description">Lokasi unik pada catatan penyimpanan.</dd>
        </div>
        <div class="dashboard-summary__row">
            <dt>Picking Aktif</dt>
            <dd class="dashboard-summary__count">{{ number_format($stats['pending_deliveries'] ?? 0, 0, ',', '.') }}</dd>
            <dd class="dashboard-summary__description">Sesi picking dengan status scanning/blocked.</dd>
        </div>
    </dl>
</div>
