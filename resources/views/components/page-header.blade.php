@props([
    'title' => '',
    'subtitle' => '',
    'icon' => 'house',
    'action' => null
])

<div class="row mb-4">
    <div class="col-12">
        <div class="app-page-hero">
            <div class="hero-content">
                <div>
                    <h1 class="h2 hero-title">
                        <i class="bi {{ str_starts_with($icon, 'bi-') ? $icon : 'bi-'.$icon }}"></i> {{ $title }}
                    </h1>
                    <p class="hero-subtitle">
                        {{ $subtitle }}
                    </p>
                </div>
                @if($action || $slot->isNotEmpty())
                    <div>
                        {{ $action ?? $slot }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
