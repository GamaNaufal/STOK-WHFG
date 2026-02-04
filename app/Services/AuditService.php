<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Log audit trail untuk aksi apapun
     * 
     * @param string $type Tipe audit: 'stock_input', 'stock_withdrawal', 'delivery_pickup', etc
     * @param string $action Aksi: 'created', 'updated', 'completed', 'reversed', etc
     * @param string|null $model Nama model yang diaudit
     * @param int|null $modelId ID dari record yang diaudit
     * @param array $oldValues Data sebelum perubahan
     * @param array $newValues Data setelah perubahan
     * @param string|null $description Deskripsi ringkas aksi
     */
    public static function log(
        $type,
        $action,
        $model = null,
        $modelId = null,
        $oldValues = [],
        $newValues = [],
        $description = null
    ) {
        try {
            $userId = auth()->id();
            
            AuditLog::create([
                'type' => $type,
                'action' => $action,
                'model' => $model,
                'model_id' => $modelId,
                'old_values' => !empty($oldValues) ? json_encode($oldValues) : null,
                'new_values' => !empty($newValues) ? json_encode($newValues) : null,
                'description' => $description,
                'user_id' => $userId,
                'ip_address' => Request::ip(),
                'user_agent' => Request::header('User-Agent'),
            ]);
        } catch (\Exception $e) {
            \Log::error('Audit log failed: ' . $e->getMessage());
        }
    }

    /**
     * Log stock input creation
     */
    public static function logStockInput($stockInput, $action = 'created', $oldValues = [])
    {
        // Load relations
        $stockInput->loadMissing('palletItem');
        
        // Get part numbers (dari array atau fallback ke single palletItem)
        $partNumbers = $stockInput->part_numbers ?? [];
        if (empty($partNumbers) && $stockInput->palletItem?->part_number) {
            $partNumbers = [$stockInput->palletItem->part_number];
        }
        $partNumberString = !empty($partNumbers) ? implode(', ', $partNumbers) : '-';
        
        $newValues = [
            'pallet_id' => $stockInput->pallet_id,
            'part_numbers' => $partNumbers,
            'part_number' => $partNumberString,
            'pcs_quantity' => $stockInput->pcs_quantity,
            'box_quantity' => $stockInput->box_quantity,
            'warehouse_location' => $stockInput->warehouse_location,
            'stored_at' => $stockInput->stored_at?->format('Y-m-d H:i:s'),
        ];

        self::log(
            'stock_input',
            $action,
            'StockInput',
            $stockInput->id,
            $oldValues,
            $newValues,
            $action === 'created' 
                ? "Input stok {$stockInput->pcs_quantity} PCS di lokasi {$stockInput->warehouse_location}"
                : "Update input stok {$stockInput->pcs_quantity} PCS"
        );
    }

    /**
     * Log stock withdrawal
     */
    public static function logStockWithdrawal($withdrawal, $action = 'created', $oldValues = [])
    {
        $newValues = [
            'part_number' => $withdrawal->part_number,
            'pcs_quantity' => $withdrawal->pcs_quantity,
            'box_quantity' => $withdrawal->box_quantity,
            'warehouse_location' => $withdrawal->warehouse_location,
            'status' => $withdrawal->status,
            'withdrawn_at' => $withdrawal->withdrawn_at?->format('Y-m-d H:i:s'),
        ];

        $actionDescription = match($action) {
            'completed' => "Pengambilan stok selesai: {$withdrawal->pcs_quantity} PCS ({$withdrawal->box_quantity} box) dari lokasi {$withdrawal->warehouse_location}",
            'reversed' => "Pengambilan stok dibatalkan: {$withdrawal->pcs_quantity} PCS ({$withdrawal->box_quantity} box)",
            'created' => "Pengambilan stok dibuat: {$withdrawal->pcs_quantity} PCS",
            default => "Aksi pengambilan stok: {$action}",
        };

        self::log(
            'stock_withdrawal',
            $action,
            'StockWithdrawal',
            $withdrawal->id,
            $oldValues,
            $newValues,
            $actionDescription
        );
    }

    /**
     * Log batch stock withdrawal (multiple boxes dalam satu session)
     */
    public static function logBatchStockWithdrawal($session, $action = 'completed', $description = null)
    {
        // Load box dengan palletnya, dan stock location
        $items = $session->items()->with(['box' => function($q) {
            $q->with('pallets.stockLocation');
        }])->get();
        
        $totalPcs = 0;
        $totalBox = $items->count();
        $boxDetails = [];
        $partNumbers = [];

        foreach ($items as $item) {
            $box = $item->box;
            if ($box) {
                $totalPcs += $box->pcs_quantity;
                
                // Get warehouse location dari pallet's stockLocation
                $warehouseLocation = 'Unknown';
                if ($box->pallets && $box->pallets->isNotEmpty()) {
                    $pallet = $box->pallets->first();
                    $warehouseLocation = $pallet->stockLocation?->warehouse_location ?? 'Unknown';
                }
                
                $boxDetails[] = [
                    'box_id' => $box->id,
                    'part_number' => $box->part_number,
                    'pcs_quantity' => (int)$box->pcs_quantity,
                    'warehouse_location' => $warehouseLocation,
                ];
                // Collect unique part numbers
                if ($box->part_number) {
                    $partNumbers[$box->part_number] = true;
                }
            }
        }

        $partNumbers = array_keys($partNumbers);
        $partNumberString = !empty($partNumbers) ? implode(', ', $partNumbers) : '-';

        $newValues = [
            'session_id' => $session->id,
            'delivery_order_id' => $session->delivery_order_id,
            'total_pcs_quantity' => $totalPcs,
            'total_box_quantity' => $totalBox,
            'part_numbers' => $partNumbers,
            'part_number' => $partNumberString,
            'boxes' => $boxDetails,
        ];

        $actionDescription = match($action) {
            'completed' => "Pengambilan stok selesai: {$totalPcs} PCS ({$totalBox} box) untuk DO #{$session->delivery_order_id}",
            'reversed' => "Pengambilan stok di-undo: {$totalPcs} PCS ({$totalBox} box) dari DO #{$session->delivery_order_id}",
            default => $description ?? "Aksi pengambilan stok batch: {$action}",
        };

        self::log(
            'stock_withdrawal',
            $action,
            'DeliveryPickSession',
            $session->id,
            [],
            $newValues,
            $description ?? $actionDescription
        );
    }

    /**
     * Log delivery pickup
     */
    public static function logDeliveryPickup($session, $action = 'created', $description = null)
    {
        $newValues = [
            'delivery_order_id' => $session->delivery_order_id,
            'completion_status' => $session->completion_status,
            'total_boxes' => $session->total_boxes,
            'completed_boxes' => $session->completed_boxes,
        ];

        self::log(
            'delivery_pickup',
            $action,
            'DeliveryPickSession',
            $session->id,
            [],
            $newValues,
            $description ?? "Pickup delivery: Order #{$session->delivery_order_id}"
        );
    }

    /**
     * Log delivery redo
     */
    public static function logDeliveryRedo($sessionId, $description)
    {
        self::log(
            'delivery_redo',
            'redo',
            'DeliveryPickSession',
            $sessionId,
            [],
            [],
            $description
        );
    }

    /**
     * Get audit trail untuk dashboard/report
     */
    public static function getAuditTrail($filters = [], $limit = 50)
    {
        $query = AuditLog::query();

        // Filter by type
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        // Filter by action
        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        // Filter by user
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        // Filter by date range
        if (!empty($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date'] . ' 00:00:00');
        }
        if (!empty($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date'] . ' 23:59:59');
        }

        return $query->with('user')
                    ->orderBy('created_at', 'desc')
                    ->paginate($limit);
    }

    /**
     * Get summary statistics
     */
    public static function getSummary($startDate = null, $endDate = null)
    {
        $query = AuditLog::query();

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        return [
            'total_actions' => $query->count(),
            'stock_inputs' => (clone $query)->where('type', 'stock_input')->count(),
            'stock_withdrawals' => (clone $query)->where('type', 'stock_withdrawal')->count(),
            'delivery_pickups' => (clone $query)->where('type', 'delivery_pickup')->count(),
            'delivery_redos' => (clone $query)->where('type', 'delivery_redo')->count(),
        ];
    }
}
