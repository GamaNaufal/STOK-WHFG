<?php

$warehouse = ['warehouse_operator', 'admin_warehouse'];
$supervisor = ['supervisi'];

$config = [
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
        'stock_opname_local_manage' => ['label' => 'Jalankan Stock Opname Lokal', 'group' => 'Warehouse', 'routes' => ['stock-opname.start', 'stock-opname.scan', 'stock-opname.refresh', 'stock-opname.finish'], 'roles' => $warehouse, 'requires' => ['stock_opname_local']],
        'stock_opname_export_manage' => ['label' => 'Jalankan Stock Opname Ekspor', 'group' => 'Pengiriman Ekspor', 'routes' => ['export.stock-opname.start', 'export.stock-opname.scan', 'export.stock-opname.refresh', 'export.stock-opname.finish'], 'roles' => ['export_operator', 'admin_warehouse'], 'requires' => ['stock_opname_export']],
        'stock_opname_local' => ['label' => 'Stock Opname Lokal', 'group' => 'Warehouse', 'icon' => 'clipboard-check', 'route' => 'stock-opname.index', 'routes' => ['stock-opname.*'], 'roles' => [...$warehouse, ...$supervisor]],
        'stock_opname_export' => ['label' => 'Stock Opname Ekspor', 'group' => 'Pengiriman Ekspor', 'icon' => 'clipboard-check', 'route' => 'export.stock-opname.index', 'routes' => ['export.stock-opname.*'], 'roles' => ['export_operator', 'admin_warehouse', ...$supervisor]],
        'stock_input' => ['label' => 'Input Stok', 'group' => 'Warehouse', 'icon' => 'plus-circle', 'route' => 'stock-input.index', 'routes' => ['stock-input.*'], 'roles' => $warehouse],
        'not_full_request' => ['label' => 'Request Box Not Full', 'group' => 'Warehouse', 'icon' => 'exclamation-circle', 'route' => 'box-not-full.create', 'routes' => ['box-not-full.create', 'box-not-full.store', 'box-not-full.export.prepare'], 'roles' => $warehouse],
        'not_full_approval' => ['label' => 'Approval Box Not Full', 'group' => 'Warehouse', 'icon' => 'clipboard-check', 'route' => 'box-not-full.approvals', 'routes' => ['box-not-full.approvals', 'box-not-full.approve', 'box-not-full.reject', 'box-not-full.export.approve', 'box-not-full.export.reject'], 'roles' => $supervisor],
        'merge_pallet' => ['label' => 'Merge Palet', 'group' => 'Warehouse', 'icon' => 'box-seam', 'route' => 'merge-pallet.index', 'routes' => ['merge-pallet.*'], 'roles' => $warehouse],
        'move_pallet' => ['label' => 'Pemindahan Pallet', 'group' => 'Warehouse', 'icon' => 'arrow-left-right', 'route' => 'move-pallet.index', 'routes' => ['move-pallet.*'], 'roles' => $warehouse],
        'export_move_pallet' => ['label' => 'Pemindahan Pallet Ekspor', 'group' => 'Warehouse', 'icon' => 'arrow-left-right', 'route' => 'export.move-pallet.index', 'routes' => ['export.move-pallet.*'], 'roles' => ['export_operator', 'admin_warehouse']],
        'stock_edit' => ['label' => 'Edit Box', 'group' => 'Warehouse', 'routes' => ['stock-view.box-update'], 'roles' => ['admin_warehouse'], 'requires' => ['stock_view']],
        'stock_delete' => ['label' => 'Hapus Box / Pallet', 'group' => 'Warehouse', 'routes' => ['stock-view.box-delete', 'stock-view.pallet-delete'], 'roles' => ['admin_warehouse', 'supervisi'], 'requires' => ['stock_view']],
        'stock_view' => ['label' => 'Stok Lokal', 'group' => 'Warehouse', 'icon' => 'boxes', 'route' => 'stock-view.index', 'routes' => ['stock-view.*', 'api.stock.part-detail', 'api.stock.pallet-detail'], 'roles' => ['warehouse_operator', 'admin_warehouse', 'ppc', 'supervisi']],
        'expired_box' => ['label' => 'Expired Box dan Handle', 'group' => 'Warehouse', 'icon' => 'exclamation-triangle', 'route' => 'expired-box.index', 'routes' => ['expired-box.*'], 'roles' => [...$supervisor, 'admin_warehouse']],
        'export_expired_box' => ['label' => 'Expired Box dan Handle Ekspor', 'group' => 'Warehouse', 'icon' => 'exclamation-triangle', 'route' => 'export.expired-box.index', 'routes' => ['export.expired-box.*'], 'roles' => [...$supervisor, 'admin_warehouse']],
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
        'parts' => ['debug_roles' => ['export_operator'], 'label' => 'Master Nomor Part Bersama', 'group' => 'Master Data', 'icon' => 'list-check', 'route' => 'part-settings.index', 'routes' => ['part-settings.*'], 'roles' => ['admin_warehouse']],
        'export_delivery_plans' => ['label' => 'Rencana Delivery Ekspor', 'group' => 'Pengiriman Ekspor', 'icon' => 'calendar2-check', 'route' => 'export.delivery-plans.index', 'routes' => ['export.delivery-plans.*'], 'roles' => ['export_operator']],
        'export_pallet_plans' => ['label' => 'Perencanaan Pallet Ekspor', 'group' => 'Pengiriman Ekspor', 'icon' => 'boxes', 'route' => 'export.pallet-plans.index', 'routes' => ['export.pallet-plans.*'], 'roles' => ['export_operator']],
        'delivery_announcements' => ['label' => 'Pengumuman Delivery', 'group' => 'Warehouse', 'icon' => 'megaphone',
            'route' => 'delivery-announcements.index', 'routes' => ['delivery-announcements.*'],
            'roles' => ['admin_warehouse', ...$supervisor], 'allowed_roles' => ['admin_warehouse', ...$supervisor]],
        'export_stock_manage' => ['label' => 'Stok Expor', 'group' => 'Pengiriman Ekspor', 'icon' => 'box-seam', 'route' => 'export.stock.index',
            'routes' => ['export.stock.box-manage', 'export.stock.box-update', 'export.stock.box-delete', 'export.stock.box-history',
                'export.stock.pallet-manage', 'export.stock.pallet-limit', 'export.stock.pallet-delete'],
            'roles' => ['admin_warehouse', ...$supervisor], 'allowed_roles' => ['admin_warehouse', ...$supervisor]],
        'export_stock_input' => ['label' => 'Penerimaan Transfer Box Ekspor', 'group' => 'Pengiriman Ekspor', 'icon' => 'upc-scan', 'route' => 'export.receiving.index', 'routes' => ['export.receiving.*'], 'roles' => ['export_operator']],
        'export_merge_pallet' => ['label' => 'Merge Pallet Ekspor', 'group' => 'Pengiriman Ekspor', 'icon' => 'intersect', 'route' => 'export.merge.index', 'routes' => ['export.merge.*'], 'roles' => ['export_operator', 'admin_warehouse']],
        'export_picking_verification' => ['label' => 'Picking Verification Ekspor', 'group' => 'Pengiriman Ekspor', 'icon' => 'search', 'route' => 'export.picking.index', 'routes' => ['export.picking.*'], 'roles' => ['export_operator'], 'requires' => ['export_stock_input']],
        'export_fulfillment' => ['label' => 'Delivery Schedule Ekspor', 'group' => 'Pengiriman Ekspor', 'icon' => 'calendar3', 'route' => 'export.fulfillment.index', 'routes' => ['export.fulfillment.*'], 'roles' => ['export_operator']],
        'export_locations' => ['label' => 'Master Lokasi Ekspor', 'group' => 'Master Data Ekspor', 'icon' => 'geo-alt', 'route' => 'export.locations.index', 'routes' => ['export.locations.*'], 'roles' => ['export_operator']],
        'reports' => ['label' => 'Laporan Barang Masuk', 'group' => 'Laporan', 'icon' => 'file-earmark-bar-graph', 'route' => 'reports.stock-input', 'routes' => ['reports.*'], 'roles' => ['admin_warehouse', 'supervisi']],
        'audit' => ['label' => 'Audit Trail', 'group' => 'Laporan', 'icon' => 'shield-check', 'route' => 'audit.index', 'routes' => ['audit.*'], 'roles' => $supervisor],
        'users' => ['label' => 'Kelola User', 'group' => 'Administrasi', 'icon' => 'people', 'route' => 'users.index', 'routes' => ['users.*'], 'roles' => [], 'admin_only' => true],
        'access_management' => ['label' => 'Hak Akses', 'group' => 'Administrasi', 'icon' => 'key', 'route' => 'access.index', 'routes' => ['access.*'], 'roles' => [], 'admin_only' => true],
    ],
    // Shared read-only lookups are available only to features that consume them.
    'lookups' => [
        'export.stock.index' => ['export_stock_input', 'export_stock_manage'],
        'export.stock.show' => ['export_stock_input', 'export_stock_manage'],
        'export.stock.export' => ['export_stock_input', 'export_stock_manage'],
        'export.stock.pallet-boxes' => ['export_stock_input', 'export_stock_manage'],
        'box-not-full.export.show' => ['not_full_request', 'not_full_approval'],
        'api.stock.by-part' => ['stock_view', 'delivery_order', 'delivery_approval', 'delivery_pick', 'delivery_assign'],
        'api.locations.search' => ['locations', 'stock_input', 'not_full_request', 'not_full_approval', 'delivery_assign', 'merge_pallet', 'move_pallet', 'expired_box', 'delivery_redo'],
        'part-settings.search' => ['export_delivery_plans', 'export_stock_input', 'parts', 'stock_input', 'not_full_request', 'delivery_order', 'delivery_assign', 'stock_edit'],
    ],
];

