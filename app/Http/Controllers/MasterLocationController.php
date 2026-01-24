<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

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
     * Remove the specified resource from storage.
     */
    public function destroy(\App\Models\MasterLocation $location)
    {
        if ($location->is_occupied) {
             return redirect()->route('locations.index')->with('error', 'Gagal hapus! Lokasi ini sedang terisi.');
        }
        
        $location->delete();

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
