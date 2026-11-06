<?php

namespace App\Http\Controllers;

use App\Models\ExportMasterLocation;
use App\Models\ExportStockPallet;
use App\Services\ExportPalletMergeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ExportPalletMergeController extends Controller
{
    public function index(Request $request, ExportPalletMergeService $service)
    {
        $service->authorize($request->user());
        $sources = ExportStockPallet::with(['plannedPallet.palletPlan.deliveryPlan', 'boxes' => fn ($q) => $q->select('id', 'current_pallet_id', 'part_number')->orderBy('part_number')])
            ->whereNull('retired_at')->whereNotNull('master_location_id')->whereHas('boxes')->orderBy('location_code')->get();
        $locations = ExportMasterLocation::with('stockPallet', 'reservation')->orderBy('code')->get();

        return view('export.merge.index', compact('sources', 'locations'));
    }

    private function data(Request $request, bool $confirm = false): array
    {
        return $request->validate(['pallet_ids' => 'required|array|min:2|max:50', 'pallet_ids.*' => 'required|integer|distinct',
            'location_id' => 'required|integer', 'box_limit' => ExportStockPallet::BOX_LIMIT_RULE, 'spare_mode' => ['required', Rule::in(['fill', 'add'])]] + ($confirm ? [
                'fingerprint' => 'required|string|size:64', 'request_token' => 'required|uuid',
                'physical_confirmed' => 'accepted', 'ready_confirmed' => 'sometimes|accepted', 'demand_confirmed' => 'sometimes|accepted',
            ] : []));
    }

    public function preview(Request $request, ExportPalletMergeService $service)
    {
        try {
            $data = $this->data($request);
            $preview = $service->preview($data, $request->user());
        } catch (ValidationException $e) {
            return $this->failed($request, $e);
        }

        return view('export.merge.preview', compact('data', 'preview') + ['token' => (string) Str::uuid()]);
    }

    public function store(Request $request, ExportPalletMergeService $service)
    {
        try {
            $service->merge($this->data($request, true), $request->user());
        } catch (ValidationException $e) {
            return $this->failed($request, $e);
        }

        return redirect()->route('export.merge.result', $request->input('request_token'))->with('success', 'Pallet ekspor berhasil digabung. Gunakan panduan dan revisi terbaru.');
    }

    private function failed(Request $request, ValidationException $e)
    {
        if ($request->expectsJson()) {
            throw $e;
        }

        // The preview is POST-only; redirecting back there would produce a 405.
        return redirect()->route('export.merge.index')->withErrors($e->validator)
            ->withInput($request->except('_token', 'fingerprint', 'request_token'));
    }

    public function result(Request $request, string $token, ExportPalletMergeService $service)
    {
        $service->authorize($request->user());
        $merge = DB::table('export_pallet_merges')->where('request_token', $token)->first();
        abort_unless($merge, 404);

        return view('export.merge.result', ['result' => json_decode($merge->snapshot, true, 512, JSON_THROW_ON_ERROR)]);
    }
}
