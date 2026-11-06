<?php

namespace App\Http\Controllers;

use App\Models\ExportStockBox;
use App\Models\ExportStockPallet;
use App\Models\PartSetting;
use App\Models\User;
use App\Services\ExportStockAdminService;
use App\Services\ExportStockHistoryService;
use Illuminate\Http\Request;

class ExportStockAdminController extends Controller
{
    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor?->canAccess('export_stock_manage'), 403);

        return $actor;
    }

    private function confirmation(Request $request, array $rules): array
    {
        return $request->validate($rules + [
            'confirmed' => 'accepted',
            'reason' => 'required|string|min:3|max:500',
        ], [
            'confirmed.accepted' => 'Konfirmasikan aksi sebelum melanjutkan.',
            'reason.required' => 'Isi alasan untuk dicatat dalam riwayat.',
        ]);
    }

    public function box(Request $request, ExportStockBox $stockBox, ExportStockAdminService $service)
    {
        $this->actor($request);
        $stockBox->load('currentPallet');
        $blocked = $service->boxBlockReason($stockBox);
        $parts = ! $blocked && ! $stockBox->reactivated_until
            ? PartSetting::orderBy('part_number')->get(['part_number', 'qty_box'])
            : collect();

        return response()->view('export.stock.manage-box', ['box' => $stockBox, 'blocked' => $blocked, 'parts' => $parts])
            ->header('Cache-Control', 'private, no-store');
    }

    public function updateBox(Request $request, ExportStockBox $stockBox, ExportStockAdminService $service)
    {
        $actor = $this->actor($request);
        $data = $this->confirmation($request, [
            'revision' => 'required|integer|min:1',
            'part_number' => 'required|string|max:100',
            'pcs_quantity' => 'required|integer|min:1|max:4294967295',
            'first_received_at' => 'required|date_format:Y-m-d\TH:i',
            'box_number' => 'prohibited',
        ]);
        $service->updateBox($stockBox->id, $data, $actor);

        return redirect()->route('export.stock.box-manage', $stockBox)->with('success', 'Data box ekspor diperbarui.');
    }

    public function deleteBox(Request $request, ExportStockBox $stockBox, ExportStockAdminService $service)
    {
        $actor = $this->actor($request);
        $service->deleteBox($stockBox->id, $this->confirmation($request, ['revision' => 'required|integer|min:1']), $actor);

        return redirect()->route('export.stock.box-history', ['box_number' => $stockBox->box_number])
            ->with('success', 'Box dihapus dari stok aktif. Riwayat tetap tersedia.');
    }

    public function pallet(Request $request, ExportStockPallet $pallet, ExportStockAdminService $service)
    {
        $this->actor($request);
        abort_if($pallet->retired_at || $pallet->export_planned_pallet_id, 404);

        return response()->view('export.stock.manage-pallet', ['pallet' => $pallet] + $service->inspectPallet($pallet))
            ->header('Cache-Control', 'private, no-store');
    }

    public function updatePalletLimit(Request $request, ExportStockPallet $pallet, ExportStockAdminService $service)
    {
        $actor = $this->actor($request);
        $data = $this->confirmation($request, ['fingerprint' => 'required|string|size:64',
            'box_limit' => 'required|integer|min:0|max:4294967295']);
        $service->updatePalletLimit($pallet->id, $data, $actor);

        return redirect()->route('export.stock.pallet-manage', $pallet)->with('success', 'Batas maksimal box diperbarui.');
    }

    public function deletePallet(Request $request, ExportStockPallet $pallet, ExportStockAdminService $service)
    {
        $actor = $this->actor($request);
        $count = $service->deletePallet($pallet->id, $this->confirmation($request, ['fingerprint' => 'required|string|size:64']), $actor);

        return redirect()->route('export.stock.index', ['mode' => 'pallet'])
            ->with('success', 'Pallet dan '.$count.' box dihapus dari stok aktif.');
    }

    public function history(Request $request, ExportStockHistoryService $service)
    {
        $this->actor($request);
        $data = $request->validate(['box_number' => ['nullable', 'string', 'regex:/^[0-9]{8}$/']]);
        $number = $data['box_number'] ?? '';
        $box = $number !== '' ? ExportStockBox::withTrashed()->with(['currentPallet', 'receiver'])->where('box_number', $number)->first() : null;
        $events = $box ? $service->events($box) : collect();

        return response()->view('export.stock.box-history', compact('number', 'box', 'events'))
            ->header('Cache-Control', 'private, no-store');
    }
}
