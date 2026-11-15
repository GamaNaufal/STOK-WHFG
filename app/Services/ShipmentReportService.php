<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Read-only shipping events, independent of active stock and the Redo window. */
class ShipmentReportService
{
    public const STATUSES = ['completed' => 'Selesai dikirim', 'reversed' => 'Dikembalikan / Redo',
        'cancelled' => 'Dibatalkan', 'mixed' => 'Perlu diperiksa'];

    public function filters(Request $request): array
    {
        $filters = app(WarehouseReportService::class)->filters($request);
        $request->validate(['status' => ['nullable', Rule::in(['completed', 'shipped', 'reversed', 'redone', 'cancelled', 'mixed'])]]);
        $filters['kind'] = 'shipments';
        $filters['status'] = !$request->exists('status') ? 'completed' : match ($filters['status']) {
            'shipped' => 'completed', 'redone' => 'reversed', default => $filters['status'],
        };
        return $filters;
    }

    private function text(string $expression): string
    {
        return 'CAST('.$expression.' AS '.(in_array(DB::getDriverName(), ['mysql', 'mariadb']) ? 'CHAR' : 'VARCHAR(100)').')';
    }

    private function concat(array $expressions): string
    {
        return DB::getDriverName() === 'sqlite' ? '('.implode(' || ', $expressions).')' : 'CONCAT('.implode(', ', $expressions).')';
    }

    private function select(Builder $query, array $columns): Builder
    {
        return $query->selectRaw(implode(', ', array_map(fn ($key, $value) => $value.' AS '.$key, array_keys($columns), $columns)));
    }

    private function localLines(bool $legacy): Builder
    {
        if ($legacy) {
            $query = DB::table('stock_withdrawals as w')->leftJoin('delivery_pick_sessions as s', 's.id', '=', 'w.pick_session_id')
                ->whereNull('s.id');
            $key = "CASE WHEN w.withdrawal_batch_id IS NOT NULL AND w.withdrawal_batch_id <> '' THEN ".
                $this->concat(["'local-b'", 'w.withdrawal_batch_id']).' ELSE '.$this->concat(["'local-w'", $this->text('w.id')]).' END';
        } else {
            $query = DB::table('delivery_pick_sessions as s')->leftJoin('stock_withdrawals as w', 'w.pick_session_id', '=', 's.id')
                ->where(fn ($q) => $q->whereNotNull('s.completed_at')->orWhereIn('s.completion_status', ['completed', 'redone'])->orWhere('s.status', 'completed'));
            $key = $this->concat(["'local-s'", $this->text('s.id')]);
        }
        $query->leftJoin('delivery_pick_items as i', fn ($join) => $join->on('i.pick_session_id', '=', 's.id')
            ->where(fn ($q) => $q->whereNull('w.id')->orWhereColumn('i.box_id', 'w.box_id')))
            ->leftJoin('delivery_orders as d', 'd.id', '=', 's.delivery_order_id')
            ->leftJoin('boxes as b', 'b.id', '=', DB::raw('COALESCE(w.box_id, i.box_id)'))
            ->leftJoin('pallet_items as pi', 'pi.id', '=', 'w.pallet_item_id')->leftJoin('pallets as p', 'p.id', '=', 'pi.pallet_id')
            ->leftJoin('users as actor', 'actor.id', '=', DB::raw('COALESCE(w.user_id, s.created_by)'))
            ->leftJoin('users as scanner', 'scanner.id', '=', 'i.scanned_by');
        $status = "CASE WHEN s.completion_status = 'redone' OR w.status = 'reversed' THEN 'reversed'
            WHEN w.deleted_at IS NOT NULL OR w.status = 'cancelled' THEN 'cancelled' ELSE 'completed' END";
        return $this->select($query, [
            'event_key' => $key, 'id' => 'COALESCE(w.id, i.id, s.id)', 'order_id' => 'd.id', 'session_id' => 's.id',
            'reference' => $legacy ? "COALESCE(w.withdrawal_batch_id, '')" : $this->concat(["'DO #'", $this->text('d.id')]),
            'customer_name' => "COALESCE(d.customer_name, '')", 'delivery_deadline' => 'd.delivery_date',
            'occurred_at' => 'COALESCE(s.completed_at, w.withdrawn_at)', 'started_at' => 's.started_at',
            'changed_at' => "CASE WHEN $status = 'reversed' THEN COALESCE(w.updated_at, s.updated_at) ELSE NULL END",
            'status' => $status, 'legacy' => $legacy ? '1' : '0',
            'box_number' => "COALESCE(b.box_number, '')", 'part_number' => "COALESCE(w.part_number, i.part_number, '')",
            'identity_cycle' => 'b.identity_cycle',
            'document_number' => "COALESCE(w.part_number, i.part_number, '')", 'pcs_quantity' => 'COALESCE(w.pcs_quantity, i.pcs_quantity, 0)',
            'box_quantity' => 'CASE WHEN w.id IS NOT NULL THEN w.box_quantity WHEN i.id IS NOT NULL THEN 1 ELSE 0 END',
            'pallet_number' => "COALESCE(p.pallet_number, '')", 'location' => "COALESCE(w.warehouse_location, '')",
            'actor_id' => 'actor.id', 'operator_name' => "COALESCE(actor.name, '')", 'scanned_at' => 'i.scanned_at',
            'scanner_name' => "COALESCE(scanner.name, '')", 'received_at' => 'NULL', 'placed_at' => 'NULL', 'placer_name' => "''",
            'notes' => "COALESCE(w.notes, '')", 'delivery_notes' => "COALESCE(d.notes, '')", 'approval_notes' => "COALESCE(s.approval_notes, '')",
        ]);
    }

