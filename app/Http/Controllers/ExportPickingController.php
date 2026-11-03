<?php

namespace App\Http\Controllers;

use App\Models\ExportPickingIssue;
use App\Models\ExportPlannedPallet;
use App\Models\ExportStockAllocationNotice;
use App\Services\ExportPickingIssueService;
use App\Services\ExportPickingService;
use App\Services\ExportReceivingTransactionService;
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
        abort_unless($pallet->palletPlan?->deliveryPlan, 404);
        $notice = $this->notice($request, $pallet);
        $rows = $service->rows($pallet->id, $notice?->id);
        $issue = $helper ? null : $service->block($request->user(), $pallet->id);
        $issue?->load(['returnPallet.plannedPallet', 'reporter', 'part']);
        $pickParameters = ['pallet' => $pallet->id] + ($notice ? ['notice' => $notice->id] : []);
        $transactionCode = $notice ? ExportReceivingTransactionService::code('recommendation', $notice->id, $pallet->id) : null;
        $transactionUrl = $notice ? route('export.receiving.transactions.recommendation', ['notice' => $notice->id, 'pallet' => $pallet->id]) : null;

        return compact('pallet', 'rows', 'issue', 'helper', 'notice', 'pickParameters', 'transactionCode', 'transactionUrl');
    }

    private function notice(Request $request, ExportPlannedPallet $pallet): ?ExportStockAllocationNotice
    {
        $data = validator(['notice' => $request->query('notice')], ['notice' => 'nullable|integer|min:1'])->validate();
        if (empty($data['notice'])) {
            return null;
        } // Existing pallet URLs remain valid for older clients.
        $notice = ExportStockAllocationNotice::whereKey($data['notice'])->where('export_pallet_plan_id', $pallet->export_pallet_plan_id)->firstOrFail();
        abort_unless($notice->boxes()->whereHas('plannedItem', fn ($q) => $q->where('export_planned_pallet_id', $pallet->id))->exists(), 404);
        abort_unless($notice->acknowledged_at, 409, 'Tinjau hasil FIFO dan klik Oke sebelum membuka picking transaksi.');

        return $notice;
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
        if (is_string($request->input('box_number'))) {
            $request->merge(['box_number' => trim($request->input('box_number'))]);
        }

        return $request->validate(['box_number' => ['required', 'string', 'regex:/^[0-9]{8}$/']],
            ['box_number.regex' => 'Nomor box harus tepat 8 angka. Scan ulang; input tidak lengkap tidak membuat issue approval.']);
    }

    public function seek(Request $request, ExportPlannedPallet $pallet, ExportPickingService $service)
    {
        $data = $this->number($request);

        return response()->json($service->seek($pallet->id, $data['box_number'], $this->notice($request, $pallet)?->id))->header('Cache-Control', 'private, no-store');
    }

    public function scan(Request $request, ExportPlannedPallet $pallet, ExportPickingService $service)
    {
        $data = $this->number($request) + $request->validate(['request_token' => 'required|uuid']);
        $result = $service->scan($pallet->id, $data, $request->user(), $this->notice($request, $pallet)?->id);
        $result['html'] = view('export.picking.workbench', $this->data($pallet, $service, $request, false))->render();

        return response()->json($result, $result['blocked'] ? 423 : 200)->header('Cache-Control', 'private, no-store');
    }

    public function confirmReturn(Request $request, ExportPickingIssue $issue, ExportPickingIssueService $service)
    {
        $data = $request->validate(['revision' => 'required|integer|min:1', 'request_token' => 'required|uuid', 'placement_confirmed' => 'accepted']);
        $result = $service->confirmReturn($issue->id, $data, $request->user());
        $parameters = ['pallet' => $result->export_planned_pallet_id];
        if ($noticeId = data_get($result->context, 'allocation_notice_id')) {
            $parameters['notice'] = $noticeId;
        }

        return redirect()->route('export.receiving.picking.show', $parameters)
            ->with('success', 'Penempatan pengembalian dikonfirmasi. Blokir issue #'.$result->id.' sudah dibuka.');
    }
}
