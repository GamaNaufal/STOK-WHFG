@php
    $duration=static function($minutes):string {
        $seconds=(int)round(abs((float)$minutes)*60);
        return ($minutes<0?'−':'').sprintf('%02d:%02d',intdiv($seconds,60),$seconds%60);
    };
@endphp
<section class="od-panel" id="processing-time">
    <div class="od-panel-header"><h2>Durasi proses pengiriman</h2><span class="od-muted">Berdasarkan waktu mulai pada periode ini</span></div>
    <div class="od-scroll" tabindex="0" role="region" aria-label="Durasi proses per lingkup">
        <table class="od-table"><thead><tr><th>Lingkup / rencana</th><th>Nama customer</th><th>Proses yang diukur</th><th>Mulai</th><th>Selesai</th><th>Proses (menit:detik)</th><th>Scan (menit:detik)</th><th>Petugas</th></tr></thead><tbody>
        @forelse($processing as $row)
            <tr><td><span class="od-scope">{{ $scopeLabel($row->scope) }}</span><small>{{ $row->reference ?? '#'.$row->record_id }}</small>@if($row->scope==='domestic')<small>Sesi #{{ $row->record_id }}</small>@endif</td>
                <td>{{ $row->customer_name ?? '—' }}</td><td>{{ $row->phase }}</td>
                <td class="od-nowrap">{{ $date($row->started_at,true) }}</td><td class="od-nowrap">{{ $date($row->completed_at,true) }}</td>
                <td class="od-nowrap">@if($row->duration_min!==null)<span class="dashboard-processing-duration">{{ $duration($row->duration_min) }}</span>@else<span title="Data belum tersedia">Belum selesai</span>@endif</td>
                <td class="od-nowrap">@if($row->scan_duration_min!==null){{ $duration($row->scan_duration_min) }}@else<span title="Data belum tersedia">Tidak diukur</span>@endif</td><td>{{ $row->operator_name ?? '—' }}</td>
            </tr>
        @empty<tr><td colspan="8" class="od-empty">Belum ada proses pada periode ini.</td></tr>@endforelse
        </tbody></table>
    </div>
    <p class="od-note">Maksimal 200 proses. Durasi ekspor dihitung dari finalisasi terakhir hingga permintaan selesai, terpisah dari durasi picking domestik. Scan ekspor tidak memiliki durasi sesi.</p>
</section>
