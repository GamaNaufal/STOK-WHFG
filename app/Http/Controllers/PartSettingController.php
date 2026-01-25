<?php

namespace App\Http\Controllers;

use App\Models\PartSetting;
use Illuminate\Http\Request;

class PartSettingController extends Controller
{
    public function index()
    {
        $parts = PartSetting::orderBy('part_number')->paginate(20);
        return view('warehouse.part-settings.index', compact('parts'));
    }

    public function create()
    {
        return view('warehouse.part-settings.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'part_number' => 'required|string|max:100|unique:part_settings,part_number',
            'qty_box' => 'required|integer|min:1',
        ]);

        PartSetting::create($validated);

        return redirect()->route('part-settings.index')->with('success', 'No Part berhasil ditambahkan.');
    }

    public function edit(PartSetting $partSetting)
    {
        return view('warehouse.part-settings.edit', compact('partSetting'));
    }

    public function update(Request $request, PartSetting $partSetting)
    {
        $validated = $request->validate([
            'part_number' => 'required|string|max:100|unique:part_settings,part_number,' . $partSetting->id,
            'qty_box' => 'required|integer|min:1',
        ]);

        $partSetting->update($validated);

        return redirect()->route('part-settings.index')->with('success', 'No Part berhasil diperbarui.');
    }

    public function destroy(PartSetting $partSetting)
    {
        $partSetting->delete();

        return redirect()->route('part-settings.index')->with('success', 'No Part berhasil dihapus.');
    }
}
