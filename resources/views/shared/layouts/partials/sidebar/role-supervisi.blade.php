<div class="mt-3 mb-2 text-muted px-2" style="font-size: 0.75rem; font-weight: 600;">SUPERVISI</div>

<li class="nav-item">
    <a class="nav-link {{ request()->routeIs('stock-view*') ? 'active' : '' }}"
       href="{{ route('stock-view.index') }}">
        <i class="bi bi-eye"></i> Lihat Stok
    </a>
</li>
<li class="nav-item">
    <a class="nav-link {{ request()->routeIs('box-not-full.approvals') ? 'active' : '' }}"
       href="{{ route('box-not-full.approvals') }}">
        <i class="bi bi-clipboard-check"></i> Approval Box Not Full
    </a>
</li>
<li class="nav-item">
    <a class="nav-link {{ request()->routeIs('expired-box*') ? 'active' : '' }}"
       href="{{ route('expired-box.index') }}">
        <i class="bi bi-exclamation-triangle"></i> Expired Box
    </a>
</li>
<li class="nav-item">
    <a class="nav-link {{ request()->routeIs('reports*') ? 'active' : '' }}"
       data-bs-toggle="collapse" href="#laporanMenu" role="button" aria-expanded="{{ request()->routeIs('reports*') ? 'true' : 'false' }}" aria-controls="laporanMenu">
        <i class="bi bi-file-earmark-pdf"></i> Laporan
        <i class="bi bi-chevron-down" style="float: right; margin-top: 3px;"></i>
    </a>
    <div class="collapse {{ request()->routeIs('reports*') ? 'show' : '' }}" id="laporanMenu">
        <ul class="nav flex-column ms-3 mt-2">
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('reports.stock-input') ? 'active' : '' }}"
                   href="{{ route('reports.stock-input') }}">
                    <i class="bi bi-arrow-right-short"></i> Input Stok
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('reports.withdrawal') ? 'active' : '' }}"
                   href="{{ route('reports.withdrawal') }}">
                    <i class="bi bi-arrow-right-short"></i> Pengambilan Stok
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('audit.index') ? 'active' : '' }}"
                   href="{{ route('audit.index') }}">
                    <i class="bi bi-arrow-right-short"></i> Audit Trail
                </a>
            </li>
        </ul>
    </div>
</li>