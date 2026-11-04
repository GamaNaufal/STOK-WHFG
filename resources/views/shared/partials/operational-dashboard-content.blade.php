@php
    $scopeLabel=fn($scope)=>\App\Services\OperationalDashboardService::SCOPES[$scope]??'Umum';
    $date=fn($value,$time=false)=>$value?\Carbon\Carbon::parse($value)->format($time?'d/m/Y H:i':'d/m/Y'):'—';
    $palletName=fn($row)=>$row->scope==='export'?($row->pallet_number?'Pallet '.$row->pallet_number:($row->pallet_id?'Pallet cadangan':'Belum ditempatkan')):($row->pallet_number??'—');
    $issueReason=fn($row)=>$row->scope==='domestic'?(new \App\Models\DeliveryIssue(['issue_type'=>$row->issue_type]))->reason:(new \App\Models\ExportPickingIssue(['issue_type'=>$row->issue_type]))->reason;
    $mainKeys=['scope','period','start_date','end_date','group_by','fulfillment_filter'];
    $auditKeys=['audit_type','audit_action','audit_user'];
    $activeAuditCount=collect($auditKeys)->filter(fn($key)=>filled($filters[$key]))->count();
    $auditUserName=$auditOptions['users']->firstWhere('user_id',(int)$filters['audit_user'])?->user_name??'User #'.$filters['audit_user'];
    $totalStock=(int)collect($stockTotals)->sum('boxes');
    $totalPallets=(int)collect($stockTotals)->sum('pallets');
    $unplaced=(int)collect($stockTotals)->sum('unplaced');
    $remainingOrders=max(0,(int)$orderSummary->total-(int)$orderSummary->full);
    $dashboardUrl=fn($changes=[])=>route('dashboard',array_filter(array_merge(\Illuminate\Support\Arr::except($filters,['audit_page']),$changes),fn($value)=>$value!==''));
    $customRange=filled($filters['start_date'])||filled($filters['end_date']);
    $detailTabs=['orders'=>['Pemenuhan','fulfillment-report','bi-truck'], 'stock'=>['Stok box','current-handling','bi-box-seam'], 'pallets'=>['Pallet','active-pallets','bi-grid'], 'processing'=>['Durasi proses','processing-time','bi-stopwatch'], 'issues'=>['Issue scan','scan-issues','bi-exclamation-circle'], 'audit'=>['Audit aktivitas','od-audit','bi-clock-history']];
