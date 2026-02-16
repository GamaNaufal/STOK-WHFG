<div class="mt-3 mb-2 text-muted px-2" style="font-size: 0.75rem; font-weight: 600;">ADMIN WAREHOUSE</div>

<li class="nav-item">
    <a class="nav-link {{ request()->routeIs('locations*') ? 'active' : '' }}"
       href="{{ route('locations.index') }}">
        <i class="bi bi-geo-alt"></i> Kelola Lokasi
    </a>
</li>
<li class="nav-item">
    <a class="nav-link {{ request()->routeIs('box-not-full.create') ? 'active' : '' }}"
       href="{{ route('box-not-full.create') }}">
        <i class="bi bi-exclamation-circle"></i> Box Not Full
    </a>
</li>
<li class="nav-item">
    <a class="nav-link {{ request()->routeIs('delivery.pick.issues') ? 'active' : '' }}"
       href="{{ route('delivery.pick.issues') }}">
        <i class="bi bi-bell"></i> Scan Issues
        @if(isset($pendingScanIssueCount) && $pendingScanIssueCount > 0)
            <span class="badge bg-danger rounded-pill ms-auto">{{ $pendingScanIssueCount }}</span>
        @endif
    </a>
</li>
<li class="nav-item">
    <a class="nav-link {{ request()->routeIs('delivery.pick.verification') ? 'active' : '' }}"
       href="{{ route('delivery.pick.verification') }}">
        <i class="bi bi-upc-scan"></i> Picking Verification
    </a>
</li>
<li class="nav-item">
    <a class="nav-link {{ request()->routeIs('part-settings*') ? 'active' : '' }}"
       href="{{ route('part-settings.index') }}">
        <i class="bi bi-list-check"></i> Master No Part
    </a>
</li>
<li class="nav-item">
    <a class="nav-link {{ request()->routeIs('stock-view*') ? 'active' : '' }}"
       href="{{ route('stock-view.index') }}">
        <i class="bi bi-eye"></i> Lihat Stok
    </a>
</li>