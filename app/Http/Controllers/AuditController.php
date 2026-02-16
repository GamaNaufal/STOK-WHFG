<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditService;
use App\Exports\AuditTrailExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!in_array($user->role, ['admin', 'supervisi'], true)) {
            return redirect()->route('dashboard')->with('error', 'Unauthorized.');
        }

        $quickFilter = $request->input('quick_filter');

        // Get filters from request
        $filters = [
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'type' => $request->input('type'),
            'action' => $request->input('action'),
            'user_id' => $request->input('user_id'),
            'quick_filter' => $quickFilter,
        ];

        if ($quickFilter === 'box_edit') {
            $filters['type'] = 'other';
            $filters['action'] = 'box_updated_by_admin_warehouse';
        }

        // Get audit logs dengan filters
        $auditLogs = AuditService::getAuditTrail($filters, 50);

        // Get summary statistics
        $startDate = $filters['start_date'] ? $filters['start_date'] . ' 00:00:00' : null;
        $endDate = $filters['end_date'] ? $filters['end_date'] . ' 23:59:59' : null;
        $summary = AuditService::getSummary($startDate, $endDate);

        // Get all users untuk dropdown
        $users = User::orderBy('name')->get();

        return view('supervisor.audit.index', compact('auditLogs', 'filters', 'summary', 'users'));
    }

    public function export(Request $request)
    {
        $user = Auth::user();
        if (!in_array($user->role, ['admin', 'supervisi'], true)) {
            return redirect()->route('dashboard')->with('error', 'Unauthorized.');
        }

        $quickFilter = $request->input('quick_filter');

        // Get filters
        $filters = [
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'type' => $request->input('type'),
            'action' => $request->input('action'),
            'user_id' => $request->input('user_id'),
            'quick_filter' => $quickFilter,
        ];

        if ($quickFilter === 'box_edit') {
            $filters['type'] = 'other';
            $filters['action'] = 'box_updated_by_admin_warehouse';
        }

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

        // Export ke Excel
        $fileName = 'audit-trail-' . now()->format('Y-m-d-H-i-s') . '.xlsx';

        return Excel::download(new AuditTrailExport($auditLogs), $fileName);
    }
}
