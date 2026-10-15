<!-- Sidebar (Offcanvas on Mobile) -->
<nav class="col-md-2 sidebar offcanvas offcanvas-start offcanvas-lg" tabindex="-1" id="mobileSidebar">
    <div class="offcanvas-header d-lg-none">
        <h5 class="offcanvas-title"><img src="{{ asset('logo.png') }}" alt="Logo" class="brand-logo"> Warehouse FG</h5>
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

                @include('shared.layouts.partials.sidebar.features')
            @endif
        </ul>

            @if(auth()->check())
                @include('shared.layouts.partials.sidebar.user-summary')
            @endif
        </div>
    </div>
</nav>