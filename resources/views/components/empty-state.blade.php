@props([
    'icon' => 'inbox',
    'title' => 'Belum ada data',
    'message' => '',
    'action' => null
])

<div class="text-center app-empty-state">
    <i class="bi {{ str_starts_with($icon, 'bi-') ? $icon : 'bi-'.$icon }}" aria-hidden="true"></i>
    <h5 class="mt-3 text-muted">{{ $title }}</h5>
    @if($message)
        <p class="text-muted small">{{ $message }}</p>
    @endif
    @if($action)
        <div class="mt-3">
            {{ $action }}
        </div>
    @endif
</div>
