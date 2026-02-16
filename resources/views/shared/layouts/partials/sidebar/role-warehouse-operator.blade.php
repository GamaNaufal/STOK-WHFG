<div class="mt-3 mb-2 text-muted px-2" style="font-size: 0.75rem; font-weight: 600;">WAREHOUSE</div>

<li class="nav-item">
    <a class="nav-link {{ request()->routeIs('stock-input*') ? 'active' : '' }}"
       href="{{ route('stock-input.index') }}">
        <i class="bi bi-plus-circle"></i> Input Stok
    </a>
</li>
<li class="nav-item">
    <a class="nav-link {{ request()->routeIs('delivery.index') ? 'active' : '' }}"
       href="{{ route('delivery.index') }}">
        <i class="bi bi-truck"></i> Delivery
        @if(isset($pendingDeliveryCount) && $pendingDeliveryCount > 0)
            <span class="badge bg-danger rounded-pill ms-auto">{{ $pendingDeliveryCount }}</span>
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
    <a class="nav-link {{ request()->routeIs('merge-pallet*') ? 'active' : '' }}"
       href="{{ route('merge-pallet.index') }}">
        <i class="bi bi-box-seam"></i> Merge Palet
    </a>
</li>
<li class="nav-item">
    <a class="nav-link {{ request()->routeIs('stock-view*') ? 'active' : '' }}"
       href="{{ route('stock-view.index') }}">
        <i class="bi bi-eye"></i> Lihat Stok
    </a>
</li>