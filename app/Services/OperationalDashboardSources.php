<?php

namespace App\Services;

use App\Models\Box;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Read-only sources. Scope remains part of every identity; quantities never mix PCS with boxes. */
class OperationalDashboardSources
{
    public function stock(string $scope, bool $byPallet = false): Builder
    {
        if ($scope === 'export') {
            return DB::table('export_stock_boxes as b')
                ->leftJoin('export_stock_pallets as p', 'p.id', '=', 'b.current_pallet_id')
                ->leftJoin('export_planned_pallets as planned', 'planned.id', '=', 'p.export_planned_pallet_id')
                ->leftJoin('export_fulfillment_boxes as f', 'f.export_stock_box_id', '=', 'b.id')
                ->whereNull('b.deleted_at')->whereIn('b.state', ['received', 'stored'])
                ->selectRaw("'export' AS scope, 'box' AS record_kind, b.id AS record_id, b.box_number, b.part_number,
                    b.pcs_quantity, 1 AS box_quantity, p.id AS pallet_id, planned.position AS pallet_number,
                    p.location_code AS warehouse_location, b.first_received_at AS occurred_at,
                    CASE WHEN planned.ready_at IS NOT NULL THEN 'ready' WHEN f.placed_at IS NOT NULL THEN 'fulfilled'
                        WHEN f.id IS NOT NULL THEN 'reserved' WHEN b.state='received' THEN 'pending' ELSE 'spare' END AS status");
        }
        // Stock totals count shared historical boxes once; pallet contents retain every physical link.
        $positions = DB::table('pallet_boxes as link')->join('pallets as p', 'p.id', '=', 'link.pallet_id')
            ->join('stock_locations as l', 'l.pallet_id', '=', 'p.id')->whereNull('p.deleted_at')
            ->where('l.warehouse_location', '<>', 'Unknown')->groupBy('link.box_id');
        if ($byPallet) {
            $positions->groupBy('p.id')->selectRaw('link.box_id, p.id AS pallet_id, 1 AS position_count');
        } else {
            $positions->selectRaw('link.box_id, MIN(p.id) AS pallet_id, COUNT(DISTINCT p.id) AS position_count');
        }
        $boxes = Box::query()->active()->joinSub($positions, 'position', fn ($join) => $join->on('position.box_id', '=', 'boxes.id'))
            ->join('pallets as p', 'p.id', '=', 'position.pallet_id')->join('stock_locations as l', 'l.pallet_id', '=', 'p.id')
            ->selectRaw("'domestic' AS scope, 'box' AS record_kind, boxes.id AS record_id, boxes.box_number, boxes.part_number,
                boxes.pcs_quantity, 1 AS box_quantity, p.id AS pallet_id, p.pallet_number, l.warehouse_location,
                boxes.created_at AS occurred_at, CASE WHEN position.position_count>1 THEN 'multiple_locations'
                    WHEN boxes.assigned_delivery_order_id IS NULL THEN 'spare' ELSE 'reserved' END AS status")->toBase();
        $legacy = DB::table('pallet_items as i')->join('pallets as p', 'p.id', '=', 'i.pallet_id')
            ->join('stock_locations as l', 'l.pallet_id', '=', 'p.id')->whereNull('p.deleted_at')
            ->where('l.warehouse_location', '<>', 'Unknown')->where('i.pcs_quantity', '>', 0)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('pallet_boxes as link')->whereColumn('link.pallet_id', 'p.id'))
            ->selectRaw("'domestic' AS scope, 'legacy' AS record_kind, i.id AS record_id, NULL AS box_number, i.part_number,
                i.pcs_quantity, i.box_quantity, p.id AS pallet_id, p.pallet_number, l.warehouse_location,
                l.stored_at AS occurred_at, 'legacy' AS status");

        return DB::query()->fromSub($boxes->unionAll($legacy), 'stock');
    }

    public function movements(string $scope, string $kind): Builder
    {
        if ($scope === 'domestic') {
            return $kind === 'receipts'
                ? DB::table('stock_inputs')->whereNull('deleted_at')->selectRaw('id AS record_id, stored_at AS occurred_at, pcs_quantity, box_quantity')
                : DB::table('stock_withdrawals')->whereNull('deleted_at')->where('status', 'completed')->selectRaw('id AS record_id, withdrawn_at AS occurred_at, pcs_quantity, box_quantity');
        }

        return app(WarehouseReportService::class)->query(['scope' => $scope, 'kind' => $kind,
            'start_date' => '', 'end_date' => '', 'q' => '', 'part_number' => '', 'warehouse_location' => '',
            'customer_name' => '', 'user_id' => '', 'status' => ''])->reorder();
    }

    public function orders(string $scope): Builder
    {
        if ($scope === 'export') {
            $ready = DB::table('export_planned_pallets')->groupBy('export_pallet_plan_id')
                ->selectRaw('export_pallet_plan_id, SUM(CASE WHEN ready_at IS NOT NULL THEN 1 ELSE 0 END) AS ready_pallets');

            return DB::query()->fromSub(app(ExportFulfillmentService::class)->overview(true), 'o')
                ->leftJoinSub($ready, 'ready', fn ($j) => $j->on('ready.export_pallet_plan_id', '=', 'o.plan_id'))
                ->where(fn ($q) => $q->whereNull('o.archived_at')->orWhereNotNull('o.completed_at'))
                ->selectRaw("'export' AS scope, o.delivery_id AS record_id, o.plan_id AS report_id, o.plan_code AS reference, o.customer_name,
                    o.delivery_deadline AS deadline, 'box' AS unit, o.required_boxes AS required, o.fulfilled_boxes AS fulfilled,
                    o.reserved_boxes AS reserved, o.pallet_count, COALESCE(ready.ready_pallets,0) AS ready_pallets,
                    CASE WHEN o.fulfillment_status='complete' AND o.required_boxes>0 THEN 1 ELSE 0 END AS is_full,
                    CASE WHEN o.completed_at IS NOT NULL THEN 'shipped' ELSE o.fulfillment_status END AS status");
        }
        $items = DB::table('delivery_order_items')->groupBy('delivery_order_id')
            ->selectRaw('delivery_order_id, SUM(quantity) AS required, SUM(COALESCE(fulfilled_quantity,0)) AS fulfilled,
                SUM(CASE WHEN COALESCE(fulfilled_quantity,0)<quantity THEN 1 ELSE 0 END) AS incomplete');

        return DB::table('delivery_orders as o')->joinSub($items, 'i', fn ($j) => $j->on('i.delivery_order_id', '=', 'o.id'))
            ->whereNull('o.deleted_at')->whereIn('o.status', ['approved', 'processing', 'partial', 'completed'])
            // Split parents are containers; their active leaf deliveries carry the real demand.
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('delivery_orders as child')->whereColumn('child.parent_delivery_order_id', 'o.id')->whereNull('child.deleted_at'))
            ->selectRaw("'domestic' AS scope, o.id AS record_id, o.id AS report_id, o.id AS reference, o.customer_name, o.delivery_date AS deadline,
                'PCS' AS unit, i.required, i.fulfilled, NULL AS reserved, NULL AS pallet_count, NULL AS ready_pallets,
                CASE WHEN i.required>0 AND i.incomplete=0 THEN 1 ELSE 0 END AS is_full, o.status");
    }

    public function issues(string $scope): Builder
    {
        if ($scope === 'export') {
            return DB::table('export_picking_issues as i')->leftJoin('export_planned_pallets as p', 'p.id', '=', 'i.export_planned_pallet_id')
                ->leftJoin('export_pallet_plans as plan', 'plan.id', '=', 'p.export_pallet_plan_id')
                ->leftJoin('export_delivery_plans as o', 'o.id', '=', 'plan.export_delivery_plan_id')
                ->selectRaw("'export' AS scope, i.id AS record_id, o.id AS order_id, o.plan_code AS reference,
                    i.box_number, i.issue_type, i.status, i.notes, i.created_at AS occurred_at,
                    CASE WHEN i.status IN ('returned','input_error') THEN 0 ELSE 1 END AS is_open");
        }

        return DB::table('delivery_issues as i')->leftJoin('delivery_pick_sessions as s', 's.id', '=', 'i.pick_session_id')
            ->selectRaw("'domestic' AS scope, i.id AS record_id, s.delivery_order_id AS order_id, s.delivery_order_id AS reference,
                i.scanned_code AS box_number, i.issue_type, i.status, i.notes, i.created_at AS occurred_at,
                CASE WHEN i.status='pending' THEN 1 ELSE 0 END AS is_open");
    }

    public function processing(string $scope): Builder
    {
        if ($scope === 'export') {
            return DB::table('export_pallet_plans as p')->join('export_delivery_plans as o', 'o.id', '=', 'p.export_delivery_plan_id')
                ->where('p.status', '<>', 'cancelled')->whereNotNull('p.finalized_at')
                ->where(fn ($q) => $q->whereNull('o.deleted_at')->orWhereNotNull('p.completed_at'))
                ->leftJoin('users as actor', 'actor.id', '=', 'p.finalized_by')
                ->selectRaw("'export' AS scope, p.id AS record_id, o.plan_code AS reference, o.customer_name,
                    'Finalisasi terakhir → selesai' AS phase, p.finalized_at AS started_at, p.completed_at,
                    NULL AS first_scan, NULL AS last_scan, actor.name AS operator_name");
        }
        $scans = DB::table('delivery_pick_items')->groupBy('pick_session_id')
            ->selectRaw('pick_session_id, MIN(scanned_at) AS first_scan, MAX(scanned_at) AS last_scan');

        return DB::table('delivery_pick_sessions as s')->leftJoin('delivery_orders as o', 'o.id', '=', 's.delivery_order_id')
            ->leftJoinSub($scans, 'scan', fn ($j) => $j->on('scan.pick_session_id', '=', 's.id'))
            ->leftJoin('users as actor', 'actor.id', '=', 's.created_by')
            ->selectRaw("'domestic' AS scope, s.id AS record_id, s.delivery_order_id AS reference, o.customer_name,
                'Picking → selesai' AS phase, s.started_at, s.completed_at, scan.first_scan, scan.last_scan, actor.name AS operator_name");
    }
}
