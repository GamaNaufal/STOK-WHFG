<?php

namespace App\Http\Controllers;

use App\Models\StockWithdrawal;
use App\Models\StockLocation;
use App\Models\PalletItem;
use App\Models\Pallet;
use App\Models\StockInput;
use Illuminate\Http\Request;

class ReportController extends Controller
{
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
            $query->where('pallet_item_id', function ($q) {
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
     * Export withdrawal report to CSV
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

        $csv = "No,Tanggal,Part Number,Box Diambil,PCS Diambil,Lokasi,Operator,Status,Keterangan\n";

        foreach ($withdrawals as $idx => $withdrawal) {
            $status = $withdrawal->status === 'completed' ? 'Selesai' : 'Dibatalkan';
            $csv .= ($idx + 1) . "," . 
                    $withdrawal->withdrawn_at->format('d/m/Y H:i') . "," . 
                    $withdrawal->part_number . "," . 
                    (int) ceil($withdrawal->box_quantity) . "," . 
                    $withdrawal->pcs_quantity . "," . 
                    $withdrawal->warehouse_location . "," . 
                    $withdrawal->user->name . "," . 
                    $status . "," . 
                    ($withdrawal->notes ?? '') . "\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="withdrawal_report_' . now()->format('Ymd_His') . '.csv"',
        ]);
    }

    /**
     * Export stock input report to CSV
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

        $csv = "No,Pallet Number,Part Number,Box Qty,PCS Qty,Lokasi,Operator,Tanggal Input\n";

        $idx = 1;
        foreach ($stockInputs as $input) {
            $csv .= $idx . "," . 
                    $input->pallet->pallet_number . "," . 
                    $input->palletItem->part_number . "," . 
                    (int)$input->box_quantity . "," . 
                    $input->pcs_quantity . "," . 
                    $input->warehouse_location . "," . 
                    $input->user->name . "," . 
                    $input->stored_at->format('d/m/Y H:i') . "\n";
            $idx++;
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="stock_input_report_' . now()->format('Ymd_His') . '.csv"',
        ]);
    }
}
