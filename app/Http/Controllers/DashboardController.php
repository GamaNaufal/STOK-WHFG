<?php

namespace App\Http\Controllers;

use App\Models\Pallet;
use App\Models\PalletItem;
use App\Models\StockLocation;
use App\Models\DeliveryOrder;
use App\Models\DeliveryPickSession;
use App\Services\OperationalReportService;
use App\Services\ExpiredBoxService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    private function activePalletQuery(bool $requireLocation = false)
    {
        $query = Pallet::whereHas('items', function ($q) {
            $q->where(function ($subQ) {
                $subQ->where('pcs_quantity', '>', 0)
                     ->orWhere('box_quantity', '>', 0);
            });
        });

        if ($requireLocation) {
            $query->has('stockLocation');
        }

        return $query;
    }

    private function countActiveItems(): int
    {
        return PalletItem::where(function ($q) {
            $q->where('pcs_quantity', '>', 0)
              ->orWhere('box_quantity', '>', 0);
        })->count();
    }

    private function getExpiredBoxStats(): array
    {
        $expiredService = app(ExpiredBoxService::class);
        $expiredService->syncStatuses();

        $expiredBoxes = $expiredService->getExpirableBoxesQuery()
            ->whereIn('boxes.expired_status', ['warning', 'expired'])
            ->get();

        return [
            'warning_boxes' => $expiredBoxes->filter(fn ($row) => $row->expired_status === 'warning')->count(),
            'expired_boxes' => $expiredBoxes->filter(fn ($row) => $row->expired_status === 'expired')->count(),
        ];
    }

    private function buildStockSummaryTotals(): array
    {
        $items = collect();

        $palletQuery = Pallet::with(['stockLocation', 'items', 'boxes'])
            ->whereHas('stockLocation', function ($q) {
                $q->where('warehouse_location', '!=', 'Unknown');
            });

        $palletQuery->chunkById(200, function ($pallets) use (&$items) {
            foreach ($pallets as $pallet) {
                $activeBoxes = $pallet->boxes->where('is_withdrawn', false);

                if ($activeBoxes->isNotEmpty()) {
                    foreach ($activeBoxes as $box) {
                        $items->push([
                            'box_quantity' => 1,
                            'pcs_quantity' => (int) $box->pcs_quantity,
                        ]);
                    }
                } else {
                    $legacyItems = $pallet->items->filter(function ($item) {
                        return $item->pcs_quantity > 0 || $item->box_quantity > 0;
                    });

                    foreach ($legacyItems as $item) {
                        $items->push([
                            'box_quantity' => (int) $item->box_quantity,
                            'pcs_quantity' => (int) $item->pcs_quantity,
                        ]);
                    }
                }
            }
        });

        return [
            'total_box' => (int) $items->sum('box_quantity'),
            'total_pcs' => $items->sum('pcs_quantity') ?? 0,
        ];
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        $userRole = $user->role;
        $stats = [];
        $stockSummaryTotals = $this->buildStockSummaryTotals();

        // WAREHOUSE OPERATOR
        if ($userRole === 'warehouse_operator') {
            $palletsWithActiveStock = $this->activePalletQuery(true)->count();

            $stats = [
                'role_label' => 'Warehouse Operator',
                'total_pallets' => Pallet::count(),
                'pallets_with_location' => $palletsWithActiveStock,
                'pallets_without_location' => Pallet::doesntHave('stockLocation')->count(),
                'total_locations' => StockLocation::distinct('warehouse_location')->count(),
                'total_items' => $this->countActiveItems(),
                'total_pcs' => $stockSummaryTotals['total_pcs'],
                'pending_deliveries' => DeliveryPickSession::whereIn('status', ['scanning', 'blocked'])->count(),
            ];
        }
        // SALES
        elseif ($userRole === 'sales') {
            $stats = [
                'role_label' => 'Sales',
                'total_orders' => DeliveryOrder::count(),
                'pending_orders' => DeliveryOrder::where('status', 'pending')->count(),
                'approved_orders' => DeliveryOrder::where('status', 'approved')->count(),
                'completed_orders' => DeliveryOrder::where('status', 'completed')->count(),
                'today_orders' => DeliveryOrder::whereDate('created_at', today())->count(),
            ];
        }
        // PPC
        elseif ($userRole === 'ppc') {
            $stats = [
                'role_label' => 'PPC (Planning & Control)',
                'pending_approval' => DeliveryOrder::where('status', 'pending')->count(),
                'approved_orders' => DeliveryOrder::where('status', 'approved')->count(),
                'total_orders' => DeliveryOrder::count(),
                'total_stok_pcs' => $stockSummaryTotals['total_pcs'],
                'total_stok_items' => $this->countActiveItems(),
            ];
        }
        // SUPERVISI
        elseif ($userRole === 'supervisi') {
            $data = app(OperationalReportService::class)->build($request);
            return view('operator.reports.operational', $data);
        }
        // ADMIN WAREHOUSE
        elseif ($userRole === 'admin_warehouse') {
            $palletsWithActiveStock = $this->activePalletQuery()->count();
            $expiredStats = $this->getExpiredBoxStats();

            $stats = [
                'role_label' => 'Admin Warehouse',
                'total_locations' => StockLocation::distinct('warehouse_location')->count(),
                'total_pallets' => Pallet::count(),
                'pallets_with_location' => $palletsWithActiveStock,
                'total_pcs' => $stockSummaryTotals['total_pcs'],
                'pending_scan_issues' => \App\Models\DeliveryIssue::where('status', 'pending')->count(),
                'total_items' => $this->countActiveItems(),
                'warning_boxes' => $expiredStats['warning_boxes'],
                'expired_boxes' => $expiredStats['expired_boxes'],
            ];
        }
        // ADMIN IT (Full access)
        elseif ($userRole === 'admin') {
            $palletsWithActiveStock = $this->activePalletQuery()->count();
            $expiredStats = $this->getExpiredBoxStats();

            $stats = [
                'role_label' => 'Admin IT',
                'total_pallets' => Pallet::count(),
                'today_pallets' => Pallet::whereDate('created_at', today())->count(),
                'pallets_with_location' => $palletsWithActiveStock,
                'total_locations' => StockLocation::distinct('warehouse_location')->count(),
                'total_items' => $this->countActiveItems(),
                'total_box' => $stockSummaryTotals['total_box'],
                'total_pcs' => $stockSummaryTotals['total_pcs'],
                'total_orders' => DeliveryOrder::count(),
                'pending_orders' => DeliveryOrder::where('status', 'pending')->count(),
                'warning_boxes' => $expiredStats['warning_boxes'],
                'expired_boxes' => $expiredStats['expired_boxes'],
            ];
        }

        return view('shared.dashboard', compact('stats', 'userRole'));
    }
}

