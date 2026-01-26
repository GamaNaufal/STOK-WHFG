<?php

namespace App\Http\Controllers;

use App\Models\MasterLocation;
use App\Models\Pallet;
use App\Models\PalletItem;
use App\Models\StockLocation;
use App\Models\StockWithdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StockWithdrawalController extends Controller
{
    /**
     * Show the fulfillment page for a delivery order
     */
    public function fulfillOrder($id)
    {
        $order = \App\Models\DeliveryOrder::with('items')->findOrFail($id);
        return view('delivery.fulfill', compact('order'));
    }

    /**
     * Search for part numbers
     */
    public function searchParts(Request $request)
    {
        $query = $request->input('q', '');

        $boxParts = DB::table('boxes')
            ->join('pallet_boxes', 'boxes.id', '=', 'pallet_boxes.box_id')
            ->join('pallets', 'pallets.id', '=', 'pallet_boxes.pallet_id')
            ->join('stock_locations', 'stock_locations.pallet_id', '=', 'pallets.id')
            ->where('boxes.is_withdrawn', false)
            ->where('stock_locations.warehouse_location', '!=', 'Unknown')
            ->where('boxes.part_number', 'like', '%' . $query . '%')
            ->distinct()
            ->limit(20)
            ->pluck('boxes.part_number');

        $legacyParts = PalletItem::select('part_number')
            ->distinct()
            ->where('part_number', 'like', '%' . $query . '%')
            ->whereHas('pallet', function ($q) {
                $q->whereHas('stockLocation')
                  ->doesntHave('boxes');
            })
            ->limit(20)
            ->pluck('part_number');

        $parts = $boxParts->merge($legacyParts)->unique();

        $results = [];
        foreach ($parts as $partNumber) {
            $totalQty = $this->getTotalStockForPart($partNumber);
            if ($totalQty > 0) {
                $results[] = [
                    'part_number' => $partNumber,
                    'total_stock' => $totalQty,
                ];
            }
        }

        return response()->json(['results' => $results]);
    }

    /**
     * Preview locations for withdrawal (FIFO order)
     */
    public function preview(Request $request)
    {
        $request->validate([
            'part_number' => 'required|string',
            'pcs_quantity' => 'required|integer|min:1',
        ]);

        $partNumber = $request->input('part_number');
        $requestedQty = $request->input('pcs_quantity');

        // Get total available stock
        $totalStock = $this->getTotalStockForPart($partNumber);

        if ($totalStock < $requestedQty) {
            return response()->json([
                'success' => false,
                'message' => "Stok tidak cukup! Available: {$totalStock} PCS, Requested: {$requestedQty} PCS",
                'available' => $totalStock,
                'requested' => $requestedQty,
            ], 422);
        }

        // Get locations in FIFO order
        $locations = $this->getLocationsByFIFO($partNumber, $requestedQty);

        return response()->json([
            'success' => true,
            'part_number' => $partNumber,
            'requested_qty' => $requestedQty,
            'total_available' => $totalStock,
            'locations' => $locations,
        ]);
    }

    /**
     * Confirm and execute withdrawal
     */
    public function confirm(Request $request)
    {
        $request->validate([
            'part_number' => 'required|string',
            'pcs_quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        $partNumber = $request->input('part_number');
        $requestedQty = $request->input('pcs_quantity');
        $notes = $request->input('notes');

        // Get total available stock
        $totalStock = $this->getTotalStockForPart($partNumber);

        if ($totalStock < $requestedQty) {
            return response()->json([
                'success' => false,
                'message' => 'Stok tidak cukup untuk pengambilan ini',
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Generate unique batch ID for this withdrawal request
            $batchId = Str::uuid();

            $remainingQty = $requestedQty;
            $withdrawals = [];

            // Get all pallet items with this part number, sorted by FIFO (oldest first)
            $palletItems = PalletItem::where('part_number', $partNumber)
                ->whereHas('pallet', function ($q) {
                    $q->whereHas('stockLocation');
                })
                ->with(['pallet' => function ($q) {
                    $q->with('stockLocation');
                }])
                ->orderBy('created_at', 'asc')
                ->get();

            foreach ($palletItems as $item) {
                if ($remainingQty <= 0) {
                    break;
                }

                if ($item->pcs_quantity <= 0) {
                    continue;
                }

                // Determine how much to take from this pallet item
                $takeQty = min($remainingQty, $item->pcs_quantity);
                
                // Calculate PCS per box
                $pcsPerBox = $item->box_quantity > 0 ? $item->pcs_quantity / $item->box_quantity : 0;
                
                // Calculate how many boxes to reduce (floor - bulatkan ke bawah)
                $boxesToReduce = $pcsPerBox > 0 ? floor($takeQty / $pcsPerBox) : 0;
                
                // Get warehouse location
                $stockLocation = $item->pallet->stockLocation;
                $warehouseLocation = $stockLocation ? $stockLocation->warehouse_location : 'Unknown';

                // Create withdrawal record
                $withdrawal = StockWithdrawal::create([
                    'withdrawal_batch_id' => $batchId,
                    'user_id' => auth()->id(),
                    'pallet_item_id' => $item->id,
                    'part_number' => $partNumber,
                    'pcs_quantity' => $takeQty,
                    'box_quantity' => $boxesToReduce,
                    'warehouse_location' => $warehouseLocation,
                    'status' => 'completed',
                    'notes' => $notes,
                    'withdrawn_at' => now(),
                ]);

                $withdrawals[] = $withdrawal;

                // Update pallet item quantity (both PCS and Box)
                $item->pcs_quantity -= $takeQty;
                $item->box_quantity -= $boxesToReduce;
                $item->save();

                // Auto-update master location jika pallet kosong
                $masterLocation = MasterLocation::where('current_pallet_id', $item->pallet_id)->first();
                if ($masterLocation) {
                    $masterLocation->autoVacateIfEmpty();
                }

                // Don't delete pallet immediately - keep it for undo operations
                // Just mark items with 0 quantity (logical deletion)

                $remainingQty -= $takeQty;
            }

            // DELIVERY ORDER FULFILLMENT LOGIC
            // If this withdrawal is linked to a DeliveryOrderItem
            $deliveryOrderItemId = $request->input('delivery_order_item_id');
            if ($deliveryOrderItemId) {
                $deliveryItem = \App\Models\DeliveryOrderItem::find($deliveryOrderItemId);
                if ($deliveryItem) {
                    $deliveryItem->fulfilled_quantity += $requestedQty; // Assuming full success of requested
                    $deliveryItem->save();

                    // Check if Parent Order is Complete
                    $order = $deliveryItem->order;
                    $isComplete = true;
                    foreach ($order->items as $checkItem) {
                        if ($checkItem->fulfilled_quantity < $checkItem->quantity) {
                            $isComplete = false;
                            break;
                        }
                    }
                    if ($isComplete) {
                        $order->status = 'completed';
                        $order->save();
                    } else {
                        // Mark as processing if not already
                        if($order->status !== 'processing') {
                            $order->status = 'processing';
                            $order->save();
                        }
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Pengambilan stok berhasil! {$requestedQty} PCS {$partNumber} telah diambil",
                'withdrawals' => $withdrawals,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store withdrawal - process multiple items from cart
     */
    public function store(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.part_number' => 'required|string',
            'items.*.pcs_quantity' => 'required|integer|min:1',
        ]);

        $items = $request->input('items');

        try {
            DB::beginTransaction();

            $batchId = Str::uuid();

            // Process each item in the cart
            foreach ($items as $cartItem) {
                $partNumber = $cartItem['part_number'];
                $requestedQty = $cartItem['pcs_quantity'];

                // Get total available stock
                $totalStock = $this->getTotalStockForPart($partNumber);

                if ($totalStock < $requestedQty) {
                    throw new \Exception("Stok tidak cukup untuk part {$partNumber}! Available: {$totalStock} PCS, Requested: {$requestedQty} PCS");
                }

                $remainingQty = $requestedQty;

                // Get all pallet items with this part number, sorted by FIFO
                $palletItems = PalletItem::where('part_number', $partNumber)
                    ->where('pcs_quantity', '>', 0)
                    ->whereHas('pallet', function ($q) {
                        $q->whereHas('stockLocation');
                    })
                    ->with(['pallet' => function ($q) {
                        $q->with('stockLocation');
                    }])
                    ->orderBy('created_at', 'asc')
                    ->get();

                foreach ($palletItems as $item) {
                    if ($remainingQty <= 0) {
                        break;
                    }

                    if ($item->pcs_quantity <= 0) {
                        continue;
                    }

                    // Determine how much to take from this pallet item
                    $takeQty = min($remainingQty, $item->pcs_quantity);
                    
                    // Calculate PCS per box
                    $pcsPerBox = $item->box_quantity > 0 ? $item->pcs_quantity / $item->box_quantity : 0;
                    
                    // Calculate how many boxes to reduce
                    $boxesToReduce = $pcsPerBox > 0 ? floor($takeQty / $pcsPerBox) : 0;
                    
                    // Get warehouse location
                    $stockLocation = $item->pallet->stockLocation;
                    $warehouseLocation = $stockLocation ? $stockLocation->warehouse_location : 'Unknown';

                    // Create withdrawal record
                    StockWithdrawal::create([
                        'withdrawal_batch_id' => $batchId,
                        'user_id' => auth()->id(),
                        'pallet_item_id' => $item->id,
                        'part_number' => $partNumber,
                        'pcs_quantity' => $takeQty,
                        'box_quantity' => $boxesToReduce,
                        'warehouse_location' => $warehouseLocation,
                        'status' => 'completed',
                        'notes' => null,
                        'withdrawn_at' => now(),
                    ]);

                    // Update pallet item quantity
                    $item->pcs_quantity -= $takeQty;
                    $item->box_quantity -= $boxesToReduce;
                    $item->save();

                    // Auto-update master location jika pallet kosong
                    $masterLocation = MasterLocation::where('current_pallet_id', $item->pallet_id)->first();
                    if ($masterLocation) {
                        $masterLocation->autoVacateIfEmpty();
                    }

                    $remainingQty -= $takeQty;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pengambilan stok dari ' . count($items) . ' part berhasil diproses',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Preview locations for multiple items from cart
     */
    public function previewCart(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.part_number' => 'required|string',
            'items.*.pcs_quantity' => 'required|integer|min:1',
        ]);

        $items = $request->input('items');
        $previewData = [];

        try {
            foreach ($items as $cartItem) {
                $partNumber = $cartItem['part_number'];
                $requestedQty = $cartItem['pcs_quantity'];

                // Get total available stock
                $totalStock = $this->getTotalStockForPart($partNumber);

                if ($totalStock < $requestedQty) {
                    return response()->json([
                        'success' => false,
                        'message' => "Stok tidak cukup untuk part {$partNumber}! Available: {$totalStock} PCS, Requested: {$requestedQty} PCS",
                    ], 422);
                }

                // Get locations in FIFO order
                $locations = $this->getLocationsByFIFO($partNumber, $requestedQty);

                $previewData[] = [
                    'part_number' => $partNumber,
                    'requested_qty' => $requestedQty,
                    'total_available' => $totalStock,
                    'locations' => $locations,
                ];
            }

            return response()->json([
                'success' => true,
                'preview' => $previewData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Undo a withdrawal
     */
    public function undo($withdrawalId)
    {
        try {
            DB::beginTransaction();

            $withdrawal = StockWithdrawal::findOrFail($withdrawalId);

            if ($withdrawal->status === 'reversed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Withdrawal ini sudah di-undo sebelumnya',
                ], 422);
            }

            // Get all withdrawals in this batch
            $batchId = $withdrawal->withdrawal_batch_id;
            $batchWithdrawals = StockWithdrawal::where('withdrawal_batch_id', $batchId)
                ->where('status', 'completed')
                ->get();

            // Undo all withdrawals in the batch
            foreach ($batchWithdrawals as $w) {
                $palletItem = $w->palletItem;

                if ($palletItem) {
                    // Restore both PCS and Box quantity
                    $palletItem->pcs_quantity += $w->pcs_quantity;
                    $palletItem->box_quantity += $w->box_quantity;
                    $palletItem->save();
                }

                // Mark withdrawal as reversed
                $w->status = 'reversed';
                $w->save();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal berhasil di-undo',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get total stock for a part number (exclude items with 0 quantity)
     */
    private function getTotalStockForPart($partNumber)
    {
        $boxTotal = DB::table('boxes')
            ->join('pallet_boxes', 'boxes.id', '=', 'pallet_boxes.box_id')
            ->join('pallets', 'pallets.id', '=', 'pallet_boxes.pallet_id')
            ->join('stock_locations', 'stock_locations.pallet_id', '=', 'pallets.id')
            ->where('boxes.part_number', $partNumber)
            ->where('boxes.is_withdrawn', false)
            ->where('stock_locations.warehouse_location', '!=', 'Unknown')
            ->sum('boxes.pcs_quantity');

        $legacyTotal = PalletItem::where('part_number', $partNumber)
            ->where('pcs_quantity', '>', 0)
            ->whereHas('pallet', function ($q) {
                $q->whereHas('stockLocation')
                  ->doesntHave('boxes');
            })
            ->sum('pcs_quantity');

        return (int) $boxTotal + (int) $legacyTotal;
    }

    /**
     * Get total box for a part number
     */
    private function getTotalBoxForPart($partNumber)
    {
        return PalletItem::where('part_number', $partNumber)
            ->whereHas('pallet', function ($q) {
                $q->whereHas('stockLocation');
            })
            ->sum('box_quantity');
    }

    /**
     * Get warehouse locations in FIFO order
     */
    private function getLocationsByFIFO($partNumber, $requestedQty)
    {
        $locations = [];
        $remainingQty = $requestedQty;

        $boxRows = DB::table('boxes')
            ->join('pallet_boxes', 'boxes.id', '=', 'pallet_boxes.box_id')
            ->join('pallets', 'pallets.id', '=', 'pallet_boxes.pallet_id')
            ->join('stock_locations', 'stock_locations.pallet_id', '=', 'pallets.id')
            ->where('boxes.part_number', $partNumber)
            ->where('boxes.is_withdrawn', false)
            ->where('stock_locations.warehouse_location', '!=', 'Unknown')
            ->orderBy('boxes.created_at', 'asc')
            ->select([
                'boxes.id as box_id',
                'boxes.box_number',
                'boxes.pcs_quantity',
                'pallets.id as pallet_id',
                'pallets.pallet_number',
                'stock_locations.warehouse_location',
                'stock_locations.stored_at',
            ])
            ->get();

        foreach ($boxRows as $box) {
            if ($remainingQty <= 0) {
                break;
            }

            $takeQty = min($remainingQty, (int) $box->pcs_quantity);

            $locations[] = [
                'pallet_id' => $box->pallet_id,
                'pallet_number' => $box->pallet_number,
                'warehouse_location' => $box->warehouse_location,
                'stored_date' => $box->stored_at ? \Carbon\Carbon::parse($box->stored_at)->format('d/m/Y H:i') : '-',
                'available_pcs' => (int) $box->pcs_quantity,
                'available_box' => 1,
                'pcs_per_box' => (int) $box->pcs_quantity,
                'will_take_pcs' => $takeQty,
                'will_take_box' => 1,
                'box_number' => $box->box_number,
                'order' => count($locations) + 1,
            ];

            $remainingQty -= (int) $box->pcs_quantity;
        }

        if (count($locations) === 0) {
            $palletItems = PalletItem::where('part_number', $partNumber)
                ->where('pcs_quantity', '>', 0)
                ->whereHas('pallet', function ($q) {
                    $q->whereHas('stockLocation')
                      ->doesntHave('boxes');
                })
                ->with(['pallet' => function ($q) {
                    $q->with('stockLocation');
                }])
                ->orderBy('created_at', 'asc')
                ->get();

            foreach ($palletItems as $item) {
                if ($remainingQty <= 0) {
                    break;
                }

                if ($item->pcs_quantity <= 0) {
                    continue;
                }

                $takeQty = min($remainingQty, $item->pcs_quantity);
                $stockLocation = $item->pallet->stockLocation;
                $pcsPerBox = $item->box_quantity > 0 ? $item->pcs_quantity / $item->box_quantity : 0;
                $boxesToTake = $pcsPerBox > 0 ? floor($takeQty / $pcsPerBox) : 0;

                $locations[] = [
                    'pallet_id' => $item->pallet->id,
                    'pallet_number' => $item->pallet->pallet_number,
                    'box_number' => '-',
                    'warehouse_location' => $stockLocation ? $stockLocation->warehouse_location : 'Unknown',
                    'stored_date' => $stockLocation ? $stockLocation->stored_at->format('d/m/Y H:i') : '-',
                    'available_pcs' => $item->pcs_quantity,
                    'available_box' => $item->box_quantity,
                    'pcs_per_box' => $pcsPerBox,
                    'will_take_pcs' => $takeQty,
                    'will_take_box' => $boxesToTake,
                    'order' => count($locations) + 1,
                ];

                $remainingQty -= $takeQty;
            }
        }

        return $locations;
    }

    /**
     * Get withdrawal history
     */
    public function history()
    {
        $withdrawals = StockWithdrawal::with(['user', 'palletItem'])
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return view('warehouse.stock-withdrawal.history', [
            'withdrawals' => $withdrawals,
        ]);
    }
}
