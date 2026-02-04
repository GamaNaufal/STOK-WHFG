<?php

namespace App\Http\Controllers;

use App\Models\Box;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\MasterLocation;
use App\Models\NotFullBoxRequest;
use App\Models\Pallet;
use App\Models\PalletItem;
use App\Models\PartSetting;
use App\Models\StockInput;
use App\Models\StockLocation;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class NotFullBoxRequestController extends Controller
{
    public function create()
    {
        $partNumbers = PartSetting::orderBy('part_number', 'asc')->get(['part_number', 'qty_box']);
        $deliveryOrders = DeliveryOrder::whereIn('status', ['approved', 'processing'])
            ->orderBy('delivery_date', 'asc')
            ->get(['id', 'customer_name', 'delivery_date', 'status']);
        $pallets = Pallet::with('stockLocation')
            ->whereHas('stockLocation')
            ->orderBy('pallet_number', 'asc')
            ->get();
        $locations = MasterLocation::where('is_occupied', false)
            ->orderBy('code', 'asc')
            ->get();

        $historyRequests = NotFullBoxRequest::with(['deliveryOrder', 'approver'])
            ->where('requested_by', Auth::id())
            ->whereIn('status', ['approved', 'rejected'])
            ->orderBy('updated_at', 'desc')
            ->limit(50)
            ->get();

        return view('operator.box-not-full.create', compact('partNumbers', 'deliveryOrders', 'pallets', 'locations', 'historyRequests'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'box_number' => 'required|string|max:100',
            'part_number' => 'required|string|exists:part_settings,part_number',
            'pcs_quantity' => 'required|integer|min:1',
            'delivery_order_id' => 'required|integer|exists:delivery_orders,id',
            'reason' => 'required|string|min:3',
            'request_type' => 'required|in:supplement,additional',
            'target_type' => 'required|in:pallet,location',
            'target_pallet_id' => 'nullable|integer|exists:pallets,id',
            'target_location_id' => 'nullable|integer|exists:master_locations,id',
        ]);

        if (Box::where('box_number', $request->box_number)->exists()) {
            return redirect()->back()->with('error', 'ID Box sudah terdaftar di sistem.')->withInput();
        }

        if (NotFullBoxRequest::where('box_number', $request->box_number)->exists()) {
            return redirect()->back()->with('error', 'ID Box sudah pernah diajukan.')->withInput();
        }

        $partSetting = PartSetting::where('part_number', $request->part_number)->first();
        if (!$partSetting) {
            return redirect()->back()->with('error', 'No Part tidak ditemukan.')->withInput();
        }

        $fixedQty = (int) $partSetting->qty_box;
        if ((int) $request->pcs_quantity >= $fixedQty) {
            return redirect()->back()->with('error', 'PCS aktual harus lebih kecil dari fixed qty.')->withInput();
        }

        $deliveryOrder = DeliveryOrder::whereIn('status', ['approved', 'processing'])
            ->find($request->delivery_order_id);
        if (!$deliveryOrder) {
            return redirect()->back()->with('error', 'Delivery yang dipilih tidak valid.')->withInput();
        }

        if ($request->target_type === 'pallet' && empty($request->target_pallet_id)) {
            return redirect()->back()->with('error', 'Pilih pallet tujuan.')->withInput();
        }

        if ($request->target_type === 'location' && empty($request->target_location_id)) {
            return redirect()->back()->with('error', 'Pilih lokasi tujuan.')->withInput();
        }

        NotFullBoxRequest::create([
            'box_number' => $request->box_number,
            'part_number' => $request->part_number,
            'pcs_quantity' => (int) $request->pcs_quantity,
            'fixed_qty' => $fixedQty,
            'reason' => $request->reason,
            'request_type' => $request->request_type,
            'delivery_order_id' => $deliveryOrder->id,
            'requested_by' => Auth::id(),
            'target_pallet_id' => $request->target_type === 'pallet' ? $request->target_pallet_id : null,
            'target_location_id' => $request->target_type === 'location' ? $request->target_location_id : null,
        ]);

        return redirect()->back()->with('success', 'Permintaan Box Not Full berhasil dikirim ke Supervisi.');
    }

    public function approvals()
    {
        $requests = NotFullBoxRequest::with(['deliveryOrder', 'requester', 'targetPallet', 'targetLocation'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->get();

        $historyRequests = NotFullBoxRequest::with(['deliveryOrder', 'requester', 'approver'])
            ->whereIn('status', ['approved', 'rejected'])
            ->orderBy('updated_at', 'desc')
            ->limit(100)
            ->get();

        return view('supervisor.approvals', compact('requests', 'historyRequests'));
    }

    public function approve($id)
    {
        $request = NotFullBoxRequest::with(['deliveryOrder', 'targetLocation', 'targetPallet'])->findOrFail($id);

        if ($request->status !== 'pending') {
            return redirect()->back()->with('error', 'Status permintaan sudah diproses.');
        }

        DB::beginTransaction();
        try {
            $pallet = $request->targetPallet;
            $locationCode = null;

            if (!$pallet) {
                $location = $request->targetLocation;
                if (!$location || $location->is_occupied) {
                    throw new \Exception('Lokasi tidak tersedia.');
                }

                $pallet = Pallet::createNext();
                $locationCode = $location->code;

                $location->update([
                    'is_occupied' => true,
                    'current_pallet_id' => $pallet->id,
                ]);

                StockLocation::create([
                    'pallet_id' => $pallet->id,
                    'warehouse_location' => $locationCode,
                    'stored_at' => now(),
                ]);
            } else {
                $locationCode = $pallet->stockLocation?->warehouse_location ?? 'Unknown';
            }

            if (Box::where('box_number', $request->box_number)->exists()) {
                throw new \Exception('ID Box sudah terdaftar di sistem.');
            }

            $box = Box::create([
                'box_number' => $request->box_number,
                'part_number' => $request->part_number,
                'pcs_quantity' => $request->pcs_quantity,
                'qr_code' => $request->box_number . '|' . $request->part_number . '|' . $request->pcs_quantity,
                'user_id' => $request->requested_by,
                'qty_box' => $request->fixed_qty,
                'is_not_full' => true,
                'not_full_reason' => $request->reason,
                'assigned_delivery_order_id' => $request->delivery_order_id,
            ]);

            $pallet->boxes()->attach($box->id);

            $palletItem = PalletItem::firstOrCreate(
                ['pallet_id' => $pallet->id, 'part_number' => $request->part_number],
                ['box_quantity' => 0, 'pcs_quantity' => 0]
            );
            $palletItem->increment('box_quantity');
            $palletItem->increment('pcs_quantity', $request->pcs_quantity);

            if ($request->request_type === 'additional') {
                $orderItem = DeliveryOrderItem::where('delivery_order_id', $request->delivery_order_id)
                    ->where('part_number', $request->part_number)
                    ->first();
                if ($orderItem) {
                    $orderItem->quantity += $request->pcs_quantity;
                    $orderItem->save();
                } else {
                    DeliveryOrderItem::create([
                        'delivery_order_id' => $request->delivery_order_id,
                        'part_number' => $request->part_number,
                        'quantity' => $request->pcs_quantity,
                        'fulfilled_quantity' => 0,
                    ]);
                }
            }

            $stockInput = StockInput::create([
                'pallet_id' => $pallet->id,
                'pallet_item_id' => $palletItem->id,
                'user_id' => Auth::id(),
                'warehouse_location' => $locationCode ?? 'Unknown',
                'pcs_quantity' => $request->pcs_quantity,
                'box_quantity' => 1,
                'stored_at' => now(),
                'part_numbers' => [$request->part_number],
            ]);
            AuditService::logStockInput($stockInput, 'created');

            $request->status = 'approved';
            $request->approved_by = Auth::id();
            $request->approved_at = now();
            $request->box_id = $box->id;
            $request->target_location_code = $locationCode;
            $request->save();

            DB::commit();

            return redirect()->back()->with('success', 'Permintaan berhasil di-approve.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('NotFullBoxRequest approval failed', [
                'request_id' => $request->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);
            return redirect()->back()->with('error', 'Gagal approve. Silakan coba lagi atau hubungi admin.');
        }
    }

    public function reject($id)
    {
        $request = NotFullBoxRequest::findOrFail($id);

        if ($request->status !== 'pending') {
            return redirect()->back()->with('error', 'Status permintaan sudah diproses.');
        }

        $request->status = 'rejected';
        $request->approved_by = Auth::id();
        $request->approved_at = now();
        $request->save();

        return redirect()->back()->with('success', 'Permintaan ditolak.');
    }

}