$config['features']['export_stock_view'] = ['label' => 'Lihat stok ekspor', 'group' => 'Pengiriman Ekspor',
    'icon' => 'boxes', 'route' => 'export.stock.index', 'routes' => ['export.stock.index', 'export.stock.show', 'export.stock.pallet-boxes'],
    'roles' => ['export_operator', 'admin_warehouse', 'supervisi'], 'legacy_sources' => ['export_stock_input', 'export_stock_manage']];
unset($config['features']['export_stock_manage']['route']);
$config['features']['export_stock_manage']['label'] = 'Akses pengelolaan stok ekspor';
$config['features']['export_stock_manage']['requires'] = ['export_stock_view'];
$actions = require __DIR__.'/feature_actions.php';
$actions['export_stock_export'][0] = 'export_stock_view';
foreach (['domestic' => ['expired_box', 'expired-box', 'Lokal'], 'export' => ['export_expired_box', 'export.expired-box', 'Ekspor']] as [$parent, $prefix, $scopeLabel]) {
    foreach (['start' => ['Mulai penanganan QA', ['transactions.create', 'handle']], 'qa' => ['Putuskan hasil QA', ['qa']],
        'packing' => ['Serahkan ke packing', ['packing']], 'receive' => ['Terima hasil packing', ['receive', 'transactions.scan']],
        'print' => ['Cetak pengantar packing', ['print', 'transactions.print']]] as $action => [$label, $routes]) {
        if ($parent === 'export_expired_box') {
            if ($action === 'print') continue; // No print endpoint in the export workspace.
            $routes = array_values(array_intersect($routes, ['transactions.create', 'transactions.scan']));
        }
        $actions[$parent.'_'.$action] = [$parent, $label.' '.$scopeLabel, array_map(fn ($route) => $prefix.'.'.$route, $routes),
            'Menjalankan tahap '.$label.' untuk box expired '.$scopeLabel.'.'];
    }
}
foreach (['stock_opname_local' => ['stock-opname', 'Lokal'], 'stock_opname_export' => ['export.stock-opname', 'Ekspor']] as $parent => [$prefix, $scopeLabel]) {
    foreach (['start' => ['Mulai sesi', 'start'], 'scan' => ['Scan box', 'scan'], 'refresh' => ['Perbarui acuan sesi', 'refresh'], 'finish' => ['Selesaikan sesi', 'finish']] as $action => [$label, $route]) {
        $actions[$parent.'_'.$action] = [$parent.'_manage', $label.' Stock Opname '.$scopeLabel, [$prefix.'.'.$route], 'Menjalankan tindakan '.$label.' pada sesi opname '.$scopeLabel.'.'];
    }
    $actions[$parent.'_download'] = [$parent, 'Unduh hasil Stock Opname '.$scopeLabel, [$prefix.'.export'], 'Mengunduh snapshot hasil sesi yang selesai.'];
}
$children = [];
foreach ($actions as $key => [$parent, $label, $routes, $description]) {
    $source = $config['features'][$parent];
    $allowed = $actions[$key][4] ?? ($source['allowed_roles'] ?? null);
    $children[$key] = ['label' => $label, 'description' => $description, 'group' => $source['group'],
        'module' => $source['label'], 'parent' => $parent, 'legacy_parent' => $parent,
        'routes' => $routes, 'roles' => array_values(array_filter($source['roles'], fn ($role) => !$allowed || in_array($role, $allowed, true))),
        'requires' => [$parent]];
    if ($allowed) $children[$key]['allowed_roles'] = $allowed;
    if (isset($source['debug_roles'])) $children[$key]['debug_roles'] = array_values(array_filter($source['debug_roles'], fn ($role) => !$allowed || in_array($role, $allowed, true)));
}
$children['export_fulfillment_cancel']['requires'][] = 'export_delivery_delete';
$children['export_stock_export']['legacy_sources'] = ['export_stock_input', 'export_stock_manage'];
$children['dashboard_details']['requires'] = [];
$children['dashboard_details']['legacy_always_roles'] = ['supervisi'];
$children['dashboard_details']['roles'] = ['supervisi'];
$config['features'] = $children + $config['features'];
$config['lookups']['export.stock.index'] = ['export_stock_view'];
$config['lookups']['export.stock.show'] = ['export_stock_view'];
$config['lookups']['export.stock.pallet-boxes'] = ['export_stock_view'];
$config['lookups']['export.stock.export'] = ['export_stock_export'];
$config['lookups']['export.pallet-plans.edit'] = ['export_pallet_edit', 'export_pallet_finalize'];
$config['action_dispatch'] = [
    'delivery.status' => ['field' => 'status', 'choices' => ['approved' => 'delivery_approve', 'rejected' => 'delivery_reject', 'correction' => 'delivery_correction']],
    'export.pallet-plans.update' => ['field' => 'action', 'choices' => ['draft' => 'export_pallet_edit', 'finalize' => 'export_pallet_finalize']],
    'export.pallet-plans.transition' => ['field' => 'action', 'choices' => ['reopen' => 'export_pallet_reopen', 'cancel' => 'export_pallet_cancel']],
];
foreach (['expired-box' => 'expired_box', 'export.expired-box' => 'export_expired_box'] as $prefix => $parent) {
    foreach (['bulk.review', 'bulk.store', 'transactions.update'] as $route) {
        $choices = ['qa' => $parent.'_qa', 'packing' => $parent.'_packing', 'receive' => $parent.'_receive'];
        if ($route !== 'transactions.update') $choices = ['start' => $parent.'_start'] + $choices;
        if ($route === 'bulk.review') $choices = $prefix === 'expired-box'
            ? $choices + ['print' => $parent.'_print', 'result' => $parent] : ['start' => $parent.'_start'];
        $config['action_dispatch'][$prefix.'.'.$route] = ['field' => 'action', 'choices' => $choices];
    }
}
$config['features']['export_customers'] = ['label' => 'Master Customer', 'group' => 'Master Data Ekspor', 'icon' => 'people',
    'route' => 'export.customers.index', 'routes' => ['export.customers.index'], 'roles' => ['export_operator', 'admin_warehouse', 'supervisi']];
