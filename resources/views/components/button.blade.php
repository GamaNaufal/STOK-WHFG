@props([
    'variant' => 'primary', // primary, secondary, success, danger, warning
    'size' => 'md', // sm, md, lg
    'icon' => null,
    'type' => 'button',
    'href' => null
])

@php
    $variants = [
        'primary' => ['bg' => '#0C7779', 'hover' => '#249E94'],
        'secondary' => ['bg' => '#249E94', 'hover' => '#0C7779'],
        'success' => ['bg' => '#10b981', 'hover' => '#059669'],
        'danger' => ['bg' => '#ef4444', 'hover' => '#dc2626'],
        'warning' => ['bg' => '#f59e0b', 'hover' => '#d97706'],
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

@if($href)
    <a href="{{ $href }}" 
       class="btn {{ $sizeClass }}" 
       style="background-color: {{ $variantStyle['bg'] }}; 
              color: {{ $textColor }}; 
              border: none; 
              padding: {{ $size === 'sm' ? '6px 12px' : ($size === 'lg' ? '10px 24px' : '8px 16px') }}; 
              border-radius: 8px; 
              transition: all 0.3s ease;"
       onmouseover="this.style.backgroundColor='{{ $variantStyle['hover'] }}'"
       onmouseout="this.style.backgroundColor='{{ $variantStyle['bg'] }}'">
        @if($icon)
            <i class="bi bi-{{ $icon }}"></i>
        @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" 
            {{ $attributes->merge(['class' => 'btn ' . $sizeClass]) }}
            style="background-color: {{ $variantStyle['bg'] }}; 
                   color: {{ $textColor }}; 
                   border: none; 
                   padding: {{ $size === 'sm' ? '6px 12px' : ($size === 'lg' ? '10px 24px' : '8px 16px') }}; 
                   border-radius: 8px; 
                   transition: all 0.3s ease;"
            onmouseover="this.style.backgroundColor='{{ $variantStyle['hover'] }}'"
            onmouseout="this.style.backgroundColor='{{ $variantStyle['bg'] }}'">
        @if($icon)
            <i class="bi bi-{{ $icon }}"></i>
        @endif
        {{ $slot }}
    </button>
@endif
