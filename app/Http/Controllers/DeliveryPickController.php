<?php

namespace App\Http\Controllers;

use App\Models\Box;
use App\Models\DeliveryIssue;
use App\Models\DeliveryOrder;
use App\Models\DeliveryPickItem;
use App\Models\DeliveryPickSession;
use App\Models\MasterLocation;
use App\Models\NotFullBoxRequest;
use App\Models\PalletItem;
use App\Models\PartSetting;
use App\Models\StockWithdrawal;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DeliveryPickController extends Controller
{
    private const DELIVERY_APPROVAL_PENDING_MESSAGE = 'Delivery diblokir: masih ada request box not full tambahan yang menunggu approval supervisi.';
    private const SESSION_RECALC_MESSAGE = 'Sesi picking ini perlu dihitung ulang karena ada order dengan prioritas tanggal lebih awal.';
    private const ACTIVE_LOCK_STATUSES = ['scanning', 'blocked'];

    private function getVerificationActiveLockedBoxIds(): array
    {
        return DB::table('delivery_pick_items')
            ->join('delivery_pick_sessions', 'delivery_pick_sessions.id', '=', 'delivery_pick_items.pick_session_id')
            ->whereIn('delivery_pick_sessions.status', self::ACTIVE_LOCK_STATUSES)
            ->pluck('delivery_pick_items.box_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function buildVerificationActiveLockedPoolsByOrderPart(): array
    {
        $rows = DB::table('delivery_pick_items')
            ->join('delivery_pick_sessions', 'delivery_pick_sessions.id', '=', 'delivery_pick_items.pick_session_id')
            ->join('boxes', 'boxes.id', '=', 'delivery_pick_items.box_id')
            ->whereIn('delivery_pick_sessions.status', self::ACTIVE_LOCK_STATUSES)
            ->select(
                'delivery_pick_sessions.delivery_order_id',
                'delivery_pick_items.part_number',
                'delivery_pick_items.pcs_quantity',
                'boxes.is_not_full'
            )
            ->orderBy('delivery_pick_items.id', 'asc')
            ->get();

        $pools = [];
        foreach ($rows as $row) {
            $orderId = (int) $row->delivery_order_id;
            $partNumber = (string) $row->part_number;
            $pools[$orderId][$partNumber][] = [
                'qty' => (int) $row->pcs_quantity,
                'is_not_full' => (bool) $row->is_not_full,
            ];
        }

        return $pools;
    }

    private function hasPendingAdditionalNotFullRequestForOrder(int $orderId, bool $lockRows = false): bool
    {
        $query = NotFullBoxRequest::where('delivery_order_id', $orderId)
            ->where('request_type', 'additional')
            ->where('status', 'pending');

        if ($lockRows) {
            $query->lockForUpdate();
        }

        return $query->exists();
    }

    private function assertOrderDeliveryGateOpenOrFail(int $orderId, bool $lockRows = false): void
    {
        if ($this->hasPendingAdditionalNotFullRequestForOrder($orderId, $lockRows)) {
            throw new \RuntimeException(self::DELIVERY_APPROVAL_PENDING_MESSAGE);
        }
    }

    private function buildVerificationFifoPoolsByPart(): array
    {
        $lockedBoxIds = $this->getVerificationActiveLockedBoxIds();

        $rows = DB::table('boxes')
            ->join('pallet_boxes', 'boxes.id', '=', 'pallet_boxes.box_id')
            ->join('pallets', 'pallets.id', '=', 'pallet_boxes.pallet_id')
            ->join('stock_locations', 'stock_locations.pallet_id', '=', 'pallets.id')
            ->where('boxes.is_withdrawn', false)
            ->whereNotIn('boxes.expired_status', ['handled', 'expired'])
            ->whereNull('boxes.assigned_delivery_order_id')
            ->when(!empty($lockedBoxIds), function ($q) use ($lockedBoxIds) {
                $q->whereNotIn('boxes.id', $lockedBoxIds);
            })
            ->where('stock_locations.warehouse_location', '!=', 'Unknown')
            ->orderBy('boxes.created_at', 'asc')
            ->select('boxes.part_number', 'boxes.pcs_quantity', 'boxes.is_not_full')
            ->get();

        $pools = [];
        foreach ($rows as $row) {
            $pools[$row->part_number][] = [
                'qty' => (int) $row->pcs_quantity,
                'is_not_full' => (bool) $row->is_not_full,
            ];
        }

        return $pools;
    }

    private function buildVerificationReservedPoolsByOrderPart(): array
    {
        $rows = DB::table('boxes')
            ->join('pallet_boxes', 'boxes.id', '=', 'pallet_boxes.box_id')
            ->join('pallets', 'pallets.id', '=', 'pallet_boxes.pallet_id')
            ->join('stock_locations', 'stock_locations.pallet_id', '=', 'pallets.id')
            ->where('boxes.is_withdrawn', false)
            ->whereNotIn('boxes.expired_status', ['handled', 'expired'])
            ->whereNotNull('boxes.assigned_delivery_order_id')
            ->where('stock_locations.warehouse_location', '!=', 'Unknown')
            ->orderBy('boxes.created_at', 'asc')
            ->select('boxes.assigned_delivery_order_id', 'boxes.part_number', 'boxes.pcs_quantity', 'boxes.is_not_full')
            ->get();

        $pools = [];
        foreach ($rows as $row) {
            $orderId = (int) $row->assigned_delivery_order_id;
            $partNumber = (string) $row->part_number;
            $pools[$orderId][$partNumber][] = [
                'qty' => (int) $row->pcs_quantity,
                'is_not_full' => (bool) $row->is_not_full,
            ];
        }

        return $pools;
    }

    public function verificationIndex()
    {
        $user = Auth::user();
        if (!in_array($user->role, ['warehouse_operator', 'admin', 'admin_warehouse'], true)) {
            return redirect()->route('delivery.index')->with('error', 'Unauthorized.');
        }

        $orders = DeliveryOrder::with('items')
            ->whereIn('status', ['approved', 'processing'])
            ->orderBy('delivery_date', 'asc')
            ->limit(100)
            ->get();

        $currentUserId = (int) Auth::id();
        $globalActiveSession = DeliveryPickSession::with('creator')
            ->whereIn('status', self::ACTIVE_LOCK_STATUSES)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();

        $fifoPools = $this->buildVerificationFifoPoolsByPart();
        $reservedPools = $this->buildVerificationReservedPoolsByOrderPart();
        $lockedPools = $this->buildVerificationActiveLockedPoolsByOrderPart();
        $partSettings = PartSetting::pluck('qty_box', 'part_number');

        $orders->each(function ($order) use (&$fifoPools, $reservedPools, $lockedPools, $partSettings, $globalActiveSession, $currentUserId) {
            $totalBoxesToPick = 0;
            $isReadyToPick = true;
            $hasPendingAdditionalApproval = $this->hasPendingAdditionalNotFullRequestForOrder((int) $order->id);

            $hasGlobalLock = $globalActiveSession !== null;
            $isActiveOrder = $hasGlobalLock && (int) $globalActiveSession->delivery_order_id === (int) $order->id;
            $isOwner = $isActiveOrder && (int) $globalActiveSession->created_by === $currentUserId;
            $ownerName = $hasGlobalLock ? trim((string) optional($globalActiveSession->creator)->name) : '';

            foreach ($order->items as $item) {
                $requiredQty = max(0, (int) $item->quantity - (int) $item->fulfilled_quantity);
                if ($requiredQty <= 0) {
                    continue;
                }

                $partNumber = (string) $item->part_number;
                $reservedPool = $reservedPools[$order->id][$partNumber] ?? [];
                $lockedPool = $lockedPools[$order->id][$partNumber] ?? [];
                $pool = $fifoPools[$partNumber] ?? [];

                $qtyPerBox = (int) ($partSettings[$partNumber] ?? 0);
                if ($qtyPerBox > 0) {
                    $fullBoxNeeded = intdiv($requiredQty, $qtyPerBox);
                    $needsNotFull = ($requiredQty % $qtyPerBox) > 0;
                    $totalBoxesToPick += $fullBoxNeeded + ($needsNotFull ? 1 : 0);

                    $fullAvailable = 0;
                    $hasNotFull = false;

                    foreach (array_merge($reservedPool, $lockedPool) as $entry) {
                        $entryQty = (int) ($entry['qty'] ?? 0);
                        $entryIsNotFull = (bool) ($entry['is_not_full'] ?? false) || $entryQty < $qtyPerBox;

                        if ($entryIsNotFull) {
                            $hasNotFull = true;
                        } elseif ($entryQty === $qtyPerBox) {
                            $fullAvailable++;
                        }
                    }

                    $fullMissing = max(0, $fullBoxNeeded - $fullAvailable);
                    $poolIndex = 0;
                    while (($fullMissing > 0 || ($needsNotFull && !$hasNotFull)) && isset($pool[$poolIndex])) {
                        $entry = $pool[$poolIndex];
                        $entryQty = (int) ($entry['qty'] ?? 0);
                        $entryIsNotFull = (bool) ($entry['is_not_full'] ?? false) || $entryQty < $qtyPerBox;

                        if ($fullMissing > 0 && !$entryIsNotFull && $entryQty === $qtyPerBox) {
                            $fullMissing--;
                            array_splice($pool, $poolIndex, 1);
                            continue;
                        }

                        if ($needsNotFull && !$hasNotFull && $entryIsNotFull) {
                            $hasNotFull = true;
                            array_splice($pool, $poolIndex, 1);
                            continue;
                        }

                        $poolIndex++;
                    }

                    if ($fullMissing > 0 || ($needsNotFull && !$hasNotFull)) {
                        $isReadyToPick = false;
                    }

                    $fifoPools[$partNumber] = $pool;
                    continue;
                }

                $reservedForOrder = 0;
                foreach (array_merge($reservedPool, $lockedPool) as $box) {
                    $reservedForOrder += (int) ($box['qty'] ?? 0);
                }

                $remainingNeeded = max(0, $requiredQty - $reservedForOrder);
                $takenFromFifo = 0;
                $selectedFifoBoxes = 0;

                $poolIndex = 0;
                while ($remainingNeeded > 0 && isset($pool[$poolIndex])) {
                    $box = $pool[$poolIndex];
                    $nextBoxQty = (int) ($box['qty'] ?? 0);
                    $isNotFull = (bool) ($box['is_not_full'] ?? false);

                    if ($nextBoxQty <= $remainingNeeded || $isNotFull) {
                        $takenFromFifo += $nextBoxQty;
                        $remainingNeeded -= $nextBoxQty;
                        $selectedFifoBoxes++;
                        array_splice($pool, $poolIndex, 1);
                    } else {
                        $poolIndex++;
                    }
                }

                $fifoPools[$partNumber] = $pool;

                $strictFulfillable = min($requiredQty, $reservedForOrder + $takenFromFifo);
                if ($strictFulfillable < $requiredQty) {
                    $isReadyToPick = false;
                }

                $totalBoxesToPick += count($reservedPool) + count($lockedPool) + $selectedFifoBoxes;
            }

            $order->total_box_to_pick = $totalBoxesToPick;
            $order->has_pending_additional_approval = $hasPendingAdditionalApproval;

            if ($hasPendingAdditionalApproval) {
                $order->is_ready_to_pick = false;
                $order->readiness_reason = 'Pending approval not full tambahan';
            } elseif ($hasGlobalLock && !$isOwner) {
                $order->is_ready_to_pick = false;
                $order->readiness_reason = null;
            } else {
                $order->is_ready_to_pick = $isReadyToPick || $isOwner;
                $order->readiness_reason = null;
            }

            $order->has_active_pick_session = $isActiveOrder;
            $order->active_pick_owned_by_current_user = $isOwner;
        });

        return view('operator.delivery.picking-verification', compact('orders'));
    }

    private function createSessionItems(DeliveryOrder $order, DeliveryPickSession $session): void
    {
        foreach ($order->items as $item) {
            $remainingQty = max(0, (int) $item->quantity - (int) $item->fulfilled_quantity);
            if ($remainingQty <= 0) {
                continue;
            }

            $reservedBoxes = $this->getReservedBoxesForOrder($order->id, $item->part_number, $order->delivery_date, true);
            foreach ($reservedBoxes as $box) {
                DeliveryPickItem::firstOrCreate(
                    [
                        'pick_session_id' => $session->id,
                        'box_id' => $box->id,
                    ],
                    [
                        'part_number' => $box->part_number,
                        'pcs_quantity' => (int) $box->pcs_quantity,
                        'status' => 'pending',
                    ]
                );
            }

            $reservedTotal = $reservedBoxes->sum('pcs_quantity');
            $remainingAfterReserved = max(0, $remainingQty - (int) $reservedTotal);

            $boxes = $this->getBoxesByFIFO($item->part_number, $remainingAfterReserved, $order->delivery_date, true);
            $totalPcs = $boxes->sum('pcs_quantity') + (int) $reservedTotal;

            if ($totalPcs < $remainingQty) {
                throw new \RuntimeException('Stok box tidak cukup untuk part ' . $item->part_number);
            }

            foreach ($boxes as $box) {
                DeliveryPickItem::firstOrCreate(
                    [
                        'pick_session_id' => $session->id,
                        'box_id' => $box->id,
                    ],
                    [
                        'part_number' => $box->part_number,
                        'pcs_quantity' => (int) $box->pcs_quantity,
                        'status' => 'pending',
                    ]
                );
            }
        }
    }

    private function startOrReusePickSession(DeliveryOrder $order, int $userId): DeliveryPickSession
    {
        return DB::transaction(function () use ($order, $userId) {
            DeliveryOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->assertOrderDeliveryGateOpenOrFail((int) $order->id, true);

            $activeSession = DeliveryPickSession::with('creator')
                ->whereIn('status', self::ACTIVE_LOCK_STATUSES)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->first();

            if ($activeSession) {
                $activeOrderId = (int) $activeSession->delivery_order_id;
                $isOwner = (int) $activeSession->created_by === $userId;

                if ($activeOrderId === (int) $order->id) {
                    if (!$isOwner) {
                        $ownerName = trim((string) optional($activeSession->creator)->name);
                        throw new \RuntimeException('Order ini sedang diproses oleh ' . ($ownerName !== '' ? $ownerName : 'operator lain') . '.');
                    }

                    return $activeSession;
                }

                if ($isOwner) {
                    throw new \RuntimeException('Anda masih memiliki proses delivery aktif di order #' . $activeOrderId . '. Lanjutkan atau batalkan dulu.');
                }

                $ownerName = trim((string) optional($activeSession->creator)->name);
                throw new \RuntimeException('Delivery sedang diproses oleh ' . ($ownerName !== '' ? $ownerName : 'operator lain') . '.');
            }

            $session = DeliveryPickSession::create([
                'delivery_order_id' => $order->id,
                'created_by' => $userId,
                'status' => 'scanning',
                'started_at' => now(),
            ]);

            $this->createSessionItems($order, $session);

            if (!$session->items()->exists()) {
                throw new \RuntimeException('Semua item sudah terpenuhi.');
            }

            return $session;
        });
    }

    public function startPick($orderId)
    {
        $user = Auth::user();
        if (!in_array($user->role, ['warehouse_operator', 'admin'], true)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $order = DeliveryOrder::with('items')->findOrFail($orderId);

        try {
            $session = $this->startOrReusePickSession($order, (int) $user->id);
        } catch (\Throwable $e) {
            $message = (string) $e->getMessage();
            $statusCode = $message === self::DELIVERY_APPROVAL_PENDING_MESSAGE || str_contains($message, 'diproses') || str_contains($message, 'aktif')
                ? 423
                : 422;
            return response()->json(['message' => $e->getMessage()], $statusCode);
        }

        return response()->json([
            'session_id' => $session->id,
            'pdf_url' => route('delivery.pick.pdf', [$order->id, $session->id]),
            'print_preview_url' => route('delivery.pick.print-preview', [$order->id, $session->id]),
            'scan_url' => route('delivery.pick.scan', [$order->id, $session->id]),
        ]);
    }

    public function startVerification($orderId)
    {
        $user = Auth::user();
        if (!in_array($user->role, ['warehouse_operator', 'admin'], true)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $order = DeliveryOrder::with('items')->findOrFail($orderId);

        try {
            $session = $this->startOrReusePickSession($order, (int) $user->id);
        } catch (\Throwable $e) {
            $message = (string) $e->getMessage();
            $statusCode = $message === self::DELIVERY_APPROVAL_PENDING_MESSAGE || str_contains($message, 'diproses') || str_contains($message, 'aktif')
                ? 423
                : 422;
            return response()->json(['message' => $e->getMessage()], $statusCode);
        }

        return response()->json([
            'session_id' => $session->id,
            'verify_url' => route('delivery.pick.verify', [$order->id, $session->id]),
            'final_scan_url' => route('delivery.pick.scan', [$order->id, $session->id]),
        ]);
    }

    public function showScan($orderId, $sessionId)
    {
        $user = Auth::user();
        if (!in_array($user->role, ['warehouse_operator', 'admin'], true)) {
            return redirect()->route('delivery.index')->with('error', 'Unauthorized.');
        }

        $session = DeliveryPickSession::with(['items.box.pallets.stockLocation', 'issues'])
            ->where('delivery_order_id', $orderId)
            ->findOrFail($sessionId);

        if ((int) $session->created_by !== (int) $user->id && $user->role !== 'admin') {
            return redirect()->route('delivery.index')->with('error', 'Sesi ini dimiliki operator lain.');
        }

        if (in_array((string) $session->status, ['stale', 'cancelled'], true)) {
            return redirect()->route('delivery.index')->with('error', self::SESSION_RECALC_MESSAGE);
        }
        $order = DeliveryOrder::findOrFail($orderId);

        return view('operator.delivery.scan', compact('session', 'order'));
    }

    public function showVerificationScan($orderId, $sessionId)
    {
        $user = Auth::user();
        if (!in_array($user->role, ['warehouse_operator', 'admin'], true)) {
            return redirect()->route('delivery.index')->with('error', 'Unauthorized.');
        }

        $session = DeliveryPickSession::with(['items.box.pallets.stockLocation'])
            ->where('delivery_order_id', $orderId)
            ->findOrFail($sessionId);

        if ((int) $session->created_by !== (int) $user->id && $user->role !== 'admin') {
            return redirect()->route('delivery.index')->with('error', 'Sesi ini dimiliki operator lain.');
        }

        if (in_array((string) $session->status, ['stale', 'cancelled'], true)) {
            return redirect()->route('delivery.index')->with('error', self::SESSION_RECALC_MESSAGE);
        }

        $order = DeliveryOrder::findOrFail($orderId);

        $verifiedBoxIds = collect($session->verification_box_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        return view('operator.delivery.verify-scan', compact('session', 'order', 'verifiedBoxIds'));
    }

    public function cancel($sessionId)
    {
        $user = Auth::user();
        if (!in_array($user->role, ['warehouse_operator', 'admin'], true)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $result = DB::transaction(function () use ($sessionId, $user) {
            $session = DeliveryPickSession::whereKey($sessionId)->lockForUpdate()->firstOrFail();

            if ((int) $session->created_by !== (int) $user->id && $user->role !== 'admin') {
                return ['success' => false, 'message' => 'Sesi ini dimiliki operator lain.', 'status' => 403];
            }

            if (!in_array((string) $session->status, self::ACTIVE_LOCK_STATUSES, true)) {
                return ['success' => false, 'message' => 'Sesi tidak dapat dibatalkan.', 'status' => 409];
            }

            if ($session->status === 'blocked' && $user->role !== 'admin') {
                return [
                    'success' => false,
                    'message' => 'Sesi sedang diblokir karena issue scan. Tunggu approval admin, operator tidak dapat membatalkan sesi ini.',
                    'status' => 423,
                ];
            }

            DeliveryPickItem::where('pick_session_id', (int) $session->id)->lockForUpdate()->delete();
            DeliveryIssue::where('pick_session_id', (int) $session->id)->where('status', 'pending')->delete();

            $notes = trim((string) $session->approval_notes);
            $cancelNote = 'Sesi dibatalkan oleh ' . ($user->name ?? ('user #' . $user->id)) . '.';

            $session->status = 'cancelled';
            $session->verification_box_ids = null;
            $session->approval_notes = $notes !== '' ? ($notes . ' | ' . $cancelNote) : $cancelNote;
            $session->save();

            return ['success' => true, 'message' => 'Proses scan dibatalkan dan lock box telah dilepas.', 'status' => 200];
        });

        return response()->json([
            'success' => (bool) ($result['success'] ?? false),
            'message' => $result['message'] ?? null,
        ], (int) ($result['status'] ?? 200));
    }

    public function scanBox(Request $request, $sessionId)
    {
        $user = Auth::user();
        if (!in_array($user->role, ['warehouse_operator', 'admin'], true)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'box_number' => 'required|string',
        ]);

        $result = DB::transaction(function () use ($request, $sessionId, $user) {
            $session = DeliveryPickSession::whereKey($sessionId)->lockForUpdate()->firstOrFail();

            if ((int) $session->created_by !== (int) $user->id && $user->role !== 'admin') {
                return ['success' => false, 'message' => 'Sesi ini dimiliki operator lain.', 'status' => 403];
            }

            if ($this->hasPendingAdditionalNotFullRequestForOrder((int) $session->delivery_order_id, true)) {
                return ['success' => false, 'message' => self::DELIVERY_APPROVAL_PENDING_MESSAGE, 'status' => 423];
            }

            if ($session->status === 'blocked') {
                return ['success' => false, 'message' => 'Scan diblokir. Tunggu approval admin.', 'status' => 423];
            }

            if ($session->status === 'stale') {
                return ['success' => false, 'message' => self::SESSION_RECALC_MESSAGE, 'status' => 409];
            }

            if ($session->status === 'completed') {
                return ['success' => false, 'message' => 'Session sudah completed.', 'status' => 409];
            }

            $box = Box::where('box_number', $request->box_number)->lockForUpdate()->first();

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

                return [
                    'success' => false,
                    'message' => $box->is_withdrawn ? 'Box sudah withdrawn. Menunggu approval admin.' : 'Box sudah expired/handled. Menunggu approval admin.',
                    'status' => 422,
                ];
            }

            $pickItem = $box
                ? DeliveryPickItem::where('pick_session_id', $session->id)
                    ->where('box_id', $box->id)
                    ->lockForUpdate()
                    ->first()
                : null;

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

                return ['success' => false, 'message' => 'Box tidak sesuai. Menunggu approval admin.', 'status' => 422];
            }

            $updated = DeliveryPickItem::whereKey($pickItem->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'scanned',
                    'scanned_at' => now(),
                    'scanned_by' => Auth::id(),
                ]);

            if ($updated === 0) {
                return ['success' => false, 'message' => 'Box sudah discan.', 'status' => 409];
            }

            $remaining = DeliveryPickItem::where('pick_session_id', $session->id)
                ->where('status', 'pending')
                ->count();

            return [
                'success' => true,
                'message' => 'Scan berhasil.',
                'remaining' => $remaining,
                'box_id' => $box?->id,
                'status' => 200,
            ];
        });

        return response()->json([
            'success' => (bool) ($result['success'] ?? false),
            'message' => $result['message'] ?? null,
            'remaining' => $result['remaining'] ?? null,
            'box_id' => $result['box_id'] ?? null,
        ], (int) ($result['status'] ?? 200));
    }

    public function verifyScanBox(Request $request, $sessionId)
    {
        $user = Auth::user();
        if (!in_array($user->role, ['warehouse_operator', 'admin'], true)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'box_number' => 'required|string',
        ]);

        $result = DB::transaction(function () use ($request, $sessionId, $user) {
            $session = DeliveryPickSession::whereKey($sessionId)->lockForUpdate()->firstOrFail();

            if ((int) $session->created_by !== (int) $user->id && $user->role !== 'admin') {
                return ['success' => false, 'message' => 'Sesi ini dimiliki operator lain.', 'status' => 403];
            }

            if ($this->hasPendingAdditionalNotFullRequestForOrder((int) $session->delivery_order_id, true)) {
                return ['success' => false, 'message' => self::DELIVERY_APPROVAL_PENDING_MESSAGE, 'status' => 423];
            }

            if ($session->status === 'blocked') {
                return ['success' => false, 'message' => 'Scan diblokir. Tunggu approval admin.', 'status' => 423];
            }

            if ($session->status === 'stale') {
                return ['success' => false, 'message' => self::SESSION_RECALC_MESSAGE, 'status' => 409];
            }

            if ($session->status === 'completed') {
                return ['success' => false, 'message' => 'Session sudah completed.', 'status' => 409];
            }

            $box = Box::where('box_number', $request->box_number)->lockForUpdate()->first();
            if (!$box) {
                return ['success' => false, 'message' => 'Box tidak sesuai daftar picking.', 'status' => 422];
            }

            if ($box->is_withdrawn || in_array($box->expired_status, ['handled', 'expired'], true)) {
                return ['success' => false, 'message' => 'Box tidak sesuai daftar picking.', 'status' => 422];
            }

            $pickItemExists = DeliveryPickItem::where('pick_session_id', $session->id)
                ->where('box_id', $box->id)
                ->exists();

            if (!$pickItemExists) {
                return ['success' => false, 'message' => 'Box tidak sesuai daftar picking.', 'status' => 422];
            }

            $verifiedBoxIds = collect($session->verification_box_ids ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values();

            $already = $verifiedBoxIds->contains((int) $box->id);
            if (!$already) {
                $verifiedBoxIds->push((int) $box->id);
                $session->verification_box_ids = $verifiedBoxIds->unique()->values()->all();
                $session->save();
            }

            $totalItems = DeliveryPickItem::where('pick_session_id', $session->id)->count();
            $remaining = max(0, $totalItems - $verifiedBoxIds->unique()->count());

            return [
                'success' => true,
                'message' => $already ? 'Box sudah diverifikasi.' : 'Scan verifikasi berhasil.',
                'remaining' => $remaining,
                'box_id' => $box->id,
                'status' => 200,
            ];
        });

        return response()->json([
            'success' => (bool) ($result['success'] ?? false),
            'message' => $result['message'] ?? null,
            'remaining' => $result['remaining'] ?? null,
            'box_id' => $result['box_id'] ?? null,
        ], (int) ($result['status'] ?? 200));
    }

    public function approveIssue(Request $request, $issueId)
    {
        $user = Auth::user();
        if (!in_array($user->role, ['admin_warehouse', 'admin'], true)) {
            return redirect()->back()->with('error', 'Unauthorized.');
        }

        $issue = DeliveryIssue::with('session')->findOrFail($issueId);
        if ($issue->status !== 'pending') {
            return redirect()->back()->with('error', 'Issue sudah diproses sebelumnya.');
        }

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
        $user = Auth::user();
        if (!in_array($user->role, ['warehouse_operator', 'admin'], true)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $result = DB::transaction(function () use ($sessionId, $user) {
                $session = DeliveryPickSession::whereKey($sessionId)->lockForUpdate()->firstOrFail();

                if ((int) $session->created_by !== (int) $user->id && $user->role !== 'admin') {
                    return ['success' => false, 'message' => 'Sesi ini dimiliki operator lain.', 'status' => 403];
                }

                if ($this->hasPendingAdditionalNotFullRequestForOrder((int) $session->delivery_order_id, true)) {
                    return ['success' => false, 'message' => self::DELIVERY_APPROVAL_PENDING_MESSAGE, 'status' => 423];
                }

                if ($session->status === 'completed') {
                    return ['success' => true, 'message' => 'Session sudah completed.'];
                }

                if ($session->status === 'stale') {
                    return ['success' => false, 'message' => self::SESSION_RECALC_MESSAGE, 'status' => 409];
                }

                if ($session->status === 'blocked') {
                    return ['success' => false, 'message' => 'Session diblokir.', 'status' => 423];
                }

                $hasPendingIssue = DeliveryIssue::where('pick_session_id', $session->id)
                    ->where('status', 'pending')
                    ->lockForUpdate()
                    ->exists();
                if ($hasPendingIssue) {
                    return ['success' => false, 'message' => 'Ada issue scan yang belum di-approve.', 'status' => 423];
                }

                $pendingItemsCount = DeliveryPickItem::where('pick_session_id', $session->id)
                    ->where('status', 'pending')
                    ->lockForUpdate()
                    ->count();
                if ($pendingItemsCount > 0) {
                    return ['success' => false, 'message' => 'Masih ada box yang belum discan.', 'status' => 422];
                }

                $order = DeliveryOrder::with('items')->whereKey($session->delivery_order_id)->lockForUpdate()->firstOrFail();
                $sessionItems = DeliveryPickItem::with(['box.pallets.stockLocation'])
                    ->where('pick_session_id', $session->id)
                    ->lockForUpdate()
                    ->get();

                $batchId = (string) Str::uuid();

                foreach ($sessionItems as $pickItem) {
                    $box = Box::whereKey($pickItem->box_id)->lockForUpdate()->first();

                    if (!$box) {
                        continue;
                    }

                    $pallet = $box->pallets()->first();
                    $palletItem = null;

                    if ($pallet) {
                        $palletItem = PalletItem::where('pallet_id', $pallet->id)
                            ->where('part_number', $box->part_number)
                            ->lockForUpdate()
                            ->first();
                    }

                    $existingWithdrawal = StockWithdrawal::where('pick_session_id', $session->id)
                        ->where('box_id', $box->id)
                        ->first();

                    if (!$existingWithdrawal) {
                        StockWithdrawal::create([
                            'withdrawal_batch_id' => $batchId,
                            'pick_session_id' => $session->id,
                            'user_id' => Auth::id(),
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
                    }

                    if (!$box->is_withdrawn) {
                        $box->is_withdrawn = true;
                        $box->withdrawn_at = now();
                        $box->save();

                        if ($palletItem) {
                            $palletItem->pcs_quantity = max(0, $palletItem->pcs_quantity - (int) $box->pcs_quantity);
                            $palletItem->box_quantity = max(0, $palletItem->box_quantity - 1);
                            $palletItem->save();
                        }
                    }

                    if ($pallet) {
                        $masterLocation = MasterLocation::where('current_pallet_id', $pallet->id)->lockForUpdate()->first();
                        if ($masterLocation) {
                            $masterLocation->autoVacateIfEmpty();
                        }
                    }
                }

                $session = DeliveryPickSession::with('items')->findOrFail($session->id);

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

                return ['success' => true];
            });

            $statusCode = (int) ($result['status'] ?? 200);
            return response()->json([
                'success' => (bool) ($result['success'] ?? false),
                'message' => $result['message'] ?? null,
            ], $statusCode);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function redo($sessionId)
    {
        $user = Auth::user();
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
            $notFullBoxCount = 0;
            $notFullRequestsCount = 0;
            
            if (!empty($boxIds)) {
                $redoContext = $this->buildRedoContext($session, $boxIds);
                $withdrawals = $redoContext['withdrawals'];

                $this->restoreBoxesToPallets(
                    $redoContext['allSessionBoxes'],
                    $withdrawals,
                    $redoContext['palletItemsById'],
                    $redoContext['boxPalletIdsByBoxId']
                );

                $this->reverseWithdrawalsAndRestorePalletItems($withdrawals, $redoContext['palletItemsById']);
                $notFullBoxCount = $this->resetSessionBoxAssignments($session, $redoContext['allSessionBoxes']);
                $notFullRequestsCount = $this->cancelNotFullRequestsForOrder((int) $session->order->id);

                // Log not_full boxes restoration if any were affected
                if ($notFullBoxCount > 0 || $notFullRequestsCount > 0) {
                    AuditService::log(
                        'delivery_redo',
                        'not_full_restored',
                        'DeliveryPickSession',
                        $session->id,
                        [],
                        [
                            'restored_not_full_boxes' => $notFullBoxCount,
                            'cancelled_requests' => $notFullRequestsCount
                        ],
                        "Redo delivery: {$notFullBoxCount} box not_full dikembalikan ke stok, {$notFullRequestsCount} request dibatalkan"
                    );
                }

                // Log batch reversal satu kali dengan detail semua boxes
                AuditService::logBatchStockWithdrawal($session, 'reversed');

                $this->rollbackOrderFulfilledQuantities($session->order, $withdrawals);
            }

            $session->completion_status = 'redone';
            $session->save();

            // Log delivery redo
            AuditService::logDeliveryRedo($session->id, "Pengambilan delivery di-redo oleh " . (Auth::user()?->name ?? 'system'));

            $order = $session->order;
            $order->status = 'processing';
            $order->save();

            DB::commit();
            
            // Build detailed success message
            $successMessage = $this->buildRedoSuccessMessage($notFullBoxCount, $notFullRequestsCount);
            
            return redirect()->back()->with('success', $successMessage);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Redo gagal: ' . $e->getMessage());
        }
    }

    private function buildRedoContext(DeliveryPickSession $session, array $boxIds): array
    {
        $withdrawals = StockWithdrawal::where('status', 'completed')
            ->where(function ($query) use ($session, $boxIds) {
                $query->where('pick_session_id', $session->id)
                    ->orWhere(function ($legacy) use ($boxIds) {
                        $legacy->whereNull('pick_session_id')
                            ->whereIn('box_id', $boxIds);
                    });
            })
            ->get();

        $palletItemIds = $withdrawals
            ->pluck('pallet_item_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $palletItemsById = $palletItemIds->isEmpty()
            ? collect()
            : PalletItem::whereIn('id', $palletItemIds)->get()->keyBy('id');

        $boxPalletIdsByBoxId = DB::table('stock_inputs')
            ->join('pallet_boxes', 'pallet_boxes.pallet_id', '=', 'stock_inputs.pallet_id')
            ->whereIn('pallet_boxes.box_id', $boxIds)
            ->select('pallet_boxes.box_id', 'stock_inputs.pallet_id', 'stock_inputs.id')
            ->orderBy('stock_inputs.id', 'desc')
            ->get()
            ->groupBy('box_id')
            ->map(function ($rows) {
                return (int) optional($rows->first())->pallet_id;
            });

        $allSessionBoxes = Box::with('pallets')->whereIn('id', $boxIds)->get();

        return [
            'withdrawals' => $withdrawals,
            'palletItemsById' => $palletItemsById,
            'boxPalletIdsByBoxId' => $boxPalletIdsByBoxId,
            'allSessionBoxes' => $allSessionBoxes,
        ];
    }

    private function restoreBoxesToPallets($allSessionBoxes, $withdrawals, $palletItemsById, $boxPalletIdsByBoxId): void
    {
        foreach ($allSessionBoxes as $box) {
            if (!$box instanceof Box) {
                continue;
            }

            $boxId = (int) $box->getKey();
            $boxWithdrawal = $withdrawals->firstWhere('box_id', $boxId);

            if ($box->is_withdrawn) {
                $box->is_withdrawn = false;
                $box->withdrawn_at = null;
                $box->save();
            }

            if ($box->pallets->isEmpty()) {
                $palletId = null;

                if ($boxWithdrawal instanceof StockWithdrawal && $boxWithdrawal->getAttribute('pallet_item_id')) {
                    $palletItem = $palletItemsById->get((int) $boxWithdrawal->getAttribute('pallet_item_id'));
                    if ($palletItem) {
                        $palletId = $palletItem->pallet_id;
                    }
                }

                if (!$palletId) {
                    $palletId = $boxPalletIdsByBoxId->get($boxId);
                }

                if ($palletId) {
                    $box->pallets()->attach($palletId);
                }
            }
        }
    }

    private function reverseWithdrawalsAndRestorePalletItems($withdrawals, $palletItemsById): void
    {
        foreach ($withdrawals as $withdrawal) {
            if (!$withdrawal instanceof StockWithdrawal) {
                continue;
            }

            if ($withdrawal->getAttribute('pallet_item_id')) {
                $palletItem = $palletItemsById->get((int) $withdrawal->getAttribute('pallet_item_id'));
                if ($palletItem) {
                    $palletItem->pcs_quantity += $withdrawal->pcs_quantity;
                    $palletItem->box_quantity += $withdrawal->box_quantity;
                    $palletItem->save();
                }
            }

            $withdrawal->status = 'reversed';
            $withdrawal->save();
        }
    }

    private function resetSessionBoxAssignments(DeliveryPickSession $session, $allSessionBoxes): int
    {
        $notFullBoxCount = 0;

        foreach ($allSessionBoxes as $box) {
            if (!$box instanceof Box) {
                continue;
            }

            $box->refresh();

            if ($box->assigned_delivery_order_id == $session->order->id) {
                $box->assigned_delivery_order_id = null;
                $box->save();

                if ($box->is_not_full) {
                    $notFullBoxCount++;
                }
            }
        }

        return $notFullBoxCount;
    }

    private function cancelNotFullRequestsForOrder(int $orderId): int
    {
        $notFullRequestQuery = \App\Models\NotFullBoxRequest::where('delivery_order_id', $orderId)
            ->whereIn('status', ['approved', 'pending']);

        $notFullRequestsCount = (clone $notFullRequestQuery)->count();
        if ($notFullRequestsCount > 0) {
            $notFullRequestQuery->update(['status' => 'cancelled_by_redo']);
        }

        return $notFullRequestsCount;
    }

    private function rollbackOrderFulfilledQuantities(DeliveryOrder $order, $withdrawals): void
    {
        foreach ($order->items as $orderItem) {
            $totalReversed = $withdrawals
                ->where('part_number', $orderItem->part_number)
                ->sum('pcs_quantity');

            $orderItem->fulfilled_quantity = max(0, (int) $orderItem->fulfilled_quantity - (int) $totalReversed);
            $orderItem->save();
        }
    }

    private function buildRedoSuccessMessage(int $notFullBoxCount, int $notFullRequestsCount): string
    {
        $successMessage = 'Redo berhasil.';
        if ($notFullBoxCount > 0 || $notFullRequestsCount > 0) {
            $successMessage .= ' Stok yang ditarik dan box not_full telah dikembalikan ke inventori.';
            if ($notFullBoxCount > 0) {
                $successMessage .= " {$notFullBoxCount} box not_full dikembalikan.";
            }
            if ($notFullRequestsCount > 0) {
                $successMessage .= " {$notFullRequestsCount} request not_full dibatalkan.";
            }
        }

        return $successMessage;
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

    public function printPreview($orderId, $sessionId)
    {
        $session = DeliveryPickSession::with([
            'creator',
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

        return view('operator.delivery.picklist_print', compact('order', 'session'));
    }

    public function issues()
    {
        $user = Auth::user();
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

    public function lockManagement()
    {
        $user = Auth::user();
        if ($user->role !== 'admin') {
            return redirect()->route('dashboard')->with('error', 'Unauthorized.');
        }

        $activeLocks = DeliveryPickSession::with(['creator:id,name', 'order:id,customer_name,delivery_date,status'])
            ->withCount([
                'items',
                'issues as pending_issue_count' => function ($q) {
                    $q->where('status', 'pending');
                },
            ])
            ->whereIn('status', self::ACTIVE_LOCK_STATUSES)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        return view('operator.delivery.lock-management', compact('activeLocks'));
    }

    public function terminateLock(Request $request, $sessionId)
    {
        $user = Auth::user();
        if ($user->role !== 'admin') {
            return redirect()->route('dashboard')->with('error', 'Unauthorized.');
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $result = DB::transaction(function () use ($sessionId, $user, $validated) {
                $session = DeliveryPickSession::whereKey($sessionId)->lockForUpdate()->firstOrFail();

                if (!in_array((string) $session->status, self::ACTIVE_LOCK_STATUSES, true)) {
                    return ['success' => false, 'message' => 'Sesi tidak aktif atau sudah tidak terkunci.'];
                }

                DeliveryPickItem::where('pick_session_id', (int) $session->id)->lockForUpdate()->delete();
                DeliveryIssue::where('pick_session_id', (int) $session->id)->where('status', 'pending')->delete();

                $notes = trim((string) $session->approval_notes);
                $reason = trim((string) ($validated['reason'] ?? ''));
                $terminateNote = 'Force terminate oleh ' . ($user->name ?? ('user #' . $user->id)) . ': ' . $reason;

                $session->status = 'cancelled';
                $session->verification_box_ids = null;
                $session->approval_notes = $notes !== '' ? ($notes . ' | ' . $terminateNote) : $terminateNote;
                $session->save();

                return [
                    'success' => true,
                    'message' => 'Lock berhasil dilepas untuk order #' . (int) $session->delivery_order_id . '.',
                ];
            });
        } catch (\Throwable $e) {
            return redirect()->route('delivery.pick.locks')->with('error', 'Gagal terminate lock: ' . $e->getMessage());
        }

        if (!($result['success'] ?? false)) {
            return redirect()->route('delivery.pick.locks')->with('error', (string) ($result['message'] ?? 'Terminate lock gagal.'));
        }

        return redirect()->route('delivery.pick.locks')->with('success', (string) $result['message']);
    }

    private function getReservedBoxesForOrder(int $orderId, string $partNumber, $deliveryDate = null, bool $lockRows = false)
    {
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
            ->where('stock_locations.warehouse_location', '!=', 'Unknown')
            ->orderBy('boxes.created_at', 'asc')
            ->select('boxes.*')
            ->when($deliveryDate, function ($q) use ($deliveryDate) {
                $cutoffDate = \Carbon\Carbon::parse($deliveryDate)->subMonths(12);
                $q->where('boxes.created_at', '>=', $cutoffDate);
            });

        if ($lockRows) {
            $query->lockForUpdate();
        }

        return $query->get();
    }

    private function getBoxesByFIFO(string $partNumber, int $requestedPcs, $deliveryDate = null, bool $lockRows = false)
    {
        $query = Box::query()
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
            ->where('stock_locations.warehouse_location', '!=', 'Unknown')
            ->when($deliveryDate, function ($q) use ($deliveryDate) {
                $cutoffDate = \Carbon\Carbon::parse($deliveryDate)->subMonths(12);
                $q->where('boxes.created_at', '>=', $cutoffDate);
            })
            ->orderBy('boxes.created_at', 'asc')
            ->select('boxes.*');

        if ($lockRows) {
            $query->lockForUpdate();
        }

        $boxes = $query->get();

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