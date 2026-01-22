<?php

namespace App\Http\Controllers;

use App\Models\Box;
use Illuminate\Http\Request;

class BarcodeController extends Controller
{
    /**
     * Tampilkan halaman scanner
     */
    public function scanner()
    {
        return view('admin.boxes.scanner');
    }

    /**
     * API untuk scan barcode dan cari box
     */
    public function scan(Request $request)
    {
        $barcode = $request->input('barcode');

        // Cari box berdasarkan barcode/box_number
        $box = Box::where('box_number', $barcode)
                  ->with('user')
                  ->first();

        if (!$box) {
            return response()->json([
                'success' => false,
                'message' => 'Box tidak ditemukan dengan nomor: ' . $barcode
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $box->id,
                'box_number' => $box->box_number,
                'part_number' => $box->part_number,
                'part_name' => $box->part_name,
                'pcs_quantity' => $box->pcs_quantity,
                'qty_box' => $box->qty_box,
                'type_box' => $box->type_box,
                'wk_transfer' => $box->wk_transfer,
                'lot01' => $box->lot01,
                'lot02' => $box->lot02,
                'lot03' => $box->lot03,
                'created_by' => $box->user->name ?? 'System',
                'created_at' => $box->created_at->format('d/m/Y H:i'),
            ]
        ]);
    }
}
