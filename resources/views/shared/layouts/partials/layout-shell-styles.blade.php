<style>
    * {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    }
    body {
        background-color: #f5f7fa;
    }
    :root {
        --top-navbar-height: 64px;
    }
    .sidebar {
        background-color: #f8f9fb;
        min-height: 100vh;
        color: #333;
        box-shadow: 2px 0 8px rgba(0,0,0,0.05);
        border-right: 1px solid #e5e9f0;
        transition: transform 0.36s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.24s ease, margin-right 0.36s cubic-bezier(0.22, 1, 0.36, 1), border-color 0.24s ease, box-shadow 0.24s ease;
        transform-origin: left center;
        will-change: flex-basis, max-width, width, opacity, transform;
    }
    .sidebar.offcanvas {
        width: 85vw;
        max-width: 320px;
        z-index: 1095;
    }

    .offcanvas-backdrop {
        z-index: 1090;
    }
    .sidebar .offcanvas-body,
    .sidebar .position-sticky {
        transition: opacity 0.18s ease, transform 0.28s ease;
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
    .sidebar .menu-group-toggle {
        justify-content: space-between;
    }
    .sidebar .menu-group-label {
        display: inline-flex;
        align-items: center;
        gap: 0.75rem;
    }
    .sidebar .menu-chevron {
        margin-left: auto;
        transition: transform 0.2s ease;
        font-size: 0.9rem;
    }
    .sidebar .menu-group-toggle[aria-expanded="true"] .menu-chevron {
        transform: rotate(180deg);
    }
    .sidebar .submenu .nav-link {
        font-size: 0.9rem;
        padding: 0.55rem 0.75rem;
        margin-bottom: 0.35rem;
    }
    .sidebar .submenu .nav-link i {
        font-size: 0.95rem;
        width: 1.2rem;
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
    .top-navbar {
        background: #f8f9fb;
        border-bottom: 1px solid #e5e9f0;
        z-index: 1100;
        isolation: isolate;
    }
    .top-navbar .navbar-inner {
        min-height: var(--top-navbar-height);
        display: flex;
        align-items: center;
        gap: 0.75rem;
        background: #f8f9fb;
    }
    .top-search-form {
        flex: 1 1 auto;
        min-width: 180px;
        max-width: 640px;
        position: relative;
    }
    .top-search-form .input-group-text {
        background: #f8f9fb;
        border-color: #e5e9f0;
    }
    .top-search-form .form-control {
        border-color: #e5e9f0;
    }
    .top-search-form .form-control:focus {
        border-color: #0C7779;
        box-shadow: 0 0 0 0.15rem rgba(12, 119, 121, 0.15);
    }
    .global-search-dropdown {
        position: absolute;
        top: calc(100% + 6px);
        left: 0;
        right: 0;
        background: #fff;
        border: 1px solid #e5e9f0;
        border-radius: 10px;
        box-shadow: 0 10px 28px rgba(15, 23, 42, 0.12);
        max-height: 320px;
        overflow-y: auto;
        z-index: 1110;
        display: none;
    }
    .global-search-item {
        width: 100%;
        border: none;
        background: transparent;
        text-align: left;
        padding: 10px 12px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        color: #374151;
        border-bottom: 1px solid #f1f5f9;
    }
    .global-search-item:last-child {
        border-bottom: none;
    }
    .global-search-item:hover,
    .global-search-item.active {
        background: #e0f5f3;
        color: #0C7779;
    }
    .global-search-item .feature-label {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.92rem;
        font-weight: 600;
    }
    .global-search-item .feature-group {
        font-size: 0.72rem;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    .global-search-empty {
        padding: 12px;
        color: #6b7280;
        font-size: 0.9rem;
    }
    .topbar-future-slot {
        flex: 0 0 140px;
        min-height: 38px;
        border: 1px dashed #d1d5db;
        border-radius: 10px;
        background: #f8fafc;
        display: none;
    }
    .avatar-trigger {
        border: 1px solid #e5e9f0;
        border-radius: 999px;
        padding: 4px 10px 4px 4px;
        background: #ffffff;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }
    .avatar-trigger:hover {
        background: #f8f9fb;
    }
    .avatar-circle {
        width: 32px;
        height: 32px;
        border-radius: 999px;
        background: #0C7779;
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: 700;
    }
    .navbar-brand {
        font-weight: bold;
        font-size: 1.3rem;
    }

    .swal2-warehouse-popup .swal2-html-container ul li {
        color: inherit !important;
    }
    .swal2-warehouse-popup .swal2-html-container ul li strong {
        color: inherit !important;
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
            top: var(--top-navbar-height);
            height: calc(100vh - var(--top-navbar-height));
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

        .sidebar-collapsed .sidebar.offcanvas {
            flex: 0 0 16.66666667% !important;
            width: 16.66666667% !important;
            max-width: 16.66666667% !important;
            opacity: 0;
            transform: translateX(-20px);
            margin-right: -16.66666667%;
            pointer-events: none;
            border-right: none;
            box-shadow: none;
            overflow: hidden;
        }

        .sidebar-collapsed .sidebar .offcanvas-body,
        .sidebar-collapsed .sidebar .position-sticky {
            opacity: 0;
            transform: translateX(-10px);
        }

        .sidebar-collapsed .main-content {
            flex: 0 0 100%;
            max-width: 100%;
            width: 100% !important;
        }

        .main-content {
            transition: flex-basis 0.36s cubic-bezier(0.22, 1, 0.36, 1), max-width 0.36s cubic-bezier(0.22, 1, 0.36, 1), width 0.36s cubic-bezier(0.22, 1, 0.36, 1);
        }

        .topbar-future-slot {
            display: block;
        }

        .sidebar.offcanvas {
            position: static;
            transform: translateX(0);
            visibility: visible !important;
            height: auto;
            width: 16.66666667% !important;
            max-width: none !important;
            flex: 0 0 16.66666667%;
            background-color: #f8f9fb;
            margin-right: 0;
            opacity: 1;
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
        :root {
            --top-navbar-height: 58px;
        }

        .sidebar .nav-link {
            padding: 0.6rem 0.75rem;
        }

        .top-navbar .btn,
        .top-navbar .btn-sm,
        .top-navbar .btn-lg {
            width: auto;
            margin-bottom: 0;
        }

        .top-navbar .navbar-inner {
            min-height: 58px;
            gap: 0.5rem;
        }

        .top-search-form {
            max-width: 100%;
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

        .top-navbar .btn,
        .top-navbar .btn-sm,
        .top-navbar .btn-lg {
            width: auto;
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
