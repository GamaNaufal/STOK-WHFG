<?php

namespace App\Http\Controllers;

use App\Models\Box;
use App\Models\DeliveryIssue;
use App\Models\DeliveryOrder;
use App\Models\DeliveryPickItem;
use App\Models\DeliveryPickSession;
use App\Models\MasterLocation;
use App\Models\PalletItem;
use App\Models\StockWithdrawal;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DeliveryPickController extends Controller
{
    public function startPick($orderId)
    {
        $user = auth()->user();
        if (!in_array($user->role, ['warehouse_operator', 'admin'], true)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $order = DeliveryOrder::with('items')->findOrFail($orderId);

        $existingSession = DeliveryPickSession::where('delivery_order_id', $order->id)
            ->whereIn('status', ['scanning', 'blocked', 'approved'])
            ->latest()
            ->first();

        if ($existingSession) {
            return response()->json([
                'session_id' => $existingSession->id,
                'pdf_url' => route('delivery.pick.pdf', [$order->id, $existingSession->id]),
                'scan_url' => route('delivery.pick.scan', [$order->id, $existingSession->id]),
            ]);
        }

        $session = DeliveryPickSession::create([
            'delivery_order_id' => $order->id,
            'created_by' => $user->id,
            'status' => 'scanning',
            'started_at' => now(),
        ]);

        try {
            DB::transaction(function () use ($order, $session) {
                foreach ($order->items as $item) {
                    $remainingQty = max(0, (int) $item->quantity - (int) $item->fulfilled_quantity);
                    if ($remainingQty <= 0) {
                        continue;
                    }

                    $reservedBoxes = $this->getReservedBoxesForOrder($order->id, $item->part_number, $order->delivery_date);
                    foreach ($reservedBoxes as $box) {
                        DeliveryPickItem::create([
                            'pick_session_id' => $session->id,
                            'box_id' => $box->id,
                            'part_number' => $box->part_number,
                            'pcs_quantity' => (int) $box->pcs_quantity,
                            'status' => 'pending',
                        ]);
                    }

                    $reservedTotal = $reservedBoxes->sum('pcs_quantity');
                    $remainingAfterReserved = max(0, $remainingQty - (int) $reservedTotal);

                    $boxes = $this->getBoxesByFIFO($item->part_number, $remainingAfterReserved, $order->delivery_date);
                    $totalPcs = $boxes->sum('pcs_quantity') + (int) $reservedTotal;

                    if ($totalPcs < $remainingQty) {
                        throw new \Exception('Stok box tidak cukup untuk part ' . $item->part_number);
                    }

                    foreach ($boxes as $box) {
                        DeliveryPickItem::create([
                            'pick_session_id' => $session->id,
                            'box_id' => $box->id,
                            'part_number' => $box->part_number,
                            'pcs_quantity' => (int) $box->pcs_quantity,
                            'status' => 'pending',
                        ]);
                    }
                }
            });
        } catch (\Exception $e) {
            $session->delete();
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($session->items()->count() === 0) {
            $session->delete();
            return response()->json(['message' => 'Semua item sudah terpenuhi.'], 422);
        }

        return response()->json([
            'session_id' => $session->id,
            'pdf_url' => route('delivery.pick.pdf', [$order->id, $session->id]),
            'scan_url' => route('delivery.pick.scan', [$order->id, $session->id]),
        ]);
    }

    public function showScan($orderId, $sessionId)
    {
        $user = auth()->user();
        if (!in_array($user->role, ['warehouse_operator', 'admin'], true)) {
            return redirect()->route('delivery.index')->with('error', 'Unauthorized.');
        }

        $session = DeliveryPickSession::with(['items.box', 'issues'])->where('delivery_order_id', $orderId)->findOrFail($sessionId);
        $order = DeliveryOrder::findOrFail($orderId);

        return view('operator.delivery.scan', compact('session', 'order'));
    }

    public function scanBox(Request $request, $sessionId)
    {
        $request->validate([
            'box_number' => 'required|string',
        ]);

        $session = DeliveryPickSession::with('items')->findOrFail($sessionId);

        if ($session->status === 'blocked') {
            return response()->json(['success' => false, 'message' => 'Scan diblokir. Tunggu approval admin.'], 423);
        }

        $box = Box::where('box_number', $request->box_number)->first();

        if ($box && ($box->is_withdrawn || in_array($box->expired_status, ['handled', 'expired'], true))) {
            DeliveryIssue::create([
                'pick_session_id' => $session->id,
                'box_id' => $box->id,
                'scanned_code' => $request->box_number,
                'issue_type' => $box->is_withdrawn ? 'box_withdrawn' : 'box_expired',
                'status' => 'pending',
            ]);

            $session->status = 'blocked';
            $session->save();

            return response()->json(['success' => false, 'message' => $box->is_withdrawn ? 'Box sudah withdrawn. Menunggu approval admin.' : 'Box sudah expired/handled. Menunggu approval admin.'], 422);
        }

        $pickItem = $box ? $session->items()->where('box_id', $box->id)->first() : null;

        if (!$pickItem) {
            DeliveryIssue::create([
                'pick_session_id' => $session->id,
                'box_id' => $box?->id,
                'scanned_code' => $request->box_number,
                'issue_type' => 'scan_mismatch',
                'status' => 'pending',
            ]);

            $session->status = 'blocked';
            $session->save();

            return response()->json(['success' => false, 'message' => 'Box tidak sesuai. Menunggu approval admin.'], 422);
        }

        if ($pickItem->status === 'scanned') {
            return response()->json(['success' => false, 'message' => 'Box sudah discan.'], 409);
        }

        $pickItem->status = 'scanned';
        $pickItem->scanned_at = now();
        $pickItem->scanned_by = auth()->id();
        $pickItem->save();

        $remaining = $session->items()->where('status', 'pending')->count();

        return response()->json([
            'success' => true,
            'message' => 'Scan berhasil.',
            'remaining' => $remaining,
        ]);
    }

    public function approveIssue(Request $request, $issueId)
    {
        $user = auth()->user();
        if (!in_array($user->role, ['admin_warehouse', 'admin'], true)) {
            return redirect()->back()->with('error', 'Unauthorized.');
        }

        $issue = DeliveryIssue::with('session')->findOrFail($issueId);
        $issue->status = 'approved';
        $issue->resolved_by = $user->id;
        $issue->resolved_at = now();
        $issue->notes = $request->input('notes');
        $issue->save();

        $session = $issue->session;
        $session->status = 'scanning';
        $session->approved_by = $user->id;
        $session->approved_at = now();
        $session->approval_notes = $issue->notes;
        $session->save();

        return redirect()->back()->with('success', 'Issue di-approve.');
    }

    public function complete(Request $request, $sessionId)
    {
        $session = DeliveryPickSession::with(['items.box.pallets.stockLocation'])->findOrFail($sessionId);
        $order = DeliveryOrder::with('items')->findOrFail($session->delivery_order_id);

        if ($session->status === 'blocked') {
            return response()->json(['success' => false, 'message' => 'Session diblokir.'], 423);
        }

        if ($session->issues()->where('status', 'pending')->exists()) {
            return response()->json(['success' => false, 'message' => 'Ada issue scan yang belum di-approve.'], 423);
        }

        if ($session->items()->where('status', 'pending')->count() > 0) {
            return response()->json(['success' => false, 'message' => 'Masih ada box yang belum discan.'], 422);
        }

        DB::beginTransaction();
        try {
            $batchId = (string) Str::uuid();

            foreach ($session->items as $pickItem) {
                $box = $pickItem->box;

                if (!$box) {
                    continue;
                }

                $pallet = $box->pallets()->first();
                $palletItem = null;

                if ($pallet) {
                    $palletItem = PalletItem::where('pallet_id', $pallet->id)
                        ->where('part_number', $box->part_number)
                        ->first();
                }

                $withdrawal = StockWithdrawal::create([
                    'withdrawal_batch_id' => $batchId,
                    'user_id' => auth()->id(),
                    'pallet_item_id' => $palletItem?->id,
                    'box_id' => $box->id,
                    'part_number' => $box->part_number,
                    'pcs_quantity' => (int) $box->pcs_quantity,
                    'box_quantity' => 1,
                    'warehouse_location' => $pallet?->stockLocation?->warehouse_location ?? 'Unknown',
                    'status' => 'completed',
                    'notes' => 'Delivery fulfillment (scan)',
                    'withdrawn_at' => now(),
                ]);

                $box->is_withdrawn = true;
                $box->withdrawn_at = now();
                $box->save();

                if ($palletItem) {
                    $palletItem->pcs_quantity = max(0, $palletItem->pcs_quantity - (int) $box->pcs_quantity);
                    $palletItem->box_quantity = max(0, $palletItem->box_quantity - 1);
                    $palletItem->save();
                }

                // Auto-update master location jika pallet kosong
                if ($pallet) {
                    $masterLocation = MasterLocation::where('current_pallet_id', $pallet->id)->first();
                    if ($masterLocation) {
                        $masterLocation->autoVacateIfEmpty();
                    }
                }
            }

            // Log batch withdrawal satu kali dengan detail semua boxes
            AuditService::logBatchStockWithdrawal($session, 'completed');

            foreach ($order->items as $orderItem) {
                $pickedPcs = $session->items->where('part_number', $orderItem->part_number)->sum('pcs_quantity');
                $orderItem->fulfilled_quantity = min(
                    (int) $orderItem->quantity,
                    (int) $orderItem->fulfilled_quantity + (int) $pickedPcs
                );
                $orderItem->save();
            }

            $order->status = 'completed';
            $order->save();

            $session->status = 'completed';
            $session->completed_at = now();
            $session->completion_status = 'completed';
            $session->redo_until = now()->addDays(5);
            $session->save();

            DB::commit();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function redo($sessionId)
    {
        $user = auth()->user();
        if (!in_array($user->role, ['admin_warehouse', 'admin'], true)) {
            return redirect()->back()->with('error', 'Unauthorized.');
        }

        $session = DeliveryPickSession::with('order', 'items')->findOrFail($sessionId);

        if (now()->greaterThan($session->redo_until)) {
            return redirect()->back()->with('error', 'Redo sudah kadaluarsa.');
        }

        DB::beginTransaction();
        try {
            // Find all boxes from pick session items and get their withdrawals
            $boxIds = $session->items()->pluck('box_id')->toArray();
            
            if (!empty($boxIds)) {
                $withdrawals = StockWithdrawal::whereIn('box_id', $boxIds)
                    ->where('status', 'completed')
                    ->get();

                foreach ($withdrawals as $withdrawal) {
                    $box = $withdrawal->box;
                    if ($box) {
                        $box->is_withdrawn = false;
                        $box->withdrawn_at = null;
                        $box->save();
                    }

                    if ($withdrawal->pallet_item_id) {
                        $palletItem = PalletItem::find($withdrawal->pallet_item_id);
                        if ($palletItem) {
                            $palletItem->pcs_quantity += $withdrawal->pcs_quantity;
                            $palletItem->box_quantity += $withdrawal->box_quantity;
                            $palletItem->save();
                        }
                    }

                    $withdrawal->status = 'reversed';
                    $withdrawal->save();
                }

                // Log batch reversal satu kali dengan detail semua boxes
                AuditService::logBatchStockWithdrawal($session, 'reversed');

                $order = $session->order;
                foreach ($order->items as $orderItem) {
                    $totalReversed = $withdrawals
                        ->where('part_number', $orderItem->part_number)
                        ->sum('pcs_quantity');

                    $orderItem->fulfilled_quantity = max(0, (int) $orderItem->fulfilled_quantity - (int) $totalReversed);
                    $orderItem->save();
                }
            }

            $session->completion_status = 'redone';
            $session->save();

            // Log delivery redo
            AuditService::logDeliveryRedo($session->id, "Pengambilan delivery di-redo oleh " . auth()->user()->name);

            $order = $session->order;
            $order->status = 'processing';
            $order->save();

            DB::commit();
            return redirect()->back()->with('success', 'Redo berhasil.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Redo gagal: ' . $e->getMessage());
        }
    }

    public function pdf($orderId, $sessionId)
    {
        // Eager load with proper joins to get location
        $session = DeliveryPickSession::with([
            'items' => function($q) {
                $q->with([
                    'box' => function($q) {
                        $q->with([
                            'pallets' => function($q) {
                                $q->with([
                                    'stockLocation' => function($q) {
                                        $q->with('masterLocation');
                                    },
                                    'currentLocation'
                                ]);
                            }
                        ]);
                    }
                ]);
            }
        ])->where('delivery_order_id', $orderId)->findOrFail($sessionId);
        
        $order = DeliveryOrder::with('items')->findOrFail($orderId);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('operator.delivery.picklist_pdf', compact('order', 'session'));
        return $pdf->stream('picklist-order-' . $order->id . '.pdf');
    }

    public function issues()
    {
        $user = auth()->user();
        if (!in_array($user->role, ['admin_warehouse', 'admin'], true)) {
            return redirect()->route('dashboard')->with('error', 'Unauthorized.');
        }

        $issues = DeliveryIssue::with(['session.order'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        $historyIssues = DeliveryIssue::with(['session.order'])
            ->where('status', '!=', 'pending')
            ->orderBy('resolved_at', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(200)
            ->get();

        return view('operator.delivery.scan-issues', compact('issues', 'historyIssues'));
    }

    private function getReservedBoxesForOrder(int $orderId, string $partNumber, $deliveryDate = null)
    {
        $storedAtSub = DB::table('pallet_boxes as pb')
            ->join('stock_inputs as si', 'si.pallet_id', '=', 'pb.pallet_id')
            ->select('pb.box_id', DB::raw('MIN(si.stored_at) as stored_at'))
            ->groupBy('pb.box_id');

        $query = Box::query()
            ->where('part_number', $partNumber)
            ->where('is_withdrawn', false)
            ->whereNotIn('expired_status', ['handled', 'expired'])
            ->where('assigned_delivery_order_id', $orderId)
            ->whereNotIn('boxes.id', function ($q) {
                $q->select('box_id')
                  ->from('delivery_pick_items')
                  ->whereIn('pick_session_id', function ($q2) {
                      $q2->select('id')
                        ->from('delivery_pick_sessions')
                        ->whereIn('status', ['scanning', 'blocked', 'approved']);
                  });
            })
            ->join('pallet_boxes', 'boxes.id', '=', 'pallet_boxes.box_id')
            ->join('pallets', 'pallets.id', '=', 'pallet_boxes.pallet_id')
            ->join('stock_locations', 'stock_locations.pallet_id', '=', 'pallets.id')
            ->leftJoinSub($storedAtSub, 'stock_in', function ($join) {
                $join->on('stock_in.box_id', '=', 'boxes.id');
            })
            ->where('stock_locations.warehouse_location', '!=', 'Unknown')
            ->orderBy('boxes.created_at', 'asc')
            ->select('boxes.*')
            ->when($deliveryDate, function ($q) use ($deliveryDate) {
                $q->where(function ($inner) use ($deliveryDate) {
                    $inner->whereNull('stock_in.stored_at')
                        ->orWhereRaw('DATE_ADD(stock_in.stored_at, INTERVAL 12 MONTH) >= ?', [$deliveryDate]);
                });
            });

        return $query->get();
    }

    private function getBoxesByFIFO(string $partNumber, int $requestedPcs, $deliveryDate = null)
    {
        $storedAtSub = DB::table('pallet_boxes as pb')
            ->join('stock_inputs as si', 'si.pallet_id', '=', 'pb.pallet_id')
            ->select('pb.box_id', DB::raw('MIN(si.stored_at) as stored_at'))
            ->groupBy('pb.box_id');

        $boxes = Box::query()
            ->where('part_number', $partNumber)
            ->where('is_withdrawn', false)
            ->whereNotIn('expired_status', ['handled', 'expired'])
            ->whereNull('boxes.assigned_delivery_order_id')
            ->whereNotIn('boxes.id', function ($q) {
                $q->select('box_id')
                  ->from('delivery_pick_items')
                  ->whereIn('pick_session_id', function ($q2) {
                      $q2->select('id')
                        ->from('delivery_pick_sessions')
                        ->whereIn('status', ['scanning', 'blocked', 'approved']);
                  });
            })
            ->join('pallet_boxes', 'boxes.id', '=', 'pallet_boxes.box_id')
            ->join('pallets', 'pallets.id', '=', 'pallet_boxes.pallet_id')
            ->join('stock_locations', 'stock_locations.pallet_id', '=', 'pallets.id')
            ->leftJoinSub($storedAtSub, 'stock_in', function ($join) {
                $join->on('stock_in.box_id', '=', 'boxes.id');
            })
            ->where('stock_locations.warehouse_location', '!=', 'Unknown')
            ->when($deliveryDate, function ($q) use ($deliveryDate) {
                $q->where(function ($inner) use ($deliveryDate) {
                    $inner->whereNull('stock_in.stored_at')
                        ->orWhereRaw('DATE_ADD(stock_in.stored_at, INTERVAL 12 MONTH) >= ?', [$deliveryDate]);
                });
            })
            ->orderBy('boxes.created_at', 'asc')
            ->select('boxes.*')
            ->get();

        $selected = collect();
        $remaining = $requestedPcs;

        foreach ($boxes as $box) {
            if ($remaining <= 0) {
                break;
            }
            if ((int) $box->pcs_quantity > $remaining) {
                continue;
            }
            $selected->push($box);
            $remaining -= (int) $box->pcs_quantity;
        }

        return $selected;
    }
}