<section class="dashboard-charts" aria-label="Grafik aktivitas stok">
    @foreach([
        ['id' => 'trendChart', 'title' => 'Tren stok masuk dan keluar', 'description' => 'Perbandingan inbound dan outbound per tanggal, dalam PCS.', 'rows' => collect($throughputDays), 'key' => 'date', 'column' => 'Tanggal'],
        ['id' => 'peakChart', 'title' => 'Aktivitas per jam', 'description' => 'Akumulasi inbound dan outbound per jam dalam periode terpilih, dalam PCS.', 'rows' => $peakHours, 'key' => 'hour', 'column' => 'Jam'],
    ] as $chart)
        <div class="dashboard-chart">
            <h2 id="{{ $chart['id'] }}-title">{{ $chart['title'] }}</h2>
            <p id="{{ $chart['id'] }}-description" class="dashboard-chart-description">{{ $chart['description'] }}</p>
            @if(!$chart['rows']->contains(fn ($row) => $row['inbound_pcs'] != 0 || $row['outbound_pcs'] != 0))
                <p class="dashboard-chart-empty">Belum ada aktivitas stok pada periode ini.</p>
            @endif
            <div class="dashboard-chart-plot">
                <canvas id="{{ $chart['id'] }}" role="img" aria-labelledby="{{ $chart['id'] }}-title" aria-describedby="{{ $chart['id'] }}-description">Angka tersedia pada tabel data di bawah grafik.</canvas>
            </div>
            <details class="dashboard-chart-data">
                <summary>Lihat data {{ $chart['column'] === 'Jam' ? 'per jam' : 'per tanggal' }}</summary>
                <div class="dashboard-chart-table" tabindex="0" role="region" aria-label="Data {{ $chart['title'] }}">
                    <table class="table table-sm mb-0">
                        <caption class="visually-hidden">{{ $chart['title'] }} dalam PCS</caption>
                        <thead><tr><th scope="col">{{ $chart['column'] }}</th><th scope="col">Inbound PCS</th><th scope="col">Outbound PCS</th></tr></thead>
                        <tbody>
                            @forelse($chart['rows'] as $row)
                                <tr><th scope="row">{{ $row[$chart['key']] }}</th><td>{{ number_format($row['inbound_pcs']) }}</td><td>{{ number_format($row['outbound_pcs']) }}</td></tr>
                            @empty
                                <tr><td colspan="3">Tidak ada data pada periode ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </details>
        </div>
    @endforeach
</section>
