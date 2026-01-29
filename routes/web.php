<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BoxController;
use App\Http\Controllers\BarcodeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\StockInputController;
use App\Http\Controllers\StockViewController;
use App\Http\Controllers\StockWithdrawalController;
use App\Http\Controllers\ReportController;

// Auth Routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Protected Routes
Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Admin Routes - Box Management (QR Code Generation)
    Route::middleware('role:admin')->group(function () {
        Route::get('/boxes', [BoxController::class, 'index'])->name('boxes.index');
        Route::get('/boxes/create', [BoxController::class, 'create'])->name('boxes.create');
        Route::post('/boxes', [BoxController::class, 'store'])->name('boxes.store');
        Route::get('/boxes/{box}', [BoxController::class, 'show'])->name('boxes.show');
        Route::delete('/boxes/{box}', [BoxController::class, 'destroy'])->name('boxes.destroy');
        
        // CRUD Users (Kelola User)
        Route::resource('users', \App\Http\Controllers\UserController::class);
    });

    // Master Locations - Admin Warehouse + Admin IT
    Route::middleware('role:admin_warehouse,admin')->group(function () {
        Route::resource('locations', \App\Http\Controllers\MasterLocationController::class);
    });

    // Admin Warehouse Routes - Master Part & Qty
    Route::middleware('role:admin_warehouse,admin')->group(function () {
        Route::get('part-settings/search', [\App\Http\Controllers\PartSettingController::class, 'search'])->name('part-settings.search');
        Route::resource('part-settings', \App\Http\Controllers\PartSettingController::class)->except(['show']);
    });

    // Barcode Scanner Routes
    Route::middleware('role:admin,warehouse_operator')->group(function () {
        Route::get('/barcode-scanner', [BarcodeController::class, 'scanner'])->name('barcode.scanner');
        Route::post('/barcode-scanner/scan', [BarcodeController::class, 'scan'])->name('barcode.scan');
    });

    // Stock Input Routes (Warehouse Operator) - Scan QR Box
    Route::middleware('role:warehouse_operator,admin')->group(function () {
        Route::get('/stock-input', [StockInputController::class, 'index'])->name('stock-input.index');
        Route::post('/stock-input/scan-box', [StockInputController::class, 'scanBox'])->name('stock-input.scan-box');
        Route::post('/stock-input/scan-barcode', [StockInputController::class, 'scanBarcode'])->name('stock-input.scan-barcode');
        Route::post('/stock-input/scan-part', [StockInputController::class, 'scanPartNumber'])->name('stock-input.scan-part');
        Route::get('/stock-input/get-pallet-data', [StockInputController::class, 'getCurrentPalletData'])->name('stock-input.get-pallet-data');
        Route::post('/stock-input/clear-session', [StockInputController::class, 'clearSession'])->name('stock-input.clear-session');
        Route::post('/stock-input/store', [StockInputController::class, 'store'])->name('stock-input.store');
    });

    // Delivery Stock Routes (Sales, PPC, Warehouse, Admin) - Replaces Stock Withdrawal
    Route::middleware('auth')->group(function () {
        
        // Main Dashboard (Schedule Only)
        Route::get('/delivery-stock', [\App\Http\Controllers\DeliveryOrderController::class, 'index'])->name('delivery.index');
        
        // Sales Side (Input & History)
        Route::get('/delivery-stock/sales-input', [\App\Http\Controllers\DeliveryOrderController::class, 'createOrder'])->name('delivery.create');
        Route::post('/delivery-stock/store', [\App\Http\Controllers\DeliveryOrderController::class, 'store'])->name('delivery.store');
        Route::get('/delivery-stock/{id}/edit', [\App\Http\Controllers\DeliveryOrderController::class, 'edit'])->name('delivery.edit');
        Route::put('/delivery-stock/{id}', [\App\Http\Controllers\DeliveryOrderController::class, 'update'])->name('delivery.update');
        
        // PPC Side (Approvals)
        Route::get('/delivery-stock/approvals', [\App\Http\Controllers\DeliveryOrderController::class, 'pendingApprovals'])->name('delivery.approvals');
        Route::get('/delivery-stock/{id}/approval-impact', [\App\Http\Controllers\DeliveryOrderController::class, 'approvalImpact'])->name('delivery.approval-impact');
        Route::post('/delivery-stock/{id}/status', [\App\Http\Controllers\DeliveryOrderController::class, 'updateStatus'])->name('delivery.status');
        Route::delete('/delivery-stock/{id}', [\App\Http\Controllers\DeliveryOrderController::class, 'destroy'])->name('delivery.destroy');
        
        // Warehouse Execution (Fulfillment) - Uses StockWithdrawalController logic
        Route::get('/delivery-stock/{id}/fulfill', [StockWithdrawalController::class, 'fulfillOrder'])->name('delivery.fulfill');
        Route::get('/delivery-stock/{id}/fulfill-data', [\App\Http\Controllers\DeliveryOrderController::class, 'fulfillData'])->name('delivery.fulfill-data');
        Route::post('/delivery-stock/confirm-withdrawal', [StockWithdrawalController::class, 'confirm'])->name('stock-withdrawal.confirm'); // Keep logic

        // Picklist + Scan Flow
        Route::post('/delivery-stock/{id}/start-pick', [\App\Http\Controllers\DeliveryPickController::class, 'startPick'])->name('delivery.pick.start');
        Route::get('/delivery-stock/{order}/pick/{session}/pdf', [\App\Http\Controllers\DeliveryPickController::class, 'pdf'])->name('delivery.pick.pdf');
        Route::get('/delivery-stock/{order}/pick/{session}/scan', [\App\Http\Controllers\DeliveryPickController::class, 'showScan'])->name('delivery.pick.scan');
        Route::post('/delivery-pick/{session}/scan', [\App\Http\Controllers\DeliveryPickController::class, 'scanBox'])->name('delivery.pick.scan.submit');
        Route::post('/delivery-pick/{session}/complete', [\App\Http\Controllers\DeliveryPickController::class, 'complete'])->name('delivery.pick.complete');
        
        // Legacy/Utility routes for withdrawal logic (Search, Preview)
        Route::post('/stock-withdrawal/search', [StockWithdrawalController::class, 'searchParts'])->name('stock-withdrawal.search');
        Route::post('/stock-withdrawal/preview', [StockWithdrawalController::class, 'preview'])->name('stock-withdrawal.preview');
    });

    // Merge Pallet Routes (Warehouse Operator + Admin)
    Route::middleware('role:warehouse_operator,admin')->group(function () {
        Route::get('/merge-pallet', [\App\Http\Controllers\MergePalletController::class, 'index'])->name('merge-pallet.index');
        Route::get('/merge-pallet/search', [\App\Http\Controllers\MergePalletController::class, 'searchPallet'])->name('merge-pallet.search');
        Route::post('/merge-pallet/store', [\App\Http\Controllers\MergePalletController::class, 'store'])->name('merge-pallet.store');
    });

    // Stock View Routes (Warehouse Operator, PPC, Admin Warehouse, Supervisi, Admin)
    Route::middleware('role:warehouse_operator,ppc,admin_warehouse,supervisi,admin')->group(function () {
        Route::get('/stock-view', [StockViewController::class, 'index'])->name('stock-view.index');
        Route::get('/stock-view/export-part', [StockViewController::class, 'exportByPart'])->name('stock-view.export-part');
        Route::get('/stock-view/export-pallet', [StockViewController::class, 'exportByPallet'])->name('stock-view.export-pallet');
    });

    // Reports - Supervisi + Admin IT
    Route::middleware('role:supervisi,admin')->group(function () {
        Route::get('/reports/withdrawal', [ReportController::class, 'withdrawalReport'])->name('reports.withdrawal');
        Route::get('/reports/stock-input', [ReportController::class, 'stockInputReport'])->name('reports.stock-input');
        Route::get('/reports/operational/export', [ReportController::class, 'exportOperationalExcel'])->name('reports.operational.export');

        // Audit Trail Report
        Route::get('/audit-trail', [\App\Http\Controllers\AuditController::class, 'index'])->name('audit.index');
        Route::get('/audit-trail/export', [\App\Http\Controllers\AuditController::class, 'export'])->name('audit.export');

        // Export
        Route::get('/reports/withdrawal-export', [ReportController::class, 'exportWithdrawalCsv'])->name('reports.withdrawal.export');
        Route::get('/reports/stock-input-export', [ReportController::class, 'exportStockInputCsv'])->name('reports.stock-input.export');
    });

    // Scan Issue Notifications (Admin Warehouse + Admin IT)
    Route::middleware('role:admin_warehouse,admin')->group(function () {
        Route::get('/delivery-scan-issues', [\App\Http\Controllers\DeliveryPickController::class, 'issues'])->name('delivery.pick.issues');
        Route::post('/delivery-scan-issues/{issue}/approve', [\App\Http\Controllers\DeliveryPickController::class, 'approveIssue'])->name('delivery.pick.issue.approve');
        Route::post('/delivery-completions/{completion}/redo', [\App\Http\Controllers\DeliveryPickController::class, 'redo'])->name('delivery.pick.redo');
    });

    // API Routes (Accessible by auth users, usually consumed by frontend scripts on allowed pages)
    Route::get('/api/stock/by-part', [StockViewController::class, 'apiGetStockByPart']);
    Route::get('/api/stock/part-detail/{partNumber}', [StockViewController::class, 'apiGetPartDetail']);
    Route::get('/api/stock/pallet-detail/{palletId}', [StockViewController::class, 'apiGetPalletDetail']);
    Route::get('/api/locations/search', [\App\Http\Controllers\MasterLocationController::class, 'apiSearchAvailable']); // Search Available Locations

});

