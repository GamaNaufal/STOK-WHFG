@php
    $largestDailyFlow = collect($throughputDays)->flatMap(fn ($day) => [$day['inbound_pcs'], $day['outbound_pcs']])->max() ?? 0;
    $activeDays = collect($throughputDays)->filter(fn ($day) => $day['inbound_pcs'] > 0 || $day['outbound_pcs'] > 0)->count();
@endphp
<section class="dashboard-stock dashboard-throughput" aria-labelledby="dashboard-throughput-title">
    <header class="dashboard-stock-header">
        <div>
            <h2 id="dashboard-throughput-title">Warehouse Throughput <span>Rincian harian</span></h2>
            <p>Bandingkan jumlah PCS masuk dan keluar pada setiap tanggal dalam periode laporan.</p>
        </div>
        <span class="dashboard-stock-count">{{ number_format(count($throughputDays)) }} hari ditampilkan · {{ number_format($activeDays) }} beraktivitas</span>
    </header>
    @if(count($throughputDays) === 0)
        <div class="dashboard-stock-empty"><strong>Tidak ada rincian harian untuk ditampilkan.</strong><p>Pilih periode laporan untuk melihat data inbound dan outbound.</p></div>
    @else
        <p class="dashboard-stock-hint" id="dashboard-throughput-help">Batang membandingkan volume dengan nilai harian terbesar pada periode ini; angka PCS adalah nilai tepatnya. Nilai 0 berarti tidak ada PCS pada arah tersebut. Geser tabel jika diperlukan; kolom Tanggal tetap terlihat.</p>
        <div class="dashboard-stock-scroll" tabindex="0" role="region" aria-labelledby="dashboard-throughput-title" aria-describedby="dashboard-throughput-help">
            <table class="table dashboard-stock-table dashboard-stock-table--throughput mb-0">
                <caption class="visually-hidden">Inbound dan outbound harian dalam PCS sesuai periode laporan</caption>
                <colgroup><col style="width:34%"><col style="width:33%"><col style="width:33%"></colgroup>
                <thead><tr><th scope="col">Tanggal</th><th scope="col" class="dashboard-stock-number">Inbound <span class="dashboard-throughput-unit">PCS masuk</span></th><th scope="col" class="dashboard-stock-number">Outbound <span class="dashboard-throughput-unit">PCS keluar</span></th></tr></thead>
                <tbody>
                    @foreach($throughputDays as $row)
                        <tr>
                            <th scope="row" class="dashboard-throughput-date">
                                {{ $row['date'] }}
                                @if($row['inbound_pcs'] === 0 && $row['outbound_pcs'] === 0)
                                    <span class="dashboard-throughput-quiet">Tidak ada pergerakan</span>
                                @endif
                            </th>
                            <td class="dashboard-stock-number">
                                <div class="dashboard-throughput-volume">
                                    <strong>{{ number_format($row['inbound_pcs']) }}</strong>
                                    <span class="dashboard-throughput-track" aria-hidden="true"><span style="width: {{ $largestDailyFlow > 0 ? round($row['inbound_pcs'] / $largestDailyFlow * 100, 1) : 0 }}%"></span></span>
                                </div>
                            </td>
                            <td class="dashboard-stock-number">
                                <div class="dashboard-throughput-volume dashboard-throughput-volume--outbound">
                                    <strong>{{ number_format($row['outbound_pcs']) }}</strong>
                                    <span class="dashboard-throughput-track" aria-hidden="true"><span style="width: {{ $largestDailyFlow > 0 ? round($row['outbound_pcs'] / $largestDailyFlow * 100, 1) : 0 }}%"></span></span>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
