<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OperationalDashboardService
{
    public const SCOPES = ['all' => 'Semua', 'domestic' => 'Domestik', 'export' => 'Ekspor'];

    public function filters(Request $request): array
    {
        $data = $request->validate([
            'scope' => ['nullable', Rule::in(array_keys(self::SCOPES))], 'period' => ['nullable', Rule::in(['today', 'week', 'month', 'all'])],
            'start_date' => 'nullable|date_format:Y-m-d', 'end_date' => ['nullable', 'date_format:Y-m-d', ...($request->filled('start_date') ? ['after_or_equal:start_date'] : [])],
            'group_by' => ['nullable', Rule::in(['day', 'week', 'month'])], 'fulfillment_filter' => ['nullable', Rule::in(['all', 'full', 'partial'])],
            'audit_type' => 'nullable|string|max:80', 'audit_action' => 'nullable|string|max:80', 'audit_user' => 'nullable|integer|min:1',
            'audit_page' => 'nullable|integer|min:1',
        ], ['end_date.after_or_equal' => 'Tanggal akhir harus sama dengan atau setelah tanggal mulai.']);

        return array_merge(['scope' => 'all', 'period' => 'week', 'group_by' => 'day', 'fulfillment_filter' => 'all',
            'start_date' => '', 'end_date' => '', 'audit_type' => '', 'audit_action' => '', 'audit_user' => ''], array_filter($data, fn ($v) => $v !== null));
    }

    private function range(array $filters): array
    {
        if ($filters['start_date'] || $filters['end_date']) {
            return [
                $filters['start_date'] ? Carbon::parse($filters['start_date'])->startOfDay() : null,
                $filters['end_date'] ? Carbon::parse($filters['end_date'])->endOfDay() : null, 'Rentang pilihan'];
        }

        return match ($filters['period']) {
            'today' => [now()->startOfDay(), now()->endOfDay(), 'Hari ini'],
            'month' => [now()->startOfMonth(), now()->endOfMonth(), 'Bulan ini'],
            'all' => [null, null, 'Seluruh periode'],
            default => [now()->startOfWeek(), now()->endOfWeek(), 'Minggu ini'],
        };
    }

    private function between(Builder $query, string $column, ?Carbon $start, ?Carbon $end, bool $dateOnly = false): Builder
    {
        if ($dateOnly) {
            return $query->when($start, fn ($q) => $q->whereDate($column, '>=', $start->toDateString()))
                ->when($end, fn ($q) => $q->whereDate($column, '<=', $end->toDateString()));
        }

        return $query->when($start, fn ($q) => $q->where($column, '>=', $start))
            ->when($end, fn ($q) => $q->where($column, '<=', $end));
    }

    private function combined(array $queries): Builder
    {
        $query = clone array_shift($queries);
        foreach ($queries as $other) {
            $query->unionAll(clone $other);
        }

        return DB::query()->fromSub($query, 'records');
    }

    private function rows(Builder $query, bool $forExport, int $limit = 200)
    {
        return ($forExport ? $query : $query->limit($limit))->get();
    }

    private function buckets($rows, string $group): array
    {
        $result = [];
        foreach ($rows as $row) {
            $date = Carbon::parse($row->day);
            $key = match ($group) {
                'week' => $date->startOfWeek()->format('Y-m-d'), 'month' => $date->startOfMonth()->format('Y-m-d'), default => $date->format('Y-m-d')
            };
            $result[$key] = ($result[$key] ?? 0) + (int) $row->quantity;
        }

        return $result;
    }

    private function series(Builder $first, Builder $second, string $dateColumn, string $firstQuantity, string $secondQuantity, array $filters, ?Carbon $start, ?Carbon $end): array
    {
        $sum = fn ($query, $quantity) => $this->buckets((clone $query)->selectRaw("DATE($dateColumn) AS day, SUM($quantity) AS quantity")
            ->whereNotNull($dateColumn)->groupBy('day')->get(), $filters['group_by']);
        $a = $sum($first, $firstQuantity);
        $b = $sum($second, $secondQuantity);
        $keys = array_unique([...array_keys($a), ...array_keys($b)]);
        // Keep short empty periods visible, without allocating years of empty daily buckets.
        if ($start && $end && $start->diffInDays($end) <= 366) {
            for ($day = $start->copy()->startOfDay(); $day->lte($end); $day->addDay()) {
                $bucket = match ($filters['group_by']) {
                    'week' => $day->copy()->startOfWeek(), 'month' => $day->copy()->startOfMonth(), default => $day
                };
                $keys[] = $bucket->format('Y-m-d');
            }
        }
        $keys = array_values(array_unique($keys));
        sort($keys);

        return array_map(fn ($key) => ['label' => Carbon::parse($key)->format($filters['group_by'] === 'month' ? 'M Y' : 'd M Y'),
            'axis_label' => Carbon::parse($key)->format($filters['group_by'] === 'month' ? 'm/Y' : 'd/m/y'),
            'first' => (int) ($a[$key] ?? 0), 'second' => (int) ($b[$key] ?? 0)], $keys);
    }

    private function domain(string $scope, array $filters, ?Carbon $start, ?Carbon $end, Builder $orders): array
    {
        $source = app(OperationalDashboardSources::class);
        $in = $this->between(DB::query()->fromSub($source->movements($scope, 'receipts'), 'flow'), 'occurred_at', $start, $end);
        $out = $this->between(DB::query()->fromSub($source->movements($scope, 'shipments'), 'flow'), 'occurred_at', $start, $end);
        $quantity = $scope === 'export' ? 'box_quantity' : 'pcs_quantity';
        $unit = $scope === 'export' ? 'box' : 'PCS';
        $throughput = $this->series($in, $out, 'occurred_at', $quantity, $quantity, $filters, $start, $end);
        $trend = $this->series($orders, $orders, 'deadline', 'required', 'fulfilled', $filters, $start, $end);
        $hour = DB::getDriverName() === 'sqlite' ? "CAST(strftime('%H',occurred_at) AS INTEGER)" : 'HOUR(occurred_at)';
        $hours = fn ($q) => (clone $q)->selectRaw("$hour AS hour, SUM($quantity) AS quantity")->groupBy('hour')->pluck('quantity', 'hour');
        $a = $hours($in);
        $b = $hours($out);
        $peak = array_map(fn ($h) => ['label' => str_pad((string) $h, 2, '0', STR_PAD_LEFT).':00', 'first' => (int) ($a[$h] ?? 0), 'second' => (int) ($b[$h] ?? 0)], range(0, 23));
        $totals = fn ($q) => (clone $q)->selectRaw('COALESCE(SUM(box_quantity),0) AS boxes, COALESCE(SUM(pcs_quantity),0) AS pcs')->first();

        return ['scope' => $scope, 'label' => self::SCOPES[$scope], 'unit' => $unit, 'inbound' => $totals($in), 'outbound' => $totals($out),
            'charts' => [
                ['key' => 'flow', 'title' => 'Stok masuk dan keluar', 'first_label' => 'Masuk', 'second_label' => 'Keluar', 'rows' => $throughput],
                ['key' => 'hours', 'title' => 'Aktivitas per jam', 'first_label' => 'Masuk', 'second_label' => 'Keluar', 'rows' => $peak],
                ['key' => 'fulfillment', 'title' => 'Rencana dan pemenuhan', 'first_label' => 'Kebutuhan', 'second_label' => 'Ditempatkan / terpenuhi', 'rows' => $trend],
            ]];
    }

    private function audit(array $filters, ?Carbon $start, ?Carbon $end, bool $forExport): array
    {
        $json = fn ($col) => DB::getDriverName() === 'sqlite' ? "CASE WHEN json_valid($col) THEN json_extract($col,'$.scope') ELSE NULL END"
            : "CASE WHEN JSON_VALID($col) THEN JSON_UNQUOTE(JSON_EXTRACT($col,'$.scope')) ELSE NULL END";
        $scope = "CASE WHEN SUBSTR(a.type,1,7)='export_' OR a.model LIKE 'Export%' OR ({$json('a.new_values')})='export' OR ({$json('a.old_values')})='export' THEN 'export'
            WHEN a.type IN ('user_management','profile','role_feature_access','system') THEN 'general' ELSE 'domestic' END";
        $source = DB::table('audit_logs as a')->leftJoin('users as actor', 'actor.id', '=', 'a.user_id')
            ->selectRaw("a.id, a.type, a.action, a.model, a.model_id, a.description, a.user_id, actor.name AS user_name, a.created_at AS occurred_at, $scope AS scope");
        $base = $this->between(DB::query()->fromSub($source, 'audit'), 'occurred_at', $start, $end)
            ->when($filters['scope'] !== 'all', fn ($q) => $q->where('scope', $filters['scope']));
        $options = ['types' => (clone $base)->select('type')->distinct()->orderBy('type')->pluck('type'),
            'actions' => (clone $base)->select('action')->distinct()->orderBy('action')->pluck('action'),
            'users' => (clone $base)->select('user_id', 'user_name')->whereNotNull('user_id')->distinct()->orderBy('user_name')->get()];
        $query = (clone $base)->when($filters['audit_type'] !== '', fn ($q) => $q->where('type', $filters['audit_type']))
            ->when($filters['audit_action'] !== '', fn ($q) => $q->where('action', $filters['audit_action']))
            ->when($filters['audit_user'] !== '', fn ($q) => $q->where('user_id', $filters['audit_user']))->orderByDesc('occurred_at')->orderByDesc('id');

        return [$forExport ? $query->get() : $query->paginate(20, ['*'], 'audit_page', (int) ($filters['audit_page'] ?? 1))->withQueryString(), $options];
    }

    public function build(Request $request, bool $forExport = false): array
    {
        $filters = $this->filters($request);
        [$start,$end,$rangeLabel] = $this->range($filters);
        $scopes = $filters['scope'] === 'all' ? ['domestic', 'export'] : [$filters['scope']];
        $source = app(OperationalDashboardSources::class);
        $stocks = $palletStocks = $orders = $issues = $processing = $domains = $stockTotals = [];
        foreach ($scopes as $scope) {
            $stocks[$scope] = $source->stock($scope);
            $palletStocks[$scope] = $source->stock($scope, true);
            $stock = DB::query()->fromSub(clone $stocks[$scope], 'stock');
            $stockTotals[$scope] = (clone $stock)->selectRaw('COALESCE(SUM(box_quantity),0) AS boxes, COALESCE(SUM(pcs_quantity),0) AS pcs,
                COUNT(DISTINCT pallet_id) AS pallets, COUNT(DISTINCT part_number) AS parts,
                COALESCE(SUM(CASE WHEN pallet_id IS NULL THEN box_quantity ELSE 0 END),0) AS unplaced')->first();
            $stockTotals[$scope]->pallets = DB::query()->fromSub(clone $palletStocks[$scope], 'pallet_stock')->distinct()->count('pallet_id');
            $orders[$scope] = $this->between(DB::query()->fromSub($source->orders($scope), 'orders'), 'deadline', $start, $end, true);
            $domains[$scope] = $this->domain($scope, $filters, $start, $end, clone $orders[$scope]);
            $issues[$scope] = $this->between(DB::query()->fromSub($source->issues($scope), 'issues'), 'occurred_at', $start, $end);
            $processing[$scope] = $this->between(DB::query()->fromSub($source->processing($scope), 'process'), 'started_at', $start, $end);
        }
        $stockQuery = $this->combined(array_values($stocks));
        $stockRows = $this->rows((clone $stockQuery)->orderBy('occurred_at')->orderBy('scope')->orderBy('record_id'), $forExport);
        $palletQuery = $this->combined(array_values($palletStocks))->whereNotNull('pallet_id')->groupBy('scope', 'pallet_id', 'pallet_number', 'warehouse_location')
            ->selectRaw('scope,pallet_id,pallet_number,warehouse_location,SUM(box_quantity) AS boxes,SUM(pcs_quantity) AS pcs,MIN(occurred_at) AS first_received_at');
        $palletRows = $this->rows($palletQuery->orderBy('first_received_at')->orderBy('scope')->orderBy('pallet_id'), $forExport);
        $orderQuery = $this->combined(array_values($orders))->when($filters['fulfillment_filter'] !== 'all', fn ($q) => $q->where('is_full', $filters['fulfillment_filter'] === 'full' ? 1 : 0));
        $orderSummary = (clone $orderQuery)->selectRaw('COUNT(*) AS total, COALESCE(SUM(is_full),0) AS full')->first();
        $orders = $this->rows($orderQuery->orderBy('deadline')->orderBy('scope')->orderBy('record_id'), $forExport);
        $issueQuery = $this->combined(array_values($issues));
        $issueSummary = (clone $issueQuery)->selectRaw('COUNT(*) AS total, COALESCE(SUM(is_open),0) AS open')->first();
        $issues = $this->rows($issueQuery->orderByDesc('occurred_at')->orderBy('scope')->orderByDesc('record_id'), $forExport, 50);
        $processing = $this->rows($this->combined(array_values($processing))->orderByDesc('started_at')->orderBy('scope')->orderByDesc('record_id'), $forExport);
        foreach ($processing as $row) {
            $row->duration_min = $row->started_at && $row->completed_at ? Carbon::parse($row->started_at)->diffInMinutes(Carbon::parse($row->completed_at)) : null;
            $row->scan_duration_min = $row->first_scan && $row->last_scan ? Carbon::parse($row->first_scan)->diffInMinutes(Carbon::parse($row->last_scan)) : null;
        }
        [$auditLogs,$auditOptions] = $this->audit($filters, $start, $end, $forExport);
        $fulfillmentRate = $orderSummary->total ? round($orderSummary->full * 100 / $orderSummary->total, 1) : 0;

        return compact('filters', 'start', 'end', 'rangeLabel', 'domains', 'stockTotals', 'stockRows', 'palletRows', 'orders', 'orderSummary', 'fulfillmentRate', 'issues', 'issueSummary', 'processing', 'auditLogs', 'auditOptions');
    }
}
