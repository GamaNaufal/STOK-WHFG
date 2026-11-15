<?php

namespace App\Http\Controllers;

use App\Exports\ShipmentReportExport;
use App\Models\User;
use App\Services\ShipmentReportService;
use App\Support\ReportPageResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\Cell;

class ShipmentReportController extends Controller
{
    public function index(Request $request, ShipmentReportService $service)
    {
        $filters = $service->filters($request);
        $rows = $service->query($filters)->paginate(15)->withQueryString();
        $summary = $service->summary($filters['scope']);
        $users = User::whereIn('id', $service->lines($filters['scope'])->select('actor_id'))->orderBy('name')->get(['id', 'name']);
        return ReportPageResponse::make($request, 'operator.reports.shipments', compact('filters', 'rows', 'summary', 'users')
            + ['withdrawals' => $rows, 'statuses' => $service::STATUSES]);
    }

    private function event(Request $request, string $shipment, ShipmentReportService $service): array
    {
        abort_unless(preg_match('/^(local-(s[0-9]+|w[0-9]+|b[A-Za-z0-9_-]{1,100})|export-p[0-9]+)$/D', $shipment), 404);
        $filters = $service->filters($request);
        abort_unless(str_starts_with($shipment, 'export-') === ($filters['scope'] === 'export'), 404);
        $row = $service->query($filters, $shipment)->first();
        abort_unless($row, 404);
        return [$filters, $row];
    }

    public function show(Request $request, string $shipment, ShipmentReportService $service)
    {
        [$filters, $row] = $this->event($request, $shipment, $service);
        $request->validate(['list_page' => 'nullable|integer|min:1']);
        $parts = $service->parts($filters, $shipment)->paginate(25, ['*'], 'parts_page')->withQueryString();
        $boxes = $service->selectedLines($filters, $shipment)->orderBy('pallet_number')->orderBy('box_number')->orderBy('id')
            ->paginate(25, ['*'], 'boxes_page')->withQueryString();
        return ReportPageResponse::make($request, 'operator.reports.shipment-detail', compact('filters', 'row', 'parts', 'boxes', 'shipment')
            + ['listPage' => $request->integer('list_page', 1), 'statuses' => $service::STATUSES]);
    }

    public function export(Request $request, ShipmentReportService $service, ?string $shipment = null)
    {
        $filters = $shipment === null ? $service->filters($request) : $this->event($request, $shipment, $service)[0];
        // Generate all sheets within one read transaction, without inventory locks.
        $binder = Cell::getValueBinder();
        try {
            return DB::transaction(fn () => Excel::download(new ShipmentReportExport($filters, $shipment),
                'laporan_pengiriman_'.($filters['scope'] === 'export' ? 'ekspor' : 'lokal').'_'.now()->format('Ymd_His').'.xlsx')->setPrivate());
        } finally {
            // PhpSpreadsheet's binder is process-global, including after exceptions.
            Cell::setValueBinder($binder);
        }
    }
}
