<?php

namespace App\Http\Controllers;

use App\Models\Pallet;
use App\Models\PalletItem;
use App\Models\StockLocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockViewController extends Controller
{
    private function buildStockItems(?string $search = null)
    {
        $palletQuery = Pallet::with(['stockLocation', 'boxes', 'items'])
            ->whereHas('stockLocation', function ($q) {
                $q->where('warehouse_location', '!=', 'Unknown');
            });

        $pallets = $palletQuery->get();
        $items = collect();

        foreach ($pallets as $pallet) {
            $location = $pallet->stockLocation->warehouse_location ?? 'Unknown';

            if ($pallet->boxes->count() > 0) {
                $boxes = $pallet->boxes->where('is_withdrawn', false);

                if ($search) {
                    $boxes = $boxes->filter(function ($box) use ($search, $pallet) {
                        return stripos($box->part_number, $search) !== false
                            || stripos($pallet->pallet_number, $search) !== false;
                    });
                }

                foreach ($boxes as $box) {
                    $items->push([
                        'pallet_id' => $pallet->id,
                        'pallet_number' => $pallet->pallet_number,
                        'location' => $location,
                        'part_number' => $box->part_number,
                        'box_quantity' => 1,
                        'pcs_quantity' => (int) $box->pcs_quantity,
                        'created_at' => $box->created_at,
                    ]);
                }
            } else {
                $legacyItems = $pallet->items->filter(function ($item) {
                    return $item->pcs_quantity > 0 || $item->box_quantity > 0;
                });

                if ($search) {
                    $legacyItems = $legacyItems->filter(function ($item) use ($search, $pallet) {
                        return stripos($item->part_number, $search) !== false
                            || stripos($pallet->pallet_number, $search) !== false;
                    });
                }

                foreach ($legacyItems as $item) {
                    $items->push([
                        'pallet_id' => $pallet->id,
                        'pallet_number' => $pallet->pallet_number,
                        'location' => $location,
                        'part_number' => $item->part_number,
                        'box_quantity' => (int) $item->box_quantity,
                        'pcs_quantity' => (int) $item->pcs_quantity,
                        'created_at' => $item->created_at,
                    ]);
                }
            }
        }

        return $items->sortBy('created_at');
    }

    public function index(Request $request)
    {
        $search = $request->input('search');
        $viewMode = $request->input('view_mode', 'part'); // Default view by part

        $items = $this->buildStockItems($search);
        // Calculate total pallets from the filtered items
        $totalPallets = $items->pluck('pallet_id')->unique()->count();

        // Data for "By Part" view
        $groupedByPart = collect();
        if ($viewMode === 'part') {
            $groupedByPart = $items->groupBy('part_number')->map(function ($itemGroup) {
                $totalPcs = $itemGroup->sum('pcs_quantity');
                $totalBox = $itemGroup->sum('box_quantity');

                return [
                    'part_number' => $itemGroup->first()['part_number'],
                    'total_box' => (int) $totalBox,
                    'total_pcs' => $totalPcs,
                    'items' => $itemGroup->sortBy('created_at'),
                ];
            });
        }

        // Data for "By Pallet" view
        $groupedByPallet = collect();
        if ($viewMode === 'pallet') {
            $groupedByPallet = $items->groupBy('pallet_id')->map(function ($itemGroup) {
                $firstItem = $itemGroup->first();
                $totalPcs = $itemGroup->sum('pcs_quantity');
                $totalBox = $itemGroup->sum('box_quantity');

                return [
                    'pallet_id' => $firstItem['pallet_id'],
                    'pallet_number' => $firstItem['pallet_number'],
                    'location' => $firstItem['location'] ?? 'Unknown',
                    'total_box' => (int) $totalBox,
                    'total_pcs' => $totalPcs,
                    'items' => $itemGroup,
                ];
            });
        }
        
        // Calculate totals for summary cards regardless of view mode
        // We use the raw items collection to calculate these compatible with both views
        $summaryTotalBox = $items->sum('box_quantity');
        $summaryTotalPcs = $items->sum('pcs_quantity');
        $summaryTotalParts = $items->pluck('part_number')->unique()->count();

        return view('shared.stock-view.index', compact(
            'groupedByPart', 
            'groupedByPallet', 
            'search', 
            'viewMode', 
            'totalPallets',
            'summaryTotalBox',
            'summaryTotalPcs',
            'summaryTotalParts'
        ));
    }

    // API: Get stock grouped by part number
    public function apiGetStockByPart()
    {
        $items = $this->buildStockItems();

        $groupedByPart = $items->groupBy('part_number')->map(function ($itemGroup) {
            $totalPcs = $itemGroup->sum('pcs_quantity');
            $totalBox = $itemGroup->sum('box_quantity');

            return [
                'part_number' => $itemGroup->first()['part_number'],
                'total_box' => (int) $totalBox,
                'total_pcs' => $totalPcs,
            ];
        })->values();

        return response()->json($groupedByPart);
    }

    // API: Get detailed information for a specific part number
    public function apiGetPartDetail($partNumber)
    {
        $items = $this->buildStockItems()
            ->filter(function ($item) use ($partNumber) {
                return $item['part_number'] === $partNumber;
            })
            ->values();

        if ($items->isEmpty()) {
            return response()->json(['error' => 'Part not found'], 404);
        }

        $totalPcs = $items->sum('pcs_quantity');
        $totalBox = $items->sum('box_quantity');

        $palletDetails = $items->map(function ($item) {
            return [
                'pallet_number' => $item['pallet_number'],
                'box_quantity' => $item['box_quantity'],
                'pcs_quantity' => $item['pcs_quantity'],
                'location' => $item['location'] ?? 'Unknown',
                'created_at' => $item['created_at']->format('d M Y H:i'),
            ];
        });

        return response()->json([
            'part_number' => $partNumber,
            'total_box' => (int)$totalBox,
            'total_pcs' => $totalPcs,
            'pallet_count' => $items->pluck('pallet_id')->unique()->count(),
            'pallets' => $palletDetails
        ]);
    }

    // API: Get detailed information for a specific pallet
    public function apiGetPalletDetail($palletId)
    {
        $pallet = Pallet::with(['items', 'boxes', 'stockLocation'])->find($palletId);

        if (!$pallet) {
            return response()->json(['error' => 'Pallet not found'], 404);
        }

        // Priority 1: Use Boxes (Source of Truth for FIFO created_at)
        if ($pallet->boxes->count() > 0) {
            $items = $pallet->boxes->where('is_withdrawn', false)->map(function ($box) {
                return [
                    'part_number' => $box->part_number,
                    'box_number' => $box->box_number,
                    'box_quantity' => 1,
                    'pcs_quantity' => (int)$box->pcs_quantity,
                    'created_at' => $box->created_at->format('d M Y H:i'),
                ];
            });
        } else {
            // Priority 2: Fallback using PalletItem (Legacy / No Box Data)
            $items = $pallet->items->where(function ($q) {
                 return $q->pcs_quantity > 0 || $q->box_quantity > 0;
            })->map(function ($item) {
                return [
                    'part_number' => $item->part_number,
                    'box_number' => '-',
                    'box_quantity' => (int)$item->box_quantity,
                    'pcs_quantity' => (int)$item->pcs_quantity,
                    'created_at' => $item->created_at->format('d M Y H:i'),
                ];
            });
        }

        return response()->json([
            'pallet_number' => $pallet->pallet_number,
            'location' => $pallet->stockLocation->warehouse_location ?? 'Unknown',
            'items' => $items->values() 
        ]);
    }
}
