<?php

namespace App\Http\Controllers;

use App\Models\Box;
use App\Models\Pallet;
use App\Models\PalletItem;
use App\Models\StockLocation;
use App\Models\StockInput;
use Illuminate\Http\Request;
use Carbon\Carbon;

class StockInputController extends Controller
{
    public function index()
    {
        return view('warehouse-operator.stock-input.index');
    }

    // API untuk scan Barcode box (dari hardware scanner)
    public function scanBarcode(Request $request)
    {
        $validated = $request->validate([
            'barcode' => 'required|string',
        ]);

        $barcode = $validated['barcode'];

        // Cari box berdasarkan barcode/box_number
        $box = Box::where('box_number', $barcode)->first();

        if (!$box) {
            return response()->json([
                'success' => false,
                'message' => 'Box tidak ditemukan: ' . $barcode
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

        // Get pallet from session or create new one
        $pallet_id = session('current_pallet_id');
        $pallet = null;

        if ($pallet_id) {
            $pallet = Pallet::find($pallet_id);
        }

        // If no pallet yet, auto-generate new pallet number
        if (!$pallet) {
            $today = Carbon::now()->format('Ymd');
            $lastPallet = Pallet::where('pallet_number', 'like', 'PLT-' . $today . '%')
                ->orderBy('pallet_number', 'desc')
                ->first();

            $nextNumber = 1;
            if ($lastPallet) {
                $lastNumber = (int) substr($lastPallet->pallet_number, -3);
                $nextNumber = $lastNumber + 1;
            }

            $palletNumber = 'PLT-' . $today . '-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

            // Create new pallet
            $pallet = Pallet::create([
                'pallet_number' => $palletNumber,
            ]);

            // Store pallet ID in session
            session(['current_pallet_id' => $pallet->id]);
        }

        // Check if box already in this pallet
        $existingBox = $pallet->boxes()->where('box_id', $box->id)->first();
        if ($existingBox) {
            return response()->json([
                'success' => false,
                'message' => 'Box ini sudah di-scan dalam palet ini'
            ], 400);
        }

        // Check if box already scanned dalam session ini
        $scannedBoxes = session('scanned_boxes', []);
        $boxAlreadyScanned = collect($scannedBoxes)->contains('box_id', $box->id);
        
        if ($boxAlreadyScanned) {
            return response()->json([
                'success' => false,
                'message' => 'Box ' . $barcode . ' sudah di-scan dalam palet ini'
            ], 400);
        }

        // Simpan ke session untuk preview
        $scannedBoxes[] = [
            'box_id' => $box->id,
            'box_number' => $box->box_number,
            'part_number' => $box->part_number,
            'part_name' => $box->part_name,
            'pcs_quantity' => $box->pcs_quantity,
            'qty_box' => $box->qty_box,
            'type_box' => $box->type_box,
        ];
        session(['scanned_boxes' => $scannedBoxes]);

        // Create pallet item for preview
        $palletItem = $pallet->items()
            ->where('part_number', $box->part_number)
            ->first();

        if (!$palletItem) {
            $palletItem = PalletItem::create([
                'pallet_id' => $pallet->id,
                'part_number' => $box->part_number,
                'box_quantity' => 1,
                'pcs_quantity' => $box->pcs_quantity,
            ]);
        } else {
            $palletItem->increment('box_quantity');
            $palletItem->increment('pcs_quantity', $box->pcs_quantity);
        }

        // Get updated pallet info
        $pallet->load('boxes');

        return response()->json([
            'success' => true,
            'pallet_id' => $pallet->id,
            'pallet_number' => $pallet->pallet_number,
            'box_number' => $box->box_number,
            'part_number' => $box->part_number,
            'part_name' => $box->part_name,
            'pcs_quantity' => $box->pcs_quantity,
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

        // Get pallet from session or create new one
        $pallet_id = session('current_pallet_id');
        $pallet = null;

        if ($pallet_id) {
            $pallet = Pallet::find($pallet_id);
        }

        // If no pallet yet, auto-generate new pallet number
        if (!$pallet) {
            $today = Carbon::now()->format('Ymd');
            $lastPallet = Pallet::where('pallet_number', 'like', 'PLT-' . $today . '%')
                ->orderBy('pallet_number', 'desc')
                ->first();

            $nextNumber = 1;
            if ($lastPallet) {
                $lastNumber = (int) substr($lastPallet->pallet_number, -3);
                $nextNumber = $lastNumber + 1;
            }

            $palletNumber = 'PLT-' . $today . '-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

            // Create new pallet
            $pallet = Pallet::create([
                'pallet_number' => $palletNumber,
            ]);

            // Store pallet ID in session
            session(['current_pallet_id' => $pallet->id]);
        }

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
        $palletItem = $pallet->items()
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

        $pallet = Pallet::with(['items'])->find($pallet_id);

        if (!$pallet) {
            session()->forget('current_pallet_id');
            return response()->json([
                'success' => false,
                'message' => 'Palet tidak ditemukan'
            ]);
        }

        // Ambil boxes dari session, bukan dari database
        $scannedBoxes = session('scanned_boxes', []);
        $totalPcs = array_sum(array_column($scannedBoxes, 'pcs_quantity'));

        return response()->json([
            'success' => true,
            'pallet' => [
                'id' => $pallet->id,
                'pallet_number' => $pallet->pallet_number,
                'boxes' => $scannedBoxes,
                'items' => $pallet->items->map(function ($item) {
                    return [
                        'part_number' => $item->part_number,
                        'box_quantity' => $item->box_quantity,
                        'pcs_quantity' => $item->pcs_quantity,
                    ];
                }),
                'total_boxes' => count($scannedBoxes),
                'total_pcs' => $totalPcs,
            ]
        ]);
    }

    public function clearSession(Request $request)
    {
        // Delete empty pallet jika tidak ada boxes yang di-attach
        $pallet_id = session('current_pallet_id');
        if ($pallet_id) {
            $pallet = Pallet::find($pallet_id);
            if ($pallet && $pallet->boxes->isEmpty()) {
                $pallet->delete();
            }
        }

        session()->forget('current_pallet_id');
        session()->forget('scanned_boxes');

        return response()->json([
            'success' => true,
            'message' => 'Session palet berhasil dihapus'
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'pallet_id' => 'required|exists:pallets,id',
            'warehouse_location' => 'required|string',
        ]);

        $pallet = Pallet::with(['items'])->findOrFail($validated['pallet_id']);

        // Verify pallet has items (scanned QR)
        if ($pallet->items->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Palet harus memiliki minimal 1 box yang di-scan'
            ], 400);
        }

        // Get scanned boxes dari session
        $scannedBoxes = session('scanned_boxes', []);
        
        if (empty($scannedBoxes)) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada box yang ter-scan'
            ], 400);
        }

        // Attach boxes ke palet sekarang (saat user klik Save)
        foreach ($scannedBoxes as $scannedBox) {
            // Double check box ada di database
            $box = Box::find($scannedBox['box_id']);
            if ($box) {
                // Attach box ke palet (jika belum)
                $pallet->boxes()->syncWithoutDetaching([$box->id]);
            }
        }

        // Create stock location
        StockLocation::create([
            'pallet_id' => $validated['pallet_id'],
            'warehouse_location' => $validated['warehouse_location'],
            'stored_at' => now(),
        ]);

        // Create stock input record for each pallet item
        foreach ($pallet->items as $item) {
            StockInput::create([
                'pallet_id' => $pallet->id,
                'pallet_item_id' => $item->id,
                'user_id' => auth()->id(),
                'warehouse_location' => $validated['warehouse_location'],
                'pcs_quantity' => $item->pcs_quantity,
                'box_quantity' => $item->box_quantity,
                'stored_at' => now(),
            ]);
        }

        // Clear session
        session()->forget('current_pallet_id');
        session()->forget('scanned_boxes');

        return redirect()->route('stock-input.index')
            ->with('success', 'Stok berhasil disimpan ke gudang!');
    }
}
