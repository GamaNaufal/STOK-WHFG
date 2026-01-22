<?php

namespace App\Http\Controllers;

use App\Models\Box;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class BoxController extends Controller
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

    // Generate nomor box otomatis: BOX-YYYYMMDD-NNN
    private function generateBoxNumber()
    {
        $today = now()->format('Ymd');
        $todayBoxes = Box::where('box_number', 'LIKE', 'BOX-' . $today . '-%')->count();
        $nextNumber = str_pad($todayBoxes + 1, 3, '0', STR_PAD_LEFT);
        return 'BOX-' . $today . '-' . $nextNumber;
    }

    public function index()
    {
        $boxes = Box::with('user')->latest()->paginate(15);
        return view('admin.boxes.index', compact('boxes'));
    }

    public function create()
    {
        $availableParts = $this->getAvailableParts();
        $nextBoxNumber = $this->generateBoxNumber();
        return view('admin.boxes.create', compact('availableParts', 'nextBoxNumber'));
    }

    public function store(Request $request)
    {
        // Auto-generate box number
        $boxNumber = $this->generateBoxNumber();

        $validated = $request->validate([
            'part_number' => 'required|string',
            'pcs_quantity' => 'required|integer|min:1',
        ]);

        // Create QR code data: box_number|part_number|pcs_quantity
        $qrData = $boxNumber . '|' . $validated['part_number'] . '|' . $validated['pcs_quantity'];
        
        // Generate QR code as SVG (no imagick required)
        $qrCodeSvg = QrCode::format('svg')
            ->size(300)
            ->generate($qrData);

        // Store as data URI
        $qrBase64 = 'data:image/svg+xml;base64,' . base64_encode($qrCodeSvg);

        // Create box record
        Box::create([
            'box_number' => $boxNumber,
            'part_number' => $validated['part_number'],
            'pcs_quantity' => $validated['pcs_quantity'],
            'qr_code' => $qrBase64,
            'user_id' => auth()->id(),
        ]);

        return redirect()->route('boxes.create')
            ->with('success', 'Box dengan No ' . $boxNumber . ' berhasil dibuat! Kode QR sudah siap untuk dicetak.');
    }

    public function show(Box $box)
    {
        return view('admin.boxes.show', compact('box'));
    }

    public function destroy(Box $box)
    {
        $boxNumber = $box->box_number;
        $box->delete();

        return redirect()->route('boxes.index')
            ->with('success', 'Box ' . $boxNumber . ' berhasil dihapus!');
    }

    // API untuk warehouse scan - get box data
    public function getScanData(Request $request)
    {
        $qrData = $request->input('qr_data');

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

        return response()->json([
            'success' => true,
            'box_number' => $box_number,
            'part_number' => $part_number,
            'pcs_quantity' => $pcs_quantity,
        ]);
    }
}
