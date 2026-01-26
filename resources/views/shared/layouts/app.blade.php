<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Stok Warehouse FG Yamato')</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        * {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        body {
            background-color: #f5f7fa;
        }
        .sidebar {
            background-color: #f8f9fb;
            min-height: 100vh;
            color: #333;
            box-shadow: 2px 0 8px rgba(0,0,0,0.05);
            border-right: 1px solid #e5e9f0;
        }
        .sidebar .px-3 {
            border-bottom: 1px solid #e5e9f0;
            padding-bottom: 1.5rem !important;
            padding-top: 1rem !important;
        }
        .sidebar .px-3 h4 {
            color: #0C7779;
            font-weight: 700;
            font-size: 1.1rem;
            letter-spacing: -0.3px;
            margin-bottom: 0.25rem;
        }
        .sidebar .px-3 small {
            color: #9ca3af;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .sidebar .nav-link {
            color: #4b5563;
            border-radius: 0.6rem;
            margin-bottom: 0.5rem;
            transition: all 0.25s ease;
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
            font-size: 0.95rem;
        }
        .sidebar .nav-link i {
            font-size: 1.1rem;
            width: 1.5rem;
            text-align: center;
            color: #6b7280;
        }
        .sidebar .nav-link:hover {
            background-color: #e0f5f3;
            color: #0C7779;
        }
        .sidebar .nav-link:hover i {
            color: #0C7779;
        }
        .sidebar .nav-link.active {
            background-color: #0C7779;
            color: white;
            font-weight: 600;
        }
        .sidebar .nav-link.active i {
            color: white;
        }
        .sidebar hr {
            border-color: #e5e9f0 !important;
            margin: 1rem 0 !important;
        }
        .sidebar .text-muted {
            color: #9ca3af !important;
        }
        .sidebar strong {
            color: #1f2937;
        }
        .main-content {
            padding: 20px;
        }
        .navbar-brand {
            font-weight: bold;
            font-size: 1.3rem;
        }
        .btn-primary {
            background-color: #0C7779;
            border-color: #0C7779;
        }
        .btn-primary:hover {
            background-color: #005461;
            border-color: #005461;
        }
        .btn-success {
            background-color: #0C7779;
            border-color: #0C7779;
        }
        .btn-success:hover {
            background-color: #005461;
            border-color: #005461;
        }
        .btn-info {
            background-color: #0C7779;
            border-color: #0C7779;
        }
        .btn-info:hover {
            background-color: #005461;
            border-color: #005461;
        }
        .btn-outline-danger {
            color: #dc2626;
            border-color: #fecaca;
        }
        .btn-outline-danger:hover {
            background-color: #fee2e2;
            border-color: #dc2626;
            color: #dc2626;
        }
        .badge.bg-info {
            background-color: #e0f5f3 !important;
            color: #0C7779;
            font-size: 0.75rem;
        }
    </style>
    
    @yield('styles')
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-2 sidebar d-md-block">
                <div class="position-sticky pt-3">
                    <div class="px-3 mb-4">
                        <h4 class="mb-0">
                            <i class="bi bi-box2"></i> Warehouse FG
                        </h4>
                        <small class="text-muted">PT. Yamatogomu Indonesia</small>
                    </div>
                    
                    <ul class="nav flex-column px-2">
                        @if(auth()->check())
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" 
                                   href="{{ route('dashboard') }}">
                                    <i class="bi bi-house"></i> Dashboard
                                </a>
                            </li>

                            @if(auth()->user()->role === 'admin')
                                <div class="mt-2 mb-1 text-muted px-2" style="font-size: 0.75rem;">ADMIN IT</div>
                            @endif

                            <!-- Kelola Lokasi: Admin Warehouse + Admin IT -->
                            @if(auth()->user()->role === 'admin_warehouse' || auth()->user()->role === 'admin')
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('locations*') ? 'active' : '' }}" 
                                       href="{{ route('locations.index') }}">
                                        <i class="bi bi-geo-alt"></i> Kelola Lokasi
                                    </a>
                                </li>
                            @endif

                            @if(auth()->user()->role === 'admin_warehouse' || auth()->user()->role === 'admin')
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('delivery.pick.issues') ? 'active' : '' }}" 
                                       href="{{ route('delivery.pick.issues') }}">
                                        <i class="bi bi-bell"></i> Scan Issues
                                        @if(isset($pendingScanIssueCount) && $pendingScanIssueCount > 0)
                                            <span class="badge bg-danger rounded-pill ms-auto">{{ $pendingScanIssueCount }}</span>
                                        @endif
                                    </a>
                                </li>
                            @endif

                            <!-- Master No Part & Qty: Admin Warehouse + Admin IT -->
                            @if(auth()->user()->role === 'admin_warehouse' || auth()->user()->role === 'admin')
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('part-settings*') ? 'active' : '' }}" 
                                       href="{{ route('part-settings.index') }}">
                                        <i class="bi bi-list-check"></i> Master No Part
                                    </a>
                                </li>
                            @endif

                            <!-- Kelola User: Admin IT only -->
                            @if(auth()->user()->role === 'admin')
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('users*') ? 'active' : '' }}" 
                                       href="{{ route('users.index') }}">
                                        <i class="bi bi-people"></i> Kelola User
                                    </a>
                                </li>
                            @endif

                            <!-- Sales Input: Sales + Admin IT -->
                            @if(auth()->user()->role === 'sales' || auth()->user()->role === 'admin')
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('delivery.create') ? 'active' : '' }}" 
                                       href="{{ route('delivery.create') }}">
                                        <i class="bi bi-cart-plus"></i> Sales Input
                                    </a>
                                </li>
                            @endif

                            <!-- Pending Approval: PPC + Admin IT -->
                            @if(auth()->user()->role === 'ppc' || auth()->user()->role === 'admin')
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('delivery.approvals') ? 'active' : '' }}" 
                                       href="{{ route('delivery.approvals') }}">
                                        <i class="bi bi-clipboard-check"></i> Pending Approval
                                        @if(isset($pendingDeliveryCount) && $pendingDeliveryCount > 0)
                                            <span class="badge bg-danger rounded-pill ms-auto">{{ $pendingDeliveryCount }}</span>
                                        @endif
                                    </a>
                                </li>
                            @endif

                            <!-- Input Stok & Delivery Stock: Warehouse Operator + Admin IT -->
                            @if(auth()->user()->role === 'warehouse_operator' || auth()->user()->role === 'admin')
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('stock-input*') ? 'active' : '' }}" 
                                       href="{{ route('stock-input.index') }}">
                                        <i class="bi bi-plus-circle"></i> Input Stok
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('delivery.index') ? 'active' : '' }}" 
                                       href="{{ route('delivery.index') }}">
                                        <i class="bi bi-truck"></i> Delivery Stock
                                    </a>
                                </li>
                            @endif

                            <!-- Merge Pallet: Admin IT only -->
                            @if(auth()->user()->role === 'admin')
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('merge-pallet*') ? 'active' : '' }}" 
                                       href="{{ route('merge-pallet.index') }}">
                                        <i class="bi bi-box-seam"></i> Merge Pallet
                                    </a>
                                </li>
                            @endif

                            <!-- Lihat Stok: PPC + Admin Warehouse + Supervisi + Admin IT -->
                            @if(in_array(auth()->user()->role, ['ppc', 'admin_warehouse', 'supervisi', 'admin'], true))
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('stock-view*') ? 'active' : '' }}" 
                                       href="{{ route('stock-view.index') }}">
                                        <i class="bi bi-eye"></i> Lihat Stok
                                    </a>
                                </li>
                            @endif

                            <!-- Laporan: Supervisi + Admin IT -->
                            @if(auth()->user()->role === 'supervisi' || auth()->user()->role === 'admin')
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
                                                    <i class="bi bi-arrow-right-short"></i> Laporan Input Stok
                                                </a>
                                            </li>
                                            <li class="nav-item">
                                                <a class="nav-link {{ request()->routeIs('reports.withdrawal') ? 'active' : '' }}" 
                                                    href="{{ route('reports.withdrawal') }}">
                                                    <i class="bi bi-arrow-right-short"></i> Laporan Pengambilan
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </li>
                            @endif
                        @endif
                    </ul>

                    @if(auth()->check())
                        <hr class="my-3" style="border-color: rgba(255,255,255,0.2);">
                        <div class="px-2">
                            <small class="text-muted">Logged in as:</small>
                            <p class="mb-3">
                                <strong>{{ auth()->user()->name }}</strong>
                                <br>
                                <span class="badge bg-info">{{ ucfirst(str_replace('_', ' ', auth()->user()->role)) }}</span>
                            </p>
                            <form method="POST" action="{{ route('logout') }}" style="display: inline;">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                                    <i class="bi bi-box-arrow-right"></i> Logout
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-10 main-content">
                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-circle"></i> {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    @yield('scripts')
</body>
</html>
