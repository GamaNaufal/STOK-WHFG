@php
    $fulfillmentFilterLabel = [
        'all' => 'Order Full',
        'full' => 'Order Full',
        'partial' => 'Order Partial',
    ][request('fulfillment_filter', 'all')] ?? 'Order Full';
    $summaryDetails = [
        ['label' => 'Active Boxes', 'value' => $currentHandling->count(), 'target' => 'active-box-popover', 'title' => 'Active Boxes (Top 5)', 'href' => '#current-handling', 'action' => 'Lihat detail active boxes'],
        ['label' => 'Active Pallets', 'value' => $activePallets->count(), 'target' => 'active-pallet-popover', 'title' => 'Active Pallets (Top 5)', 'href' => '#active-pallets', 'action' => 'Lihat detail active pallets'],
        ['label' => 'Scan Issues', 'value' => $issueSummary->sum('total'), 'target' => 'scan-issues-popover', 'title' => 'Scan Issues (Top 5)', 'href' => '#scan-issues', 'action' => 'Lihat detail scan issues'],
    ];
@endphp
<div class="dashboard-summary dashboard-summary--supervisor" aria-label="Ringkasan operasional">
    <dl class="dashboard-summary__inventory">
        <div class="dashboard-summary__linked" data-bs-toggle="popover" data-bs-html="true" data-bs-placement="left" data-popover-target="fulfillment-popover" data-popover-title="Fulfillment (Top 5)">
            <dt>Fulfillment Rate ({{ $fulfillmentFilterLabel }})</dt>
            <dd class="dashboard-summary__figure">{{ $fulfillmentRate }}%</dd>
            <dd class="dashboard-summary__description">Periode: {{ $rangeLabel }}</dd>
            <dd><a href="#fulfillment-report" class="dashboard-summary__link stretched-link" aria-label="Lihat detail fulfillment">Lihat detail fulfillment <i class="bi bi-arrow-right" aria-hidden="true"></i></a></dd>
        </div>
    </dl>
    <dl class="dashboard-summary__details">
        @foreach($summaryDetails as $metric)
            <div class="dashboard-summary__row dashboard-summary__linked" data-bs-toggle="popover" data-bs-html="true" data-bs-placement="left" data-popover-target="{{ $metric['target'] }}" data-popover-title="{{ $metric['title'] }}">
                <dt>{{ $metric['label'] }}</dt>
                <dd class="dashboard-summary__count">{{ $metric['value'] }}</dd>
                <dd class="dashboard-summary__description"><a href="{{ $metric['href'] }}" class="dashboard-summary__link stretched-link" aria-label="{{ $metric['action'] }}">{{ $metric['action'] }} <i class="bi bi-arrow-right" aria-hidden="true"></i></a></dd>
            </div>
        @endforeach
    </dl>
</div>
