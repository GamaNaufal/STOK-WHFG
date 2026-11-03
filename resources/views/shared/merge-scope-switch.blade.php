@php
    $mergeRoutes = app(\App\Services\NavigationContextService::class)->mergeRoutes(auth()->user());
@endphp

@if(count($mergeRoutes) === 2)
    @include('shared.warehouse-scope-switch', [
        'scopeLabel' => 'Lingkup merge pallet',
        'scopeUrls' => array_map(fn ($name) => route($name), $mergeRoutes),
    ])
@endif
