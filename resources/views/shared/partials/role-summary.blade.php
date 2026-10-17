{{-- Presentation-only grouping; all values come from the existing dashboard stats. --}}
@php
    $metrics = match ($variant) {
        'sales' => [
            ['label' => 'Total Order', 'value' => number_format($stats['total_orders'] ?? 0, 0, ',', '.'), 'description' => 'Jumlah seluruh order penjualan.', 'note' => null],
            ['label' => 'Order Hari Ini', 'value' => number_format($stats['today_orders'] ?? 0, 0, ',', '.'), 'description' => 'Order yang dibuat hari ini.', 'note' => null],
            ['label' => 'Menunggu Persetujuan', 'value' => number_format($stats['pending_orders'] ?? 0, 0, ',', '.'), 'description' => 'Order berstatus pending.', 'note' => null],
            ['label' => 'Disetujui', 'value' => number_format($stats['approved_orders'] ?? 0, 0, ',', '.'), 'description' => 'Order berstatus approved.', 'note' => null],
            ['label' => 'Selesai', 'value' => number_format($stats['completed_orders'] ?? 0, 0, ',', '.'), 'description' => 'Order berstatus completed.', 'note' => null],
        ],
        'ppc' => [
            ['label' => 'Menunggu Persetujuan', 'value' => number_format($stats['pending_approval'] ?? 0, 0, ',', '.'), 'description' => 'Order menunggu persetujuan.', 'note' => null],
            ['label' => 'Order Disetujui', 'value' => number_format($stats['approved_orders'] ?? 0, 0, ',', '.'), 'description' => 'Order yang sudah disetujui.', 'note' => null],
            ['label' => 'Total Order', 'value' => number_format($stats['total_orders'] ?? 0, 0, ',', '.'), 'description' => 'Jumlah seluruh order.', 'note' => null],
            ['label' => 'Total Stok (PCS)', 'value' => number_format($stats['total_stok_pcs'] ?? 0, 0, ',', '.'), 'description' => 'Total PCS stok aktif.', 'note' => null],
            ['label' => 'Total Part', 'value' => number_format($stats['total_stok_items'] ?? 0, 0, ',', '.'), 'description' => 'Jumlah item/part dengan stok aktif.', 'note' => null],
        ],
        'supervisi' => [
            ['label' => 'Total Pallet', 'value' => number_format($stats['total_pallets'] ?? 0, 0, ',', '.'), 'description' => '', 'note' => null],
            ['label' => 'Total PCS', 'value' => number_format($stats['total_pcs'] ?? 0, 0, ',', '.'), 'description' => '', 'note' => null],
            ['label' => 'Total Lokasi', 'value' => number_format($stats['total_locations'] ?? 0, 0, ',', '.'), 'description' => 'Lokasi unik pada catatan penyimpanan.', 'note' => null],
            ['label' => 'Total Order', 'value' => number_format($stats['total_orders'] ?? 0, 0, ',', '.'), 'description' => '', 'note' => null],
            ['label' => 'Selesai Hari Ini', 'value' => number_format($stats['completed_orders_today'] ?? 0, 0, ',', '.'), 'description' => '', 'note' => null],
            ['label' => 'Total Delivery', 'value' => number_format($stats['total_deliveries'] ?? 0, 0, ',', '.'), 'description' => '', 'note' => null],
        ],
        'admin_warehouse' => [
            ['label' => 'Total Pallet', 'value' => number_format($stats['total_pallets'] ?? 0, 0, ',', '.'), 'description' => 'Jumlah seluruh pallet di sistem.', 'note' => null],
            ['label' => 'Pallet Tersimpan', 'value' => number_format($stats['pallets_with_location'] ?? 0, 0, ',', '.'), 'description' => 'Pallet dengan stok aktif.', 'note' => null],
            ['label' => 'Total PCS', 'value' => number_format($stats['total_pcs'] ?? 0, 0, ',', '.'), 'description' => 'Total PCS stok aktif.', 'note' => null],
            ['label' => 'Total Lokasi', 'value' => number_format($stats['total_locations'] ?? 0, 0, ',', '.'), 'description' => 'Lokasi unik pada catatan penyimpanan.', 'note' => null],
            ['label' => 'Scan Issues', 'value' => number_format($stats['pending_scan_issues'] ?? 0, 0, ',', '.'), 'description' => 'Jumlah scan issue berstatus pending.', 'note' => null],
            ['label' => 'Total Part', 'value' => number_format($stats['total_items'] ?? 0, 0, ',', '.'), 'description' => 'Jumlah item/part dengan stok aktif.', 'note' => null],
        ],
        'admin' => [
            ['label' => 'Total Pallet', 'value' => number_format($stats['total_pallets'] ?? 0, 0, ',', '.'), 'description' => 'Jumlah seluruh pallet di sistem.', 'note' => ($stats['today_pallets'] ?? 0) . ' Hari Ini'],
            ['label' => 'Total Pallet Tersimpan', 'value' => number_format($stats['pallets_with_location'] ?? 0, 0, ',', '.'), 'description' => 'Pallet dengan stok aktif.', 'note' => null],
            ['label' => 'Total Box', 'value' => number_format((int) ($stats['total_box'] ?? 0), 0, ',', '.'), 'description' => 'Total box aktif (belum withdrawn).', 'note' => null],
            ['label' => 'Total Lokasi', 'value' => number_format($stats['total_locations'] ?? 0, 0, ',', '.'), 'description' => 'Lokasi unik pada catatan penyimpanan.', 'note' => null],
            ['label' => 'Total Part', 'value' => number_format($stats['total_items'] ?? 0, 0, ',', '.'), 'description' => 'Jumlah item/part dengan stok aktif.', 'note' => null],
            ['label' => 'Total PCS', 'value' => number_format($stats['total_pcs'] ?? 0, 0, ',', '.'), 'description' => 'Total PCS stok aktif.', 'note' => null],
            ['label' => 'Total Order', 'value' => number_format($stats['total_orders'] ?? 0, 0, ',', '.'), 'description' => 'Jumlah seluruh order penjualan.', 'note' => ($stats['pending_orders'] ?? 0) . ' Pending'],
        ],
    };
@endphp
<x-dashboard-summary :metrics="$metrics" :lead-count="$leadCount" />
