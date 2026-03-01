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
