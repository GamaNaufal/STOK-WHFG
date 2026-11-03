<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class InventoryUnificationCheck
{
    public const PART_REFERENCES = ['export_delivery_plan_items', 'export_planned_pallet_items', 'export_fulfillment_boxes',
        'export_stock_boxes', 'export_receiving_batch_items', 'export_picking_issues', 'export_not_full_requests'];

    public static function codeClaims(): array
    {
        $claims = [];
        $add = function ($code, $id) use (&$claims) {
            $key = DeliveryPlanCode::normalize(is_string($code) ? $code : null);
            if ($key !== '' && $id) {
                $claims[$key][(int) $id] = true;
            }
        };
        DB::table('export_delivery_plans')->orderBy('id')->chunkById(500, function ($plans) use ($add) {
            foreach ($plans as $plan) {
                $add($plan->plan_code, $plan->id);
            }
        });
        DB::table('audit_logs')->where('model', 'ExportDeliveryPlan')->orderBy('id')->chunkById(500, function ($logs) use ($add) {
            foreach ($logs as $log) {
                foreach (['old_values', 'new_values'] as $field) {
                    $snapshot = json_decode($log->$field ?? '', true);
                    if (is_array($snapshot)) {
                        $add($snapshot['plan_code'] ?? null, $log->model_id);
                    }
                }
            }
        });
        if (Schema::hasTable('export_delivery_plan_codes')) {
            foreach (DB::table('export_delivery_plan_codes')->get() as $entry) {
                $add($entry->code, $entry->export_delivery_plan_id);
            }
        }

        return array_map(fn ($ids) => array_keys($ids), $claims);
    }

    public static function report(): array
    {
        $merges = [];
        $referenceConflicts = [];
        $activeConflicts = [];
        if (Schema::hasColumn('part_settings', 'scope')) {
            $duplicates = DB::table('part_settings')->select('part_number')->groupBy('part_number')->havingRaw('COUNT(*)>1')->get();
            foreach ($duplicates as $duplicate) {
                // Match the database collation used by the global unique index.
                $group = DB::table('part_settings')->where('part_number', $duplicate->part_number)->orderBy('id')->get();
                $target = $group->firstWhere('scope', 'domestic') ?? $group->first();
                foreach ($group as $part) {
                    if ($part->id === $target->id) {
                        continue;
                    }
                    $merges[] = ['source_id' => $part->id, 'target_id' => $target->id, 'part_number' => $part->part_number,
                        'source_qty_box' => $part->qty_box, 'target_qty_box' => $target->qty_box];
                    foreach (['export_delivery_plan_items' => ['export_delivery_plan_id'], 'export_planned_pallet_items' => ['export_planned_pallet_id', 'pcs_per_box']] as $table => $keys) {
                        if (DB::table($table)->whereIn('part_setting_id', [$part->id, $target->id])->select($keys)->groupBy($keys)->havingRaw('COUNT(*)>1')->exists()) {
                            $referenceConflicts[] = ['table' => $table, 'source_id' => $part->id, 'target_id' => $target->id];
                        }
                    }
                    if ((int) $part->qty_box !== (int) $target->qty_box) {
                        $active = DB::table('export_delivery_plan_items as item')->join('export_delivery_plans as plan', 'plan.id', '=', 'item.export_delivery_plan_id')
                            ->where('item.part_setting_id', $part->id)->whereNull('plan.deleted_at')->whereNotIn('plan.status', ['completed', 'cancelled'])
                            ->where('item.pcs_per_box', '!=', $target->qty_box)->get(['plan.id', 'plan.plan_code']);
                        foreach ($active as $plan) {
                            $activeConflicts[] = ['plan_id' => $plan->id, 'plan_code' => $plan->plan_code, 'part_number' => $part->part_number,
                                'old_capacity' => $part->qty_box, 'shared_capacity' => $target->qty_box];
                        }
                    }
                }
            }
        }
        $domestic = DB::table('box_identities')->select('box_number');
        if (Schema::hasColumn('box_identities', 'origin_scope')) {
            $domestic->where('origin_scope', 'domestic');
        }
        $domestic->union(DB::table('boxes')->select('box_number'));
        $export = DB::table('export_stock_boxes')->select('box_number')->union(DB::table('export_fulfillment_boxes')->whereNull('export_stock_box_id')->select('box_number'));
        $boxConflicts = DB::query()->fromSub($export, 'export_ids')->joinSub($domestic, 'domestic_ids', 'export_ids.box_number', '=', 'domestic_ids.box_number')
            ->distinct()->pluck('export_ids.box_number')->all();
        $codes = DB::table('export_delivery_plans')->select('id', 'plan_code')->orderBy('id')->get();
        $missing = $codes->filter(fn ($plan) => DeliveryPlanCode::normalize($plan->plan_code) === '')->pluck('id')->all();
        $duplicates = array_filter(self::codeClaims(), fn ($ids) => count($ids) > 1);

        return ['part_merges' => $merges, 'part_reference_conflicts' => $referenceConflicts, 'active_capacity_conflicts' => $activeConflicts,
            'box_conflicts' => $boxConflicts, 'missing_plan_codes' => $missing, 'duplicate_plan_codes' => $duplicates];
    }

    public static function blocked(array $report): bool
    {
        return collect($report)->except('part_merges')->contains(fn ($rows) => count($rows) > 0);
    }
}
