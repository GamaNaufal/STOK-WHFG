<?php

namespace App\Http\Controllers;

use App\Models\Box;
use App\Models\Pallet;
use App\Models\PalletItem;
use App\Models\StockLocation;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        if ($user->role !== 'admin') {
            if ($user->role === 'warehouse_operator') return redirect()->route('stock-input.index');
            if ($user->role === 'sales') return redirect()->route('delivery.create');
            if ($user->role === 'ppc') return redirect()->route('delivery.approvals');
            // If unknown role?
            return redirect()->route('login');
        }

        $userRole = $user->role;
        $stats = [];
        
        // Helper function to calculate actual boxes based on PCS
        $calculateActualBoxes = function() {
            $totalPcs = PalletItem::where(function ($q) {
                $q->where('pcs_quantity', '>', 0)
                  ->orWhere('box_quantity', '>', 0);
            })->sum('pcs_quantity') ?? 0;
            $totalBox = PalletItem::where(function ($q) {
                $q->where('pcs_quantity', '>', 0)
                  ->orWhere('box_quantity', '>', 0);
            })->sum('box_quantity') ?? 0;
            
            return (int)$totalBox;
        };

        if ($userRole === 'packing_department') {
            // Packing department - DEPRECATED, no longer used
            $stats = [];
        } elseif ($userRole === 'warehouse_operator') {
            // Warehouse operator - show stock location statistics
            // pallets_with_location = pallets yang saat ini memiliki stok aktual dan punya lokasi
            $palletsWithActiveStock = Pallet::whereHas('items', function ($q) {
                $q->where(function ($subQ) {
                    $subQ->where('pcs_quantity', '>', 0)
                         ->orWhere('box_quantity', '>', 0);
                });
            })->has('stockLocation')->count();

            $stats = [
                'total_pallets' => Pallet::count(),
                'pallets_with_location' => $palletsWithActiveStock,
                'pallets_without_location' => Pallet::doesntHave('stockLocation')->count(),
                'total_locations' => StockLocation::distinct('warehouse_location')->count(),
                'total_items' => PalletItem::where(function ($q) {
                    $q->where('pcs_quantity', '>', 0)
                      ->orWhere('box_quantity', '>', 0);
                })->count(),
                'total_pcs' => PalletItem::where(function ($q) {
                    $q->where('pcs_quantity', '>', 0)
                      ->orWhere('box_quantity', '>', 0);
                })->sum('pcs_quantity') ?? 0,
            ];
        } elseif ($userRole === 'admin') {
            // Admin - show all statistics + box management
            $palletsWithActiveStock = Pallet::whereHas('items', function ($q) {
                $q->where(function ($subQ) {
                    $subQ->where('pcs_quantity', '>', 0)
                         ->orWhere('box_quantity', '>', 0);
                });
            })->count();

            $stats = [
                'total_pallets' => Pallet::count(),
                'today_pallets' => Pallet::whereDate('created_at', today())->count(),
                'pallets_with_location' => $palletsWithActiveStock,
                'total_locations' => StockLocation::distinct('warehouse_location')->count(),
                'total_items' => PalletItem::where(function ($q) {
                    $q->where('pcs_quantity', '>', 0)
                      ->orWhere('box_quantity', '>', 0);
                })->count(),
                'total_box' => $calculateActualBoxes(),
                'total_pcs' => PalletItem::where(function ($q) {
                    $q->where('pcs_quantity', '>', 0)
                      ->orWhere('box_quantity', '>', 0);
                })->sum('pcs_quantity') ?? 0,
                'total_qr_boxes' => Box::count(),
                'today_qr_boxes' => Box::whereDate('created_at', today())->count(),
            ];
        }

        return view('shared.dashboard', compact('stats', 'userRole'));
    }
}

