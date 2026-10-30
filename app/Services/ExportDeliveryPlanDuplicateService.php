<?php

namespace App\Services;

use App\Exceptions\DuplicateExportDeliveryPlan;
use App\Models\{ExportDeliveryPlan, User};

class ExportDeliveryPlanDuplicateService
{
    private function text(?string $value): string
    {
        return preg_replace('/\s+/u', '', mb_strtolower($value ?? '', 'UTF-8'));
    }

    private function items(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            if ((int) $item['pcs_quantity'] > 0) $result[(int) $item['part_setting_id']] = (int) $item['pcs_quantity'];
        }
        ksort($result, SORT_NUMERIC);
        return $result;
    }

    /** Called under the inventory mutex, including for legacy records without a stored fingerprint. */
    public function review(array $data, array $items, User $actor, ?int $id): ?array
    {
        $code = $this->text($data['plan_code'] ?? null);
        $customer = $this->text($data['customer_name']);
        $wanted = $this->items($items);
        $matches = [];
        ExportDeliveryPlan::withTrashed()->with('items')
            ->when($id, fn ($query) => $query->whereKeyNot($id))
            ->whereDate('delivery_deadline', $data['delivery_deadline'])
            ->where('total_pcs', array_sum($wanted))
            ->where(fn ($query) => $query->where('status', 'completed')->orWhere(fn ($active) => $active
                ->whereNull('deleted_at')->whereNotIn('status', ['cancelled', 'completed'])))
            ->chunkById(100, function ($plans) use (&$matches, $code, $customer, $wanted) {
                foreach ($plans as $plan) {
                    if ($this->text($plan->plan_code) !== $code || $this->text($plan->customer_name) !== $customer
                        || $this->items($plan->items->toArray()) !== $wanted) continue;
                    $matches[] = ['id' => $plan->id, 'revision' => $plan->revision,
                        'plan_code' => $plan->plan_code, 'customer_name' => $plan->customer_name,
                        'delivery_deadline' => $plan->delivery_deadline->format('Y-m-d'),
                        'status' => $plan->status === 'completed' ? 'Selesai dikirim' : 'Aktif',
                        'total_pcs' => $plan->total_pcs, 'total_boxes' => $plan->total_boxes,
                        'archived' => $plan->trashed()];
                }
            });
        if (!$matches) return null;
        $proof = hash_hmac('sha256', json_encode([$actor->id, $id, $data['request_token'] ?? '',
            $code, $customer, $data['delivery_deadline'], $wanted, $matches,
        ], JSON_THROW_ON_ERROR), config('app.key'));
        $reason = trim($data['duplicate_reason'] ?? '');
        $confirmed = in_array($data['duplicate_confirmed'] ?? null, [true, 1, '1', 'yes', 'on'], true);
        if (!$confirmed || $reason === '' || mb_strlen($reason) > 500
            || !hash_equals($proof, (string) ($data['duplicate_review_token'] ?? ''))) {
            throw new DuplicateExportDeliveryPlan(['matches' => $matches, 'token' => $proof]);
        }
        return ['duplicate_reason' => $reason, 'duplicate_plan_ids' => implode(', ', array_column($matches, 'id'))];
    }
}
