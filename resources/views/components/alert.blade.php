@props([
    'type' => 'success', // success, error, warning, info
    'dismissible' => true,
    'message' => '',
    'icon' => null
])

@php
    $styles = [
        'success' => [
            'bg' => '#d1fae5',
            'color' => '#065f46',
            'border' => '#a7f3d0',
            'icon' => 'check-circle'
        ],
        'error' => [
            'bg' => '#fee2e2',
            'color' => '#991b1b',
            'border' => '#fecaca',
            'icon' => 'x-circle'
        ],
        'warning' => [
            'bg' => '#fef3c7',
            'color' => '#92400e',
            'border' => '#fde68a',
            'icon' => 'exclamation-triangle'
        ],
        'info' => [
            'bg' => '#dbeafe',
            'color' => '#1e40af',
            'border' => '#bfdbfe',
            'icon' => 'info-circle'
        ]
    ];
    
    $resolvedType = $type === 'danger' ? 'error' : $type;
    $style = $styles[$resolvedType] ?? $styles['info'];
    $resolvedIcon = $icon ?: $style['icon'];
@endphp

<div class="alert {{ $dismissible ? 'alert-dismissible' : '' }} fade show" role="{{ $resolvedType === 'error' ? 'alert' : 'status' }}"
     style="background-color: {{ $style['bg'] }}; 
            color: {{ $style['color'] }}; 
            border: 1px solid {{ $style['border'] }}; 
            border-radius: 8px;
            border-left: 4px solid {{ $style['color'] }};">
    <i class="bi {{ str_starts_with($resolvedIcon, 'bi-') ? $resolvedIcon : 'bi-'.$resolvedIcon }}" aria-hidden="true"></i>
    @if($message !== '' && $message !== null)
        {{ $message }}
    @else
        {{ $slot }}
    @endif
    @if($dismissible)
        <button type="button" class="btn-close" style="color: {{ $style['color'] }};" data-bs-dismiss="alert" aria-label="Tutup pesan"></button>
    @endif
</div>
