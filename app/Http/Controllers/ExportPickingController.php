<?php

namespace App\Http\Controllers;

use App\Models\{ExportPickingIssue, ExportPlannedPallet};
use App\Services\{ExportPickingIssueService, ExportPickingService};
use Illuminate\Http\Request;

class ExportPickingController extends Controller
{
    public function index(Request $request, ExportPickingService $service)
    {
        $tasks = $service->tasks()->paginate(20)->withQueryString();
        return view('export.picking.index', compact('tasks'));
    }

    private function data(ExportPlannedPallet $pallet, ExportPickingService $service, Request $request, bool $helper): array
    {
        $pallet->load('palletPlan.deliveryPlan');
        $rows = $service->rows($pallet->id);
        $issue = $helper ? null : $service->block($request->user(), $pallet->id);
        $issue?->load(['returnPallet.plannedPallet', 'reporter', 'part']);
        return compact('pallet', 'rows', 'issue', 'helper');
    }

    public function helper(Request $request, ExportPlannedPallet $pallet, ExportPickingService $service)
    {
        return response()->view('export.picking.show', $this->data($pallet, $service, $request, true))->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, ExportPlannedPallet $pallet, ExportPickingService $service)
    {
        return response()->view('export.picking.show', $this->data($pallet, $service, $request, false))->header('Cache-Control', 'private, no-store');
    }

    private function number(Request $request): array
    {
        if (is_string($request->input('box_number'))) $request->merge(['box_number' => trim($request->input('box_number'))]);
        return $request->validate(['box_number' => ['required', 'string', 'regex:/^[0-9]{8}$/']],
            ['box_number.regex' => 'Nomor box harus tepat 8 angka. Scan ulang; input tidak lengkap tidak membuat issue approval.']);
    }

    public function seek(Request $request, ExportPlannedPallet $pallet, ExportPickingService $service)
    {
        $data = $this->number($request);
        return response()->json($service->seek($pallet->id, $data['box_number']))->header('Cache-Control', 'private, no-store');
    }

    public function scan(Request $request, ExportPlannedPallet $pallet, ExportPickingService $service)
    {
        $data = $this->number($request) + $request->validate(['request_token' => 'required|uuid']);
        $result = $service->scan($pallet->id, $data, $request->user());
        $result['html'] = view('export.picking.workbench', $this->data($pallet, $service, $request, false))->render();
        return response()->json($result, $result['blocked'] ? 423 : 200)->header('Cache-Control', 'private, no-store');
    }

    public function confirmReturn(Request $request, ExportPickingIssue $issue, ExportPickingIssueService $service)
    {
        $data = $request->validate(['revision' => 'required|integer|min:1', 'request_token' => 'required|uuid', 'placement_confirmed' => 'accepted']);
        $result = $service->confirmReturn($issue->id, $data, $request->user());
        return redirect()->route('export.receiving.picking.show', ['pallet' => $result->export_planned_pallet_id])
            ->with('success', 'Penempatan pengembalian dikonfirmasi. Blokir issue #'.$result->id.' sudah dibuka.');
    }
}
