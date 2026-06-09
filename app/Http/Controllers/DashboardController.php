<?php

namespace App\Http\Controllers;

use App\Models\Pallet;
use App\Models\StockLocation;
use App\Models\DeliveryOrder;
use App\Models\DeliveryIssue;
use App\Models\DeliveryPickSession;
use App\Services\OperationalReportService;
use App\Services\ExpiredBoxService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    private function applyStoredLocationExistsFilter($query)
    {
        return $query->whereExists(function ($sub) {
            $sub->select(DB::raw(1))
                ->from('pallet_boxes')
                ->join('pallets', 'pallets.id', '=', 'pallet_boxes.pallet_id')
                ->join('stock_locations', 'stock_locations.pallet_id', '=', 'pallets.id')
                ->whereColumn('pallet_boxes.box_id', 'boxes.id')
                ->where('stock_locations.warehouse_location', '!=', 'Unknown');
        });
    }

    private function buildStockSummaryTotals(): array
    {
        // 1. Boxes
        $boxQuery = DB::table('boxes')
            ->where('boxes.is_withdrawn', false)
            ->whereNull('boxes.deleted_at')
            ->where(function ($q) {
                $q->whereNull('boxes.expired_status')
                  ->orWhereNotIn('boxes.expired_status', ['handled', 'expired']);
            });

        $boxQuery = $this->applyStoredLocationExistsFilter($boxQuery);

        $totalBox = $boxQuery->count();
        $totalPcs = (int) $boxQuery->sum('boxes.pcs_quantity');
        $boxPartNumbers = $boxQuery->pluck('boxes.part_number')->filter()->unique()->values();

        // 2. Legacy Pallet Items
        $legacyItemsQuery = DB::table('pallet_items')
            ->join('pallets', 'pallets.id', '=', 'pallet_items.pallet_id')
            ->join('stock_locations', 'stock_locations.pallet_id', '=', 'pallets.id')
            ->where('stock_locations.warehouse_location', '!=', 'Unknown')
            ->where('pallet_items.pcs_quantity', '>', 0)
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('pallet_boxes')
                    ->whereColumn('pallet_boxes.pallet_id', 'pallets.id');
            });

        $legacyBox = (int) $legacyItemsQuery->sum('pallet_items.box_quantity');
        $legacyPcs = (int) $legacyItemsQuery->sum('pallet_items.pcs_quantity');
        $legacyPartNumbers = $legacyItemsQuery->pluck('pallet_items.part_number')->filter()->unique()->values();

        $totalBox += $legacyBox;
        $totalPcs += $legacyPcs;

        $totalItems = $boxPartNumbers->merge($legacyPartNumbers)->unique()->count();

        // 3. Pallets with Location that contain stock
        $palletsWithLocation = DB::table('stock_locations')
            ->where('warehouse_location', '!=', 'Unknown')
            ->where(function ($q) {
                $q->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('pallet_boxes')
                        ->join('boxes', 'boxes.id', '=', 'pallet_boxes.box_id')
                        ->whereColumn('pallet_boxes.pallet_id', 'stock_locations.pallet_id')
                        ->where('boxes.is_withdrawn', false)
                        ->whereNull('boxes.deleted_at')
                        ->where(function ($q2) {
                            $q2->whereNull('boxes.expired_status')
                               ->orWhereNotIn('boxes.expired_status', ['handled', 'expired']);
                        });
                })->orWhereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('pallet_items')
                        ->whereColumn('pallet_items.pallet_id', 'stock_locations.pallet_id')
                        ->where('pallet_items.pcs_quantity', '>', 0)
                        ->whereNotExists(function ($sub2) {
                            $sub2->select(DB::raw(1))
                                ->from('pallet_boxes')
                                ->whereColumn('pallet_boxes.pallet_id', 'stock_locations.pallet_id');
                        });
                });
            })
            ->count();

        return [
            'pallets_with_location' => $palletsWithLocation,
            'total_items' => $totalItems,
            'total_box' => $totalBox,
            'total_pcs' => $totalPcs,
        ];
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $userRole = $user->role;
        $stats = [];
        $stockSummaryTotals = $this->buildStockSummaryTotals();

        // WAREHOUSE OPERATOR
        if ($userRole === 'warehouse_operator') {
            $stats = [
                'role_label' => 'Warehouse Operator',
                'total_pallets' => Pallet::count(),
                'pallets_with_location' => $stockSummaryTotals['pallets_with_location'],
                'pallets_without_location' => Pallet::doesntHave('stockLocation')->count(),
                'total_locations' => StockLocation::distinct('warehouse_location')->count(),
                'total_items' => $stockSummaryTotals['total_items'],
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
                'total_stok_items' => $stockSummaryTotals['total_items'],
            ];
        }
        // SUPERVISI
        elseif ($userRole === 'supervisi') {
            $data = app(OperationalReportService::class)->build($request);
            return view('operator.reports.operational', $data);
        }
        // ADMIN WAREHOUSE
        elseif ($userRole === 'admin_warehouse') {
            // Get expired box information
            $expiredService = app(ExpiredBoxService::class);
            $expiredService->syncStatuses();
            $expiredBoxes = $expiredService->getExpirableBoxesQuery()
                ->whereIn('boxes.expired_status', ['warning', 'expired'])
                ->get();
            $warningBoxes = $expiredBoxes->filter(fn($row) => $row->expired_status === 'warning')->count();
            $expiredBoxesCount = $expiredBoxes->filter(fn($row) => $row->expired_status === 'expired')->count();

            $stats = [
                'role_label' => 'Admin Warehouse',
                'total_locations' => StockLocation::distinct('warehouse_location')->count(),
                'total_pallets' => Pallet::count(),
                'pallets_with_location' => $stockSummaryTotals['pallets_with_location'],
                'total_pcs' => $stockSummaryTotals['total_pcs'],
                'pending_scan_issues' => DeliveryIssue::where('status', 'pending')->count(),
                'total_items' => $stockSummaryTotals['total_items'],
                'warning_boxes' => $warningBoxes,
                'expired_boxes' => $expiredBoxesCount,
            ];
        }
        // ADMIN IT (Full access)
        elseif ($userRole === 'admin') {
            // Get expired box information
            $expiredService = app(ExpiredBoxService::class);
            $expiredService->syncStatuses();
            $expiredBoxes = $expiredService->getExpirableBoxesQuery()
                ->whereIn('boxes.expired_status', ['warning', 'expired'])
                ->get();
            $warningBoxes = $expiredBoxes->filter(fn($row) => $row->expired_status === 'warning')->count();
            $expiredBoxesCount = $expiredBoxes->filter(fn($row) => $row->expired_status === 'expired')->count();

            $stats = [
                'role_label' => 'Admin IT',
                'total_pallets' => Pallet::count(),
                'today_pallets' => Pallet::whereDate('created_at', today())->count(),
                'pallets_with_location' => $stockSummaryTotals['pallets_with_location'],
                'total_locations' => StockLocation::distinct('warehouse_location')->count(),
                'total_items' => $stockSummaryTotals['total_items'],
                'total_box' => $stockSummaryTotals['total_box'],
                'total_pcs' => $stockSummaryTotals['total_pcs'],
                'total_orders' => DeliveryOrder::count(),
                'pending_orders' => DeliveryOrder::where('status', 'pending')->count(),
                'warning_boxes' => $warningBoxes,
                'expired_boxes' => $expiredBoxesCount,
            ];
        }

        return view('shared.dashboard', compact('stats', 'userRole'));
    }
}

