<section class="dashboard-stock dashboard-processing" id="scan-issues" aria-labelledby="dashboard-processing-title">
    <header class="dashboard-stock-header">
        <div>
            <h2 id="dashboard-processing-title">Processing Time <span>Lead Time</span></h2>
            <p>Durasi picking/delivery sesuai periode laporan, bukan scan saat input stok.</p>
        </div>
        <span class="dashboard-stock-count">{{ number_format($processingReport->count()) }} sesi ditampilkan</span>
    </header>
    <dl class="dashboard-processing-definitions">
        <div><dt>Durasi Proses</dt><dd>Mulai picking sampai selesai.</dd></div>
        <div><dt>Durasi Scan</dt><dd>Scan pertama sampai scan terakhir.</dd></div>
    </dl>
    @if($processingReport->isEmpty())
        <div class="dashboard-stock-empty"><strong>Tidak ada sesi untuk ditampilkan.</strong><p>Periksa periode laporan untuk melihat sesi picking/delivery.</p></div>
    @else
        <p class="dashboard-stock-hint" id="dashboard-processing-help">Durasi dalam menit. Tanda — berarti data belum tersedia. Geser tabel untuk melihat semua kolom; kolom Session tetap terlihat.</p>
        <div class="dashboard-stock-scroll" tabindex="0" role="region" aria-labelledby="dashboard-processing-title" aria-describedby="dashboard-processing-help">
            <table class="table dashboard-stock-table dashboard-stock-table--processing mb-0">
                <caption class="visually-hidden">Durasi proses dan scan per sesi picking/delivery dalam menit</caption>
                <colgroup><col style="width:12%"><col style="width:12%"><col style="width:22%"><col style="width:22%"><col style="width:16%"><col style="width:16%"></colgroup>
                <thead><tr><th scope="col">Session</th><th scope="col">Order</th><th scope="col">Started</th><th scope="col">Completed</th><th scope="col" class="dashboard-stock-number">Proses (menit)</th><th scope="col" class="dashboard-stock-number">Scan (menit)</th></tr></thead>
                <tbody>
                    @foreach($processingReport as $row)
                        <tr>
                            <th scope="row">{{ $row['session_id'] }}</th>
                            <td>#{{ $row['order_id'] }}</td>
                            <td>{{ $row['started_at'] ?? '—' }}</td>
                            <td>{{ $row['completed_at'] ?? '—' }}</td>
                            <td class="dashboard-stock-number">{{ $row['duration_min'] ?? '—' }}</td>
                            <td class="dashboard-stock-number">{{ $row['scan_duration_min'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
