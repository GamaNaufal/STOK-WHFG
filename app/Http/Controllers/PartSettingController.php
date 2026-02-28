<?php

namespace App\Http\Controllers;

use App\Models\PartSetting;
use Illuminate\Http\Request;

class PartSettingController extends Controller
{
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
            'part_number' => ['required', 'string', 'max:100', 'not_regex:/\p{L}/u', 'unique:part_settings,part_number'],
            'qty_box' => 'required|integer|min:1|max:4294967295',
        ], [
            'part_number.not_regex' => 'No Part tidak boleh mengandung huruf. Angka dan simbol diperbolehkan.',
        ]);

        PartSetting::create($validated);

        return redirect()->route('part-settings.index')->with('success', 'No Part berhasil ditambahkan.');
    }

    public function edit(PartSetting $partSetting)
    {
        return view('operator.part-settings.edit', compact('partSetting'));
    }

    public function update(Request $request, PartSetting $partSetting)
    {
        $validated = $request->validate([
            'part_number' => ['required', 'string', 'max:100', 'not_regex:/\p{L}/u', 'unique:part_settings,part_number,' . $partSetting->id],
            'qty_box' => 'required|integer|min:1|max:4294967295',
        ], [
            'part_number.not_regex' => 'No Part tidak boleh mengandung huruf. Angka dan simbol diperbolehkan.',
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
