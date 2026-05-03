<style>
    .search-dropdown {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        border-radius: 10px;
        overflow: hidden;
    }
    
    .search-dropdown .dropdown-item {
        padding: 12px 16px;
        cursor: pointer;
        border-bottom: 1px solid #e5e7eb;
        transition: all 0.3s ease;
        font-size: 14px;
        background: white;
    }
    
    .search-dropdown .dropdown-item:last-child {
        border-bottom: none;
    }
    
    .search-dropdown .dropdown-item:hover,
    .search-dropdown .dropdown-item.active {
        background-color: #f0f4f8;
        color: #0C7779;
        font-weight: 600;
    }

    .table tbody tr:hover {
        background-color: #f9fafb;
    }

    .stock-sort-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        width: 100%;
        color: inherit;
        text-decoration: none;
        font: inherit;
        line-height: 1.2;
    }

    .stock-sort-link:hover {
        color: #005461;
        text-decoration: none;
    }

    .stock-sort-link.is-center {
        justify-content: center;
        text-align: center;
    }

    .stock-sort-link.is-left {
        justify-content: flex-start;
        text-align: left;
    }

    .stock-sort-icon {
        font-size: 0.9rem;
        opacity: 0.85;
    }

    .stock-sort-link.is-active .stock-sort-icon {
        opacity: 1;
    }

    .stock-sort-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 12px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.18);
        color: #ffffff;
        text-decoration: none;
        font-size: 12px;
        font-weight: 700;
        line-height: 1;
        border: 1px solid rgba(255, 255, 255, 0.25);
        transition: all 0.2s ease;
    }

    .stock-sort-pill:hover {
        background: rgba(255, 255, 255, 0.28);
        color: #ffffff;
        text-decoration: none;
    }

    .stock-sort-pill.is-active {
        background: #2dbf78;
        border-color: rgba(255, 255, 255, 0.35);
        box-shadow: 0 6px 14px rgba(45, 191, 120, 0.24);
    }

    .stock-sort-pill i {
        font-size: 0.95rem;
    }

    .select2-container {
        width: 100% !important;
    }

    .select2-container--default .select2-selection--single {
        height: calc(1.5em + .75rem + 2px);
        border: 1px solid #ced4da;
        border-radius: .375rem;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: calc(1.5em + .75rem);
        padding-left: .75rem;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: calc(1.5em + .75rem + 2px);
    }
</style>
