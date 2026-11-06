<?php

namespace App\Services;

use App\Models\Box;
use App\Models\DeliveryAnnouncement;
use App\Models\DeliveryAnnouncementTarget;
use App\Models\DeliveryOrder;
use App\Models\ExportDeliveryPlan;
use App\Models\ExportFulfillmentBox;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryAnnouncementService
{
    public const LOCAL_ACTIVE = ['approved', 'processing', 'partial'];

    public static function normalize(string $value): string
    {
        return mb_strtolower(trim($value), 'UTF-8');
    }

    private function transaction(callable $callback): mixed
    {
        return DB::transaction(function () use ($callback) {
            DB::table('delivery_announcement_locks')->where('id', 1)->lockForUpdate()->first();
            return $callback();
        }, 3);
    }

    public function create(array $data, User $actor): DeliveryAnnouncement
    {
        return $this->transaction(function () use ($data, $actor) {
            $rule = DeliveryAnnouncement::create($data + [
                'customer_key' => self::normalize($data['customer_name']),
                'part_key' => self::normalize($data['part_number']),
                'created_by' => $actor->id,
                'enabled' => true,
            ]);

            $local = DeliveryOrder::whereIn('status', self::LOCAL_ACTIVE)->get()->map(fn ($order) => [
                'order' => $order, 'active_at' => $order->updated_at,
            ]);
            $export = ExportDeliveryPlan::whereHas('palletPlan', fn ($plan) => $plan->where('status', 'final')
                ->whereNull('completed_at')->whereColumn('delivery_revision', 'export_delivery_plans.revision'))
                ->with('palletPlan')->get()->map(fn ($order) => [
                    'order' => $order, 'active_at' => $order->palletPlan->finalized_at ?? $order->updated_at,
                ]);

            foreach ($local->concat($export)->sortBy('active_at') as $row) {
                $order = $row['order'];
                if ($rule->customer_key === self::normalize($order->customer_name)) {
                    $this->syncOrder($order, [], [], $rule);
                }
            }

            AuditService::record('delivery_announcement', 'created', 'DeliveryAnnouncement', $rule->id, $actor->id,
                [], $rule->only(['customer_name', 'part_number', 'message', 'quota_type', 'quota', 'enabled']));

            return $rule;
        });
    }

    public function update(int $id, array $data, User $actor): void
    {
        $this->transaction(function () use ($id, $data, $actor) {
            $rule = DeliveryAnnouncement::whereKey($id)->lockForUpdate()->firstOrFail();
            $targets = $this->targets($rule);
            $rule->setRelation('targets', $targets);
            $usage = $rule->usage();
            if (isset($data['quota']) && $data['quota'] < $usage['used'] + $usage['reserved']) {
                throw ValidationException::withMessages(['quota' => 'Batas baru harus mencakup kuota yang sudah dipakai dan direservasi.']);
            }

            $before = $rule->only(array_keys($data));
            $rule->update($data);
            $this->rebalance($rule, $targets);
            AuditService::record('delivery_announcement', 'updated', 'DeliveryAnnouncement', $rule->id, $actor->id, $before, $data);
        });
    }

    /** Call inside the delivery transaction after assignment, activation or a status change. */
    public function sync(DeliveryOrder|ExportDeliveryPlan $order, array $shipped = [], array $reversed = []): void
    {
        $this->syncOrders([$order], $shipped, $reversed);
    }

    /** The optional dispatch and return lists apply to a single order. */
    public function syncOrders(iterable $orders, array $shipped = [], array $reversed = []): void
    {
        $this->transaction(function () use ($orders, $shipped, $reversed) {
            foreach ($orders as $order) {
                $this->syncOrder($order, $shipped, $reversed);
            }
        });
    }

    private function targets(DeliveryAnnouncement $rule): Collection
    {
        return $rule->targets()->orderBy('id')->lockForUpdate()->get();
    }

    private function syncOrder(DeliveryOrder|ExportDeliveryPlan $order, array $shipped, array $reversed, ?DeliveryAnnouncement $newRule = null): void
    {
        $export = $order instanceof ExportDeliveryPlan;
        $scope = $export ? 'export' : 'domestic';
        $customer = self::normalize($order->customer_name);
        $rules = $newRule ? collect([$newRule]) : DeliveryAnnouncement::where(fn ($query) => $query->where('customer_key', $customer)
            ->orWhereHas('targets', fn ($target) => $target->where('scope', $scope)->where('delivery_id', $order->id)))
            ->orderBy('id')->lockForUpdate()->get();

        if ($rules->isEmpty()) {
            return;
        }

        $state = $this->state($order);
        $parts = $order->items()->pluck('part_number')->map(fn ($part) => self::normalize($part))->all();
        $boxRules = $rules->where('quota_type', 'box')->where('customer_key', $customer)->whereIn('part_key', $parts);
        $eligible = $state === 'active' && $boxRules->isNotEmpty()
            ? ($export ? $this->exportBoxes($order) : $this->localBoxes($order))
            : collect();
        $eligible = $this->groupBoxes($eligible);
        $dispatch = $this->groupBoxes(collect($shipped));

        foreach ($rules as $rule) {
            $targets = $this->targets($rule);
            $target = $targets->first(fn ($row) => $row->scope === $scope && (int) $row->delivery_id === $order->id);
            $matches = $rule->customer_key === $customer && in_array($rule->part_key, $parts, true);
            $sent = $matches ? ($dispatch[$rule->part_key] ?? []) : [];
            if (! $target && (! $matches || ($state !== 'active' && ! $sent))) {
                continue;
            }

            $targetState = $matches ? $state : 'cancelled';
            $boxNumbers = $matches ? ($eligible[$rule->part_key] ?? []) : [];
            if (! $target) {
                $target = $rule->targets()->create(['scope' => $scope, 'delivery_id' => $order->id,
                    'delivery_reference' => $export ? $order->plan_code : 'DO #'.$order->id, 'state' => 'draft',
                    'delivery_granted' => false, 'eligible_boxes' => [], 'reserved_boxes' => [], 'shipped_boxes' => []]);
                $targets->push($target);
            }

            $target->delivery_reference = $export ? $order->plan_code : 'DO #'.$order->id;
            if ($target->state !== $targetState) {
                $target->closed_message = $targetState === 'completed' && $rule->enabled ? $rule->message : null;
            }
            $target->state = $targetState;
            if ($targetState === 'cancelled') {
                $target->delivery_granted = false;
            }
            $target->shipped_boxes = array_values(array_diff($target->shipped_boxes, $reversed));
            $target->eligible_boxes = array_values(array_diff(array_unique([...$sent, ...$boxNumbers]), $target->shipped_boxes));
            $target->reserved_boxes = array_values(array_intersect($target->reserved_boxes, $target->eligible_boxes));
            $target->save();

            $this->rebalance($rule, $targets, $target->id, $sent);

            if ($sent && $rule->quota_type === 'box') {
                $target->shipped_boxes = [...$target->shipped_boxes, ...array_intersect($target->reserved_boxes, $sent)];
                $target->eligible_boxes = array_values(array_diff($target->eligible_boxes, $sent));
                $target->reserved_boxes = array_values(array_diff($target->reserved_boxes, $sent));
                $target->save();
                $this->rebalance($rule, $targets);
            }
        }
    }

    private function rebalance(DeliveryAnnouncement $rule, Collection $targets, ?int $dispatchTarget = null, array $dispatch = []): void
    {
        if (! $rule->enabled) {
            return;
        }

        $rule->setRelation('targets', $targets);
        $remaining = $rule->usage()['remaining'];
        foreach ($targets as $target) {
            if ($remaining <= 0) {
                break;
            }
            if ($rule->quota_type === 'delivery') {
                if ($target->state === 'active' && ! $target->delivery_granted) {
                    $target->update(['delivery_granted' => true]);
                    $remaining--;
                }
                continue;
            }

            $candidates = match (true) {
                $target->state === 'active' => $target->eligible_boxes,
                $target->id === $dispatchTarget && $target->state === 'completed' => $dispatch,
                default => [],
            };
            $added = array_slice(array_values(array_diff($candidates, $target->reserved_boxes, $target->shipped_boxes)), 0, $remaining);
            if ($added) {
                $target->update(['reserved_boxes' => [...$target->reserved_boxes, ...$added]]);
                $remaining -= count($added);
            }
        }
    }

    private function state(DeliveryOrder|ExportDeliveryPlan $order): string
    {
        if ($order->status === 'completed') {
            return 'completed';
        }
        if ($order->trashed() || in_array($order->status, ['deleted', 'cancelled', 'rejected'], true)) {
            return 'cancelled';
        }
        if ($order instanceof DeliveryOrder) {
            return in_array($order->status, self::LOCAL_ACTIVE, true) ? 'active' : 'draft';
        }

        $plan = $order->palletPlan()->first();

        return match (true) {
            $plan?->status === 'cancelled' => 'cancelled',
            $plan?->isFinalFor($order) => 'active',
            default => 'draft',
        };
    }

    private function groupBoxes(Collection $rows): array
    {
        return $rows->groupBy(fn ($row) => self::normalize(data_get($row, 'part_number')))
            ->map(fn ($boxes) => $boxes->pluck('box_number')->map(fn ($number) => (string) $number)->unique()->values()->all())->all();
    }

    private function localBoxes(DeliveryOrder $order): Collection
    {
        return Box::query()->where('is_withdrawn', false)
            ->where(fn ($query) => $query->where('assigned_delivery_order_id', $order->id)
                ->orWhereIn('id', DB::table('delivery_pick_items as item')
                    ->join('delivery_pick_sessions as session', 'session.id', '=', 'item.pick_session_id')
                    ->where('session.delivery_order_id', $order->id)
                    ->whereIn('session.status', ['pending', 'scanning', 'blocked', 'approved'])
                    ->select('item.box_id')))
            ->orderBy('created_at')->orderBy('id')->get(['box_number', 'part_number']);
    }

    private function exportBoxes(ExportDeliveryPlan $order): Collection
    {
        return ExportFulfillmentBox::query()->whereHas('plannedItem.pallet.palletPlan',
            fn ($plan) => $plan->where('export_delivery_plan_id', $order->id))
            ->join('part_settings as part', 'part.id', '=', 'export_fulfillment_boxes.part_setting_id')
            ->orderBy('first_received_at')->orderBy('export_fulfillment_boxes.id')
            ->get(['export_fulfillment_boxes.box_number', 'part.part_number']);
    }
}
