<?php

namespace App\Http\Controllers;

use App\Models\Box;
use App\Models\Pallet;
use App\Models\PalletItem;
use App\Models\PartSetting;
use App\Models\MasterLocation;
use App\Models\StockLocation;
use App\Models\StockInput;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockInputController extends Controller
{
    private function isDuplicateKeyException(QueryException $e): bool
    {
        $sqlState = (string) ($e->getCode() ?? '');
        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        return $sqlState === '23000' || $driverCode === 1062;
    }

    private function resolveActivePallet(): Pallet
    {
        $pallet_id = session('current_pallet_id');
        $pallet = $pallet_id ? Pallet::find($pallet_id) : null;

        if ($pallet) {
            return $pallet;
        }

        $maxAttempts = 5;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $maxNumber = Pallet::query()
                ->where('pallet_number', 'like', 'PLT-%')
                ->pluck('pallet_number')
                ->map(function ($palletNumber) {
                    preg_match('/-?(\d+)$/', (string) $palletNumber, $matches);
                    return isset($matches[1]) ? (int) $matches[1] : 0;
                })
                ->max() ?? 0;

            $nextNumber = $maxNumber + 1;
            $palletNumber = 'PLT-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

            try {
                $pallet = Pallet::create([
                    'pallet_number' => $palletNumber,
                ]);

                session([
                    'current_pallet_id' => $pallet->id,
                    'current_pallet_source' => 'new',
                ]);

                return $pallet;
            } catch (QueryException $e) {
                if ($this->isDuplicateKeyException($e) && $attempt < $maxAttempts) {
                    continue;
                }

                throw $e;
            }
        }

        throw new \RuntimeException('Gagal membuat nomor palet unik. Silakan coba lagi.');
    }

    public function index()
    {
        return view('operator.stock-input.index');
    }

    public function searchExistingPallet(Request $request)
    {
        $query = trim((string) $request->query('q', ''));

        $pallets = Pallet::query()
            ->with(['stockLocation'])
            ->withCount('activeBoxes')
            ->whereHas('stockLocation')
            ->whereHas('activeBoxes')
            ->when($query !== '', function ($q) use ($query) {
                $q->where('pallet_number', 'like', '%' . $query . '%');
            })
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(function (Pallet $pallet) {
                return [
                    'id' => $pallet->id,
                    'pallet_number' => $pallet->pallet_number,
                    'warehouse_location' => $pallet->stockLocation?->warehouse_location,
                    'total_boxes' => (int) $pallet->active_boxes_count,
                ];
            })
            ->values();

        return response()->json($pallets);
    }

    private function moveSessionPalletItems(Pallet $fromPallet, Pallet $toPallet): void
    {
        $fromItems = $fromPallet->items()->get();

        foreach ($fromItems as $fromItem) {
            $targetItem = PalletItem::query()
                ->where('pallet_id', $toPallet->id)
                ->where('part_number', $fromItem->part_number)
                ->first();

            if (!$targetItem) {
                PalletItem::create([
                    'pallet_id' => $toPallet->id,
                    'part_number' => $fromItem->part_number,
                    'box_quantity' => (int) $fromItem->box_quantity,
                    'pcs_quantity' => (int) $fromItem->pcs_quantity,
                ]);
                continue;
            }

            $targetItem->increment('box_quantity', (int) $fromItem->box_quantity);
            $targetItem->increment('pcs_quantity', (int) $fromItem->pcs_quantity);
        }

        $fromPallet->items()->delete();
    }

    public function selectExistingPallet(Request $request)
    {
        $validated = $request->validate([
            'pallet_id' => 'required|integer|exists:pallets,id',
        ]);

        if (session()->has('pending_box')) {
            return response()->json([
                'success' => false,
                'message' => 'Selesaikan scan No Part box yang sedang pending terlebih dahulu.',
            ], 422);
        }

        $pallet = Pallet::with('stockLocation')->findOrFail($validated['pallet_id']);

        if (!$pallet->stockLocation) {
            return response()->json([
                'success' => false,
                'message' => 'Pallet ini belum punya lokasi tersimpan dan tidak bisa dipilih sebagai pallet existing.',
            ], 422);
        }

        if (!$pallet->activeBoxes()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Pallet ini sudah kosong dan tidak bisa dipilih sebagai pallet existing.',
            ], 422);
        }

        $currentPalletId = session('current_pallet_id');
        $currentPalletSource = session('current_pallet_source', 'new');

        if ($currentPalletId && (int) $currentPalletId === (int) $pallet->id) {
            session([
                'current_pallet_id' => $pallet->id,
                'current_pallet_source' => 'existing',
            ]);

            return response()->json([
                'success' => true,
                'pallet_id' => $pallet->id,
                'pallet_number' => $pallet->pallet_number,
                'warehouse_location' => $pallet->stockLocation->warehouse_location,
                'message' => 'Pallet existing sudah aktif.',
            ]);
        }

        if ($currentPalletId && (int) $currentPalletId !== (int) $pallet->id) {
            $currentPallet = Pallet::with(['items', 'stockLocation'])->find($currentPalletId);

            if ($currentPallet) {
                $this->moveSessionPalletItems($currentPallet, $pallet);

                if ($currentPalletSource === 'new' && $currentPallet->boxes()->count() === 0 && !$currentPallet->stockLocation) {
                    $currentPallet->delete();
                }
            }
        }

        session([
            'current_pallet_id' => $pallet->id,
            'current_pallet_source' => 'existing',
        ]);

        return response()->json([
            'success' => true,
            'pallet_id' => $pallet->id,
            'pallet_number' => $pallet->pallet_number,
            'warehouse_location' => $pallet->stockLocation->warehouse_location,
            'message' => 'Pallet existing berhasil dipilih.',
        ]);
    }

    // API untuk scan Barcode box (dari hardware scanner)
    public function scanBarcode(Request $request)
    {
        $request->merge([
            'barcode' => preg_replace('/[[:cntrl:][:space:]]+/', '', (string) $request->input('barcode', '')),
        ]);

        $validated = $request->validate([
            'barcode' => ['bail', 'required', 'string', 'regex:/^[0-9]+$/'],
        ], [
            'barcode.regex' => 'ID Box hanya boleh berisi angka.',
        ]);

        $barcode = $validated['barcode'];

        // Pastikan tidak ada box pending yang belum di-scan no part
        if (session()->has('pending_box')) {
            return response()->json([
                'success' => false,
                'message' => 'Selesaikan scan No Part untuk box sebelumnya terlebih dahulu.'
            ], 400);
        }

        // Jika box sudah ada di DB dan sudah tersimpan, tolak
        $existingBox = Box::where('box_number', $barcode)->first();
        if ($existingBox) {
            $existingPallet = $existingBox->pallets()
                ->whereHas('stockLocation')
                ->first();

            if ($existingPallet) {
                return response()->json([
                    'success' => false,
                    'message' => 'Box ini sudah tersimpan di palet ' . $existingPallet->pallet_number
                ], 400);
            }
        }

        $pallet = $this->resolveActivePallet();

        // Check if box already scanned dalam session ini
        $scannedBoxes = session('scanned_boxes', []);
        $boxAlreadyScanned = collect($scannedBoxes)->contains('box_number', $barcode);
        if ($boxAlreadyScanned) {
            return response()->json([
                'success' => false,
                'message' => 'Box ' . $barcode . ' sudah di-scan dalam palet ini'
            ], 400);
        }

        // Simpan sementara sebagai pending box sampai no part di-scan
        session(['pending_box' => [
            'box_number' => $barcode,
        ]]);

        return response()->json([
            'success' => true,
            'pallet_id' => $pallet->id,
            'pallet_number' => $pallet->pallet_number,
            'has_stock_location' => (bool) $pallet->stockLocation()->exists(),
            'warehouse_location' => $pallet->stockLocation?->warehouse_location,
            'box_number' => $barcode,
            'message' => 'Scan No Part untuk konfirmasi.'
        ]);
    }

    // API untuk scan No Part (konfirmasi setelah scan box)
    public function scanPartNumber(Request $request)
    {
        $validated = $request->validate([
            'part_number' => 'required|string',
            'pcs_quantity' => 'nullable|integer|min:1',
            'not_full_reason' => 'nullable|string',
            'delivery_order_id' => 'nullable|integer|exists:delivery_orders,id',
        ]);

        $pendingBox = session('pending_box');
        if (!$pendingBox) {
            return response()->json([
                'success' => false,
                'message' => 'Scan box terlebih dahulu.'
            ], 400);
        }

        $partNumber = $validated['part_number'];

        $partSetting = PartSetting::where('part_number', $partNumber)->first();
        if (!$partSetting) {
            return response()->json([
                'success' => false,
                'message' => 'No Part belum terdaftar di Master Part.'
            ], 400);
        }

        $fixedQty = (int) $partSetting->qty_box;
        $pcsQuantity = $validated['pcs_quantity'] ?? $fixedQty;
        $isNotFull = $pcsQuantity !== $fixedQty;

        if ($pcsQuantity > $fixedQty) {
            return response()->json([
                'success' => false,
                'message' => 'Qty box tidak boleh melebihi fixed qty.'
            ], 422);
        }

        if ($isNotFull) {
            if (empty($validated['not_full_reason'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Alasan box not full wajib diisi.'
                ], 422);
            }

            if (empty($validated['delivery_order_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Box not full wajib disisipkan ke salah satu delivery.'
                ], 422);
            }

            $deliveryOrder = DeliveryOrder::whereIn('status', ['approved', 'processing'])
                ->find($validated['delivery_order_id']);
            if (!$deliveryOrder) {
                return response()->json([
                    'success' => false,
                    'message' => 'Delivery yang dipilih tidak valid.'
                ], 422);
            }
        }

        $pallet_id = session('current_pallet_id');
        $pallet = $pallet_id ? Pallet::find($pallet_id) : null;
        if (!$pallet) {
            return response()->json([
                'success' => false,
                'message' => 'Palet tidak ditemukan. Ulangi scan box.'
            ], 400);
        }

        // Simpan ke session untuk preview
        $scannedBoxes = session('scanned_boxes', []);
        $boxAlreadyScanned = collect($scannedBoxes)->contains('box_number', $pendingBox['box_number']);
        if ($boxAlreadyScanned) {
            return response()->json([
                'success' => false,
                'message' => 'Box ' . $pendingBox['box_number'] . ' sudah di-scan dalam palet ini'
            ], 400);
        }

        $scannedBoxes[] = [
            'box_number' => $pendingBox['box_number'],
            'part_number' => $partNumber,
            'pcs_quantity' => $pcsQuantity,
            'qty_box' => $fixedQty,
            'is_not_full' => $isNotFull,
            'not_full_reason' => $isNotFull ? $validated['not_full_reason'] : null,
            'delivery_order_id' => $isNotFull ? (int) $validated['delivery_order_id'] : null,
        ];
        session(['scanned_boxes' => $scannedBoxes]);

        $palletItem = PalletItem::query()
            ->where('pallet_id', $pallet->id)
            ->where('part_number', $partNumber)
            ->first();

        if (!$palletItem) {
            PalletItem::create([
                'pallet_id' => $pallet->id,
                'part_number' => $partNumber,
                'box_quantity' => 1,
                'pcs_quantity' => $pcsQuantity,
            ]);
        } else {
            $palletItem->increment('box_quantity');
            $palletItem->increment('pcs_quantity', $pcsQuantity);
        }

        session()->forget('pending_box');

        return response()->json([
            'success' => true,
            'pallet_id' => $pallet->id,
            'pallet_number' => $pallet->pallet_number,
            'has_stock_location' => (bool) $pallet->stockLocation()->exists(),
            'warehouse_location' => $pallet->stockLocation?->warehouse_location,
            'box_number' => $pendingBox['box_number'],
            'part_number' => $partNumber,
            'qty_box' => $fixedQty,
            'pcs_quantity' => $pcsQuantity,
            'is_not_full' => $isNotFull,
            'boxes_in_pallet' => count($scannedBoxes),
        ]);
    }

    // API untuk scan QR box dan create/update palet
    public function scanBox(Request $request)
    {
        $validated = $request->validate([
            'qr_data' => 'required|string',
        ]);

        $qrData = $validated['qr_data'];

        // Parse QR data: box_number|part_number|pcs_quantity
        $parts = explode('|', $qrData);

        if (count($parts) !== 3) {
            return response()->json([
                'success' => false,
                'message' => 'Format QR code tidak valid'
            ], 400);
        }

        $box_number = $parts[0];
        $part_number = $parts[1];
        $pcs_quantity = (int) $parts[2];

        if (!preg_match('/^\d+$/', $box_number)) {
            return response()->json([
                'success' => false,
                'message' => 'ID Box hanya boleh berisi angka.'
            ], 422);
        }

        // Verify box exists in database
        $box = Box::where('box_number', $box_number)->first();

        if (!$box) {
            return response()->json([
                'success' => false,
                'message' => 'Box tidak ditemukan di sistem'
            ], 404);
        }

        // Check if box already attached to a pallet with stock location (SUDAH TERSIMPAN)
        $existingPallet = $box->pallets()
            ->whereHas('stockLocation')
            ->first();

        if ($existingPallet) {
            return response()->json([
                'success' => false,
                'message' => 'Box ini sudah tersimpan di palet ' . $existingPallet->pallet_number
            ], 400);
        }

        $pallet = $this->resolveActivePallet();

        // Check if box already in this pallet
        $existingBox = $pallet->boxes()->where('box_id', $box->id)->first();
        if ($existingBox) {
            return response()->json([
                'success' => false,
                'message' => 'Box ini sudah di-scan dalam palet ini'
            ], 400);
        }

        // Check if box already scanned dalam session ini (untuk prevent duplikat dalam satu pallet)
        $scannedBoxes = session('scanned_boxes', []);
        $boxAlreadyScanned = collect($scannedBoxes)->contains('box_id', $box->id);
        
        if ($boxAlreadyScanned) {
            return response()->json([
                'success' => false,
                'message' => 'Box ' . $box_number . ' sudah di-scan. Setiap box hanya bisa di-scan sekali karena kode QR-nya unik!'
            ], 400);
        }

        // JANGAN attach box ke palet di sini! Hanya simpan ke session untuk preview
        // Akan di-attach saat user klik Save
        $scannedBoxes[] = [
            'box_id' => $box->id,
            'box_number' => $box_number,
            'part_number' => $part_number,
            'pcs_quantity' => $pcs_quantity,
        ];
        session(['scanned_boxes' => $scannedBoxes]);

        // Create pallet item for preview (tapi belum commit ke database)
        $palletItem = PalletItem::query()
            ->where('pallet_id', $pallet->id)
            ->where('part_number', $part_number)
            ->first();

        if (!$palletItem) {
            $palletItem = PalletItem::create([
                'pallet_id' => $pallet->id,
                'part_number' => $part_number,
                'box_quantity' => 1,
                'pcs_quantity' => $pcs_quantity,
            ]);
        } else {
            $palletItem->increment('box_quantity');
            $palletItem->increment('pcs_quantity', $pcs_quantity);
        }

        // Get updated pallet info
        $pallet->load('boxes');

        return response()->json([
            'success' => true,
            'pallet_id' => $pallet->id,
            'pallet_number' => $pallet->pallet_number,
            'has_stock_location' => (bool) $pallet->stockLocation()->exists(),
            'warehouse_location' => $pallet->stockLocation?->warehouse_location,
            'box_number' => $box_number,
            'part_number' => $part_number,
            'pcs_quantity' => $pcs_quantity,
            'boxes_in_pallet' => $pallet->boxes->count(),
        ]);
    }

    public function getCurrentPalletData(Request $request)
    {
        $pallet_id = session('current_pallet_id');

        if (!$pallet_id) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada palet yang sedang aktif'
            ]);
        }

        $pallet = Pallet::with(['items', 'stockLocation'])->find($pallet_id);

        if (!$pallet) {
            session()->forget('current_pallet_id');
            return response()->json([
                'success' => false,
                'message' => 'Palet tidak ditemukan'
            ]);
        }

        $scannedBoxes = session('scanned_boxes', []);

        $existingBoxes = $pallet->activeBoxes()
            ->select('boxes.id', 'boxes.box_number', 'boxes.part_number', 'boxes.pcs_quantity', 'boxes.qty_box', 'boxes.is_not_full')
            ->get()
            ->map(function ($box) {
                return [
                    'box_id' => $box->id,
                    'box_number' => $box->box_number,
                    'part_number' => $box->part_number,
                    'pcs_quantity' => (int) $box->pcs_quantity,
                    'qty_box' => $box->qty_box !== null ? (int) $box->qty_box : null,
                    'is_not_full' => (bool) $box->is_not_full,
                    'source' => 'existing',
                ];
            })
            ->values()
            ->all();

        $pendingBoxes = collect($scannedBoxes)
            ->map(function ($box) {
                return [
                    'box_id' => $box['box_id'] ?? null,
                    'box_number' => $box['box_number'] ?? null,
                    'part_number' => $box['part_number'] ?? null,
                    'pcs_quantity' => (int) ($box['pcs_quantity'] ?? 0),
                    'qty_box' => isset($box['qty_box']) ? (int) $box['qty_box'] : null,
                    'is_not_full' => (bool) ($box['is_not_full'] ?? false),
                    'source' => 'pending',
                ];
            })
            ->values();

        $boxesForPrint = collect($existingBoxes)
            ->concat($pendingBoxes)
            ->unique(function ($box) {
                return $box['box_id'] ?? $box['box_number'] ?? spl_object_id((object) $box);
            })
            ->values()
            ->all();

        $totalPcs = collect($boxesForPrint)->sum(function ($box) {
            return (int) ($box['pcs_quantity'] ?? 0);
        });

        return response()->json([
            'success' => true,
            'pallet' => [
                'id' => $pallet->id,
                'pallet_number' => $pallet->pallet_number,
                'source' => session('current_pallet_source', 'new'),
                'warehouse_location' => $pallet->stockLocation?->warehouse_location,
                'has_stock_location' => (bool) $pallet->stockLocation()->exists(),
                'boxes' => $scannedBoxes,
                'boxes_existing' => $existingBoxes,
                'boxes_pending' => $pendingBoxes,
                'boxes_for_print' => $boxesForPrint,
                'items' => $pallet->items->map(function ($item) {
                    return [
                        'part_number' => $item->part_number,
                        'box_quantity' => $item->box_quantity,
                        'pcs_quantity' => $item->pcs_quantity,
                    ];
                }),
                'total_boxes' => count($scannedBoxes),
                'total_boxes_existing' => count($existingBoxes),
                'total_boxes_pending' => $pendingBoxes->count(),
                'total_boxes_combined' => count($boxesForPrint),
                'total_pcs' => $totalPcs,
            ]
        ]);
    }

    public function clearSession(Request $request)
    {
        // Delete empty pallet jika tidak ada boxes yang di-attach
        $pallet_id = session('current_pallet_id');
        $palletSource = session('current_pallet_source');
        if ($pallet_id) {
            $pallet = Pallet::find($pallet_id);
            if ($pallet && $pallet->boxes->isEmpty() && $palletSource === 'new') {
                $pallet->delete();
            }
        }

        session()->forget('current_pallet_id');
        session()->forget('current_pallet_source');
        session()->forget('scanned_boxes');
        session()->forget('pending_box');

        return response()->json([
            'success' => true,
            'message' => 'Session palet berhasil dihapus'
        ]);
    }

    private function validateStoreRequest(Request $request): array
    {
        return $request->validate([
            'pallet_id' => 'required|exists:pallets,id',
            // 'warehouse_location' => 'required|string', // Validasi manual di bawah karena bisa 'location_id' atau text
        ]);
    }

    private function hasLocationInput(Request $request): bool
    {
        return (bool) ($request->input('location_id') || $request->input('warehouse_location'));
    }

    private function getScannedBoxes(): array
    {
        return session('scanned_boxes', []);
    }

    private function attachScannedBoxes(Pallet $pallet, array $scannedBoxes): array
    {
        $boxNumbers = collect($scannedBoxes)
            ->pluck('box_number')
            ->filter()
            ->unique()
            ->values();

        $existingBoxesByNumber = $boxNumbers->isEmpty()
            ? collect()
            : Box::whereIn('box_number', $boxNumbers)->get()->keyBy('box_number');

        $notFullDeliveryOrderIds = collect($scannedBoxes)
            ->filter(fn ($box) => !empty($box['is_not_full']) && !empty($box['delivery_order_id']))
            ->pluck('delivery_order_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $deliveryOrdersById = $notFullDeliveryOrderIds->isEmpty()
            ? collect()
            : DeliveryOrder::with('items')->whereIn('id', $notFullDeliveryOrderIds)->get()->keyBy('id');

        $attachedBoxIds = [];

        foreach ($scannedBoxes as $scannedBox) {
            $boxNumber = (string) ($scannedBox['box_number'] ?? '');
            $box = $existingBoxesByNumber->get($boxNumber);
            if (!$box) {
                if (!empty($scannedBox['is_not_full']) && empty($scannedBox['delivery_order_id'])) {
                    throw new \Exception('Box not full wajib disisipkan ke delivery.');
                }

                $box = Box::create([
                    'box_number' => $scannedBox['box_number'],
                    'part_number' => $scannedBox['part_number'],
                    'pcs_quantity' => $scannedBox['pcs_quantity'] ?? $scannedBox['qty_box'],
                    'qr_code' => $scannedBox['box_number'] . '|' . $scannedBox['part_number'] . '|' . ($scannedBox['pcs_quantity'] ?? $scannedBox['qty_box']),
                    'user_id' => Auth::id(),
                    'qty_box' => $scannedBox['qty_box'] ?? null,
                    'is_not_full' => (bool) ($scannedBox['is_not_full'] ?? false),
                    'not_full_reason' => $scannedBox['not_full_reason'] ?? null,
                    'assigned_delivery_order_id' => $scannedBox['delivery_order_id'] ?? null,
                ]);

                $existingBoxesByNumber->put($boxNumber, $box);
                $this->updateDeliveryOrderForNotFull($scannedBox, $deliveryOrdersById);
            }

            $pallet->boxes()->syncWithoutDetaching([$box->id]);
            $attachedBoxIds[] = (int) $box->id;
        }

        return array_values(array_unique(array_filter($attachedBoxIds)));
    }

    private function createStockInputBoxRecords(StockInput $stockInput, array $boxIds): void
    {
        $rows = collect($boxIds)
            ->map(fn ($boxId) => (int) $boxId)
            ->filter(fn ($boxId) => $boxId > 0)
            ->unique()
            ->values()
            ->map(fn ($boxId) => [
                'stock_input_id' => $stockInput->id,
                'box_id' => $boxId,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all();

        if (empty($rows)) {
            throw new \RuntimeException('Tidak ada ID box valid untuk disimpan ke transaksi input stok.');
        }

        DB::table('stock_input_boxes')->insert($rows);
    }

    private function updateDeliveryOrderForNotFull(array $scannedBox, Collection $deliveryOrdersById): void
    {
        if (empty($scannedBox['is_not_full']) || empty($scannedBox['delivery_order_id'])) {
            return;
        }

        $order = $deliveryOrdersById->get((int) $scannedBox['delivery_order_id']);
        if (!$order) {
            return;
        }

        $item = $order->items->firstWhere('part_number', $scannedBox['part_number']);
        if ($item) {
            $item->quantity += (int) ($scannedBox['pcs_quantity'] ?? 0);
            $item->save();
            return;
        }

        $newItem = DeliveryOrderItem::create([
            'delivery_order_id' => $order->id,
            'part_number' => $scannedBox['part_number'],
            'quantity' => (int) ($scannedBox['pcs_quantity'] ?? 0),
            'fulfilled_quantity' => 0,
        ]);

        $order->setRelation('items', $order->items->push($newItem));
    }

    private function resolveLocationCode(Request $request, Pallet $pallet): ?string
    {
        $locationId = $request->input('location_id');
        $locationCode = null;

        if ($locationId) {
            $masterLocation = MasterLocation::find($locationId);
            if (!$masterLocation) {
                throw new \Exception('Lokasi yang dipilih tidak ditemukan!');
            }

            $claimed = MasterLocation::where('id', $masterLocation->id)
                ->where('is_occupied', false)
                ->update([
                    'is_occupied' => true,
                    'current_pallet_id' => $pallet->id,
                    'updated_at' => now(),
                ]);

            if ($claimed === 0) {
                throw new \Exception('Lokasi yang dipilih sudah terisi!');
            }

            $locationCode = $masterLocation->code;

            return $locationCode;
        }

        $locationCode = $request->input('warehouse_location');
        if ($locationCode) {
            $locationCode = strtoupper($locationCode);
        }

        if ($locationCode) {
            $masterLocation = MasterLocation::where('code', $locationCode)->first();
            if ($masterLocation) {
                $claimed = MasterLocation::where('id', $masterLocation->id)
                    ->where('is_occupied', false)
                    ->update([
                        'is_occupied' => true,
                        'current_pallet_id' => $pallet->id,
                        'updated_at' => now(),
                    ]);

                if ($claimed === 0) {
                    throw new \Exception("Lokasi {$locationCode} sudah terisi!");
                }
            }
        }

        return $locationCode;
    }

    private function createStockLocationRecord(Pallet $pallet, ?string $locationCode): void
    {
        StockLocation::create([
            'pallet_id' => $pallet->id,
            'warehouse_location' => $locationCode ?? 'Unknown',
            'stored_at' => now(),
        ]);
    }

    private function createStockInputRecord(Pallet $pallet, array $attachedBoxIds, ?string $locationCode): StockInput
    {
        $attachedBoxes = Box::query()
            ->whereIn('id', $attachedBoxIds)
            ->get(['id', 'part_number', 'pcs_quantity']);

        if ($attachedBoxes->isEmpty()) {
            throw new \RuntimeException('Tidak ada box transaksi valid untuk membuat stock input.');
        }

        $totalPcs = (int) $attachedBoxes->sum('pcs_quantity');
        $partNumbers = $attachedBoxes
            ->pluck('part_number')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $palletItem = !empty($partNumbers)
            ? $pallet->items()->whereIn('part_number', $partNumbers)->orderBy('id')->first()
            : null;

        return StockInput::create([
            'pallet_id' => $pallet->id,
            'pallet_item_id' => $palletItem?->id,
            'user_id' => Auth::id(),
            'warehouse_location' => $locationCode ?? 'Unknown',
            'pcs_quantity' => $totalPcs,
            'box_quantity' => $attachedBoxes->count(),
            'stored_at' => now(),
            'part_numbers' => $partNumbers,
        ]);
    }

    private function clearStockInputSession(): void
    {
        session()->forget('scanned_boxes');
        session()->forget('current_pallet_id');
        session()->forget('current_pallet_source');
    }

    public function store(Request $request)
    {
        $validated = $this->validateStoreRequest($request);

        DB::beginTransaction(); // Start transaction to ensure data integrity

        try {

            $pallet = Pallet::with(['items', 'stockLocation'])->findOrFail($validated['pallet_id']);

            // Verify pallet has items (scanned QR) (INI KHUSUS UNTUK FLOW SCAN QR BARU - JIKA KITA PAKAI LOGIC DI BAWAH, LOGIC INI MUNGKIN PERLU DISESUAIKAN)
            // KARENA SAAT INI ITEM BELUM DI ATTACH KE PALLET (MASIH DI SESSION)
            // TAPI DI KODE SEBELUMNYA `items` RELASI KE `PalletItems` SUDAH DIBUAT SAAT SCAN? TIDAK, SAAT SCAN HANYA SESSION.
            
            // LOGIC KOREKSI: Relasi items (PalletItems) baru dibuat di bawah, jadi pengecekan valid disini harusnya check Session.
            
            // Get scanned boxes dari session
            $scannedBoxes = $this->getScannedBoxes();
            
            if (empty($scannedBoxes)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada box yang ter-scan'
                ], 400);
            }

            $hasLocationInput = $this->hasLocationInput($request);
            $hasExistingLocation = (bool) $pallet->stockLocation;

            if (!$hasLocationInput && !$hasExistingLocation) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Lokasi penyimpanan harus diisi untuk pallet baru.'
                ], 422);
            }

            // Attach boxes ke palet sekarang (saat user klik Save)
            $attachedBoxIds = $this->attachScannedBoxes($pallet, $scannedBoxes);

            // Simpan lokasi bila pallet belum punya lokasi, atau gunakan lokasi existing
            $locationCode = $pallet->stockLocation?->warehouse_location;
            if ($hasLocationInput) {
                $locationCode = $this->resolveLocationCode($request, $pallet);
                if (!$hasExistingLocation) {
                    $this->createStockLocationRecord($pallet, $locationCode);
                }
            }

            // Create StockInput record for audit
            $stockInput = $this->createStockInputRecord($pallet, $attachedBoxIds, $locationCode);

            // Persist exact box mapping per stock input transaction for 100% audit accuracy.
            $this->createStockInputBoxRecords($stockInput, $attachedBoxIds);

            // Log audit trail
            AuditService::logStockInput($stockInput, 'created');

            // Clear session data
            $this->clearStockInputSession();

            DB::commit(); // Commit transaction

            return response()->json(['message' => 'Stok berhasil disimpan!'], 200);

        } catch (\Exception $e) {
            DB::rollBack(); // Rollback transaction on error
            // Log the error for debugging
             \Illuminate\Support\Facades\Log::error('Stock Input Error: ' . $e->getMessage());
            return response()->json(['message' => 'Terjadi kesalahan saat menyimpan data: ' . $e->getMessage()], 500);
        }
    }
}
