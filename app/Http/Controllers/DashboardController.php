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
    private function getCanonicalPalletByActiveBoxId(): array
    {
        return DB::table('pallet_boxes as pb')
            ->join('pallets as p', 'p.id', '=', 'pb.pallet_id')
            ->join('stock_locations as sl', 'sl.pallet_id', '=', 'p.id')
            ->join('boxes as b', 'b.id', '=', 'pb.box_id')
            ->whereNull('b.deleted_at')
            ->where('b.is_withdrawn', false)
            ->where(function ($q) {
                $q->whereNull('b.expired_status')
                    ->orWhereNotIn('b.expired_status', ['handled', 'expired']);
            })
            ->where('sl.warehouse_location', '!=', 'Unknown')
            ->groupBy('pb.box_id')
            ->select('pb.box_id', DB::raw('MIN(pb.pallet_id) as canonical_pallet_id'))
            ->pluck('canonical_pallet_id', 'pb.box_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function buildStockSummaryTotals(): array
    {
        $totalBox = 0;
        $totalPcs = 0;
        $palletsWithLocation = 0;
        $partNumbers = [];
        $canonicalPalletByBoxId = $this->getCanonicalPalletByActiveBoxId();

        $palletQuery = Pallet::query()
            ->select(['id'])
            ->with([
                'stockLocation:id,pallet_id,warehouse_location',
                'items:id,pallet_id,part_number,box_quantity,pcs_quantity',
                'boxes:id,part_number,is_withdrawn,expired_status,pcs_quantity',
            ])
            ->whereHas('stockLocation', function ($q) {
                $q->where('warehouse_location', '!=', 'Unknown');
            });

        $palletQuery->chunkById(200, function ($pallets) use (&$totalBox, &$totalPcs, &$palletsWithLocation, &$partNumbers, $canonicalPalletByBoxId) {
            foreach ($pallets as $pallet) {
                $hasAnyBoxHistory = $pallet->boxes->isNotEmpty();
                $activeBoxes = $pallet->boxes
                    ->where('is_withdrawn', false)
                    ->where(function ($q) { $q->whereNull('expired_status')->orWhereNotIn('expired_status', ['handled', 'expired']); })
                    ->filter(function ($box) use ($canonicalPalletByBoxId, $pallet) {
                        $boxId = (int) $box->id;
                        $canonicalPalletId = (int) ($canonicalPalletByBoxId[$boxId] ?? $pallet->id);
                        return $canonicalPalletId === (int) $pallet->id;
                    });

                if ($activeBoxes->isNotEmpty()) {
                    $palletsWithLocation++;
                    $totalBox += $activeBoxes->count();
                    $totalPcs += (int) $activeBoxes->sum('pcs_quantity');

                    foreach ($activeBoxes as $box) {
                        if (!empty($box->part_number)) {
                            $partNumbers[(string) $box->part_number] = true;
                        }
                    }
                } elseif (!$hasAnyBoxHistory) {
                    // Fallback only for true legacy pallets that have no box history.
                    $legacyItems = $pallet->items->filter(function ($item) {
                        return $item->pcs_quantity > 0 || $item->box_quantity > 0;
                    });

                    if ($legacyItems->isNotEmpty()) {
                        $palletsWithLocation++;
                        $totalBox += (int) $legacyItems->sum('box_quantity');
                        $totalPcs += (int) $legacyItems->sum('pcs_quantity');

                        foreach ($legacyItems as $item) {
                            if (!empty($item->part_number)) {
                                $partNumbers[(string) $item->part_number] = true;
                            }
                        }
                    }
                }
            }
        });

        $totalItems = count($partNumbers);

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

