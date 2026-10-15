@props([
    'variant' => 'primary', // primary, secondary, success, danger, warning
    'size' => 'md', // sm, md, lg
    'icon' => null,
    'type' => 'button',
    'href' => null
])

@php
    $variants = [
        'primary' => ['bg' => '#0C7779', 'hover' => '#086365'],
        'secondary' => ['bg' => '#086365', 'hover' => '#0C7779'],
        'success' => ['bg' => '#15803d', 'hover' => '#166534'],
        'danger' => ['bg' => '#b91c1c', 'hover' => '#dc2626'],
        'warning' => ['bg' => '#fbbf24', 'hover' => '#f59e0b', 'text' => '#422006'],
        'light' => ['bg' => '#e5e7eb', 'hover' => '#d1d5db', 'text' => '#374151']
    ];
    
    $sizes = [
        'sm' => 'btn-sm',
        'md' => '',
        'lg' => 'btn-lg'
    ];
    
    $variantStyle = $variants[$variant] ?? $variants['primary'];
    $sizeClass = $sizes[$size] ?? '';
    $textColor = $variantStyle['text'] ?? 'white';
@endphp

@php
    $padding = $size === 'sm' ? '6px 12px' : ($size === 'lg' ? '10px 24px' : '8px 16px');
    $hoverBg = $variantStyle['hover'];
    $baseBg = $variantStyle['bg'];
@endphp

@if($href)
    <a href="{{ $href }}" 
       {{ $attributes->merge(['class' => 'btn app-button ' . $sizeClass]) }} 
       style="--button-bg: {{ $baseBg }}; --button-hover: {{ $hoverBg }}; color: {{ $textColor }}; border: none; padding: {{ $padding }}; border-radius: 8px; transition: background-color 150ms ease;">
        @if($icon)
            <i class="bi {{ str_starts_with($icon, 'bi-') ? $icon : 'bi-'.$icon }}"></i>
        @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" 
            {{ $attributes->merge(['class' => 'btn app-button ' . $sizeClass]) }}
            style="--button-bg: {{ $baseBg }}; --button-hover: {{ $hoverBg }}; color: {{ $textColor }}; border: none; padding: {{ $padding }}; border-radius: 8px; transition: background-color 150ms ease;">
        @if($icon)
            <i class="bi {{ str_starts_with($icon, 'bi-') ? $icon : 'bi-'.$icon }}"></i>
        @endif
        {{ $slot }}
    </button>
@endif
