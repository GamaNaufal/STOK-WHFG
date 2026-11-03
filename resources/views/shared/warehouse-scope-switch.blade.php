<nav class="warehouse-scope-switch" data-scope="{{ $scope }}" aria-label="{{ $scopeLabel }}">
    <span class="warehouse-scope-thumb" aria-hidden="true"></span>
    @foreach(['domestic' => 'Domestik', 'export' => 'Ekspor'] as $key => $label)
        <a href="{{ $scopeUrls[$key] }}" data-warehouse-scope="{{ $key }}" @if($scope === $key) aria-current="page" @endif>
            <span>{{ $label }}</span>
            @if($key === 'export' && isset($exportPending))
                <span class="warehouse-scope-count" title="{{ $exportPending }} wajib dipenuhi">{{ $exportPending }}<span class="visually-hidden"> wajib dipenuhi</span></span>
            @endif
        </a>
    @endforeach
</nav>
<script src="{{ asset('js/pages/warehouse-scope-switch.js') }}?v={{ filemtime(public_path('js/pages/warehouse-scope-switch.js')) }}" defer></script>
