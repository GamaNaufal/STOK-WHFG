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
        .sidebar.offcanvas {
            width: 85vw;
            max-width: 320px;
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
        .main-content {
            width: 100%;
            min-height: 100vh;
        }
        .table-responsive {
            -webkit-overflow-scrolling: touch;
        }
        .table th,
        .table td {
            vertical-align: middle;
        }
        .form-control,
        .form-select,
        .input-group,
        .btn,
        .btn-group {
            max-width: 100%;
        }
        .card,
        .modal-content {
            overflow-wrap: anywhere;
        }

        @media (max-width: 991.98px) {
            .container-fluid > .row {
                flex-direction: column;
            }

            .sidebar {
                min-height: auto;
                box-shadow: none;
                border-right: none;
            }

            .main-content {
                width: 100% !important;
                padding: 1rem !important;
            }

            .table-responsive {
                overflow-x: auto;
            }

            .card,
            .section-card,
            .history-card {
                margin-bottom: 1rem;
            }
        }

        @media (max-width: 992px) {
            .sidebar.offcanvas {
                width: 90vw;
                max-width: 360px;
            }

            nav.sidebar .position-sticky {
                position: static !important;
            }

            .card[style*="position: sticky"] {
                position: static !important;
                top: auto !important;
            }

            .table {
                font-size: 0.9rem;
            }

            .table th,
            .table td {
                white-space: nowrap;
            }

            .card-header,
            .card-body,
            .card-footer {
                padding: 0.9rem !important;
            }

            .btn-lg {
                padding: 0.65rem 1rem;
                font-size: 1rem;
            }
        }

        @media (min-width: 992px) {
            .container-fluid > .row {
                flex-wrap: nowrap;
                align-items: stretch;
            }

            .sidebar.offcanvas {
                position: static;
                transform: none !important;
                visibility: visible !important;
                height: auto;
                width: 16.66666667% !important;
                max-width: none !important;
                flex: 0 0 16.66666667%;
                background-color: #f8f9fb;
            }

            .main-content {
                flex: 1 1 auto;
                width: auto !important;
            }

            .sidebar.offcanvas .offcanvas-header {
                display: none;
            }

            .sidebar.offcanvas .offcanvas-body {
                display: block;
                padding: 0;
            }

            .offcanvas-backdrop {
                display: none !important;
            }
        }

        @media (max-width: 768px) {
            .sidebar .nav-link {
                padding: 0.6rem 0.75rem;
            }

            .page-header,
            .section-header {
                padding: 1rem !important;
            }

            .btn,
            .btn-sm,
            .btn-lg {
                width: 100%;
                margin-bottom: 0.5rem;
            }

            .action-buttons {
                flex-wrap: wrap;
                gap: 0.5rem;
            }

            .table-responsive {
                margin-bottom: 1rem;
            }

            .input-group {
                flex-wrap: wrap;
            }

            .input-group > .form-control,
            .input-group > .form-select,
            .input-group > .btn {
                width: 100%;
                border-radius: 0.5rem !important;
            }

            .main-content {
                padding: 0.75rem !important;
            }
        }

        @media (max-width: 576px) {
            .btn,
            .btn-sm,
            .btn-lg {
                width: 100%;
            }

            .row.g-3,
            .row.g-2 {
                row-gap: 0.75rem;
            }

            .card-header h5,
            .card-header h6,
            .card-body h5,
            .card-body h6 {
                font-size: 1rem;
            }
        }
    </style>
    
    @yield('styles')
</head>
<body>
    <div class="container-fluid">
        <div class="d-lg-none sticky-top" style="background: #f8f9fb; border-bottom: 1px solid #e5e9f0; z-index: 1030;">
            <div class="d-flex align-items-center justify-content-between px-3 py-2">
                <div class="fw-bold" style="color:#0C7779;"><i class="bi bi-box2"></i> Warehouse FG</div>
                <button class="btn btn-primary" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" aria-controls="mobileSidebar">
                    <i class="bi bi-list"></i> Menu
                </button>
            </div>
        </div>
        <div class="row">
            <!-- Sidebar (Offcanvas on Mobile) -->
            <nav class="col-md-2 sidebar offcanvas offcanvas-start offcanvas-lg" tabindex="-1" id="mobileSidebar">
                <div class="offcanvas-header d-lg-none">
                    <h5 class="offcanvas-title"><i class="bi bi-box2"></i> Warehouse FG</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                </div>
                <div class="offcanvas-body p-0">
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
                            <li class="nav-item">
                                <a class="nav-link {{ request()->routeIs('profile.*') ? 'active' : '' }}" 
                                   href="{{ route('profile.edit') }}">
                                    <i class="bi bi-person"></i> Edit Profile
                                </a>
                            </li>

                            <!-- ==================== WAREHOUSE OPERATOR ==================== -->
                            @if(auth()->user()->role === 'warehouse_operator')
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
                            @endif

                            <!-- ==================== SALES ==================== -->
                            @if(auth()->user()->role === 'sales')
                                <div class="mt-3 mb-2 text-muted px-2" style="font-size: 0.75rem; font-weight: 600;">SALES</div>
                                
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('delivery.create') ? 'active' : '' }}" 
                                       href="{{ route('delivery.create') }}">
                                        <i class="bi bi-cart-plus"></i> Sales Input
                                    </a>
                                </li>
                            @endif

                            <!-- ==================== PPC ==================== -->
                            @if(auth()->user()->role === 'ppc')
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
                            @endif

                            <!-- ==================== SUPERVISI ==================== -->
                            @if(auth()->user()->role === 'supervisi')
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
                            @endif

                            <!-- ==================== ADMIN WAREHOUSE ==================== -->
                            @if(auth()->user()->role === 'admin_warehouse')
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
                            @endif

                            <!-- ==================== ADMIN IT ==================== -->
                            @if(auth()->user()->role === 'admin')
                                <div class="mt-3 mb-2 text-muted px-2" style="font-size: 0.75rem; font-weight: 600;">ADMIN IT</div>
                                
                                <!-- All Features for Admin -->
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('stock-input*') ? 'active' : '' }}" 
                                       href="{{ route('stock-input.index') }}">
                                        <i class="bi bi-plus-circle"></i> Input Stok
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('box-not-full.create') ? 'active' : '' }}" 
                                       href="{{ route('box-not-full.create') }}">
                                        <i class="bi bi-exclamation-circle"></i> Box Not Full
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('box-not-full.approvals') ? 'active' : '' }}" 
                                       href="{{ route('box-not-full.approvals') }}">
                                        <i class="bi bi-clipboard-check"></i> Approval Box Not Full
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('delivery.index') ? 'active' : '' }}" 
                                       href="{{ route('delivery.index') }}">
                                        <i class="bi bi-truck"></i> Delivery
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('delivery.create') ? 'active' : '' }}" 
                                       href="{{ route('delivery.create') }}">
                                        <i class="bi bi-cart-plus"></i> Sales Input
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('delivery.approvals') ? 'active' : '' }}" 
                                       href="{{ route('delivery.approvals') }}">
                                        <i class="bi bi-clipboard-check"></i> Approval
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('merge-pallet*') ? 'active' : '' }}" 
                                       href="{{ route('merge-pallet.index') }}">
                                        <i class="bi bi-box-seam"></i> Merge Palet
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('locations*') ? 'active' : '' }}" 
                                       href="{{ route('locations.index') }}">
                                        <i class="bi bi-geo-alt"></i> Lokasi
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('delivery.pick.issues') ? 'active' : '' }}" 
                                       href="{{ route('delivery.pick.issues') }}">
                                        <i class="bi bi-bell"></i> Scan Issues
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('part-settings*') ? 'active' : '' }}" 
                                       href="{{ route('part-settings.index') }}">
                                        <i class="bi bi-list-check"></i> Master No Part
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('users*') ? 'active' : '' }}" 
                                       href="{{ route('users.index') }}">
                                        <i class="bi bi-people"></i> Kelola User
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ request()->routeIs('stock-view*') ? 'active' : '' }}" 
                                       href="{{ route('stock-view.index') }}">
                                        <i class="bi bi-eye"></i> Lihat Stok
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
                                <a href="{{ route('profile.edit') }}" class="btn btn-sm btn-outline-light w-100 mb-2">
                                    <i class="bi bi-person"></i> Edit Profile
                                </a>
                                <form method="POST" action="{{ route('logout') }}" style="display: inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-danger w-100">
                                        <i class="bi bi-box-arrow-right"></i> Logout
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>
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

    <div id="toast-container" class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080;"></div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        window.showToast = function(message, type = 'info') {
            const toastContainer = document.getElementById('toast-container');
            if (!toastContainer) return;

            const typeMap = {
                success: { bg: 'success', icon: 'check-circle' },
                danger: { bg: 'danger', icon: 'exclamation-triangle' },
                warning: { bg: 'warning', icon: 'exclamation-circle' },
                info: { bg: 'info', icon: 'info-circle' }
            };

            const config = typeMap[type] || typeMap.info;
            const toastEl = document.createElement('div');
            toastEl.className = `toast align-items-center text-bg-${config.bg} border-0`; 
            toastEl.setAttribute('role', 'alert');
            toastEl.setAttribute('aria-live', 'assertive');
            toastEl.setAttribute('aria-atomic', 'true');

            toastEl.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="bi bi-${config.icon}"></i> ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            `;

            toastContainer.appendChild(toastEl);
            const toast = new bootstrap.Toast(toastEl, { delay: 3500 });
            toast.show();

            toastEl.addEventListener('hidden.bs.toast', () => {
                toastEl.remove();
            });
        };
    </script>
    
    @yield('scripts')
</body>
</html>
