<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Box;
use App\Models\DeliveryIssue;
use App\Models\DeliveryOrder;
use App\Models\DeliveryPickSession;
use App\Models\PalletItem;
use App\Models\StockInput;
use App\Models\StockWithdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class OperationalReportService
{
    private function resolveDateRange(Request $request): array
    {
        $start = null;
        $end = null;
        $label = 'All Time';

        if ($request->filled('start_date') || $request->filled('end_date')) {
            $start = $request->filled('start_date') ? Carbon::parse($request->input('start_date'))->startOfDay() : null;
            $end = $request->filled('end_date') ? Carbon::parse($request->input('end_date'))->endOfDay() : null;
            $label = 'Custom Range';
        } else {
            $period = $request->input('period', 'week');
            if ($period === 'today') {
                $start = now()->startOfDay();
                $end = now()->endOfDay();
                $label = 'Today';
            } elseif ($period === 'week') {
                $start = now()->startOfWeek();
                $end = now()->endOfWeek();
                $label = 'This Week';
            } elseif ($period === 'month') {
                $start = now()->startOfMonth();
                $end = now()->endOfMonth();
                $label = 'This Month';
            } elseif ($period === 'all') {
                $label = 'All Time';
            }
        }

        return [$start, $end, $label];
    }

    public function build(Request $request, bool $forExport = false): array
    {
        [$start, $end, $rangeLabel] = $this->resolveDateRange($request);

        $currentHandling = Box::query()
            ->where('is_withdrawn', false)
            ->join('pallet_boxes', 'boxes.id', '=', 'pallet_boxes.box_id')
            ->join('pallets', 'pallets.id', '=', 'pallet_boxes.pallet_id')
            ->leftJoin('stock_locations', 'stock_locations.pallet_id', '=', 'pallets.id')
            ->where(function ($q) {
                $q->whereNull('stock_locations.warehouse_location')
                  ->orWhere('stock_locations.warehouse_location', '!=', 'Unknown');
            })
            ->select('boxes.box_number', 'boxes.part_number', 'boxes.pcs_quantity', 'pallets.pallet_number', 'stock_locations.warehouse_location', 'boxes.created_at')
            ->orderBy('boxes.created_at', 'asc')
            ->limit(200)
            ->get();

        $matchingOrders = DeliveryOrder::with('items')
            ->whereIn('status', ['approved', 'processing', 'completed'])
            ->when($start, fn ($q) => $q->whereDate('delivery_date', '>=', $start->toDateString()))
            ->when($end, fn ($q) => $q->whereDate('delivery_date', '<=', $end->toDateString()))
            ->orderBy('delivery_date', 'asc')
            ->get();

        $matchingReport = $matchingOrders->map(function ($order) {
            $required = (int) $order->items->sum('quantity');
            $fulfilled = (int) $order->items->sum('fulfilled_quantity');
            $rate = $required > 0 ? round(($fulfilled / $required) * 100, 1) : 0;
            $isFull = $order->items->every(fn ($item) => $item->fulfilled_quantity >= $item->quantity);

            return [
                'order_id' => $order->id,
                'customer' => $order->customer_name,
                'delivery_date' => $order->delivery_date?->format('d M Y'),
                'required' => $required,
                'fulfilled' => $fulfilled,
                'rate' => $rate,
                'status' => $isFull ? 'Full' : 'Partial',
            ];
        });

        $pickSessions = DeliveryPickSession::with('items')
            ->when($start, fn ($q) => $q->where('started_at', '>=', $start))
            ->when($end, fn ($q) => $q->where('started_at', '<=', $end))
            ->orderBy('started_at', 'desc')
            ->get();

        $processingReport = $pickSessions->map(function ($session) {
            $duration = $session->started_at && $session->completed_at
                ? $session->started_at->diffInMinutes($session->completed_at)
                : null;

            $scannedTimes = $session->items->pluck('scanned_at')->filter();
            $scanDuration = null;
            if ($scannedTimes->isNotEmpty()) {
                $first = $scannedTimes->min();
                $last = $scannedTimes->max();
                $scanDuration = Carbon::parse($first)->diffInMinutes(Carbon::parse($last));
            }

            return [
                'session_id' => $session->id,
                'order_id' => $session->delivery_order_id,
                'started_at' => optional($session->started_at)->format('d M Y H:i'),
                'completed_at' => optional($session->completed_at)->format('d M Y H:i'),
                'duration_min' => $duration,
                'scan_duration_min' => $scanDuration,
            ];
        });

        $inboundQuery = StockInput::query();
        $outboundQuery = StockWithdrawal::query()->where('status', 'completed');

        if ($start) {
            $inboundQuery->where('stored_at', '>=', $start);
            $outboundQuery->where('withdrawn_at', '>=', $start);
        }
        if ($end) {
            $inboundQuery->where('stored_at', '<=', $end);
            $outboundQuery->where('withdrawn_at', '<=', $end);
        }

        $inboundByDay = $inboundQuery->clone()
            ->select(DB::raw('DATE(stored_at) as day'), DB::raw('SUM(pcs_quantity) as pcs'))
            ->groupBy('day')
            ->pluck('pcs', 'day');

        $outboundByDay = $outboundQuery->clone()
            ->select(DB::raw('DATE(withdrawn_at) as day'), DB::raw('SUM(pcs_quantity) as pcs'))
            ->groupBy('day')
            ->pluck('pcs', 'day');

        $throughputDays = [];
        if ($start && $end) {
            $period = Carbon::parse($start)->daysUntil($end->copy()->addDay());
            foreach ($period as $day) {
                $key = $day->format('Y-m-d');
                $throughputDays[] = [
                    'date' => $day->format('d M Y'),
                    'inbound_pcs' => (int) ($inboundByDay[$key] ?? 0),
                    'outbound_pcs' => (int) ($outboundByDay[$key] ?? 0),
                ];
            }
        }

        $inboundHours = $inboundQuery->clone()
            ->select(DB::raw('HOUR(stored_at) as hour'), DB::raw('SUM(pcs_quantity) as pcs'))
            ->groupBy('hour')
            ->pluck('pcs', 'hour');
        $outboundHours = $outboundQuery->clone()
            ->select(DB::raw('HOUR(withdrawn_at) as hour'), DB::raw('SUM(pcs_quantity) as pcs'))
            ->groupBy('hour')
            ->pluck('pcs', 'hour');

        $peakHours = collect(range(0, 23))->map(function ($hour) use ($inboundHours, $outboundHours) {
            return [
                'hour' => str_pad((string) $hour, 2, '0', STR_PAD_LEFT) . ':00',
                'inbound_pcs' => (int) ($inboundHours[$hour] ?? 0),
                'outbound_pcs' => (int) ($outboundHours[$hour] ?? 0),
            ];
        });

        $issuesBaseQuery = DeliveryIssue::with(['session'])
            ->when($start, fn ($q) => $q->where('created_at', '>=', $start))
            ->when($end, fn ($q) => $q->where('created_at', '<=', $end));

        $issueSummary = $issuesBaseQuery->clone()
            ->select('issue_type', DB::raw('COUNT(*) as total'))
            ->groupBy('issue_type')
            ->get();

        $issueList = $issuesBaseQuery->clone()
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($issue) {
                return [
                    'order_id' => $issue->session?->delivery_order_id,
                    'scanned_code' => $issue->scanned_code,
                    'issue_type' => $issue->issue_type,
                    'status' => $issue->status,
                    'notes' => $issue->notes,
                    'created_at' => $issue->created_at->format('d M Y H:i'),
                ];
            });

        $fulfillmentOrders = DeliveryOrder::with('items')
            ->whereIn('status', ['approved', 'processing', 'completed'])
            ->when($start, fn ($q) => $q->whereDate('delivery_date', '>=', $start->toDateString()))
            ->when($end, fn ($q) => $q->whereDate('delivery_date', '<=', $end->toDateString()))
            ->orderBy('delivery_date', 'asc')
            ->get();

        $fulfillmentRows = $fulfillmentOrders->map(function ($order) {
            $required = (int) $order->items->sum('quantity');
            $fulfilled = (int) $order->items->sum('fulfilled_quantity');
            $rate = $required > 0 ? round(($fulfilled / $required) * 100, 1) : 0;
            $isFull = $order->items->every(fn ($item) => $item->fulfilled_quantity >= $item->quantity);

            return [
                'order_id' => $order->id,
                'customer' => $order->customer_name,
                'delivery_date' => $order->delivery_date?->format('d M Y'),
                'required' => $required,
                'fulfilled' => $fulfilled,
                'rate' => $rate,
                'status' => $isFull ? 'Full' : 'Partial',
            ];
        });

        $fullCount = $fulfillmentRows->where('status', 'Full')->count();
        $totalCount = $fulfillmentRows->count();
        $fulfillmentRate = $totalCount > 0 ? round(($fullCount / $totalCount) * 100, 1) : 0;

        $auditBaseQuery = AuditLog::query()
            ->when($start, fn ($q) => $q->where('created_at', '>=', $start))
            ->when($end, fn ($q) => $q->where('created_at', '<=', $end));

        $auditTypes = $auditBaseQuery->clone()->select('type')->distinct()->orderBy('type')->pluck('type');
        $auditActions = $auditBaseQuery->clone()->select('action')->distinct()->orderBy('action')->pluck('action');
        $auditUsers = $auditBaseQuery->clone()
            ->select('user_id')
            ->distinct()
            ->whereNotNull('user_id')
            ->orderBy('user_id')
            ->pluck('user_id');

        $auditUserOptions = \App\Models\User::whereIn('id', $auditUsers->all())
            ->orderBy('name')
            ->get(['id', 'name']);

        $auditQuery = $auditBaseQuery->clone()->with('user');

        if ($request->filled('audit_type')) {
            $auditQuery->where('type', $request->input('audit_type'));
        }
        if ($request->filled('audit_action')) {
            $auditQuery->where('action', $request->input('audit_action'));
        }
        if ($request->filled('audit_user')) {
            $auditQuery->where('user_id', $request->input('audit_user'));
        }

        $auditSummary = $auditQuery->clone()
            ->select('type', DB::raw('COUNT(*) as total'))
            ->groupBy('type')
            ->orderBy('total', 'desc')
            ->get();

        $auditLogs = $auditQuery->clone()->orderBy('created_at', 'desc');
        if ($forExport) {
            $auditLogs = $auditLogs->get();
        } else {
            $auditLogs = $auditLogs->paginate(20)->withQueryString();
        }

        return [
            'rangeLabel' => $rangeLabel,
            'start' => $start,
            'end' => $end,
            'currentHandling' => $currentHandling,
            'matchingReport' => $matchingReport,
            'processingReport' => $processingReport,
            'throughputDays' => $throughputDays,
            'peakHours' => $peakHours,
            'issueSummary' => $issueSummary,
            'issueList' => $issueList,
            'fulfillmentRows' => $fulfillmentRows,
            'fulfillmentRate' => $fulfillmentRate,
            'auditSummary' => $auditSummary,
            'auditLogs' => $auditLogs,
            'auditTypes' => $auditTypes,
            'auditActions' => $auditActions,
            'auditUsers' => $auditUserOptions,
        ];
    }
}
