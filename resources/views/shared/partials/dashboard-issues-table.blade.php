<section class="dashboard-stock dashboard-issues" id="scan-issues" aria-labelledby="dashboard-issues-title">
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
        <p class="dashboard-stock-hint" id="dashboard-issues-help">Alasan ditampilkan bersama kode issue untuk audit. Geser tabel untuk melihat semua kolom; Order tetap terlihat dan catatan admin ditampilkan lengkap.</p>
        <div class="dashboard-stock-scroll" tabindex="0" role="region" aria-labelledby="dashboard-issues-title" aria-describedby="dashboard-issues-help">
            <table class="table dashboard-stock-table dashboard-stock-table--issues mb-0">
                <caption class="visually-hidden">Maksimal 50 issue scan terbaru sesuai periode laporan</caption>
                <colgroup><col style="width:10%"><col style="width:21%"><col style="width:18%"><col style="width:12%"><col style="width:25%"><col style="width:14%"></colgroup>
                <thead><tr><th scope="col">Order</th><th scope="col">Kode scan</th><th scope="col">Alasan issue</th><th scope="col">Status</th><th scope="col">Catatan admin</th><th scope="col">Waktu</th></tr></thead>
                <tbody>
                    @foreach($issueList as $row)
                        @php
                            $statusLabel = match ($row['status']) {
                                'pending' => 'Menunggu',
                                'approved' => 'Disetujui',
                                'rejected' => 'Ditolak',
                                'resolved' => 'Selesai',
                                default => ucfirst(str_replace('_', ' ', $row['status'])),
                            };
                        @endphp
                        <tr>
                            <th scope="row">{{ $row['order_id'] !== null ? '#'.$row['order_id'] : '—' }}</th>
                            <td><code class="dashboard-issue-code">{{ $row['scanned_code'] }}</code></td>
                            <td><span class="dashboard-issue-reason"><strong>{{ $row['reason'] }}</strong><span>{{ $row['issue_type'] }}</span></span></td>
                            <td><span @class(['dashboard-issue-status', 'is-pending' => $row['status'] === 'pending', 'is-approved' => in_array($row['status'], ['approved', 'resolved'], true), 'is-rejected' => $row['status'] === 'rejected'])>{{ $statusLabel }}</span></td>
                            <td class="dashboard-issue-notes">{{ filled($row['notes']) ? $row['notes'] : 'Belum ada catatan' }}</td>
                            <td><span class="dashboard-issue-date"><strong>{{ \Illuminate\Support\Str::beforeLast($row['created_at'], ' ') }}</strong><span>{{ \Illuminate\Support\Str::afterLast($row['created_at'], ' ') }}</span></span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
