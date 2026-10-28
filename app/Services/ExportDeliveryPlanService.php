<?php

namespace App\Services;

use App\Models\ExportDeliveryPlan;
use App\Models\ExportPartSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExportDeliveryPlanService
{
    public function snapshot(ExportDeliveryPlan $plan): array
    {
        return [
            'customer_name' => $plan->customer_name,
            'delivery_deadline' => $plan->delivery_deadline->format('Y-m-d'),
            'revision' => $plan->revision, 'total_pcs' => $plan->total_pcs, 'total_boxes' => $plan->total_boxes,
            'source_type' => $plan->source_type, 'source_name' => $plan->source_name,
            'items' => $plan->items->map(fn ($item) => $item->only([
                'part_setting_id', 'part_number', 'pcs_quantity', 'pcs_per_box', 'box_quantity',
            ]))->all(),
        ];
    }

    public function save(array $data, User $actor, ?int $id = null): ExportDeliveryPlan
    {
        return DB::transaction(function () use ($data, $actor, $id) {
            $plan = $id ? ExportDeliveryPlan::with('items')->lockForUpdate()->findOrFail($id) : new ExportDeliveryPlan;
            if ($id && ($plan->status !== 'draft' || $plan->revision !== (int) $data['revision'])) {
                throw ValidationException::withMessages(['revision' => 'Rencana sudah berubah. Muat ulang halaman sebelum menyimpan.']);
            }
            $before = $id ? $this->snapshot($plan) : [];
            $existing = $id ? $plan->items->keyBy('part_setting_id') : collect();
            $parts = ExportPartSetting::whereIn('part_number', array_column($data['items'], 'part_number'))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('part_number');
            $items = [];
            $errors = [];
            foreach ($data['items'] as $index => $row) {
                $part = $parts->get($row['part_number']);
                if (! $part || (string) $part->part_number !== $row['part_number']) {
                    $errors["items.{$index}.part_number"] = 'Nomor part belum terdaftar pada master ekspor.';
                    continue;
                }
                // Existing rows keep their capacity snapshot when the master changes.
                $capacity = (int) ($existing->get($part->id)?->pcs_per_box ?? $part->qty_box);
                $quantity = (int) $row['pcs_quantity'];
                if ($capacity < 1 || $quantity % $capacity !== 0) {
                    $errors["items.{$index}.pcs_quantity"] = "Jumlah PCS harus kelipatan {$capacity}. Box tidak penuh belum didukung.";
                    continue;
                }
                $items[] = ['part_setting_id' => $part->id, 'part_number' => $part->part_number,
                    'position' => $index + 1, 'pcs_quantity' => $quantity,
                    'pcs_per_box' => $capacity, 'box_quantity' => intdiv($quantity, $capacity)];
            }
            if ($errors) {
                throw ValidationException::withMessages($errors);
            }
            $plan->fill([
                'customer_name' => trim($data['customer_name']), 'delivery_deadline' => $data['delivery_deadline'],
                'status' => 'draft', 'revision' => $id ? $plan->revision + 1 : 1,
                'source_type' => $data['source_type'], 'source_name' => $data['source_name'] ?? null,
                'total_pcs' => array_sum(array_column($items, 'pcs_quantity')),
                'total_boxes' => array_sum(array_column($items, 'box_quantity')),
                'created_by' => $id ? $plan->created_by : $actor->id, 'updated_by' => $actor->id,
            ])->save();
            $plan->items()->whereNotIn('part_setting_id', array_column($items, 'part_setting_id'))->delete();
            foreach ($items as $item) {
                $plan->items()->updateOrCreate(['part_setting_id' => $item['part_setting_id']], $item);
            }
            $plan->load('items');
            AuditService::record('export_delivery_plan', $id ? 'updated' : 'created', 'ExportDeliveryPlan',
                $plan->id, $actor->id, $before, $this->snapshot($plan), 'Rencana delivery ekspor disimpan.');

            return $plan;
        });
    }
}
