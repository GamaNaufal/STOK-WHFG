<?php

namespace App\Http\Controllers;

use App\Models\Box;
use App\Models\Pallet;
use App\Models\PalletItem;
use App\Models\StockLocation;
use App\Models\DeliveryOrder;
use App\Models\DeliveryPickSession;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $userRole = $user->role;
        $stats = [];

        // Helper function untuk hitung boxes berdasarkan PCS
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

        // WAREHOUSE OPERATOR
        if ($userRole === 'warehouse_operator') {
            $palletsWithActiveStock = Pallet::whereHas('items', function ($q) {
                $q->where(function ($subQ) {
                    $subQ->where('pcs_quantity', '>', 0)
                         ->orWhere('box_quantity', '>', 0);
                });
            })->has('stockLocation')->count();

            $stats = [
                'role_label' => 'Warehouse Operator',
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
                'total_stok_pcs' => PalletItem::where(function ($q) {
                    $q->where('pcs_quantity', '>', 0)
                      ->orWhere('box_quantity', '>', 0);
                })->sum('pcs_quantity') ?? 0,
                'total_stok_items' => PalletItem::where(function ($q) {
                    $q->where('pcs_quantity', '>', 0)
                      ->orWhere('box_quantity', '>', 0);
                })->count(),
            ];
        }
        // SUPERVISI
        elseif ($userRole === 'supervisi') {
            $stats = [
                'role_label' => 'Supervisi',
                'total_pallets' => Pallet::count(),
                'total_pcs' => PalletItem::where(function ($q) {
                    $q->where('pcs_quantity', '>', 0)
                      ->orWhere('box_quantity', '>', 0);
                })->sum('pcs_quantity') ?? 0,
                'total_locations' => StockLocation::distinct('warehouse_location')->count(),
                'total_orders' => DeliveryOrder::count(),
                'completed_orders_today' => DeliveryOrder::where('status', 'completed')
                    ->whereDate('created_at', today())->count(),
                'total_deliveries' => DeliveryPickSession::count(),
            ];
        }
        // ADMIN WAREHOUSE
        elseif ($userRole === 'admin_warehouse') {
            $palletsWithActiveStock = Pallet::whereHas('items', function ($q) {
                $q->where(function ($subQ) {
                    $subQ->where('pcs_quantity', '>', 0)
                         ->orWhere('box_quantity', '>', 0);
                });
            })->count();

            $stats = [
                'role_label' => 'Admin Warehouse',
                'total_locations' => StockLocation::distinct('warehouse_location')->count(),
                'total_pallets' => Pallet::count(),
                'pallets_with_location' => $palletsWithActiveStock,
                'total_pcs' => PalletItem::where(function ($q) {
                    $q->where('pcs_quantity', '>', 0)
                      ->orWhere('box_quantity', '>', 0);
                })->sum('pcs_quantity') ?? 0,
                'pending_scan_issues' => \App\Models\DeliveryIssue::where('status', 'pending')->count(),
                'total_items' => PalletItem::where(function ($q) {
                    $q->where('pcs_quantity', '>', 0)
                      ->orWhere('box_quantity', '>', 0);
                })->count(),
            ];
        }
        // ADMIN IT (Full access)
        elseif ($userRole === 'admin') {
            $palletsWithActiveStock = Pallet::whereHas('items', function ($q) {
                $q->where(function ($subQ) {
                    $subQ->where('pcs_quantity', '>', 0)
                         ->orWhere('box_quantity', '>', 0);
                });
            })->count();

            $stats = [
                'role_label' => 'Admin IT',
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
                'total_orders' => DeliveryOrder::count(),
                'pending_orders' => DeliveryOrder::where('status', 'pending')->count(),
            ];
        }

        return view('shared.dashboard', compact('stats', 'userRole'));
    }
}

