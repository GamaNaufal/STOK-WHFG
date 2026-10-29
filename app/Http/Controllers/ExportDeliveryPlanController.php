<?php

namespace App\Http\Controllers;

use App\Models\ExportDeliveryPlan;
use App\Models\ExportPartSetting;
use App\Services\AuditService;
use App\Services\ExportDeliveryPlanImportService;
use App\Services\ExportDeliveryPlanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $parts = ExportPartSetting::orderBy('part_number')->get(['id', 'part_number', 'qty_box']);
        $rows = $plan?->items->map(fn ($item) => $item->only(['part_number', 'pcs_quantity', 'pcs_per_box']))->all() ?? [];

        return view('export.delivery-plans.form', compact('plan', 'parts', 'rows'));
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

        return view('export.delivery-plans.show', ['plan' => $deliveryPlan]);
    }

    private function validated(Request $request, ?ExportDeliveryPlan $plan = null): array
    {
        $sources = ['manual', 'excel'];
        // Preserve provenance when editing a historical plan; new photo input is disabled.
        if ($plan?->source_type === 'photo') {
            $sources[] = 'photo';
        }

        return $request->validate([
            'plan_code' => 'nullable|string|max:100',
            'customer_name' => 'required|string|max:255',
            'delivery_deadline' => 'required|date_format:Y-m-d',
            'source_type' => 'required|in:'.implode(',', $sources),
            'source_name' => 'nullable|string|max:255',
            'reviewed' => ['exclude_if:source_type,manual', 'required', 'accepted'],
            'revision' => $plan ? 'required|integer|min:1' : 'sometimes|integer|min:1',
            'items' => 'required|array|min:1|max:500',
            'items.*.part_number' => 'required|string|max:100|distinct:strict',
            'items.*.pcs_quantity' => 'required|integer|min:1|max:4294967295',
        ], [
            'items.*.part_number.distinct' => 'Nomor part tidak boleh berulang dalam satu rencana.',
            'reviewed.accepted' => 'Periksa dan konfirmasi hasil impor sebelum menyimpan.',
        ]);
    }

    public function store(Request $request, ExportDeliveryPlanService $service)
    {
        $plan = $service->save($this->validated($request), $request->user());

        return redirect()->route('export.delivery-plans.show', $plan)->with('success', 'Rencana delivery berhasil disimpan.');
    }

    public function update(Request $request, ExportDeliveryPlan $deliveryPlan, ExportDeliveryPlanService $service)
    {
        $plan = $service->save($this->validated($request, $deliveryPlan), $request->user(), $deliveryPlan->id);

        return redirect()->route('export.delivery-plans.show', $plan)->with('success', 'Rencana delivery berhasil diperbarui.');
    }

    public function destroy(Request $request, ExportDeliveryPlan $deliveryPlan, ExportDeliveryPlanService $service)
    {
        $data = $request->validate(['revision' => 'required|integer|min:1']);
        DB::transaction(function () use ($deliveryPlan, $data, $request, $service) {
            $locked = ExportDeliveryPlan::with('items')->lockForUpdate()->findOrFail($deliveryPlan->id);
            abort_unless($locked->status === 'draft' && $locked->revision === (int) $data['revision'], 409, 'Rencana sudah berubah. Muat ulang halaman.');
            $before = $service->snapshot($locked);
            if ($locked->palletPlan()->where('status', '!=', 'cancelled')->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'pallet_plan' => 'Batalkan perencanaan pallet terlebih dahulu sebelum mengarsipkan rencana delivery.',
                ]);
            }
            $locked->delete();
            AuditService::record('export_delivery_plan', 'deleted', 'ExportDeliveryPlan', $locked->id,
                $request->user()->id, $before, [], 'Rencana delivery ekspor diarsipkan.');
        });

        return redirect()->route('export.delivery-plans.index')->with('success', 'Rencana delivery berhasil diarsipkan.');
    }

    public function importExcel(Request $request, ExportDeliveryPlanImportService $importer)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls|max:5120']);

        return response()->json($importer->parse($request->file('file')));
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
