@php
    $mergeRoutes = app(\App\Services\NavigationContextService::class)->mergeRoutes(auth()->user());
@endphp

@if(count($mergeRoutes) === 2)
    <nav class="merge-scope-switch" data-scope="{{ $scope }}" aria-label="Lingkup merge pallet">
        <span class="merge-scope-thumb" aria-hidden="true"></span>
        @foreach(['domestic' => 'Domestik', 'export' => 'Ekspor'] as $key => $label)
            <a href="{{ route($mergeRoutes[$key]) }}" @if($scope === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
@endif
