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
        $query = PalletItem::with('pallet.stockLocation')
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
            
            // Calculate actual boxes based on PCS quantity
            // Each box should be recalculated based on current PCS
            $pcsPerBox = $totalBox > 0 ? $totalPcs / $totalBox : 0;
            $actualBoxes = $pcsPerBox > 0 ? ceil($totalPcs / $pcsPerBox) : 0;
            
            return [
                'part_number' => $itemGroup->first()->part_number,
                'total_box' => $actualBoxes, // Use calculated boxes based on PCS
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
}