$config['features']['export_customers_manage'] = ['label' => 'Kelola master customer Ekspor', 'group' => 'Master Data Ekspor',
    'routes' => ['export.customers.create', 'export.customers.edit', 'export.customers.store', 'export.customers.update'],
    'roles' => [], 'requires' => ['export_customers'], 'description' => 'Menambah atau memperbarui kode, nama, alias dan status customer.'];
$config['features']['export_stock_customer_manage'] = ['label' => 'Kelola reservasi customer Ekspor', 'group' => 'Pengiriman Ekspor',
    'routes' => ['export.stock.customer-reservations', 'export.customer-delivery.link'], 'roles' => [], 'requires' => ['export_stock_view'],
    'description' => 'Menetapkan/melepas pemilik box yang bebas dan memetakan customer delivery lama, dengan alasan dan audit.'];
$config['features']['export_stock_customer_exception'] = ['label' => 'Gunakan stok customer lain', 'group' => 'Pengiriman Ekspor',
    'routes' => ['export.customer-exceptions.*'], 'roles' => [], 'requires' => ['export_fulfillment'],
    'description' => 'Mengizinkan box customer sumber untuk satu delivery, dengan alasan wajib tanpa mengubah pemilik.'];
$config['lookups']['export.customers.options'] = ['export_delivery_plans', 'export_stock_input', 'export_stock_view', 'export_customers'];
return $config;
