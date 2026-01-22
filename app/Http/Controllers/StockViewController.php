<?php

namespace App\Http\Controllers;

use App\Models\Pallet;
use App\Models\PalletItem;
use App\Models\StockLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockViewController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        // Get pallet items grouped by part_number with their pallet and stock location
        // Order by created_at ASC untuk FIFO (First In First Out)
        // Filter: hanya items dengan stok > 0 (pcs_quantity > 0 atau box_quantity > 0)
        $query = PalletItem::with('pallet.stockLocation')
            ->where(function ($q) {
                $q->where('pcs_quantity', '>', 0)
                  ->orWhere('box_quantity', '>', 0);
            })
            ->orderBy('created_at', 'asc');

        if ($search) {
            $query->where('part_number', 'like', '%' . $search . '%');
        }

        $items = $query->get();

        // Group by part_number and calculate totals
        // Keep items sorted by FIFO order
        $groupedByPart = $items->groupBy('part_number')->map(function ($itemGroup) {
            $totalPcs = $itemGroup->sum('pcs_quantity');
            $totalBox = $itemGroup->sum('box_quantity');
            
            return [
                'part_number' => $itemGroup->first()->part_number,
                'total_box' => (int)$totalBox, // Direct box quantity
                'total_pcs' => $totalPcs,
                'items' => $itemGroup->sortBy('created_at'), // FIFO order
            ];
        });

        return view('shared.stock-view.index', compact('groupedByPart', 'search'));
    }

    public function show($pallet_id)
    {
        $pallet = Pallet::with('items', 'stockLocation')->findOrFail($pallet_id);

        return view('shared.stock-view.detail', compact('pallet'));
    }

    // API: Get stock grouped by part number
    public function apiGetStockByPart()
    {
        $items = PalletItem::where(function ($q) {
                $q->where('pcs_quantity', '>', 0)
                  ->orWhere('box_quantity', '>', 0);
            })
            ->orderBy('created_at', 'asc')
            ->get();

        $groupedByPart = $items->groupBy('part_number')->map(function ($itemGroup) {
            $totalPcs = $itemGroup->sum('pcs_quantity');
            $totalBox = $itemGroup->sum('box_quantity');
            
            return [
                'part_number' => $itemGroup->first()->part_number,
                'total_box' => (int)$totalBox,
                'total_pcs' => $totalPcs,
            ];
        })->values();

        return response()->json($groupedByPart);
    }

    // API: Get detailed information for a specific part number
    public function apiGetPartDetail($partNumber)
    {
        $items = PalletItem::with('pallet.stockLocation')
            ->where('part_number', $partNumber)
            ->where(function ($q) {
                $q->where('pcs_quantity', '>', 0)
                  ->orWhere('box_quantity', '>', 0);
            })
            ->orderBy('created_at', 'asc')
            ->get();

        if ($items->isEmpty()) {
            return response()->json(['error' => 'Part not found'], 404);
        }

        $totalPcs = $items->sum('pcs_quantity');
        $totalBox = $items->sum('box_quantity');

        $palletDetails = $items->map(function ($item) {
            return [
                'pallet_number' => $item->pallet->pallet_number,
                'pcs_quantity' => $item->pcs_quantity,
                'box_quantity' => $item->box_quantity,
                'location' => $item->pallet->stockLocation ? $item->pallet->stockLocation->warehouse_location : 'N/A',
                'created_at' => $item->created_at->format('d/m/Y H:i'),
            ];
        });

        return response()->json([
            'part_number' => $partNumber,
            'total_pcs' => $totalPcs,
            'total_box' => $totalBox,
            'pallet_count' => $items->count(),
            'pallets' => $palletDetails,
        ]);
    }
}