    private function exportLines(): Builder
    {
        $query = DB::table('export_pallet_plans as plan')->join('export_delivery_plans as d', 'd.id', '=', 'plan.export_delivery_plan_id')
            ->leftJoin('export_planned_pallets as p', 'p.export_pallet_plan_id', '=', 'plan.id')
            ->leftJoin('export_planned_pallet_items as i', 'i.export_planned_pallet_id', '=', 'p.id')
            ->leftJoin('export_fulfillment_boxes as b', 'b.export_planned_pallet_item_id', '=', 'i.id')
            ->leftJoin('export_delivery_plan_items as di', fn ($j) => $j->on('di.export_delivery_plan_id', '=', 'd.id')->on('di.part_setting_id', '=', 'i.part_setting_id'))
            ->leftJoin('users as actor', 'actor.id', '=', 'plan.completed_by')
            ->leftJoin('users as scanner', 'scanner.id', '=', 'b.picking_verified_by')
            ->leftJoin('users as placer', 'placer.id', '=', 'b.placed_by')->whereNotNull('plan.completed_at');
        return $this->select($query, [
            'event_key' => $this->concat(["'export-p'", $this->text('plan.id')]), 'id' => 'COALESCE(b.id, plan.id)',
            'order_id' => 'd.id', 'session_id' => 'plan.id', 'reference' => 'd.plan_code', 'customer_name' => 'd.customer_name',
            'delivery_deadline' => 'd.delivery_deadline', 'occurred_at' => 'plan.completed_at', 'started_at' => 'plan.finalized_at', 'changed_at' => 'NULL',
            'status' => "'completed'", 'legacy' => '0', 'box_number' => "COALESCE(b.box_number, '')",
            'identity_cycle' => 'b.identity_cycle',
            'part_number' => "COALESCE(i.part_number, '')", 'document_number' => "COALESCE(di.document_part_number, di.part_number, i.part_number, '')",
            'pcs_quantity' => 'COALESCE(b.pcs_quantity, 0)', 'box_quantity' => 'CASE WHEN b.id IS NULL THEN 0 ELSE 1 END',
            'pallet_number' => 'COALESCE('.$this->text('p.position').", '')", 'location' => "COALESCE(p.location_code, '')",
            'actor_id' => 'actor.id', 'operator_name' => "COALESCE(actor.name, '')", 'scanned_at' => 'b.picking_verified_at',
            'scanner_name' => "COALESCE(scanner.name, '')", 'received_at' => 'b.first_received_at', 'placed_at' => 'b.placed_at',
            'placer_name' => "COALESCE(placer.name, '')", 'notes' => "''", 'delivery_notes' => "COALESCE(d.source_name, '')", 'approval_notes' => "''",
        ]);
    }

    public function lines(string $scope): Builder
    {
        $base = $scope === 'export' ? $this->exportLines() : $this->localLines(false)->unionAll($this->localLines(true));
        return DB::query()->fromSub($base, 'shipment_lines');
    }

    private function identity(string $column): string
    {
        return match (DB::getDriverName()) {
            'mysql', 'mariadb', 'sqlite' => "HEX($column)",
            'pgsql' => "encode(convert_to($column, 'UTF8'), 'hex')",
            default => "CONVERT(VARCHAR(MAX), CONVERT(VARBINARY(MAX), $column), 2)",
        };
    }

