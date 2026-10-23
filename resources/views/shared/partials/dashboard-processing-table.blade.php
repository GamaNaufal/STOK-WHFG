<section class="dashboard-stock dashboard-processing" id="processing-time" aria-labelledby="dashboard-processing-title">
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
        <p class="dashboard-stock-hint" id="dashboard-processing-help">Operator sesi adalah akun yang memulai picking. Durasi memakai format menit:detik (contoh 00:17); tanda — berarti data belum tersedia. Geser tabel untuk melihat semua kolom; kolom Sesi tetap terlihat.</p>
        <div class="dashboard-stock-scroll" tabindex="0" role="region" aria-labelledby="dashboard-processing-title" aria-describedby="dashboard-processing-help">
            <table class="table dashboard-stock-table dashboard-stock-table--processing mb-0">
                <caption class="visually-hidden">Durasi proses dan scan per sesi picking/delivery dalam format menit:detik</caption>
                <colgroup><col style="width:9%"><col style="width:8%"><col style="width:17%"><col style="width:15%"><col style="width:15.5%"><col style="width:15.5%"><col style="width:10%"><col style="width:10%"></colgroup>
                <thead><tr><th scope="col">Sesi</th><th scope="col">Order</th><th scope="col">Nama customer</th><th scope="col">Operator sesi</th><th scope="col">Mulai</th><th scope="col">Selesai</th><th scope="col" class="dashboard-stock-number">Proses <span class="dashboard-processing-unit">menit:detik</span></th><th scope="col" class="dashboard-stock-number">Scan <span class="dashboard-processing-unit">menit:detik</span></th></tr></thead>
                <tbody>
                    @php
                        $formatMinutesSeconds = static function ($minutes): string {
                            $seconds = (int) round(abs((float) $minutes) * 60);
                            return ($minutes < 0 ? '−' : '') . sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60);
                        };
                    @endphp
                    @foreach($processingReport as $row)
                        <tr>
                            <th scope="row" class="dashboard-processing-session">Sesi #{{ $row['session_id'] }}</th>
                            <td class="dashboard-processing-order">#{{ $row['order_id'] }}</td>
                            <td class="dashboard-processing-customer">{{ $row['customer_name'] ?: '—' }}</td>
                            <td class="dashboard-processing-operator">{{ $row['operator_name'] ?: '—' }}</td>
                            <td>
                                @if($row['started_at'])
                                    <span class="dashboard-processing-date"><strong>{{ \Illuminate\Support\Str::beforeLast($row['started_at'], ' ') }}</strong><span>{{ \Illuminate\Support\Str::afterLast($row['started_at'], ' ') }}</span></span>
                                @else
                                    <span class="dashboard-processing-unavailable" title="Data belum tersedia">—</span>
                                @endif
                            </td>
                            <td>
                                @if($row['completed_at'])
                                    <span class="dashboard-processing-date"><strong>{{ \Illuminate\Support\Str::beforeLast($row['completed_at'], ' ') }}</strong><span>{{ \Illuminate\Support\Str::afterLast($row['completed_at'], ' ') }}</span></span>
                                @else
                                    <span class="dashboard-processing-unavailable" title="Data belum tersedia">—</span>
                                @endif
                            </td>
                            <td class="dashboard-stock-number">
                                @if($row['duration_min'] !== null)
                                    <span class="dashboard-processing-duration">{{ $formatMinutesSeconds($row['duration_min']) }}</span>
                                @else
                                    <span class="dashboard-processing-unavailable" title="Data belum tersedia">—</span>
                                @endif
                            </td>
                            <td class="dashboard-stock-number">
                                @if($row['scan_duration_min'] !== null)
                                    <span class="dashboard-processing-duration dashboard-processing-duration--scan">{{ $formatMinutesSeconds($row['scan_duration_min']) }}</span>
                                @else
                                    <span class="dashboard-processing-unavailable" title="Data belum tersedia">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
