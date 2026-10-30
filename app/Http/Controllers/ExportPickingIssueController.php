<?php

namespace App\Http\Controllers;

use App\Models\ExportPickingIssue;
use App\Services\ExportPickingIssueService;
use Illuminate\Http\Request;

class ExportPickingIssueController extends Controller
{
    public function approve(Request $request, ExportPickingIssue $issue, ExportPickingIssueService $service)
    {
        $data = $request->validate(['revision' => 'required|integer|min:1', 'notes' => 'required|string|max:500',
            'part_number' => 'nullable|string|max:100', 'destination' => ['nullable', 'string', 'regex:/^(pallet|location):[1-9][0-9]*$/']]);
        $service->approve($issue->id, $data, $request->user());
        return redirect()->route('delivery.pick.issues')->with('success', 'Approval ekspor disimpan. Blokir tetap aktif sampai operator mengonfirmasi penempatan pengembalian.');
    }
}
