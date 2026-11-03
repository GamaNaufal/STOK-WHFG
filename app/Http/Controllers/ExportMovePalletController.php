<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ExportStockPallet;
use App\Services\ExportPalletMoveService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ExportMovePalletController extends Controller
{
    public function index(ExportPalletMoveService $service)
    {
        $pallets = ExportStockPallet::whereNull('retired_at')->whereHas('boxes')
            ->with('plannedPallet')->withCount('boxes')->withSum('boxes', 'pcs_quantity')
            ->orderBy('id')->limit(100)->get();
        $availableLocations = $service->locations()->orderBy('code')->limit(200)->get(['id', 'code']);
        $moveHistory = AuditLog::where('type', 'export_pallet_location_moved')->with('user')->latest()->limit(20)->get();

        return view('operator.move-pallet.index', compact('pallets', 'availableLocations', 'moveHistory') + ['exportMove' => true]);
    }

    public function search(Request $request, ExportPalletMoveService $service)
    {
        $data = $request->validate(['code' => 'required|string|max:32']);
        $code = strtoupper(trim($data['code']));
        abort_unless(preg_match('/^EX-(\d{6,18})$/D', $code, $match), 404, 'Pallet ekspor tidak ditemukan. Gunakan kode fisik seperti EX-000001.');
        $pallet = ExportStockPallet::findOrFail((int) $match[1]);

        return response()->json(['success' => true, 'pallet' => $service->inspect($pallet) + [
            'request_token' => (string) Str::uuid(),
        ]])->header('Cache-Control', 'no-store');
    }

    public function locations(Request $request, ExportPalletMoveService $service)
    {
        $data = $request->validate(['q' => 'nullable|string|max:64']);
        $q = trim($data['q'] ?? '');

        return response()->json($service->locations()->when($q !== '', fn ($query) => $query->where('code', 'like', '%'.$q.'%'))
            ->orderBy('code')->limit(20)->get(['id', 'code']))->header('Cache-Control', 'no-store');
    }

    public function store(Request $request, ExportPalletMoveService $service)
    {
        $data = $request->validate([
            'pallet_id' => 'required|integer',
            'location_id' => 'required|integer',
            'request_token' => 'required|uuid',
            'fingerprint' => 'required|string|size:64',
            'ready_confirmed' => 'sometimes|boolean',
        ]);
        $result = $service->move($data, $request->user());
        $message = "Pallet berhasil dipindahkan dari {$result['from']} ke {$result['to']}.";
        if (! empty($data['ready_confirmed'])) {
            $message .= ' Konfirmasikan kembali status Siap di Pemenuhan Permintaan.';
        }
        $request->session()->flash('export_move_success', $message);

        return response()->json(['success' => true, 'message' => $message]);
    }
}
