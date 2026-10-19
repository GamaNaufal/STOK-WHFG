<section class="dashboard-stock dashboard-issues" aria-labelledby="dashboard-issues-title">
    <header class="dashboard-stock-header">
        <div>
            <h2 id="dashboard-issues-title">Scan Mismatch Report <span>Detail issue</span></h2>
            <p>Maksimal 50 issue terbaru sesuai periode laporan. Ringkasan mismatch dapat mencakup lebih banyak issue.</p>
        </div>
        <span class="dashboard-stock-count">{{ number_format($issueList->count()) }} issue ditampilkan</span>
    </header>
    @if($issueList->isEmpty())
        <div class="dashboard-stock-empty"><strong>Tidak ada issue pada periode ini.</strong><p>Pilih periode lain untuk melihat riwayat mismatch.</p></div>
    @else
        <p class="dashboard-stock-hint" id="dashboard-issues-help">Geser tabel untuk melihat semua kolom. Kolom Order tetap terlihat; catatan admin ditampilkan lengkap.</p>
        <div class="dashboard-stock-scroll" tabindex="0" role="region" aria-labelledby="dashboard-issues-title" aria-describedby="dashboard-issues-help">
            <table class="table dashboard-stock-table dashboard-stock-table--issues mb-0">
                <caption class="visually-hidden">Maksimal 50 issue scan terbaru sesuai periode laporan</caption>
                <colgroup><col style="width:10%"><col style="width:20%"><col style="width:15%"><col style="width:12%"><col style="width:27%"><col style="width:16%"></colgroup>
                <thead><tr><th scope="col">Order</th><th scope="col">Kode scan</th><th scope="col">Issue Type</th><th scope="col">Status</th><th scope="col">Keterangan Admin</th><th scope="col">Tanggal</th></tr></thead>
                <tbody>
                    @foreach($issueList as $row)
                        <tr>
                            <th scope="row">{{ $row['order_id'] !== null ? '#'.$row['order_id'] : '—' }}</th>
                            <td class="dashboard-issue-code">{{ $row['scanned_code'] }}</td>
                            <td>{{ $row['issue_type'] }}</td>
                            <td><span class="dashboard-issue-status">{{ $row['status'] }}</span></td>
                            <td class="dashboard-issue-notes">{{ $row['notes'] ?? '—' }}</td>
                            <td>{{ $row['created_at'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
