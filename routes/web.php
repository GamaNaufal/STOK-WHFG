<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PalletInputController;
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

    // Packing Department - Input Pallet Routes
    Route::middleware('role:packing_department,admin')->group(function () {
        Route::get('/pallet-input', [PalletInputController::class, 'index'])->name('pallet-input.index');
        Route::get('/pallet-input/create', [PalletInputController::class, 'create'])->name('pallet-input.create');
        Route::post('/pallet-input', [PalletInputController::class, 'store'])->name('pallet-input.store');
        Route::get('/pallet-input/{pallet}/edit', [PalletInputController::class, 'edit'])->name('pallet-input.edit');
        Route::put('/pallet-input/{pallet}', [PalletInputController::class, 'update'])->name('pallet-input.update');
        Route::delete('/pallet-input/{pallet}', [PalletInputController::class, 'destroy'])->name('pallet-input.destroy');
        Route::get('/api/pallet-input/part-numbers', [PalletInputController::class, 'getPartNumbers'])->name('pallet-input.api.parts');
    });

    // Stock Input Routes (Warehouse Operator)
    Route::middleware('role:warehouse_operator,admin')->group(function () {
        Route::get('/stock-input', [StockInputController::class, 'index'])->name('stock-input.index');
        Route::get('/api/stock-input/pallets', [StockInputController::class, 'getPallets'])->name('stock-input.get-pallets');
        Route::post('/stock-input/search', [StockInputController::class, 'searchPallet'])->name('stock-input.search');
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
