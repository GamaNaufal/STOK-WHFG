<?php

namespace App\Http\Controllers;

use App\Models\ExportMasterLocation;
use App\Models\MasterLocation;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MasterLocationController extends Controller
{
    private function modelClass(): string
    {
        return request()->routeIs('export.*') ? ExportMasterLocation::class : MasterLocation::class;
    }

    private function context(): array
    {
        $isExport = $this->modelClass() === ExportMasterLocation::class;

        return ['isExport' => $isExport, 'routePrefix' => $isExport ? 'export.locations' : 'locations'];
    }

    public function index()
    {
        $model = $this->modelClass();
        $query = $model::with('currentPallet');
        if ($model === ExportMasterLocation::class) {
            $query->with('reservation.pallet', 'reservation.palletPlan.deliveryPlan', 'stockPallet')->withExists(['plannedPallets', 'stockPalletHistory']);
        }
        $locations = $query->orderBy('code')->paginate(10);

        return view('admin.locations.index', $this->context() + compact('locations'));
    }

    public function edit(string $location)
    {
        $model = $this->modelClass();
        $location = $model::findOrFail($location);

        return view('admin.locations.edit', $this->context() + compact('location'));
    }

    public function store(Request $request)
    {
        $model = $this->modelClass();
        $request->merge(['code' => is_string($request->code) ? strtoupper(trim($request->code)) : $request->code]);
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255', Rule::unique('master_locations', 'code')->where('scope', $model::MASTER_SCOPE)],
        ]);

        DB::transaction(function () use ($validated, $request, $model) {
            $location = $model::create(['code' => $validated['code'], 'is_occupied' => false]);
            AuditService::record('master_location', 'created', class_basename($location), $location->id, $request->user()->id,
                [], ['scope' => $location->scope, 'location' => $location->code], "Lokasi {$location->code} ({$location->scope}) dibuat.");
        });

        return redirect()->route($this->context()['routePrefix'].'.index')->with('success', 'Lokasi berhasil ditambahkan!');
    }

    public function update(Request $request, string $location)
    {
        $model = $this->modelClass();
        $location = $model::findOrFail($location);
        $request->merge(['code' => is_string($request->code) ? strtoupper(trim($request->code)) : $request->code]);
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255', Rule::unique('master_locations', 'code')->where('scope', $model::MASTER_SCOPE)->ignore($location->id)],
        ]);

        try {
            DB::transaction(function () use ($request, $location, $validated, $model) {
                $locked = $model::whereKey($location->id)->lockForUpdate()->firstOrFail();
                if ($locked instanceof ExportMasterLocation && ($locked->plannedPallets()->exists() || $locked->stockPalletHistory()->exists())) {
                    throw new \RuntimeException('Lokasi ini tercatat pada perencanaan atau stok ekspor; identitasnya tidak dapat diubah.');
                }
                if ($locked->is_occupied) {
                    throw new \RuntimeException('Gagal edit! Lokasi ini sedang terisi.');
                }

                $previousCode = $locked->code;
                $locked->update(['code' => $validated['code']]);
                AuditService::record('master_location', 'updated', class_basename($locked), $locked->id, $request->user()->id,
                    ['scope' => $locked->scope, 'location' => $previousCode],
                    ['scope' => $locked->scope, 'location' => $locked->code],
                    "Lokasi {$previousCode} ({$locked->scope}) diperbarui menjadi {$locked->code}.");
            });
        } catch (\Throwable $e) {
            return redirect()->route($this->context()['routePrefix'].'.index')->with('error', $e->getMessage());
        }

        return redirect()->route($this->context()['routePrefix'].'.index')->with('success', 'Lokasi berhasil diperbarui!');
    }

    public function destroy(string $location)
    {
        $model = $this->modelClass();
        $location = $model::findOrFail($location);

        try {
            DB::transaction(function () use ($location, $model) {
                $locked = $model::whereKey($location->id)->lockForUpdate()->firstOrFail();
                if ($locked instanceof ExportMasterLocation && ($locked->plannedPallets()->exists() || $locked->stockPalletHistory()->exists())) {
                    throw new \RuntimeException('Lokasi ini tercatat pada perencanaan atau stok ekspor dan tidak dapat dihapus.');
                }
                if (($locked->is_occupied && $locked->current_pallet_id) || $locked->stockLocations()->exists()) {
                    throw new \RuntimeException('Gagal hapus! Lokasi ini sedang digunakan.');
                }

                $locked->delete();
                AuditService::record('master_location', 'deleted', class_basename($locked), $locked->id, request()->user()->id,
                    ['scope' => $locked->scope, 'location' => $locked->code], [], "Lokasi {$locked->code} ({$locked->scope}) dihapus.");
            });
        } catch (\Throwable $e) {
            return redirect()->route($this->context()['routePrefix'].'.index')->with('error', $e->getMessage());
        }

        return redirect()->route($this->context()['routePrefix'].'.index')->with('success', 'Lokasi berhasil dihapus!');
    }

    public function apiSearchAvailable(Request $request)
    {
        $search = $request->query('q');
        $model = $this->modelClass();
        $query = $model::where('is_occupied', false);
        if ($model === ExportMasterLocation::class) $query->whereDoesntHave('reservation')->whereDoesntHave('stockPallet')
            ->whereDoesntHave('stockLocations')->whereNull('current_pallet_id');
        if ($search) {
            $query->where('code', 'like', "%{$search}%");
        }

        return response()->json($query->limit(20)->get(['id', 'code']));
    }
}