    public function events(string $scope): Builder
    {
        return DB::query()->fromSub($this->lines($scope), 'l')->groupBy('event_key')
            ->select('event_key')->selectRaw("MIN(id) AS id, MIN(order_id) AS order_id, MIN(session_id) AS session_id,
                MIN(reference) AS reference, MIN(customer_name) AS customer_name, MIN(delivery_deadline) AS delivery_deadline,
                MIN(occurred_at) AS occurred_at, MIN(started_at) AS started_at, MAX(changed_at) AS changed_at,
                CASE WHEN MIN(status) = MAX(status) THEN MIN(status) ELSE 'mixed' END AS status, MAX(legacy) AS legacy,
                SUM(pcs_quantity) AS pcs_quantity, SUM(box_quantity) AS box_quantity,
                SUM(CASE WHEN status = 'completed' THEN pcs_quantity ELSE 0 END) AS shipped_pcs,
                SUM(CASE WHEN status = 'completed' THEN box_quantity ELSE 0 END) AS shipped_boxes,
                COUNT(DISTINCT CASE WHEN part_number <> '' THEN ".$this->identity('part_number')." ELSE NULL END) AS part_count,
                COUNT(DISTINCT CASE WHEN pallet_number <> '' THEN pallet_number ELSE NULL END) AS pallet_count,
                CASE WHEN COUNT(DISTINCT actor_id) > 1 THEN 'Beberapa operator' ELSE MIN(operator_name) END AS operator_name,
                MAX(notes) AS notes, MIN(delivery_notes) AS delivery_notes, MIN(approval_notes) AS approval_notes");
    }

    public function query(array $filters, ?string $event = null): Builder
    {
        $query = DB::query()->fromSub($this->events($filters['scope']), 'events');
        if ($event !== null) return $query->where('event_key', $event);
        foreach (['start_date' => '>=', 'end_date' => '<='] as $field => $operator) {
            if ($filters[$field] !== '') $query->whereDate('occurred_at', $operator, $filters[$field]);
        }
        if ($filters['status'] !== '') $query->where('status', $filters['status']);
        if ($filters['customer_name'] !== '') $query->where('customer_name', 'like', '%'.$filters['customer_name'].'%');
        $lineFilters = array_intersect_key($filters, array_flip(['part_number', 'warehouse_location', 'user_id']));
        foreach ($lineFilters as $field => $value) {
            if ($value === '') continue;
            $column = ['part_number' => 'part_number', 'warehouse_location' => 'location', 'user_id' => 'actor_id'][$field];
            $lines = $this->lines($filters['scope'])->select('event_key');
            $field === 'user_id' ? $lines->where($column, $value) : $lines->where($column, 'like', '%'.$value.'%');
            $query->whereIn('event_key', $lines);
        }
        if ($filters['q'] !== '') {
            $needle = '%'.$filters['q'].'%';
            $lines = $this->lines($filters['scope'])->select('event_key')->where(fn ($q) => $q
                ->where('box_number', 'like', $needle)->orWhere('part_number', 'like', $needle)
                ->orWhere('document_number', 'like', $needle)->orWhere('location', 'like', $needle)->orWhere('notes', 'like', $needle));
            $query->where(fn ($q) => $q->where('reference', 'like', $needle)->orWhere('customer_name', 'like', $needle)
                ->orWhere('event_key', 'like', $needle)->orWhereIn('event_key', $lines));
        }
        return $query->orderByDesc('occurred_at')->orderByDesc('event_key');
    }

    public function selectedLines(array $filters, ?string $event = null): Builder
    {
        return $this->lines($filters['scope'])->whereIn('event_key', $this->query($filters, $event)->reorder()->select('event_key'));
    }

    public function parts(array $filters, ?string $event = null): Builder
    {
        return DB::query()->fromSub($this->selectedLines($filters, $event), 'l')->where('part_number', '<>', '')
            ->groupBy('event_key', 'status')->groupByRaw($this->identity('part_number').', '.$this->identity('document_number'))
            ->select('event_key', 'status')->selectRaw('MIN(reference) AS reference, MIN(customer_name) AS customer_name,
                MIN(occurred_at) AS occurred_at, MIN(part_number) AS part_number, MIN(document_number) AS document_number,
                SUM(box_quantity) AS box_quantity, SUM(pcs_quantity) AS pcs_quantity')
            ->orderBy('event_key')->orderBy('part_number')->orderBy('document_number')->orderBy('status');
    }

    public function summary(string $scope): object
    {
        return DB::query()->fromSub($this->events($scope), 'events')->selectRaw("COUNT(*) AS total_events,
            COALESCE(SUM(CASE WHEN status = 'completed' AND legacy = 0 THEN 1 ELSE 0 END), 0) AS completed_deliveries,
            COALESCE(SUM(shipped_pcs), 0) AS shipped_pcs, COALESCE(SUM(shipped_boxes), 0) AS shipped_boxes,
            COALESCE(SUM(CASE WHEN status = 'reversed' THEN 1 ELSE 0 END), 0) AS redo_events,
            COALESCE(SUM(CASE WHEN legacy = 1 THEN 1 ELSE 0 END), 0) AS legacy_events")->first();
    }

    public static function reference(object $row): string
    {
        return ($row->reference ?? '') !== '' ? $row->reference : 'Catatan lama #'.($row->id ?? $row->event_key);
    }
}
