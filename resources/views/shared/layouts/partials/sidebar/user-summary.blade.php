<hr class="my-3" style="border-color: rgba(255,255,255,0.2);">
<div class="px-2">
    <small class="text-muted">Logged in as:</small>
    <p class="mb-3">
        <strong>{{ auth()->user()->name }}</strong>
        <br>
        <span class="badge bg-info">{{ ucfirst(str_replace('_', ' ', auth()->user()->role)) }}</span>
    </p>
</div>