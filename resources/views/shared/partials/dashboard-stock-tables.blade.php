<section class="dashboard-stock" id="current-handling" aria-labelledby="dashboard-handling-title">
    <header class="dashboard-stock-header">
        <div>
            <h2 id="dashboard-handling-title">Current Handling <span>Barang aktif</span></h2>
            <p>Maksimal 200 baris, diurutkan dari tanggal masuk terlama. Tidak mengikuti filter periode.</p>
        </div>
        <span class="dashboard-stock-count">{{ number_format($currentHandling->count()) }} baris ditampilkan</span>
    </header>
    @if($currentHandling->isEmpty())
        <div class="dashboard-stock-empty"><strong>Belum ada barang aktif untuk ditampilkan.</strong><p>Data akan tampil ketika tersedia pada laporan barang aktif.</p></div>
    @else
        <p class="dashboard-stock-hint" id="dashboard-handling-help">Geser tabel untuk melihat semua kolom. Kolom Box tetap terlihat.</p>
        <div class="dashboard-stock-scroll" tabindex="0" role="region" aria-label="Tabel barang aktif" aria-describedby="dashboard-handling-help">
            <table class="table dashboard-stock-table dashboard-stock-table--boxes mb-0">
                <caption class="visually-hidden">Current Handling — barang aktif yang ditampilkan</caption>
                <colgroup><col style="width:14%"><col style="width:20%"><col style="width:14%"><col style="width:16%"><col style="width:16%"><col style="width:20%"></colgroup>
                <thead><tr><th scope="col">Box</th><th scope="col">Part</th><th scope="col" class="dashboard-stock-number">PCS</th><th scope="col">Pallet</th><th scope="col">Lokasi</th><th scope="col">Tanggal masuk</th></tr></thead>
                <tbody>
                    @foreach($currentHandling as $row)
                        <tr>
                            <th scope="row">{{ $row->box_number }}</th>
                            <td>{{ $row->part_number }}</td>
                            <td class="dashboard-stock-number">{{ number_format($row->pcs_quantity) }}</td>
                            <td>{{ $row->pallet_number }}</td>
                            <td>{{ $row->warehouse_location ?? 'Unknown' }}</td>
                            <td>{{ optional($row->created_at)->format('d M Y H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

<section class="dashboard-stock" id="active-pallets" aria-labelledby="dashboard-pallets-title">
    <header class="dashboard-stock-header">
        <div>
            <h2 id="dashboard-pallets-title">Active Pallet <span>Pallet aktif</span></h2>
            <p>Ringkasan pallet dari baris Current Handling yang ditampilkan, bukan total seluruh gudang.</p>
        </div>
        <span class="dashboard-stock-count">{{ number_format($activePallets->count()) }} pallet ditampilkan</span>
    </header>
    @if($activePallets->isEmpty())
        <div class="dashboard-stock-empty"><strong>Belum ada pallet aktif untuk ditampilkan.</strong><p>Ringkasan mengikuti data pada tabel Current Handling.</p></div>
    @else
        <p class="dashboard-stock-hint" id="dashboard-pallets-help">Geser tabel untuk melihat semua kolom. Kolom Pallet tetap terlihat.</p>
        <div class="dashboard-stock-scroll" tabindex="0" role="region" aria-label="Tabel pallet aktif" aria-describedby="dashboard-pallets-help">
            <table class="table dashboard-stock-table dashboard-stock-table--pallets mb-0">
                <caption class="visually-hidden">Active Pallet — ringkasan dari barang aktif yang ditampilkan</caption>
                <colgroup><col style="width:22%"><col style="width:14%"><col style="width:18%"><col style="width:22%"><col style="width:24%"></colgroup>
                <thead><tr><th scope="col">Pallet</th><th scope="col" class="dashboard-stock-number">Total Box</th><th scope="col" class="dashboard-stock-number">Total PCS</th><th scope="col">Lokasi</th><th scope="col">First In</th></tr></thead>
                <tbody>
                    @foreach($activePallets as $row)
                        <tr>
                            <th scope="row">{{ $row['pallet_number'] }}</th>
                            <td class="dashboard-stock-number">{{ number_format($row['total_boxes']) }}</td>
                            <td class="dashboard-stock-number">{{ number_format($row['total_pcs']) }}</td>
                            <td>{{ $row['location'] }}</td>
                            <td>{{ $row['first_in'] ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
