<?php

$warehouse = ['warehouse_operator', 'admin_warehouse'];
$supervisor = ['supervisi'];

return [
    'roles' => [
        'warehouse_operator' => 'Warehouse Operator',
        'export_operator' => 'Operator Export',
        'admin_warehouse' => 'Admin Warehouse',
        'sales' => 'Sales',
        'ppc' => 'PPC',
        'supervisi' => 'Supervisi',
    ],
    // Order matters: specific actions must precede wildcard routes.
    'features' => [
        'stock_input' => ['label' => 'Input Stok', 'group' => 'Warehouse', 'icon' => 'plus-circle', 'route' => 'stock-input.index', 'routes' => ['stock-input.*'], 'roles' => $warehouse],
        'not_full_request' => ['label' => 'Request Box Not Full', 'group' => 'Warehouse', 'icon' => 'exclamation-circle', 'route' => 'box-not-full.create', 'routes' => ['box-not-full.create', 'box-not-full.store'], 'roles' => $warehouse],
        'not_full_approval' => ['label' => 'Approval Box Not Full', 'group' => 'Warehouse', 'icon' => 'clipboard-check', 'route' => 'box-not-full.approvals', 'routes' => ['box-not-full.approvals', 'box-not-full.approve', 'box-not-full.reject'], 'roles' => $supervisor],
        'merge_pallet' => ['label' => 'Merge Palet', 'group' => 'Warehouse', 'icon' => 'box-seam', 'route' => 'merge-pallet.index', 'routes' => ['merge-pallet.*'], 'roles' => $warehouse],
        'move_pallet' => ['label' => 'Pemindahan Pallet', 'group' => 'Warehouse', 'icon' => 'arrow-left-right', 'route' => 'move-pallet.index', 'routes' => ['move-pallet.*'], 'roles' => $warehouse],
        'stock_edit' => ['label' => 'Edit Box', 'group' => 'Warehouse', 'routes' => ['stock-view.box-update'], 'roles' => ['admin_warehouse'], 'requires' => ['stock_view']],
        'stock_delete' => ['label' => 'Hapus Box / Pallet', 'group' => 'Warehouse', 'routes' => ['stock-view.box-delete', 'stock-view.pallet-delete'], 'roles' => ['admin_warehouse', 'supervisi'], 'requires' => ['stock_view']],
        'stock_view' => ['label' => 'Lihat Stok dan Export', 'group' => 'Warehouse', 'icon' => 'eye', 'route' => 'stock-view.index', 'routes' => ['stock-view.*', 'api.stock.part-detail', 'api.stock.pallet-detail'], 'roles' => ['warehouse_operator', 'admin_warehouse', 'ppc', 'supervisi']],
        'expired_box' => ['label' => 'Expired Box dan Handle', 'group' => 'Warehouse', 'icon' => 'exclamation-triangle', 'route' => 'expired-box.index', 'routes' => ['expired-box.*'], 'roles' => $supervisor],
        'delivery_schedule' => ['label' => 'Jadwal Delivery', 'group' => 'Delivery', 'icon' => 'truck', 'route' => 'delivery.index', 'routes' => ['delivery.index'], 'roles' => ['warehouse_operator', 'admin_warehouse', 'ppc']],
        'delivery_order' => ['label' => 'Sales Input dan Koreksi Order', 'group' => 'Delivery', 'icon' => 'cart-plus', 'route' => 'delivery.create', 'routes' => ['delivery.create', 'delivery.store', 'delivery.edit', 'delivery.update'], 'roles' => ['sales']],
        'delivery_approval' => ['label' => 'Approval Delivery (PPC)', 'group' => 'Delivery', 'icon' => 'clipboard-check', 'route' => 'delivery.approvals', 'routes' => ['delivery.approvals', 'delivery.approval-impact', 'delivery.status'], 'roles' => ['ppc']],
        'delivery_assign' => ['label' => 'Assign Delivery', 'group' => 'Delivery', 'icon' => 'send', 'route' => 'delivery-assign.index', 'routes' => ['delivery-assign.*'], 'roles' => $warehouse],
        'delivery_split' => ['label' => 'Split dan Restore Delivery', 'group' => 'Delivery', 'routes' => ['delivery.split', 'delivery.restore-split'], 'roles' => ['admin_warehouse'], 'requires' => ['delivery_schedule']],
        'delivery_delete' => ['label' => 'Hapus Delivery', 'group' => 'Delivery', 'routes' => ['delivery.destroy'], 'roles' => [], 'requires' => ['delivery_schedule']],
        'delivery_issues' => ['label' => 'Scan Issues dan Approval', 'group' => 'Delivery', 'icon' => 'bell', 'route' => 'delivery.pick.issues', 'routes' => ['delivery.pick.issues', 'delivery.pick.issue.approve', 'delivery.pick.export-issue.approve', 'delivery.pick.export-issue.input-error'], 'roles' => ['admin_warehouse']],
        'delivery_redo' => ['label' => 'Redo Delivery', 'group' => 'Delivery', 'routes' => ['delivery.pick.redo', 'delivery.pick.redo-options'], 'roles' => ['admin_warehouse'], 'requires' => ['delivery_schedule']],
        'delivery_locks' => ['label' => 'Lock Management', 'group' => 'Administrasi', 'icon' => 'shield-lock', 'route' => 'delivery.pick.locks', 'routes' => ['delivery.pick.locks', 'delivery.pick.lock.terminate'], 'roles' => [], 'admin_only' => true],
        'delivery_pick' => ['label' => 'Picking, Verifikasi dan Pengambilan', 'group' => 'Delivery', 'icon' => 'upc-scan', 'route' => 'delivery.pick.verification', 'routes' => ['delivery.pick.*', 'delivery.fulfill', 'delivery.fulfill-data', 'stock-withdrawal.*'], 'roles' => $warehouse, 'requires' => ['delivery_schedule']],
        'locations' => ['label' => 'Kelola Lokasi', 'group' => 'Master Data', 'icon' => 'geo-alt', 'route' => 'locations.index', 'routes' => ['locations.*'], 'roles' => ['admin_warehouse']],
        'parts' => ['label' => 'Master No Part', 'group' => 'Master Data', 'icon' => 'list-check', 'route' => 'part-settings.index', 'routes' => ['part-settings.*'], 'roles' => ['admin_warehouse']],
        'export_delivery_plans' => ['label' => 'Rencana Delivery Ekspor', 'group' => 'Pengiriman Ekspor', 'icon' => 'calendar2-check', 'route' => 'export.delivery-plans.index', 'routes' => ['export.delivery-plans.*'], 'roles' => ['export_operator']],
        'export_pallet_plans' => ['label' => 'Perencanaan Pallet Ekspor', 'group' => 'Pengiriman Ekspor', 'icon' => 'boxes', 'route' => 'export.pallet-plans.index', 'routes' => ['export.pallet-plans.*'], 'roles' => ['export_operator']],
        'export_stock_input' => ['label' => 'Penerimaan Transfer Box Ekspor', 'group' => 'Pengiriman Ekspor', 'icon' => 'upc-scan', 'route' => 'export.receiving.index', 'routes' => ['export.receiving.*', 'export.stock.*'], 'roles' => ['export_operator']],
        'export_picking_verification' => ['label' => 'Picking Verification Ekspor', 'group' => 'Pengiriman Ekspor', 'icon' => 'search', 'route' => 'export.picking.index', 'routes' => ['export.picking.*'], 'roles' => ['export_operator'], 'requires' => ['export_stock_input']],
        'export_fulfillment' => ['label' => 'Pemenuhan Permintaan', 'group' => 'Pengiriman Ekspor', 'icon' => 'clipboard-data', 'route' => 'export.fulfillment.index', 'routes' => ['export.fulfillment.*'], 'roles' => ['export_operator']],
        'export_parts' => ['label' => 'Master Nomor Part Ekspor', 'group' => 'Master Data Ekspor', 'icon' => 'list-check', 'route' => 'export.part-settings.index', 'routes' => ['export.part-settings.*'], 'roles' => ['export_operator']],
        'export_locations' => ['label' => 'Master Lokasi Ekspor', 'group' => 'Master Data Ekspor', 'icon' => 'geo-alt', 'route' => 'export.locations.index', 'routes' => ['export.locations.*'], 'roles' => ['export_operator']],
        'reports' => ['label' => 'Laporan dan Export Operasional', 'group' => 'Laporan', 'icon' => 'file-earmark-bar-graph', 'route' => 'reports.stock-input', 'routes' => ['reports.*'], 'roles' => ['admin_warehouse', 'supervisi']],
        'audit' => ['label' => 'Audit Trail', 'group' => 'Laporan', 'icon' => 'shield-check', 'route' => 'audit.index', 'routes' => ['audit.*'], 'roles' => $supervisor],
        'users' => ['label' => 'Kelola User', 'group' => 'Administrasi', 'icon' => 'people', 'route' => 'users.index', 'routes' => ['users.*'], 'roles' => [], 'admin_only' => true],
        'access_management' => ['label' => 'Hak Akses', 'group' => 'Administrasi', 'icon' => 'key', 'route' => 'access.index', 'routes' => ['access.*'], 'roles' => [], 'admin_only' => true],
    ],
    // Shared read-only lookups are available only to features that consume them.
    'lookups' => [
        'api.stock.by-part' => ['stock_view', 'delivery_order', 'delivery_approval', 'delivery_pick', 'delivery_assign'],
        'api.locations.search' => ['locations', 'stock_input', 'not_full_request', 'not_full_approval', 'delivery_assign', 'merge_pallet', 'move_pallet', 'expired_box', 'delivery_redo'],
        'part-settings.search' => ['parts', 'stock_input', 'not_full_request', 'delivery_order', 'delivery_assign', 'stock_edit'],
    ],
];
