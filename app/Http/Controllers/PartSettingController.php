<?php

namespace App\Http\Controllers;

use App\Models\PartSetting;
use App\Services\AuditService;
use App\Services\ExportInventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PartSettingController extends Controller
{
    private function isPartReferenced(PartSetting $part): bool
    {
        foreach (\App\Support\InventoryUnificationCheck::PART_REFERENCES as $table) {
            if (DB::table($table)->where('part_setting_id', $part->id)->exists()) {
                return true;
            }
        }
        foreach (['boxes', 'pallet_items', 'delivery_order_items', 'not_full_box_requests', 'stock_withdrawals'] as $table) {
            if (DB::table($table)->where('part_number', $part->part_number)->exists()) {
                return true;
            }
        }

        return false;
    }

    public function index()
    {
        return view('operator.part-settings.index', ['parts' => PartSetting::orderBy('part_number')->paginate(10)]);
    }

    public function create()
    {
        return view('operator.part-settings.create');
    }

    public function edit(string $partSetting)
    {
        return view('operator.part-settings.edit', ['partSetting' => PartSetting::findOrFail($partSetting)]);
    }

    public function search(Request $request)
    {
        $search = trim((string) $request->get('q', ''));

        return response()->json(['data' => PartSetting::when($search !== '', fn ($q) => $q->where('part_number', 'like', '%'.$search.'%'))->orderBy('part_number')->limit(50)->get(['id', 'part_number', 'qty_box'])]);
    }

    private function validatePart(Request $request, ?int $id = null): array
    {
        return $request->validate(['part_number' => ['required', 'string', 'max:100', Rule::unique('part_settings', 'part_number')->ignore($id)], 'qty_box' => 'required|integer|min:1|max:4294967295']);
    }

    private function snapshot(PartSetting $part): array
    {
        return ['scope' => 'shared', 'part_number' => $part->part_number, 'qty_box' => (int) $part->qty_box];
    }

    public function store(Request $request)
    {
        $data = $this->validatePart($request);
        app(ExportInventoryService::class)->transaction(function () use ($data, $request) {
            $part = PartSetting::create($data);
            AuditService::record('master_part', 'created', 'PartSetting', $part->id, $request->user()->id, [], $this->snapshot($part), 'Master part bersama dibuat.');
        });

        return redirect()->route('part-settings.index')->with('success', 'No Part berhasil ditambahkan.');
    }

    public function update(Request $request, string $partSetting)
    {
        $part = PartSetting::findOrFail($partSetting);
        $data = $this->validatePart($request, $part->id);
        $updated = app(ExportInventoryService::class)->transaction(function () use ($part, $data, $request) {
            $part = PartSetting::whereKey($part->id)->lockForUpdate()->firstOrFail();
            if ((int) $part->qty_box !== (int) $data['qty_box'] && DB::table('export_delivery_plan_items as item')->join('export_delivery_plans as plan', 'plan.id', '=', 'item.export_delivery_plan_id')
                ->where('item.part_setting_id', $part->id)->where('item.box_quantity', '>', 0)->whereNull('plan.deleted_at')->whereNotIn('plan.status', ['completed', 'cancelled'])->exists()) {
                throw ValidationException::withMessages(['qty_box' => 'PCS per box belum dapat diubah karena part masih dipakai rencana delivery ekspor aktif, termasuk draft. Selesaikan atau batalkan permintaan terkait terlebih dahulu.']);
            }
            if ($part->part_number !== $data['part_number'] && $this->isPartReferenced($part)) {
                return false;
            }
            $before = $this->snapshot($part);
            $part->update($data);
            AuditService::record('master_part', 'updated', 'PartSetting', $part->id, $request->user()->id, $before, $this->snapshot($part), 'Master part bersama diperbarui.');

            return true;
        });
        if (! $updated) {
            return back()->withInput()->with('error', 'No Part sudah dipakai dalam transaksi/histori dan tidak dapat diganti.');
        }

        return redirect()->route('part-settings.index')->with('success', 'No Part berhasil diperbarui.');
    }

    public function destroy(string $partSetting)
    {
        $deleted = app(ExportInventoryService::class)->transaction(function () use ($partSetting) {
            $part = PartSetting::whereKey($partSetting)->lockForUpdate()->firstOrFail();
            if ($this->isPartReferenced($part)) {
                return false;
            }
            $before = $this->snapshot($part);
            $part->delete();
            AuditService::record('master_part', 'deleted', 'PartSetting', $part->id, request()->user()->id, $before, [], 'Master part bersama dihapus.');

            return true;
        });

        return $deleted ? redirect()->route('part-settings.index')->with('success','No Part berhasil dihapus.') : back()->with('error','No Part sudah dipakai dalam transaksi/histori dan tidak dapat dihapus.');
    }
}
