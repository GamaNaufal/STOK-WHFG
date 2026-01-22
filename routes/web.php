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
Route::post('/login', [AuthController::class, 'login']);
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
        Route::get('/stock-input/get-pallet-data', [StockInputController::class, 'getCurrentPalletData'])->name('stock-input.get-pallet-data');
        Route::post('/stock-input/clear-session', [StockInputController::class, 'clearSession'])->name('stock-input.clear-session');
        Route::post('/stock-input/store', [StockInputController::class, 'store'])->name('stock-input.store');
    });

    // Stock Withdrawal Routes (Warehouse Operator)
    Route::middleware('role:warehouse_operator,admin')->group(function () {
        Route::get('/stock-withdrawal', [StockWithdrawalController::class, 'index'])->name('stock-withdrawal.index');
        Route::post('/stock-withdrawal/search', [StockWithdrawalController::class, 'searchParts'])->name('stock-withdrawal.search');
        Route::post('/stock-withdrawal/preview', [StockWithdrawalController::class, 'preview'])->name('stock-withdrawal.preview');
        Route::post('/stock-withdrawal/confirm', [StockWithdrawalController::class, 'confirm'])->name('stock-withdrawal.confirm');
        Route::post('/stock-withdrawal/{withdrawal}/undo', [StockWithdrawalController::class, 'undo'])->name('stock-withdrawal.undo');
        Route::get('/stock-withdrawal/history', [StockWithdrawalController::class, 'history'])->name('stock-withdrawal.history');
    });

    // Stock View Routes (All users)
    Route::get('/stock-view', [StockViewController::class, 'index'])->name('stock-view.index');
    Route::get('/stock-view/{pallet}', [StockViewController::class, 'show'])->name('stock-view.show');

    // Report Routes (Warehouse Operator & Admin)
    Route::middleware('role:warehouse_operator,admin')->group(function () {
        Route::get('/reports/withdrawal', [ReportController::class, 'withdrawalReport'])->name('reports.withdrawal');
        Route::get('/reports/stock-input', [ReportController::class, 'stockInputReport'])->name('reports.stock-input');
    });

    // Export Routes with query parameter handling
    Route::middleware('role:warehouse_operator,admin')->group(function () {
        Route::get('/reports/withdrawal-export', [ReportController::class, 'exportWithdrawalCsv'])->name('reports.withdrawal.export');
        Route::get('/reports/stock-input-export', [ReportController::class, 'exportStockInputCsv'])->name('reports.stock-input.export');
    });
});

