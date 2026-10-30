<?php

namespace App\Services;

use App\Models\ExportDeliveryPlan;
use App\Models\ExportPartSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExportDeliveryPlanService
{
    private function requestHash(array $data, User $actor, ?int $id): string
    {
        return hash('sha256', json_encode([
            'actor' => $actor->id, 'target' => $id, 'revision' => $id ? (int) $data['revision'] : null,
            'plan_code' => trim((string) ($data['plan_code'] ?? '')),
            'customer_name' => trim($data['customer_name']), 'delivery_deadline' => $data['delivery_deadline'],
            'source_type' => $data['source_type'], 'source_name' => $data['source_name'] ?? null,
            'duplicate_reason' => trim($data['duplicate_reason'] ?? ''),
            'duplicate_confirmed' => in_array($data['duplicate_confirmed'] ?? null, [true, 1, '1', 'yes', 'on'], true),
            'duplicate_review_token' => $data['duplicate_review_token'] ?? '',
            'items' => array_map(fn ($row) => ['part_number' => $row['part_number'], 'pcs_quantity' => (int) $row['pcs_quantity']], $data['items']),
        ], JSON_THROW_ON_ERROR));
    }

    public function snapshot(ExportDeliveryPlan $plan): array
    {
        return [
            'plan_code' => $plan->plan_code,
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
        return app(ExportInventoryService::class)->transaction(function () use ($data, $actor, $id) {
            $token = $data['request_token'] ?? null;
            $hash = $token ? $this->requestHash($data, $actor, $id) : null;
            if ($token && ($previous = DB::table('export_delivery_plan_requests')->where('request_token', $token)->first())) {
                if (!hash_equals($previous->request_hash, $hash)) {
                    throw ValidationException::withMessages(['request_token' => 'Formulir ini sudah dipakai untuk penyimpanan lain. Muat ulang formulir sebelum menyimpan perubahan.']);
                }
                // Resolve successful retries before checking current revision, master capacity or archive status.
                return ExportDeliveryPlan::withTrashed()->with('items')->findOrFail($previous->export_delivery_plan_id);
            }
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
            $code = trim((string) (array_key_exists('plan_code', $data) ? $data['plan_code'] : $plan->plan_code));
            $duplicate = app(ExportDeliveryPlanDuplicateService::class)->review(
                array_merge($data, ['plan_code' => $code]), $items, $actor, $id);
            $plan->fill([
                'plan_code' => $code !== '' ? $code : null,
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
            if ($id) app(ExportPalletPlanningService::class)->invalidateForDeliveryRevision($plan, $actor);
            AuditService::record('export_delivery_plan', $id ? 'updated' : 'created', 'ExportDeliveryPlan',
                $plan->id, $actor->id, $before, $this->snapshot($plan) + ($duplicate ?? []),
                $duplicate ? 'Rencana delivery ekspor disimpan setelah konfirmasi duplikasi dengan alasan.' : 'Rencana delivery ekspor disimpan.');
            if ($token) {
                DB::table('export_delivery_plan_requests')->insert(['request_token' => $token, 'request_hash' => $hash,
                    'export_delivery_plan_id' => $plan->id, 'created_by' => $actor->id,
                    'action' => $id ? 'update' : 'create', 'created_at' => now()]);
            }

            return $plan;
        });
    }
}
