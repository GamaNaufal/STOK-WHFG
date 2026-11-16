<?php

namespace App\Http\Controllers;

use App\Services\DeliveryAnnouncementService;
use App\Models\ExportDeliveryPlan;
use App\Models\PartSetting;
use App\Services\ExportDeliveryPlanImportService;
use App\Services\ExportDeliveryPlanService;
use App\Services\ExportDeliveryPlanDeletionService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExportDeliveryPlanController extends Controller
{
    public function index(Request $request)
    {
        $search = trim(mb_substr((string) $request->query('q', ''), 0, 100));
        $plans = ExportDeliveryPlan::with('creator')
            ->when($search !== '', fn ($query) => $query->where(fn ($match) => $match
                ->where('customer_name', 'like', '%'.$search.'%')->orWhere('plan_code', 'like', '%'.$search.'%')))
            ->orderByDesc('id')->paginate(15)->withQueryString();

        return view('export.delivery-plans.index', compact('plans', 'search'));
    }

    private function form(?ExportDeliveryPlan $plan = null)
    {
        $parts = PartSetting::with('documentNumbers')->orderBy('part_number')->get(['id', 'part_number', 'qty_box']);
        $rows = $plan?->items->map(fn ($item) => [
            'part_number' => $item->document_part_number ?? $item->part_number,
            'scan_part_number' => $item->part_number,
            'part_setting_id' => $item->part_setting_id,
            'pcs_quantity' => $item->pcs_quantity, 'pcs_per_box' => $item->pcs_per_box,
        ])->all() ?? [];

        $customerReady = app(\App\Services\ExportCustomerStockPolicy::class)->ready();
        $masterCustomers = app(\App\Services\ExportCustomerService::class)->options();
        $customerId = $plan ? app(\App\Services\ExportCustomerStockPolicy::class)->customerId($plan) : null;
        if ($customerReady) {
            if ($customerId && ! $masterCustomers->contains('id', $customerId)) $masterCustomers->push(\App\Models\ExportCustomer::findOrFail($customerId));
            $masterCustomers->load('aliases');
        }
        return view('export.delivery-plans.form', compact('plan', 'parts', 'rows', 'customerReady', 'masterCustomers', 'customerId'));
    }

    public function create()
    {
        return $this->form();
    }

    public function edit(ExportDeliveryPlan $deliveryPlan)
    {
        abort_unless($deliveryPlan->status === 'draft', 409);
        $deliveryPlan->load('items');

        return $this->form($deliveryPlan);
    }

    public function show(ExportDeliveryPlan $deliveryPlan)
    {
        $deliveryPlan->load(['items', 'creator', 'palletPlan']);

        return view('export.delivery-plans.show', ['plan' => $deliveryPlan,
            'printReady' => $deliveryPlan->palletPlan?->isFinalFor($deliveryPlan) ?? false]);
    }

    public function deletionPreview(ExportDeliveryPlan $deliveryPlan, ExportDeliveryPlanDeletionService $service)
    {
        $review = $service->preview($deliveryPlan->id, auth()->user());
        return response()->json(['can_delete' => $review['can_delete'], 'plan_code' => $deliveryPlan->plan_code,
            'html' => view('export.delivery-plans.deletion-preview', compact('review'))->render()])
            ->header('Cache-Control', 'private, no-store');
    }

    private function validated(Request $request, ?ExportDeliveryPlan $plan = null): array
    {
        $sources = ['manual', 'excel'];
        // Preserve provenance when editing a historical plan; new photo input is disabled.
        if ($plan?->source_type === 'photo') {
            $sources[] = 'photo';
        }

        return $request->validate([
            'request_token' => 'required|uuid',
            // Preserve historical request hashes on replay; these fields cannot bypass code uniqueness.
            'duplicate_review_token' => 'nullable|string|size:64',
            'duplicate_confirmed' => 'nullable|in:1', 'duplicate_reason' => 'nullable|string|max:500',
            'plan_code' => 'required|string|max:100',
            'customer_name' => 'required|string|max:255',
            'customer_id' => 'nullable|integer|min:1', 'customer_reference_version' => 'nullable|in:1', 'customer_source_name' => 'nullable|string|max:255',
            'delivery_deadline' => 'required|date_format:Y-m-d',
            'source_type' => 'required|in:'.implode(',', $sources),
            'source_name' => 'nullable|string|max:255',
            'reviewed' => ['exclude_if:source_type,manual', 'required', 'accepted'],
            'revision' => $plan ? 'required|integer|min:1' : 'sometimes|integer|min:1',
            'items' => 'required|array|min:1|max:500',
            'items.*.part_number' => 'required|string|max:100|distinct:strict',
            'items.*.expected_part_setting_id' => 'sometimes|nullable|integer|min:1',
            'items.*.pcs_quantity' => 'required|integer|min:1|max:4294967295',
        ], [
            'request_token.required' => 'Muat ulang formulir rencana sebelum menyimpan. Isian yang belum tersimpan dapat diperiksa kembali.',
            'items.*.part_number.distinct' => 'Nomor part tidak boleh berulang dalam satu rencana.',
            'reviewed.accepted' => 'Periksa dan konfirmasi hasil impor sebelum menyimpan.',
        ]);
    }

    public function store(Request $request, ExportDeliveryPlanService $service)
    {
        try { $plan = $service->save($this->validated($request), $request->user()); }
        catch (ValidationException $error) { return $this->validationResponse($request, $error); }

        return ($plan->trashed() ? redirect()->route('export.delivery-plans.index') : redirect()->route('export.delivery-plans.show', $plan))
            ->with('success', $plan->trashed() ? 'Penyimpanan sebelumnya sudah tercatat, rencana tersebut sudah diarsipkan.' : $this->savedMessage($plan));
    }

    public function update(Request $request, int $deliveryPlan, ExportDeliveryPlanService $service)
    {
        $existing = ExportDeliveryPlan::withTrashed()->findOrFail($deliveryPlan);
        try { $plan = $service->save($this->validated($request, $existing), $request->user(), $deliveryPlan); }
        catch (ValidationException $error) { return $this->validationResponse($request, $error); }

        return ($plan->trashed() ? redirect()->route('export.delivery-plans.index') : redirect()->route('export.delivery-plans.show', $plan))
            ->with('success', $plan->trashed() ? 'Penyimpanan sebelumnya sudah tercatat, rencana tersebut sudah diarsipkan.' : $this->savedMessage($plan));
    }

    private function savedMessage(ExportDeliveryPlan $plan): string
    {
        $needs = $plan->items->where('not_full_pcs', '>', 0);
        return 'Rencana delivery berhasil disimpan.'.($needs->isNotEmpty()
            ? ' Terdapat '.$needs->count().' part membutuhkan Box Not Full. Request wajib sudah dicatat otomatis. Hubungi Admin Warehouse/Admin Utama untuk penyiapan setelah perencanaan pallet final, lalu approval Admin Utama/Supervisi. Kebutuhan ini harus terpenuhi sebelum pallet siap dan permintaan selesai.' : '');
    }

    private function validationResponse(Request $request, ValidationException $error)
    {
        if ($request->expectsJson() || !isset($error->errors()['request_token'])) throw $error;
        // A Back/restored form may carry an already-used token. Preserve the operator's edits,
        // but require another deliberate submit with a fresh token and the normal duplicate review.
        return redirect()->back()->withInput(array_replace($request->except('_token'), ['request_token' => (string) \Illuminate\Support\Str::uuid()]))
            ->withErrors(array_replace($error->errors(), ['request_token' => 'Formulir sebelumnya tidak dapat dipakai untuk isian ini. Isian tetap dipertahankan, periksa lalu simpan kembali.']));
    }

    public function destroy(Request $request, ExportDeliveryPlan $deliveryPlan, ExportDeliveryPlanDeletionService $service)
    {
        $data = $request->validate(['revision' => 'required|integer|min:1', 'pallet_revision' => 'nullable|integer|min:1',
            'review_token' => 'nullable|string|max:16384', 'reason' => 'nullable|string|max:500']);
        $service->delete($deliveryPlan->id, $data, $request->user());

        return redirect()->route('export.delivery-plans.index')->with('success', 'Rencana delivery berhasil dihapus dari daftar aktif. Riwayat tetap tersimpan.');
    }

    public function importExcel(Request $request, ExportDeliveryPlanImportService $importer)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls|max:5120']);

        $parsed = $importer->parse($request->file('file'));
        if (app(\App\Services\ExportCustomerStockPolicy::class)->ready()) $parsed['customer_match'] = app(\App\Services\ExportCustomerService::class)->resolve($parsed['customer_name'], $parsed['customer_code'] ?? '');
        return response()->json($parsed);
    }

    public function template()
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'Customer');
        $sheet->setCellValue('A2', 'Batas Tanggal Pengiriman');
        $sheet->setCellValueExplicit('B2', 'YYYY-MM-DD', DataType::TYPE_STRING);
        $sheet->setCellValue('A3', 'Kode Rencana Delivery');
        $sheet->setCellValue('A4', 'Nomor Part');
        $sheet->setCellValue('B4', 'Jumlah PCS');
        $sheet->getStyle('A1:A4')->getFont()->setBold(true);
        $sheet->getStyle('B4')->getFont()->setBold(true);
        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(24);
        $sheet->getStyle('A5:A504')->getNumberFormat()->setFormatCode('@');

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, 'template-rencana-delivery-ekspor.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
