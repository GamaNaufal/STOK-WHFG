<?php

namespace App\Http\Controllers;

use App\Models\ExportPartSetting;
use App\Models\PartSetting;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PartSettingController extends Controller
{
    private function modelClass(): string
    {
        return request()->routeIs('export.*') ? ExportPartSetting::class : PartSetting::class;
    }

    private function context(): array
    {
        $isExport = $this->modelClass() === ExportPartSetting::class;

        return ['isExport' => $isExport, 'routePrefix' => $isExport ? 'export.part-settings' : 'part-settings'];
    }

    private function isPartReferenced(PartSetting $part): bool
    {
        if ($part->scope === 'export') {
            return DB::table('export_delivery_plan_items')->where('part_setting_id', $part->id)->exists()
                || DB::table('export_receiving_batch_items')->where('part_setting_id', $part->id)->exists()
                || DB::table('export_stock_boxes')->where('part_setting_id', $part->id)->exists()
                || DB::table('export_planned_pallet_items')->where('part_setting_id', $part->id)->exists();
        }

        $partNumber = $part->part_number;

        return DB::table('boxes')->where('part_number', $partNumber)->exists()
            || DB::table('pallet_items')->where('part_number', $partNumber)->exists()
            || DB::table('delivery_order_items')->where('part_number', $partNumber)->exists()
            || DB::table('not_full_box_requests')->where('part_number', $partNumber)->exists()
            || DB::table('stock_withdrawals')->where('part_number', $partNumber)->exists();
    }

    public function index()
    {
        $model = $this->modelClass();
        $parts = $model::orderBy('part_number')->paginate(10);

        return view('operator.part-settings.index', $this->context() + compact('parts'));
    }

    public function search(Request $request)
    {
        $query = trim((string) $request->get('q', ''));
        $model = $this->modelClass();
        $parts = $model::query()
            ->when($query !== '', fn ($builder) => $builder->where('part_number', 'like', '%'.$query.'%'))
            ->orderBy('part_number')->limit(50)->get(['id', 'part_number', 'qty_box']);

        return response()->json(['data' => $parts]);
    }

    public function create()
    {
        return view('operator.part-settings.create', $this->context());
    }

    public function store(Request $request)
    {
        $model = $this->modelClass();
        $validated = $request->validate([
            'part_number' => ['required', 'string', 'max:100', Rule::unique('part_settings', 'part_number')->where('scope', $model::MASTER_SCOPE)],
            'qty_box' => 'required|integer|min:1|max:4294967295',
        ]);

        DB::transaction(function () use ($validated, $request, $model) {
            $part = $model::create($validated);
            AuditService::record('master_part', 'created', class_basename($part), $part->id, $request->user()->id,
                [], ['scope' => $part->scope, 'part_number' => $part->part_number, 'qty_box' => (int) $part->qty_box],
                "Master part {$part->part_number} ({$part->scope}) dibuat.");
        });

        return redirect()->route($this->context()['routePrefix'].'.index')->with('success', 'No Part berhasil ditambahkan.');
    }

    public function edit(string $partSetting)
    {
        $model = $this->modelClass();
        $partSetting = $model::findOrFail($partSetting);

        return view('operator.part-settings.edit', $this->context() + compact('partSetting'));
    }

    public function update(Request $request, string $partSetting)
    {
        $model = $this->modelClass();
        $partSetting = $model::findOrFail($partSetting);
        $validated = $request->validate([
            'part_number' => ['required', 'string', 'max:100', Rule::unique('part_settings', 'part_number')->where('scope', $model::MASTER_SCOPE)->ignore($partSetting->id)],
            'qty_box' => 'required|integer|min:1|max:4294967295',
        ]);

        $updated = DB::transaction(function () use ($partSetting, $validated, $request, $model) {
            $partSetting = $model::whereKey($partSetting->id)->lockForUpdate()->firstOrFail();
            if ((string) $partSetting->part_number !== $validated['part_number'] && $this->isPartReferenced($partSetting)) {
                return false;
            }
            $before = ['scope' => $partSetting->scope, 'part_number' => $partSetting->part_number, 'qty_box' => (int) $partSetting->qty_box];
            $partSetting->update($validated);
            AuditService::record('master_part', 'updated', class_basename($partSetting), $partSetting->id, $request->user()->id,
                $before, ['scope' => $partSetting->scope, 'part_number' => $partSetting->part_number, 'qty_box' => (int) $partSetting->qty_box],
                "Master part {$partSetting->part_number} ({$partSetting->scope}) diperbarui.");

            return true;
        });
        if (! $updated) {
            return redirect()->back()->withInput()
                ->with('error', 'No Part sudah dipakai dalam transaksi/histori dan tidak dapat diganti. Qty per box tetap dapat diperbarui.');
        }

        return redirect()->route($this->context()['routePrefix'].'.index')->with('success', 'No Part berhasil diperbarui.');
    }

    public function destroy(string $partSetting)
    {
        $model = $this->modelClass();
        $partSetting = $model::findOrFail($partSetting);
        $deleted = DB::transaction(function () use ($partSetting, $model) {
            $partSetting = $model::whereKey($partSetting->id)->lockForUpdate()->firstOrFail();
            if ($this->isPartReferenced($partSetting)) {
                return false;
            }
            $partSetting->delete();
            AuditService::record('master_part', 'deleted', class_basename($partSetting), $partSetting->id, request()->user()->id,
                ['scope' => $partSetting->scope, 'part_number' => $partSetting->part_number, 'qty_box' => (int) $partSetting->qty_box], [],
                "Master part {$partSetting->part_number} ({$partSetting->scope}) dihapus.");

            return true;
        });
        if (! $deleted) {
            return redirect()->back()->with('error', 'No Part sudah dipakai dalam transaksi/histori dan tidak dapat dihapus.');
        }

        return redirect()->route($this->context()['routePrefix'].'.index')->with('success', 'No Part berhasil dihapus.');
    }
}
