<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MasterLocationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $locations = \App\Models\MasterLocation::with('currentPallet')->orderBy('code', 'asc')->paginate(10);
        return view('admin.locations.index', compact('locations'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('admin.locations.create');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(\App\Models\MasterLocation $location)
    {
        return view('admin.locations.edit', compact('location'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|unique:master_locations|max:255',
        ]);

        \App\Models\MasterLocation::create([
            'code' => strtoupper($request->code),
            'is_occupied' => false
        ]);

        return redirect()->route('locations.index')->with('success', 'Lokasi berhasil ditambahkan!');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, \App\Models\MasterLocation $location)
    {
        $request->validate([
            'code' => 'required|max:255|unique:master_locations,code,' . $location->id,
        ]);

        try {
            DB::transaction(function () use ($request, $location) {
                $locked = \App\Models\MasterLocation::whereKey($location->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($locked->is_occupied) {
                    throw new \RuntimeException('Gagal edit! Lokasi ini sedang terisi.');
                }

                $locked->update([
                    'code' => strtoupper($request->code),
                ]);
            });
        } catch (\Throwable $e) {
            return redirect()->route('locations.index')->with('error', $e->getMessage());
        }

        return redirect()->route('locations.index')->with('success', 'Lokasi berhasil diperbarui!');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(\App\Models\MasterLocation $location)
    {
        try {
            DB::transaction(function () use ($location) {
                $locked = \App\Models\MasterLocation::whereKey($location->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($locked->is_occupied && $locked->current_pallet_id) {
                    throw new \RuntimeException('Gagal hapus! Lokasi ini sedang terisi.');
                }

                $locked->delete();
            });
        } catch (\Throwable $e) {
            return redirect()->route('locations.index')->with('error', $e->getMessage());
        }

        return redirect()->route('locations.index')->with('success', 'Lokasi berhasil dihapus!');
    }

    // API Search Available Locations
    public function apiSearchAvailable(Request $request)
    {
        $search = $request->query('q');
        
        $query = \App\Models\MasterLocation::where('is_occupied', false);
        
        if ($search) {
            $query->where('code', 'like', "%{$search}%");
        }
        
        return response()->json($query->limit(20)->get(['id', 'code']));
    }
}
