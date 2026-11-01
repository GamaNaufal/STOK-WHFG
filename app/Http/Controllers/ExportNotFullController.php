<?php

namespace App\Http\Controllers;

use App\Models\ExportNotFullRequest;
use App\Services\ExportNotFullService;
use Illuminate\Http\Request;

class ExportNotFullController extends Controller
{
    private function access(Request $request): void
    {
        abort_unless(in_array($request->user()->role, ['admin', 'admin_warehouse', 'supervisi'], true), 403);
    }

    public function index(Request $request, bool $approval = false)
    {
        $this->access($request);
        $pending = app(ExportNotFullService::class)->pending()->select('export_not_full_requests.id');
        $requests = ExportNotFullRequest::with(['item', 'delivery.palletPlan', 'preparer', 'approver'])
            ->leftJoinSub($pending, 'pending_nf', 'pending_nf.id', '=', 'export_not_full_requests.id')
            ->join('export_delivery_plans as delivery', 'delivery.id', '=', 'export_not_full_requests.export_delivery_plan_id')
            ->select('export_not_full_requests.*')->orderByRaw('CASE WHEN pending_nf.id IS NOT NULL THEN 0 ELSE 1 END')
            ->orderByRaw('CASE WHEN pending_nf.id IS NOT NULL THEN delivery.delivery_deadline ELSE NULL END')
            ->orderByRaw('CASE WHEN pending_nf.id IS NOT NULL THEN export_not_full_requests.id ELSE NULL END')
            ->orderByDesc('export_not_full_requests.id')->paginate(20)->withQueryString();

        return view('export.not-full.index', compact('requests', 'approval') + ['service' => app(ExportNotFullService::class)]);
    }

    public function show(Request $request, ExportNotFullRequest $exportRequest, ExportNotFullService $service)
    {
        $this->access($request);
        $exportRequest->load(['item', 'delivery.palletPlan', 'preparer', 'approver', 'stockBox']);
        $target = $service->target($exportRequest);
        $state = $service->state($exportRequest, $target);
        $source = $target && ! $target->fulfillmentBoxes()->exists() ? $service->source($exportRequest) : null;

        return view('export.not-full.show', ['record' => $exportRequest] + compact('target', 'state', 'source'));
    }

    public function prepare(Request $request, ExportNotFullRequest $exportRequest, ExportNotFullService $service)
    {
        $data = $request->validate(['revision' => 'required|integer|min:1', 'mode' => 'required|in:external,split',
            'box_number' => ['required', 'string', 'regex:/^[0-9]{8}$/D'], 'notes' => 'required|string|min:3|max:1000']);
        $service->prepare($exportRequest->id, $data, $request->user());

        return back()->with('success', 'Request diperbarui. Periksa status dan rincian; stok belum dibuat oleh penyiapan data.');
    }

    public function approve(Request $request, ExportNotFullRequest $exportRequest, ExportNotFullService $service)
    {
        $data = $request->validate(['revision' => 'required|integer|min:1', 'physical_confirmed' => 'required|accepted']);
        $service->approve($exportRequest->id, $data, $request->user());

        return back()->with('success', 'Request diproses. Periksa hasil dan penempatan box pada rincian.');
    }

    public function reject(Request $request, ExportNotFullRequest $exportRequest, ExportNotFullService $service)
    {
        $data = $request->validate(['revision' => 'required|integer|min:1', 'reason' => 'required|string|min:3|max:1000']);
        $service->reject($exportRequest->id, $data, $request->user());

        return back()->with('success', 'Penyiapan ditolak. Kebutuhan Not Full tetap wajib dipenuhi.');
    }
}
