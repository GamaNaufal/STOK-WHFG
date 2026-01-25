<?php

namespace App\Http\Controllers;

use App\Models\Pallet;
use App\Models\PalletItem;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PalletInputController extends Controller
{
    // List part numbers yang tersedia
    private function getAvailableParts()
    {
        return [
            'PN-A001', 'PN-A002', 'PN-A003',
            'PN-B001', 'PN-B002', 'PN-B003',
            'PN-C001', 'PN-C002',
            'PN-D001', 'PN-D002',
            'PN-E001',
            'PN-F001',
            'PN-G001',
        ];
    }

    public function index()
    {
        // Warehouse operator (Staff Warehouse) can create and view list
        // if (auth()->user()->role === 'warehouse_operator') {
        //     return redirect()->route('pallet-input.create');
        // }

        $pallets = Pallet::with('items')->latest()->paginate(10);
        return view('packing-department.pallet-input.index', compact('pallets'));
    }

    public function create()
    {
        $availableParts = $this->getAvailableParts();
        return view('packing-department.pallet-input.create', compact('availableParts'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.part_number' => 'required|string',
            'items.*.box_quantity' => 'required|integer|min:1',
            'items.*.pcs_quantity' => 'required|integer|min:1',
        ]);

        // Auto-generate pallet number based on date
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

        // Create pallet
        $pallet = Pallet::create([
            'pallet_number' => $palletNumber,
        ]);

        // Create pallet items
        foreach ($request->items as $item) {
            PalletItem::create([
                'pallet_id' => $pallet->id,
                'part_number' => $item['part_number'],
                'box_quantity' => $item['box_quantity'],
                'pcs_quantity' => $item['pcs_quantity'],
            ]);
        }

        return redirect()->route('pallet-input.create')
            ->with('success', 'Pallet ' . $palletNumber . ' berhasil ditambahkan dengan ' . count($request->items) . ' item!');
    }

    public function edit(Pallet $pallet)
    {
        $pallet->load('items');
        $availableParts = $this->getAvailableParts();
        return view('packing-department.pallet-input.edit', compact('pallet', 'availableParts'));
    }

    public function update(Request $request, Pallet $pallet)
    {
        $request->validate([
            'pallet_number' => 'required|string|unique:pallets,pallet_number,' . $pallet->id,
            'items' => 'required|array|min:1',
            'items.*.part_number' => 'required|string',
            'items.*.box_quantity' => 'required|integer|min:1',
            'items.*.pcs_quantity' => 'required|integer|min:1',
        ]);

        $pallet->update([
            'pallet_number' => $request->pallet_number,
        ]);

        // Delete old items and create new ones
        $pallet->items()->delete();

        foreach ($request->items as $item) {
            PalletItem::create([
                'pallet_id' => $pallet->id,
                'part_number' => $item['part_number'],
                'box_quantity' => $item['box_quantity'],
                'pcs_quantity' => $item['pcs_quantity'],
            ]);
        }

        return redirect()->route('pallet-input.index')
            ->with('success', 'Pallet berhasil diperbarui!');
    }

    public function destroy(Pallet $pallet)
    {
        $pallet->delete();

        return redirect()->route('pallet-input.index')
            ->with('success', 'Pallet berhasil dihapus!');
    }

    // API endpoint untuk get part numbers with search
    public function getPartNumbers(Request $request)
    {
        $search = $request->query('search', '');
        $availableParts = $this->getAvailableParts();

        if ($search) {
            $availableParts = array_filter($availableParts, function ($part) use ($search) {
                return stripos($part, $search) !== false;
            });
        }

        return response()->json(array_values($availableParts));
    }
}
