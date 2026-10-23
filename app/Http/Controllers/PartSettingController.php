<?php

namespace App\Http\Controllers;

use App\Models\PartSetting;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PartSettingController extends Controller
{
    private function isPartReferenced(string $partNumber): bool
    {
        return DB::table('boxes')->where('part_number', $partNumber)->exists()
            || DB::table('pallet_items')->where('part_number', $partNumber)->exists()
            || DB::table('delivery_order_items')->where('part_number', $partNumber)->exists()
            || DB::table('not_full_box_requests')->where('part_number', $partNumber)->exists()
            || DB::table('stock_withdrawals')->where('part_number', $partNumber)->exists();
    }

    public function index()
    {
        $parts = PartSetting::orderBy('part_number')->paginate(10);
        return view('operator.part-settings.index', compact('parts'));
    }

    public function search(Request $request)
    {
        $query = trim((string) $request->get('q', ''));

        $parts = PartSetting::query()
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where('part_number', 'like', '%' . $query . '%');
            })
            ->orderBy('part_number')
            ->limit(50)
            ->get(['id', 'part_number', 'qty_box']);

        return response()->json([
            'data' => $parts,
        ]);
    }

    public function create()
    {
        return view('operator.part-settings.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'part_number' => ['required', 'string', 'max:100', 'unique:part_settings,part_number'],
            'qty_box' => 'required|integer|min:1|max:4294967295',
        ]);

        DB::transaction(function () use ($validated, $request) {
            $part = PartSetting::create($validated);
            AuditService::record('master_part', 'created', 'PartSetting', $part->id, $request->user()->id,
                [], ['part_number' => $part->part_number, 'qty_box' => (int) $part->qty_box], "Master part {$part->part_number} dibuat.");
        });

        return redirect()->route('part-settings.index')->with('success', 'No Part berhasil ditambahkan.');
    }

    public function edit(PartSetting $partSetting)
    {
        return view('operator.part-settings.edit', compact('partSetting'));
    }

    public function update(Request $request, PartSetting $partSetting)
    {
        $validated = $request->validate([
            'part_number' => ['required', 'string', 'max:100', 'unique:part_settings,part_number,' . $partSetting->id],
            'qty_box' => 'required|integer|min:1|max:4294967295',
        ]);

        if (
            (string) $partSetting->part_number !== (string) $validated['part_number']
            && $this->isPartReferenced((string) $partSetting->part_number)
        ) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'No Part sudah dipakai dalam transaksi/histori dan tidak dapat diganti. Qty per box tetap dapat diperbarui.');
        }

        DB::transaction(function () use ($partSetting, $validated, $request) {
            $before = ['part_number' => $partSetting->part_number, 'qty_box' => (int) $partSetting->qty_box];
            $partSetting->update($validated);
            AuditService::record('master_part', 'updated', 'PartSetting', $partSetting->id, $request->user()->id,
                $before, ['part_number' => $partSetting->part_number, 'qty_box' => (int) $partSetting->qty_box],
                "Master part {$partSetting->part_number} diperbarui.");
        });

        return redirect()->route('part-settings.index')->with('success', 'No Part berhasil diperbarui.');
    }

    public function destroy(PartSetting $partSetting)
    {
        if ($this->isPartReferenced((string) $partSetting->part_number)) {
            return redirect()->back()->with('error', 'No Part sudah dipakai dalam transaksi/histori dan tidak dapat dihapus.');
        }

        DB::transaction(function () use ($partSetting) {
            $partSetting->delete();
            AuditService::record('master_part', 'deleted', 'PartSetting', $partSetting->id, request()->user()->id,
                ['part_number' => $partSetting->part_number, 'qty_box' => (int) $partSetting->qty_box], [],
                "Master part {$partSetting->part_number} dihapus.");
        });

        return redirect()->route('part-settings.index')->with('success', 'No Part berhasil dihapus.');
    }
}
