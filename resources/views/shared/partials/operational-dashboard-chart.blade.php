@php
    $chartId='od-'.$domain['scope'].'-'.$chart['key'];
    $rows=$chart['rows'];
    $peak=collect($rows)->max(fn($row)=>max($row['first'],$row['second']))??0;
    $magnitude=pow(10,floor(log10(max(1,$peak))));
    $maximum=max(2,ceil(max(1,$peak)/$magnitude)*$magnitude);
    $count=count($rows); $plotLeft=64; $plotWidth=628; $plotBottom=222; $plotHeight=168;
    $coordinates=[]; $firstPoints=[]; $secondPoints=[];
    foreach($rows as $index=>$row) {
        $x=$count>1?$plotLeft+$index*$plotWidth/($count-1):$plotLeft+$plotWidth/2;
        $firstY=$plotBottom-$row['first']/$maximum*$plotHeight;
        $secondY=$plotBottom-$row['second']/$maximum*$plotHeight;
        $firstPoints[]=round($x,2).','.round($firstY,2); $secondPoints[]=round($x,2).','.round($secondY,2);
        $coordinates[]=$row+['x'=>round($x,2),'firstY'=>round($firstY,2),'secondY'=>round($secondY,2)];
    }
    $firstPath=$count?'M'.implode(' L',$firstPoints):''; $secondPath=$count?'M'.implode(' L',$secondPoints):'';
    $area=$count?$firstPath.' L'.$coordinates[$count-1]['x'].','.$plotBottom.' L'.$coordinates[0]['x'].','.$plotBottom.' Z':'';
    $peakRow=$peak?collect($rows)->first(fn($row)=>max($row['first'],$row['second'])===$peak):null;
    $labelIndices=$count>1?array_unique(array_map(fn($n)=>(int)round($n*($count-1)/4),range(0,4))):($count?[0]:[]);
@endphp
<article class="od-chart" data-analytics-chart="{{ $chart['key'] }}" data-chart-id="{{ $chartId }}" data-unit="{{ $domain['unit'] }}" data-first-label="{{ $chart['first_label'] }}" data-second-label="{{ $chart['second_label'] }}" aria-labelledby="{{ $chartId }}-title">
    <div class="od-chart-caption"><h4 id="{{ $chartId }}-title">{{ $chart['title'] }} <span>· {{ $domain['unit'] }}</span></h4><span class="od-chart-hint" data-chart-hint>Rincian angka di bawah</span></div>
    <div class="od-chart-readout"><span data-chart-date>{{ $peakRow?'Puncak · '.$peakRow['label']:'Belum ada aktivitas' }}</span><span class="od-readout-first" data-chart-first>{{ $peakRow?$chart['first_label'].' '.number_format($peakRow['first'],0,',','.').' '.$domain['unit']:'—' }}</span><span class="od-readout-second" data-chart-second>{{ $peakRow?$chart['second_label'].' '.number_format($peakRow['second'],0,',','.').' '.$domain['unit']:'' }}</span><button type="button" data-unpin-point class="od-unpin" hidden aria-label="Lepas titik yang dipilih"><i class="bi bi-pin-angle-fill" aria-hidden="true"></i></button></div>
    <svg class="od-plot" viewBox="0 0 720 268" role="img" aria-labelledby="{{ $chartId }}-title {{ $chartId }}-description" data-chart-plot>
        <title id="{{ $chartId }}-description">{{ $domain['label'] }}, {{ $domain['unit'] }}. Rincian angka tersedia pada tabel di bawah grafik.</title>
        <defs><linearGradient id="{{ $chartId }}-fill" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="currentColor" stop-opacity=".2"/><stop offset="100%" stop-color="currentColor" stop-opacity=".01"/></linearGradient></defs>
        @foreach([0,.5,1] as $tick)
            <line class="od-grid" x1="{{ $plotLeft }}" y1="{{ $plotBottom-$tick*$plotHeight }}" x2="692" y2="{{ $plotBottom-$tick*$plotHeight }}"/>
            <text class="od-axis-label" x="52" y="{{ $plotBottom-$tick*$plotHeight+5 }}" text-anchor="end">{{ \Illuminate\Support\Number::abbreviate($maximum*$tick,maxPrecision:1) }}</text>
        @endforeach
        @if($peak)
            <path class="od-series-area" data-series="first" d="{{ $area }}" fill="url(#{{ $chartId }}-fill)"/>
            <path class="od-series-line od-line-first" data-series="first" d="{{ $firstPath }}" pathLength="1"/>
            <path class="od-series-line od-line-second" data-series="second" d="{{ $secondPath }}" pathLength="1"/>
            @if($count===1)<circle data-series="first" class="od-point-first" cx="{{ $coordinates[0]['x'] }}" cy="{{ $coordinates[0]['firstY'] }}" r="4"/><circle data-series="second" class="od-point-second" cx="{{ $coordinates[0]['x'] }}" cy="{{ $coordinates[0]['secondY'] }}" r="4"/>@endif
        @else<text class="od-chart-empty" x="378" y="125" text-anchor="middle">Belum ada aktivitas pada periode ini.</text><text class="od-axis-label" x="378" y="151" text-anchor="middle">Coba periode lain untuk melihat pergerakan.</text>@endif
        @foreach($labelIndices as $index)<text class="od-axis-label {{ count($labelIndices)>3&&in_array($loop->index,[1,3])?'od-mobile-hide':'' }}" x="{{ $coordinates[$index]['x'] }}" y="252" text-anchor="{{ $index===0?'start':($index===$count-1?'end':'middle') }}">{{ $rows[$index]['axis_label']??$rows[$index]['label'] }}</text>@endforeach
        <g data-chart-cursor visibility="hidden" aria-hidden="true"><line class="od-cursor-line" y1="42" y2="222"/><circle class="od-point-first" data-series="first" r="5"/><circle class="od-point-second" data-series="second" r="5"/></g>
    </svg>
    <script type="application/json" data-chart-data>{!! json_encode($coordinates,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
    <div class="od-chart-footer"><div class="od-legend" role="group" aria-label="Seri {{ $domain['label'] }} {{ $chart['title'] }}"><button type="button" data-toggle-series="first" disabled aria-pressed="true"><span class="od-legend-dot"></span>{{ $chart['first_label'] }}</button><button type="button" data-toggle-series="second" disabled aria-pressed="true"><span class="od-legend-dot"></span>{{ $chart['second_label'] }}</button></div><span class="od-chart-unit">{{ $domain['unit'] }}</span></div>
    <details class="od-chart-details"><summary>Lihat rincian {{ strtolower($chart['title']) }} <i class="bi bi-chevron-down" aria-hidden="true"></i></summary><div class="od-scroll" tabindex="0" role="region" aria-label="Rincian {{ $domain['label'] }} {{ $chart['title'] }}"><table class="od-table"><thead><tr><th>Periode / jam</th><th>{{ $chart['first_label'] }} ({{ $domain['unit'] }})</th><th>{{ $chart['second_label'] }} ({{ $domain['unit'] }})</th></tr></thead><tbody>@forelse($rows as $row)<tr><td>{{ $row['label'] }}</td><td>{{ number_format($row['first'],0,',','.') }}</td><td>{{ number_format($row['second'],0,',','.') }}</td></tr>@empty<tr><td colspan="3">Tidak ada data.</td></tr>@endforelse</tbody></table></div></details>
</article>
