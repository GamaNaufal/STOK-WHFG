<details class="dashboard-delivery-data">
    <summary>Lihat data rencana dan aktual</summary>
    <div class="dashboard-delivery-table" tabindex="0" role="region" aria-label="Data rencana dan aktual delivery">
        <table class="table table-sm mb-0">
            <caption class="visually-hidden">Rencana Sales dan Aktual Delivery dalam QTY</caption>
            <thead><tr><th scope="col">Periode</th><th scope="col">Rencana Sales</th><th scope="col">Aktual Delivery</th></tr></thead>
            <tbody>
                @forelse($deliveryTrend as $row)
                    <tr><th scope="row">{{ $row['label'] }}</th><td>{{ number_format($row['planned_qty']) }}</td><td>{{ number_format($row['actual_qty']) }}</td></tr>
                @empty
                    <tr><td colspan="3">Tidak ada data pada periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</details>
