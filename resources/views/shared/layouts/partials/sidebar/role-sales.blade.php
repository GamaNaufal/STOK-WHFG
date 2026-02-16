<div class="mt-3 mb-2 text-muted px-2" style="font-size: 0.75rem; font-weight: 600;">SALES</div>

<li class="nav-item">
    <a class="nav-link {{ request()->routeIs('delivery.create') ? 'active' : '' }}"
       href="{{ route('delivery.create') }}">
        <i class="bi bi-cart-plus"></i> Sales Input
    </a>
</li>