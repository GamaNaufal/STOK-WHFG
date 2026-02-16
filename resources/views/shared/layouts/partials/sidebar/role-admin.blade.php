<div class="mt-3 mb-2 text-muted px-2" style="font-size: 0.75rem; font-weight: 600;">ADMIN IT</div>

@php
    $adminWarehouseOpsActive = request()->routeIs('stock-input*')
        || request()->routeIs('box-not-full.create')
        || request()->routeIs('box-not-full.approvals')
        || request()->routeIs('merge-pallet*')
        || request()->routeIs('stock-view*')
        || request()->routeIs('expired-box*');

    $adminDeliveryActive = request()->routeIs('delivery.index')
        || request()->routeIs('delivery.pick.verification')
        || request()->routeIs('delivery.create')
        || request()->routeIs('delivery.approvals')
        || request()->routeIs('delivery.pick.issues');

    $adminMasterActive = request()->routeIs('locations*')
        || request()->routeIs('part-settings*')
        || request()->routeIs('users*');

    $adminReportsActive = request()->routeIs('reports*') || request()->routeIs('audit.index');
@endphp

<li class="nav-item">
    <a class="nav-link menu-group-toggle {{ $adminWarehouseOpsActive ? '' : 'collapsed' }}"
       data-bs-toggle="collapse"
       href="#adminWarehouseOpsMenu"
       role="button"
       aria-expanded="{{ $adminWarehouseOpsActive ? 'true' : 'false' }}"
       aria-controls="adminWarehouseOpsMenu">
        <span class="menu-group-label"><i class="bi bi-boxes"></i> Warehouse</span>
        <i class="bi bi-chevron-down menu-chevron"></i>
    </a>
    <div class="collapse {{ $adminWarehouseOpsActive ? 'show' : '' }}" id="adminWarehouseOpsMenu">
        <ul class="nav flex-column ms-3 mt-2 mb-1 submenu">
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('stock-input*') ? 'active' : '' }}" href="{{ route('stock-input.index') }}">
                    <i class="bi bi-plus-circle"></i> Input Stok
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('box-not-full.create') ? 'active' : '' }}" href="{{ route('box-not-full.create') }}">
                    <i class="bi bi-exclamation-circle"></i> Box Not Full
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('box-not-full.approvals') ? 'active' : '' }}" href="{{ route('box-not-full.approvals') }}">
                    <i class="bi bi-clipboard-check"></i> Approval Box Not Full
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('merge-pallet*') ? 'active' : '' }}" href="{{ route('merge-pallet.index') }}">
                    <i class="bi bi-box-seam"></i> Merge Palet
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('stock-view*') ? 'active' : '' }}" href="{{ route('stock-view.index') }}">
                    <i class="bi bi-eye"></i> Lihat Stok
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('expired-box*') ? 'active' : '' }}" href="{{ route('expired-box.index') }}">
                    <i class="bi bi-exclamation-triangle"></i> Expired Box
                </a>
            </li>
        </ul>
    </div>
</li>

<li class="nav-item">
    <a class="nav-link menu-group-toggle {{ $adminDeliveryActive ? '' : 'collapsed' }}"
       data-bs-toggle="collapse"
       href="#adminDeliveryMenu"
       role="button"
       aria-expanded="{{ $adminDeliveryActive ? 'true' : 'false' }}"
       aria-controls="adminDeliveryMenu">
        <span class="menu-group-label"><i class="bi bi-truck"></i> Delivery / Sales / PPC</span>
        <i class="bi bi-chevron-down menu-chevron"></i>
    </a>
    <div class="collapse {{ $adminDeliveryActive ? 'show' : '' }}" id="adminDeliveryMenu">
        <ul class="nav flex-column ms-3 mt-2 mb-1 submenu">
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('delivery.index') ? 'active' : '' }}" href="{{ route('delivery.index') }}">
                    <i class="bi bi-truck"></i> Delivery
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('delivery.pick.verification') ? 'active' : '' }}" href="{{ route('delivery.pick.verification') }}">
                    <i class="bi bi-upc-scan"></i> Picking Verification
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('delivery.create') ? 'active' : '' }}" href="{{ route('delivery.create') }}">
                    <i class="bi bi-cart-plus"></i> Sales Input
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('delivery.approvals') ? 'active' : '' }}" href="{{ route('delivery.approvals') }}">
                    <i class="bi bi-clipboard-check"></i> Approval
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('delivery.pick.issues') ? 'active' : '' }}" href="{{ route('delivery.pick.issues') }}">
                    <i class="bi bi-bell"></i> Scan Issues
                </a>
            </li>
        </ul>
    </div>
</li>

<li class="nav-item">
    <a class="nav-link menu-group-toggle {{ $adminMasterActive ? '' : 'collapsed' }}"
       data-bs-toggle="collapse"
       href="#adminMasterMenu"
       role="button"
       aria-expanded="{{ $adminMasterActive ? 'true' : 'false' }}"
       aria-controls="adminMasterMenu">
        <span class="menu-group-label"><i class="bi bi-database"></i> Master Data</span>
        <i class="bi bi-chevron-down menu-chevron"></i>
    </a>
    <div class="collapse {{ $adminMasterActive ? 'show' : '' }}" id="adminMasterMenu">
        <ul class="nav flex-column ms-3 mt-2 mb-1 submenu">
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('locations*') ? 'active' : '' }}" href="{{ route('locations.index') }}">
                    <i class="bi bi-geo-alt"></i> Lokasi
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('part-settings*') ? 'active' : '' }}" href="{{ route('part-settings.index') }}">
                    <i class="bi bi-list-check"></i> Master No Part
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('users*') ? 'active' : '' }}" href="{{ route('users.index') }}">
                    <i class="bi bi-people"></i> Kelola User
                </a>
            </li>
        </ul>
    </div>
</li>

<li class="nav-item">
    <a class="nav-link menu-group-toggle {{ $adminReportsActive ? '' : 'collapsed' }}"
       data-bs-toggle="collapse"
       href="#adminReportsMenu"
       role="button"
       aria-expanded="{{ $adminReportsActive ? 'true' : 'false' }}"
       aria-controls="adminReportsMenu">
        <span class="menu-group-label"><i class="bi bi-file-earmark-pdf"></i> Laporan</span>
        <i class="bi bi-chevron-down menu-chevron"></i>
    </a>
    <div class="collapse {{ $adminReportsActive ? 'show' : '' }}" id="adminReportsMenu">
        <ul class="nav flex-column ms-3 mt-2 mb-1 submenu">
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('reports.stock-input') ? 'active' : '' }}" href="{{ route('reports.stock-input') }}">
                    <i class="bi bi-arrow-right-short"></i> Input Stok
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('reports.withdrawal') ? 'active' : '' }}" href="{{ route('reports.withdrawal') }}">
                    <i class="bi bi-arrow-right-short"></i> Pengambilan Stok
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('audit.index') ? 'active' : '' }}" href="{{ route('audit.index') }}">
                    <i class="bi bi-arrow-right-short"></i> Audit Trail
                </a>
            </li>
        </ul>
    </div>
</li>