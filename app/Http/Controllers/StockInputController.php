<?php

namespace App\Http\Controllers;

use App\Models\Pallet;
use App\Models\StockLocation;
use Illuminate\Http\Request;

class StockInputController extends Controller
{
    public function index()
    {
        return view('warehouse-operator.stock-input.index');
    }

    public function getPallets()
    {
        // Ambil pallet yang belum memiliki stock location (menunggu input lokasi)
        $pallets = Pallet::with('items')
            ->doesntHave('stockLocation')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($pallet) {
                return [
                    'id' => $pallet->id,
                    'pallet_number' => $pallet->pallet_number,
                    'items_count' => $pallet->items->count(),
                ];
            });

        return response()->json([
            'pallets' => $pallets
        ]);
    }

    public function searchPallet(Request $request)
    {
        $pallet_number = $request->input('pallet_number');
        $pallet = Pallet::with('items')->where('pallet_number', $pallet_number)->first();

        if ($pallet) {
            return response()->json([
                'success' => true,
                'pallet' => $pallet,
                'items' => $pallet->items
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Pallet tidak ditemukan'
        ], 404);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'pallet_id' => 'required|exists:pallets,id',
            'warehouse_location' => 'required|string',
        ]);

        StockLocation::create([
            'pallet_id' => $validated['pallet_id'],
            'warehouse_location' => $validated['warehouse_location'],
            'stored_at' => now(),
        ]);

        return redirect()->route('stock-input.index')
            ->with('success', 'Stok berhasil disimpan ke gudang!');
    }
}
