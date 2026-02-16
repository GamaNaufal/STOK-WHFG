<!-- Sidebar (Offcanvas on Mobile) -->
<nav class="col-md-2 sidebar offcanvas offcanvas-start offcanvas-lg" tabindex="-1" id="mobileSidebar">
    <div class="offcanvas-header d-lg-none">
        <h5 class="offcanvas-title"><i class="bi bi-box2"></i> Warehouse FG</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0">
        <div class="position-sticky pt-3">
            @include('shared.layouts.partials.sidebar.brand-header')

            <ul class="nav flex-column px-2">
            @if(auth()->check())
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"
                       href="{{ route('dashboard') }}">
                        <i class="bi bi-house"></i> Dashboard
                    </a>
                </li>

                @php($role = auth()->user()->role)

                @if($role === 'warehouse_operator')
                    @include('shared.layouts.partials.sidebar.role-warehouse-operator')
                @endif

                @if($role === 'sales')
                    @include('shared.layouts.partials.sidebar.role-sales')
                @endif

                @if($role === 'ppc')
                    @include('shared.layouts.partials.sidebar.role-ppc')
                @endif

                @if($role === 'supervisi')
                    @include('shared.layouts.partials.sidebar.role-supervisi')
                @endif

                @if($role === 'admin_warehouse')
                    @include('shared.layouts.partials.sidebar.role-admin-warehouse')
                @endif

                @if($role === 'admin')
                    @include('shared.layouts.partials.sidebar.role-admin')
                @endif
            @endif
        </ul>

            @if(auth()->check())
                @include('shared.layouts.partials.sidebar.user-summary')
            @endif
        </div>
    </div>
</nav>