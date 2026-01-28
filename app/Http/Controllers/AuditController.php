<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        if (!in_array($user->role, ['admin', 'supervisi'], true)) {
            return redirect()->route('dashboard')->with('error', 'Unauthorized.');
        }

        // Get filters from request
        $filters = [
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'type' => $request->input('type'),
            'action' => $request->input('action'),
            'user_id' => $request->input('user_id'),
        ];

        // Get audit logs dengan filters
        $auditLogs = AuditService::getAuditTrail($filters, 50);

        // Get summary statistics
        $startDate = $filters['start_date'] ? $filters['start_date'] . ' 00:00:00' : null;
        $endDate = $filters['end_date'] ? $filters['end_date'] . ' 23:59:59' : null;
        $summary = AuditService::getSummary($startDate, $endDate);

        // Get all users untuk dropdown
        $users = User::orderBy('name')->get();

        return view('audit.index', compact('auditLogs', 'filters', 'summary', 'users'));
    }

    public function export(Request $request)
    {
        $user = auth()->user();
        if (!in_array($user->role, ['admin', 'supervisi'], true)) {
            return redirect()->route('dashboard')->with('error', 'Unauthorized.');
        }

        // Get filters
        $filters = [
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'type' => $request->input('type'),
            'action' => $request->input('action'),
            'user_id' => $request->input('user_id'),
        ];

        // Get all audit logs (no pagination for export)
        $query = AuditLog::query();

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        if (!empty($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date'] . ' 00:00:00');
        }
        if (!empty($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date'] . ' 23:59:59');
        }

        $auditLogs = $query->with('user')->orderBy('created_at', 'desc')->get();

        // Generate CSV
        $csvFileName = 'audit-trail-' . now()->format('Y-m-d-H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$csvFileName\"",
        ];

        $columns = ['ID', 'Tanggal', 'Waktu', 'Tipe', 'Aksi', 'Model', 'Model ID', 'Operator', 'Deskripsi', 'IP Address'];

        $callback = function() use ($auditLogs, $columns) {
            $file = fopen('php://output', 'w');
            
            // Header
            fputcsv($file, $columns);

            // Data
            foreach ($auditLogs as $log) {
                fputcsv($file, [
                    $log->id,
                    $log->created_at->format('d/m/Y'),
                    $log->created_at->format('H:i:s'),
                    ucfirst(str_replace('_', ' ', $log->type)),
                    ucfirst($log->action),
                    $log->model ?? '-',
                    $log->model_id ?? '-',
                    $log->user?->name ?? 'System',
                    $log->description ?? '-',
                    $log->ip_address ?? '-',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