@endphp
<div class="operational-dashboard" data-analytics-dashboard>
    @if($errors->any())<div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <header class="od-header">
        <div><p class="od-eyebrow"><span class="od-brand-mark" aria-hidden="true"><i class="bi bi-bar-chart-line"></i></span> WAREHOUSE ANALYTICS</p><h1>Setiap pergerakan, berarti.</h1><p class="od-subtitle">Dashboard analitik · {{ $scopeLabel($filters['scope']) }} · {{ $rangeLabel }}</p></div>
        <div class="od-header-actions">
            <button type="button" class="od-motion-toggle" data-motion-toggle hidden aria-pressed="true"><i class="bi bi-play-circle" aria-hidden="true"></i><span data-motion-label>Animasi aktif</span></button>
            <a class="od-icon-button" href="{{ $dashboardUrl() }}" data-report-reset aria-label="Perbarui dashboard" title="Perbarui dashboard"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i></a>
            @if(auth()->user()->canAccess('reports'))<a class="btn btn-dark od-export" data-report-export href="{{ route('reports.operational.export',\Illuminate\Support\Arr::except($filters,['audit_page'])) }}"><i class="bi bi-download" aria-hidden="true"></i> Export Excel</a>@endif
        </div>
    </header>
    <section class="od-toolbar" aria-label="Filter dashboard">
        <div class="od-toolbar-row">
            <nav class="od-segments" aria-label="Lingkup analitik">@foreach(\App\Services\OperationalDashboardService::SCOPES as $key=>$label)<a href="{{ $dashboardUrl(['scope'=>$key]) }}" data-report-reset @if($filters['scope']===$key) aria-current="true" @endif><i class="bi {{ ['all'=>'bi-layers','domestic'=>'bi-building','export'=>'bi-globe2'][$key] }}" aria-hidden="true"></i>{{ $label }}</a>@endforeach</nav>
            <nav class="od-periods" aria-label="Periode cepat">@foreach(['today'=>'Hari ini','week'=>'Minggu ini','month'=>'Bulan ini','all'=>'Semua waktu'] as $key=>$label)<a href="{{ $dashboardUrl(['period'=>$key,'start_date'=>'','end_date'=>'']) }}" data-report-reset @if(!$customRange&&$filters['period']===$key) aria-current="true" @endif>{{ $label }}</a>@endforeach</nav>
        </div>
        <details class="od-advanced" data-advanced-filters @if($customRange||$filters['fulfillment_filter']!=='all') open @endif>
            <summary><span><i class="bi bi-sliders2" aria-hidden="true"></i> Sesuaikan analisis @if($customRange||$filters['fulfillment_filter']!=='all')<span class="od-filter-dot" aria-label="Filter khusus aktif"></span>@endif</span><span class="od-muted">{{ $start?$date($start):'Awal data' }} — {{ $end?$date($end):'Saat ini' }} <i class="bi bi-chevron-down" aria-hidden="true"></i></span></summary>
            <form id="dashboard-main-filters" method="GET" action="{{ route('dashboard') }}" data-report-filter-form aria-label="Filter lanjutan dashboard">
                @foreach([...$auditKeys,'scope','period'] as $key)<input type="hidden" name="{{ $key }}" value="{{ $filters[$key] }}">@endforeach
                <div class="od-filters">
                    <div><label for="dashboard-start">Tanggal mulai</label><input id="dashboard-start" type="date" name="start_date" class="form-control" value="{{ $filters['start_date'] }}"></div>
                    <div><label for="dashboard-end">Tanggal akhir</label><input id="dashboard-end" type="date" name="end_date" class="form-control" value="{{ $filters['end_date'] }}"></div>
                    <div><label for="dashboard-group">Kelompokkan grafik</label><select id="dashboard-group" name="group_by" class="form-select">@foreach(['day'=>'Harian','week'=>'Mingguan','month'=>'Bulanan'] as $key=>$label)<option value="{{ $key }}" @selected($filters['group_by']===$key)>{{ $label }}</option>@endforeach</select></div>
                    <div><label for="dashboard-fulfillment">Status pemenuhan</label><select id="dashboard-fulfillment" name="fulfillment_filter" class="form-select">@foreach(['all'=>'Semua','full'=>'Penuh','partial'=>'Belum penuh'] as $key=>$label)<option value="{{ $key }}" @selected($filters['fulfillment_filter']===$key)>{{ $label }}</option>@endforeach</select></div>
                </div>
                <div class="od-filter-actions"><button class="btn btn-primary" type="submit">Terapkan</button><a class="od-text-link" data-report-reset href="{{ route('dashboard',['scope'=>'all','period'=>'week']) }}">Reset filter</a><span class="od-muted">Tanggal pilihan menggantikan periode cepat. Status menyaring ringkasan dan rincian pemenuhan.</span></div>
            </form>
        </details>
    </section>
    <dl class="od-summary" aria-label="Ringkasan operasional">
        <div class="od-kpi od-kpi-stock"><dt><i class="bi bi-box-seam" aria-hidden="true"></i> Box dalam stok saat ini</dt><dd class="od-number"><span data-count="{{ $totalStock }}">{{ number_format($totalStock,0,',','.') }}</span><span class="od-unit">box</span></dd><dd class="od-kpi-context">@foreach($stockTotals as $scope=>$total)<span><span class="od-dot od-dot-{{ $scope }}"></span>{{ $scopeLabel($scope) }} <strong>{{ number_format($total->boxes,0,',','.') }}</strong></span>@endforeach</dd><dd class="od-kpi-footer">Seluruh stok, tidak mengikuti periode.</dd></div>
        <div class="od-kpi"><dt><i class="bi bi-grid" aria-hidden="true"></i> Pallet berisi stok</dt><dd class="od-number"><span data-count="{{ $totalPallets }}">{{ number_format($totalPallets,0,',','.') }}</span><span class="od-unit">pallet</span></dd><dd class="od-kpi-context">@foreach($stockTotals as $scope=>$total)<span>{{ $scopeLabel($scope) }} <strong>{{ number_format($total->pallets,0,',','.') }}</strong></span>@endforeach</dd><dd class="od-kpi-footer">{{ number_format($unplaced,0,',','.') }} box belum memiliki penempatan.</dd></div>
        <div class="od-kpi od-kpi-fulfillment"><dt><i class="bi bi-check2-circle" aria-hidden="true"></i> Permintaan terpenuhi penuh</dt><dd class="od-number"><span data-count="{{ $fulfillmentRate }}" data-decimals="1">{{ number_format($fulfillmentRate,1,',','.') }}</span><span class="od-unit">%</span><svg class="od-mini-ring" viewBox="0 0 44 44" aria-hidden="true"><circle cx="22" cy="22" r="18"/><circle class="od-ring-value" cx="22" cy="22" r="18" pathLength="100" stroke-dasharray="{{ $fulfillmentRate }} 100"/></svg></dd><dd class="od-kpi-context">{{ $orderSummary->full }} dari {{ $orderSummary->total }} permintaan sesuai periode/status.</dd><dd class="od-kpi-footer">Reservasi belum dihitung sebagai terpenuhi.</dd></div>
        <div class="od-kpi {{ $issueSummary->open?'od-kpi-warning':'' }}"><dt><i class="bi bi-shield-exclamation" aria-hidden="true"></i> Issue scan masih terbuka</dt><dd class="od-number"><span data-count="{{ $issueSummary->open }}">{{ number_format($issueSummary->open,0,',','.') }}</span><span class="od-unit">issue</span></dd><dd class="od-kpi-context">{{ $issueSummary->total }} issue scan pada periode ini.</dd><dd class="od-kpi-footer">Termasuk histori salah input yang sudah ditutup.</dd></div>
    </dl>
    <section class="od-analytics" aria-labelledby="od-trends-title">
        <div class="od-section-heading"><div><p class="od-eyebrow">BACA POLANYA</p><h2 id="od-trends-title">Dinamika gudang</h2><p class="od-muted">Lokal dalam PCS. Ekspor dalam box. Setiap lingkup punya skalanya sendiri.</p></div><div class="od-metric-switch" role="group" aria-label="Tampilan grafik" hidden><button type="button" data-chart-view="flow" aria-pressed="true"><i class="bi bi-activity" aria-hidden="true"></i> Arus stok</button><button type="button" data-chart-view="fulfillment" aria-pressed="false">Pemenuhan</button><button type="button" data-chart-view="hours" aria-pressed="false">Per jam</button></div></div>
        <div class="od-domain-grid {{ count($domains)===1?'od-domain-grid-single':'' }}">
        @foreach($domains as $domain)
            <section class="od-domain od-domain-{{ $domain['scope'] }}" aria-label="Grafik {{ $domain['label'] }}" data-domain="{{ $domain['scope'] }}">
                <div class="od-domain-title"><span class="od-domain-icon"><i class="bi {{ $domain['scope']==='export'?'bi-globe2':'bi-building' }}" aria-hidden="true"></i></span><div><h3>{{ $domain['label'] }}</h3><span>{{ $domain['unit'] }} · {{ ['day'=>'Harian','week'=>'Mingguan','month'=>'Bulanan'][$filters['group_by']] }}</span></div><span class="od-scope-badge">{{ $domain['scope']==='export'?'EXPORT':'LOKAL' }}</span></div>
                <div class="od-flow-totals"><div><span><i class="bi bi-arrow-down-left" aria-hidden="true"></i> Masuk</span><strong>{{ number_format($domain['unit']==='box'?$domain['inbound']->boxes:$domain['inbound']->pcs,0,',','.') }} <small>{{ $domain['unit'] }}</small></strong></div><div><span><i class="bi bi-arrow-up-right" aria-hidden="true"></i> Keluar</span><strong>{{ number_format($domain['unit']==='box'?$domain['outbound']->boxes:$domain['outbound']->pcs,0,',','.') }} <small>{{ $domain['unit'] }}</small></strong></div></div>
                @foreach($domain['charts'] as $chart)@include('shared.partials.operational-dashboard-chart')@endforeach
                <details class="od-domain-note"><summary>Dasar perhitungan <i class="bi bi-info-circle" aria-hidden="true"></i></summary><p>@if($domain['scope']==='export')Pemenuhan berasal dari penempatan yang dikonfirmasi. Pallet siap belum dihitung keluar; pengiriman baru tercatat setelah permintaan diselesaikan. Pemecahan Not Full dicatat sebagai penambahan kemasan internal.@else Pemenuhan mengikuti realisasi order lokal. Arus keluar berasal dari pengambilan stok yang selesai.@endif Masuk: {{ number_format($domain['inbound']->boxes,0,',','.') }} box / {{ number_format($domain['inbound']->pcs,0,',','.') }} PCS. Keluar: {{ number_format($domain['outbound']->boxes,0,',','.') }} box / {{ number_format($domain['outbound']->pcs,0,',','.') }} PCS.</p></details>
            </section>
        @endforeach
        </div>
        <p class="visually-hidden" role="status" data-chart-announcement></p>
    </section>
    <div class="od-insights">
        <section class="od-panel od-stock-composition" aria-labelledby="od-composition-title"><div class="od-panel-header"><div><p class="od-eyebrow">POSISI TERKINI</p><h2 id="od-composition-title">Komposisi stok</h2></div><span class="od-period-tag">Tidak mengikuti periode</span></div><div class="od-body">
            <div class="od-stock-bar" role="img" aria-label="Komposisi {{ number_format($totalStock) }} box stok saat ini">@foreach($stockTotals as $scope=>$total)<span class="od-stock-segment od-stock-{{ $scope }}" style="--share:{{ $totalStock?$total->boxes/$totalStock*100:0 }}%" title="{{ $scopeLabel($scope) }}: {{ $total->boxes }} box"></span>@endforeach</div>
            @foreach($stockTotals as $scope=>$total)<div class="od-composition-row"><span><span class="od-dot od-dot-{{ $scope }}"></span>{{ $scopeLabel($scope) }}</span><strong>{{ number_format($total->boxes,0,',','.') }} <small>box</small></strong><span class="od-muted">{{ number_format($total->pcs,0,',','.') }} PCS · {{ $total->parts }} part</span></div>@endforeach
            @if(!$totalStock)<p class="od-muted mb-0">Belum ada stok aktif. Arus barang akan terlihat setelah penerimaan.</p>@endif
        </div></section>
        <section class="od-panel od-attention" aria-labelledby="od-attention-title"><div class="od-panel-header"><div><p class="od-eyebrow">FOKUS BERIKUTNYA</p><h2 id="od-attention-title">Perlu dipantau</h2></div><i class="bi bi-crosshair" aria-hidden="true"></i></div><div class="od-attention-list">
            <a href="#fulfillment-report" data-open-detail="orders"><span class="od-attention-icon"><i class="bi bi-truck" aria-hidden="true"></i></span><span><strong>{{ $remainingOrders }} permintaan belum penuh</strong><small>Sesuai periode dan status pilihan</small></span><i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
            <a href="#current-handling" data-open-detail="stock"><span class="od-attention-icon"><i class="bi bi-box-arrow-in-down" aria-hidden="true"></i></span><span><strong>{{ number_format($unplaced,0,',','.') }} box menunggu penempatan</strong><small>Posisi stok saat ini</small></span><i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
            <a href="#scan-issues" data-open-detail="issues"><span class="od-attention-icon {{ $issueSummary->open?'is-warning':'' }}"><i class="bi bi-shield-exclamation" aria-hidden="true"></i></span><span><strong>{{ $issueSummary->open }} issue scan terbuka</strong><small>Dari issue yang dibuat pada periode ini</small></span><i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
        </div></section>
    </div>
    <section class="od-details" aria-labelledby="od-details-title" data-analytics-details>
        <div class="od-section-heading"><div><p class="od-eyebrow">TELUSURI LEBIH DALAM</p><h2 id="od-details-title">Di balik angkanya</h2><p class="od-muted">Rincian operasional tetap tersedia saat dibutuhkan.</p></div><span class="od-updated"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> Data per {{ now()->format('d/m/Y H:i') }}</span></div>
        <nav class="od-detail-tabs" aria-label="Rincian dashboard">@foreach($detailTabs as $key=>$tab)<a id="od-tab-{{ $key }}" href="#{{ $tab[1] }}" data-detail-tab="{{ $key }}"><i class="bi {{ $tab[2] }}" aria-hidden="true"></i>{{ $tab[0] }}</a>@endforeach</nav>
        @include('shared.partials.operational-dashboard-details')
    </section>
</div>
