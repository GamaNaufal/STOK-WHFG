@props([
    'title' => '',
    'icon' => 'box',
    'borderColor' => '#0C7779',
    'headerColor' => '#0C7779',
    'noPadding' => false
])

<div class="card shadow-sm border-0" style="border-left: 4px solid {{ $borderColor }}; border-radius: 12px; overflow: hidden;">
    @if($title)
        <div class="card-header text-white {{ in_array(strtolower($headerColor), ['#0c7779', '#249e94', '#005461']) ? 'app-section-heading' : '' }}" style="background-color: {{ $headerColor }}; border: none;">
            <i class="bi {{ str_starts_with($icon, 'bi-') ? $icon : 'bi-'.$icon }}"></i> {{ $title }}
        </div>
    @endif
    <div class="card-body {{ $noPadding ? 'p-0' : 'p-4' }}">
        {{ $slot }}
    </div>
</div>
