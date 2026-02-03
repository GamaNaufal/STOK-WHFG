@props([
    'icon' => 'inbox',
    'title' => 'Belum ada data',
    'message' => '',
    'action' => null
])

<div class="text-center py-5">
    <i class="bi bi-{{ $icon }}" style="font-size: 3rem; color: #ccc;"></i>
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
