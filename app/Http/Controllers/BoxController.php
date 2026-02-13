<?php

namespace App\Http\Controllers;

use App\Models\Box;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BoxController extends Controller
{
    public function index()
    {
        $boxes = Box::with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return view('admin.boxes.index', compact('boxes'));
    }

    public function create()
    {
        return view('admin.boxes.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'box_number' => 'required|string|max:255|unique:boxes,box_number',
            'part_number' => 'required|string|max:255',
            'part_name' => 'nullable|string|max:255',
            'pcs_quantity' => 'required|integer|min:1',
            'qty_box' => 'nullable|integer|min:1',
            'type_box' => 'nullable|string|max:255',
            'wk_transfer' => 'nullable|string|max:255',
            'lot01' => 'nullable|string|max:255',
            'lot02' => 'nullable|string|max:255',
            'lot03' => 'nullable|string|max:255',
        ]);

        $qrCode = $validated['box_number'] . '|' . $validated['part_number'] . '|' . $validated['pcs_quantity'];

        Box::create([
            'box_number' => $validated['box_number'],
            'part_number' => $validated['part_number'],
            'part_name' => $validated['part_name'] ?? null,
            'pcs_quantity' => $validated['pcs_quantity'],
            'qty_box' => $validated['qty_box'] ?? null,
            'type_box' => $validated['type_box'] ?? null,
            'wk_transfer' => $validated['wk_transfer'] ?? null,
            'lot01' => $validated['lot01'] ?? null,
            'lot02' => $validated['lot02'] ?? null,
            'lot03' => $validated['lot03'] ?? null,
            'qr_code' => $qrCode,
            'user_id' => Auth::id(),
        ]);

        return redirect()->route('boxes.index')->with('success', 'Box berhasil dibuat.');
    }

    public function show(Box $box)
    {
        $box->load('user');
        return view('admin.boxes.show', compact('box'));
    }

    public function destroy(Box $box)
    {
        $box->delete();

        return redirect()->route('boxes.index')->with('success', 'Box berhasil dihapus.');
    }
}
