<div class="mt-3 mb-2 text-muted px-2" style="font-size: 0.75rem; font-weight: 600;">PPC</div>

<li class="nav-item">
    <a class="nav-link {{ request()->routeIs('delivery.approvals') ? 'active' : '' }}"
       href="{{ route('delivery.approvals') }}">
        <i class="bi bi-clipboard-check"></i> Pending Approval
        @if(isset($pendingDeliveryCount) && $pendingDeliveryCount > 0)
            <span class="badge bg-danger rounded-pill ms-auto">{{ $pendingDeliveryCount }}</span>
        @endif
    </a>
</li>
<li class="nav-item">
    <a class="nav-link {{ request()->routeIs('delivery.index') ? 'active' : '' }}"
       href="{{ route('delivery.index') }}">
        <i class="bi bi-truck"></i> Delivery Schedule
    </a>
</li>
<li class="nav-item">
    <a class="nav-link {{ request()->routeIs('stock-view*') ? 'active' : '' }}"
       href="{{ route('stock-view.index') }}">
        <i class="bi bi-eye"></i> Lihat Stok
    </a>
</li>