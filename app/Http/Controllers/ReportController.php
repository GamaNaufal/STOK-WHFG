<?php

namespace App\Http\Controllers;

use App\Exports\OperationalReportsExport;
use App\Models\StockInput;
use App\Models\StockWithdrawal;
use App\Services\OperationalReportService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function operationalReports(Request $request)
    {
        $data = app(OperationalReportService::class)->build($request);
        return view('warehouse.reports.operational', $data);
    }

    public function exportOperationalExcel(Request $request)
    {
        $data = (object) app(OperationalReportService::class)->build($request, true);
        $start = $data->start;
        $end = $data->end;
        $rangeLabel = $data->rangeLabel;

        $summaryRows = [[
            'range' => $rangeLabel,
            'start_date' => optional($start)->format('d M Y'),
            'end_date' => optional($end)->format('d M Y'),
            'fulfillment_rate' => $data->fulfillmentRate . '%',
        ]];

        $exportData = [
            'summary_headings' => ['Range', 'Start Date', 'End Date', 'Fulfillment Rate'],
            'summary_rows' => $summaryRows,
            'current_headings' => ['Box', 'Part', 'PCS', 'Pallet', 'Lokasi', 'Tanggal Masuk'],
            'current_rows' => $data->currentHandling->map(fn ($row) => [
                $row->box_number,
                $row->part_number,
                $row->pcs_quantity,
                $row->pallet_number,
                $row->warehouse_location ?? 'Unknown',
                optional($row->created_at)->format('d M Y H:i'),
            ])->toArray(),
            'matching_headings' => ['Order', 'Customer', 'Delivery Date', 'Required', 'Fulfilled', 'Rate', 'Status'],
            'matching_rows' => $data->matchingReport->map(fn ($row) => [
                $row['order_id'],
                $row['customer'],
                $row['delivery_date'],
                $row['required'],
                $row['fulfilled'],
                $row['rate'] . '%',
                $row['status'],
            ])->toArray(),
            'processing_headings' => ['Session', 'Order', 'Started', 'Completed', 'Duration (min)', 'Scan Duration (min)'],
            'processing_rows' => $data->processingReport->map(fn ($row) => [
                $row['session_id'],
                $row['order_id'],
                $row['started_at'],
                $row['completed_at'],
                $row['duration_min'],
                $row['scan_duration_min'],
            ])->toArray(),
            'throughput_headings' => ['Date', 'Inbound PCS', 'Outbound PCS'],
            'throughput_rows' => collect($data->throughputDays)->map(fn ($row) => [
                $row['date'],
                $row['inbound_pcs'],
                $row['outbound_pcs'],
            ])->toArray(),
            'peak_headings' => ['Hour', 'Inbound PCS', 'Outbound PCS'],
            'peak_rows' => $data->peakHours->map(fn ($row) => [
                $row['hour'],
                $row['inbound_pcs'],
                $row['outbound_pcs'],
            ])->toArray(),
            'delivery_trend_headings' => ['Period', 'Planned Qty', 'Actual Qty'],
            'delivery_trend_rows' => collect($data->deliveryTrend ?? [])->map(fn ($row) => [
                $row['label'] ?? '- ',
                $row['planned_qty'] ?? 0,
                $row['actual_qty'] ?? 0,
            ])->toArray(),
            'mismatch_headings' => ['Order', 'Scanned', 'Issue Type', 'Status', 'Tanggal'],
            'mismatch_rows' => $data->issueList->map(fn ($row) => [
                $row['order_id'],
                $row['scanned_code'],
                $row['issue_type'],
                $row['status'],
                $row['created_at'],
            ])->toArray(),
            'fulfillment_headings' => ['Order', 'Customer', 'Delivery Date', 'Required', 'Fulfilled', 'Rate', 'Status'],
            'fulfillment_rows' => $data->fulfillmentRows->map(fn ($row) => [
                $row['order_id'],
                $row['customer'],
                $row['delivery_date'],
                $row['required'],
                $row['fulfilled'],
                $row['rate'] . '%',
                $row['status'],
            ])->toArray(),
            'audit_headings' => ['Type', 'Action', 'Model', 'Model ID', 'Description', 'User', 'Date'],
            'audit_rows' => $data->auditLogs->map(fn ($log) => [
                $log->type,
                $log->action,
                $log->model,
                $log->model_id,
                $log->description,
                $log->user_id,
                $log->created_at->format('d M Y H:i'),
            ])->toArray(),
        ];

        return Excel::download(
            new OperationalReportsExport($exportData),
            'operational_reports_' . now()->format('Ymd_His') . '.xlsx',
            \Maatwebsite\Excel\Excel::XLSX,
            ['includeCharts' => true]
        );
    }
    /**
     * Show withdrawal history report
     */
    public function withdrawalReport(Request $request)
    {
        $query = StockWithdrawal::with(['user', 'palletItem']);

        // Date range filter
        if ($request->filled('start_date')) {
            $query->whereDate('withdrawn_at', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('withdrawn_at', '<=', $request->input('end_date'));
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Part number filter
        if ($request->filled('part_number')) {
            $query->where('part_number', 'like', '%' . $request->input('part_number') . '%');
        }

        // Operator filter
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        $withdrawals = $query->orderBy('withdrawn_at', 'desc')->paginate(50);

        // Get statistics
        $totalWithdrawals = StockWithdrawal::whereStatus('completed')->count();
        $totalPcsWithdrawn = StockWithdrawal::whereStatus('completed')->sum('pcs_quantity');
        $totalReversed = StockWithdrawal::whereStatus('reversed')->count();

        // Get all users for filter
        $users = \App\Models\User::whereIn('role', ['warehouse_operator', 'admin'])->get();

        return view('warehouse.reports.withdrawal', [
            'withdrawals' => $withdrawals,
            'totalWithdrawals' => $totalWithdrawals,
            'totalPcsWithdrawn' => $totalPcsWithdrawn,
            'totalReversed' => $totalReversed,
            'users' => $users,
            'filters' => [
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
                'status' => $request->input('status'),
                'part_number' => $request->input('part_number'),
                'user_id' => $request->input('user_id'),
            ]
        ]);
    }

    /**
     * Show stock input history report
     */
    public function stockInputReport(Request $request)
    {
        // Get stock inputs from stock_inputs table
        $query = StockInput::with(['pallet.items', 'palletItem', 'user']);

        // Date range filter
        if ($request->filled('start_date')) {
            $query->whereDate('stored_at', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('stored_at', '<=', $request->input('end_date'));
        }

        // Part number filter
        if ($request->filled('part_number')) {
            $query->whereIn('pallet_item_id', function ($q) {
                $q->select('id')
                  ->from('pallet_items')
                  ->where('part_number', 'like', '%' . request()->input('part_number') . '%');
            });
        }

        // Location filter
        if ($request->filled('warehouse_location')) {
            $query->where('warehouse_location', 'like', '%' . request()->input('warehouse_location') . '%');
        }

        $stockInputs = $query->orderBy('stored_at', 'desc')->paginate(50);

        // Get statistics from stock_inputs table
        $totalRecords = StockInput::count();
        $totalPcs = StockInput::sum('pcs_quantity');
        $totalBoxesRaw = StockInput::sum('box_quantity');
        
        // Calculate actual boxes based on PCS average
        if ($totalBoxesRaw > 0 && $totalPcs > 0) {
            $pcsPerBox = $totalPcs / $totalBoxesRaw;
            $totalBoxes = ceil($totalPcs / $pcsPerBox);
        } else {
            $totalBoxes = 0;
        }

        // Get unique warehouse locations from stock_inputs
        $locations = StockInput::distinct('warehouse_location')->pluck('warehouse_location');

        return view('warehouse.reports.stock-input', [
            'stockInputs' => $stockInputs,
            'totalRecords' => $totalRecords,
            'totalItems' => $totalPcs,
            'totalBoxes' => $totalBoxes,
            'locations' => $locations,
            'filters' => [
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
                'part_number' => $request->input('part_number'),
                'warehouse_location' => $request->input('warehouse_location'),
            ]
        ]);
    }

    /**
     * Export withdrawal report to Excel
     */
    public function exportWithdrawalCsv(Request $request)
    {
        $query = StockWithdrawal::with(['user', 'palletItem']);

        if ($request->filled('start_date')) {
            $query->whereDate('withdrawn_at', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('withdrawn_at', '<=', $request->input('end_date'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $withdrawals = $query->orderBy('withdrawn_at', 'desc')->get();

        return Excel::download(
            new \App\Exports\StockWithdrawalExport($withdrawals),
            'stock_withdrawal_report_' . now()->format('Ymd_His') . '.xlsx'
        );
    }

    /**
     * Export stock input report to Excel
     */
    public function exportStockInputCsv(Request $request)
    {
        $query = StockInput::with(['pallet', 'palletItem', 'user']);

        if ($request->filled('start_date')) {
            $query->whereDate('stored_at', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('stored_at', '<=', $request->input('end_date'));
        }

        $stockInputs = $query->orderBy('stored_at', 'desc')->get();

        return Excel::download(
            new \App\Exports\StockInputExport($stockInputs),
            'stock_input_report_' . now()->format('Ymd_His') . '.xlsx'
        );
    }
}
