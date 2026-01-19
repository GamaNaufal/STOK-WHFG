<?php

namespace App\Http\Controllers;

use App\Models\Pallet;
use App\Models\PalletItem;
use App\Models\StockLocation;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $userRole = auth()->user()->role;
        $stats = [];
        
        // Helper function to calculate actual boxes based on PCS
        $calculateActualBoxes = function() {
            $totalPcs = PalletItem::sum('pcs_quantity') ?? 0;
            $totalBox = PalletItem::sum('box_quantity') ?? 0;
            
            if ($totalBox > 0 && $totalPcs > 0) {
                $pcsPerBox = $totalPcs / $totalBox;
                return ceil($totalPcs / $pcsPerBox);
            }
            return 0;
        };

        if ($userRole === 'packing_department') {
            // Packing department - show pallet input statistics
            $stats = [
                'total_pallets' => Pallet::count(),
                'today_pallets' => Pallet::whereDate('created_at', today())->count(),
                'total_items' => PalletItem::count(),
                'total_box' => $calculateActualBoxes(),
                'total_pcs' => PalletItem::sum('pcs_quantity') ?? 0,
            ];
        } elseif ($userRole === 'warehouse_operator') {
            // Warehouse operator - show stock location statistics
            $stats = [
                'total_pallets' => Pallet::count(),
                'pallets_with_location' => Pallet::has('stockLocation')->count(),
                'pallets_without_location' => Pallet::doesntHave('stockLocation')->count(),
                'total_locations' => StockLocation::distinct('warehouse_location')->count(),
                'total_items' => PalletItem::count(),
                'total_pcs' => PalletItem::sum('pcs_quantity') ?? 0,
            ];
        } elseif ($userRole === 'admin') {
            // Admin - show all statistics
            $stats = [
                'total_pallets' => Pallet::count(),
                'today_pallets' => Pallet::whereDate('created_at', today())->count(),
                'pallets_with_location' => Pallet::has('stockLocation')->count(),
                'total_locations' => StockLocation::distinct('warehouse_location')->count(),
                'total_items' => PalletItem::count(),
                'total_box' => $calculateActualBoxes(),
                'total_pcs' => PalletItem::sum('pcs_quantity') ?? 0,
            ];
        }

        return view('shared.dashboard', compact('stats', 'userRole'));
    }
}
