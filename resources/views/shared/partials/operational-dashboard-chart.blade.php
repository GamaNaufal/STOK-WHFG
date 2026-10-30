@php
    $chartId = 'od-'.$domain['scope'].'-'.$chart['key'];
    $maximum = max(2, ceil((collect($chart['rows'])->max(fn($row)=>max($row['first'],$row['second'])) ?? 0)/2)*2);
    $count = max(1,count($chart['rows'])); $step = 440/$count; $barWidth = min(15,$step*.35);
    $labelStep = max(1,(int)ceil($count/5));
@endphp
<article class="od-panel od-chart" aria-labelledby="{{ $chartId }}-title">
    <h3 id="{{ $chartId }}-title">{{ $chart['title'] }} · {{ $domain['unit'] }}</h3>
    <svg viewBox="0 0 560 230" role="img" aria-labelledby="{{ $chartId }}-title {{ $chartId }}-description">
        <title id="{{ $chartId }}-description">{{ $domain['label'] }}, {{ $domain['unit'] }}. Rincian angka tersedia pada tabel di bawah grafik.</title>
        @foreach([0, .5, 1] as $tick)
            <line class="od-grid" x1="90" y1="{{ 185-$tick*155 }}" x2="545" y2="{{ 185-$tick*155 }}"/>
            <text class="od-axis-label" x="82" y="{{ 191-$tick*155 }}" text-anchor="end">{{ number_format($maximum*$tick,0,',','.') }}</text>
        @endforeach
        @foreach($chart['rows'] as $row)
            @php($x = 95+$loop->index*$step+$step/2)
            <rect class="od-first" x="{{ $x-$barWidth }}" y="{{ 185-$row['first']/$maximum*155 }}" width="{{ $barWidth }}" height="{{ $row['first']/$maximum*155 }}"><title>{{ $row['label'] }} · {{ $chart['first_label'] }}: {{ number_format($row['first'],0,',','.') }} {{ $domain['unit'] }}</title></rect>
            <rect class="od-second" x="{{ $x }}" y="{{ 185-$row['second']/$maximum*155 }}" width="{{ $barWidth }}" height="{{ $row['second']/$maximum*155 }}"><title>{{ $row['label'] }} · {{ $chart['second_label'] }}: {{ number_format($row['second'],0,',','.') }} {{ $domain['unit'] }}</title></rect>
            @if($loop->index%$labelStep===0)<text class="od-axis-label" x="{{ $x }}" y="218" text-anchor="middle">{{ $row['axis_label'] ?? $row['label'] }}</text>@endif
        @endforeach
        @unless(count($chart['rows']))<text class="od-axis-label" x="300" y="100" text-anchor="middle">Belum ada aktivitas pada periode ini.</text>@endunless
    </svg>
    <div class="od-legend"><span>{{ $chart['first_label'] }}</span><span>{{ $chart['second_label'] }}</span></div>
    <details><summary>Lihat rincian {{ strtolower($chart['title']) }}</summary><div class="od-scroll" tabindex="0" role="region" aria-label="Rincian {{ $domain['label'] }} {{ $chart['title'] }}">
        <table class="od-table"><thead><tr><th>Periode / jam</th><th>{{ $chart['first_label'] }} ({{ $domain['unit'] }})</th><th>{{ $chart['second_label'] }} ({{ $domain['unit'] }})</th></tr></thead><tbody>
            @forelse($chart['rows'] as $row)<tr><td>{{ $row['label'] }}</td><td>{{ number_format($row['first'],0,',','.') }}</td><td>{{ number_format($row['second'],0,',','.') }}</td></tr>@empty<tr><td colspan="3">Tidak ada data.</td></tr>@endforelse
        </tbody></table>
    </div></details>
</article>
