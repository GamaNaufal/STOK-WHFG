<?php

namespace App\Services;

use App\Models\DeliveryAnnouncementTarget;
use App\Models\DeliveryOrder;
use App\Models\ExportDeliveryPlan;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DeliveryAnnouncementDisplay
{
    public const SURFACES = [
        'local_schedule' => 'Jadwal delivery',
        'local_fulfill' => 'Pemenuhan Lokal',
        'local_verify' => 'Picking Verification Lokal',
        'local_scan' => 'Scan pengambilan Lokal',
        'local_print' => 'Pratinjau cetak picklist',
        'local_pdf' => 'Dokumen PDF picklist',
        'export_delivery' => 'Rencana delivery Ekspor',
        'export_planning' => 'Perencanaan pallet Ekspor',
        'export_fulfill' => 'Pemenuhan Ekspor',
        'export_ready' => 'Konfirmasi pallet siap',
        'export_picking' => 'Picking Verification Ekspor',
        'export_seek' => 'Cari Box Ekspor',
        'export_receiving' => 'Penempatan Ekspor',
        'export_guide' => 'Panduan cetak peletakan',
        'export_print_delivery' => 'Cetak delivery Ekspor',
        'export_print_pallet' => 'Cetak pallet Ekspor',
    ];

    private const PLANNING_SURFACES = ['local_schedule', 'local_fulfill', 'export_delivery', 'export_planning', 'export_fulfill'];

    public function targets(string $scope, iterable $ids): Collection
    {
        $ids = collect($ids)->unique()->all();

        return DeliveryAnnouncementTarget::where('scope', $scope)->whereIn('delivery_id', $ids)
            ->whereIn('state', ['active', 'completed'])->with('announcement')->orderBy('id')->get()->groupBy('delivery_id');
    }

    /**
     * Supply only the boxes and parts in the current pallet, transaction or picklist.
     * Null represents a delivery-wide view. An empty list represents an empty selection.
     */
    public function notes(DeliveryOrder|ExportDeliveryPlan $delivery, string $surface, ?User $actor,
        ?iterable $boxes = null, ?iterable $parts = null, ?string $context = null, ?Collection $targets = null): Collection
    {
        $scope = $delivery instanceof ExportDeliveryPlan ? 'export' : 'domestic';
        $targets ??= $this->targets($scope, [$delivery->id])->get($delivery->id, collect());
        $boxFilter = $boxes === null ? null : collect($boxes)->map(fn ($id) => (string) $id)->all();
        $partFilter = $parts === null ? null : collect($parts)->map(fn ($part) => DeliveryAnnouncementService::normalize($part))->all();
        $notes = collect();
        $impressions = [];
        $now = now();

        foreach ($targets as $target) {
            $rule = $target->announcement;
            $message = $target->state === 'completed' ? $target->closed_message : ($rule->enabled ? $rule->message : null);
            if ($message === null || ($partFilter !== null && ! in_array($rule->part_key, $partFilter, true))) {
                continue;
            }

            $numbers = $rule->quota_type === 'box' ? [...$target->shipped_boxes, ...$target->reserved_boxes] : [];
            if ($boxFilter !== null) {
                $numbers = array_values(array_intersect($numbers, $boxFilter));
            }
            $allBoxes = $rule->quota_type === 'delivery';
            $pending = ! $allBoxes && ! $numbers && $target->state === 'active'
                && $target->eligible_boxes === [] && in_array($surface, self::PLANNING_SURFACES, true)
                && $rule->loadMissing('targets')->usage()['remaining'] > 0;
            if ($allBoxes ? ! $target->delivery_granted : (! $numbers && ! $pending)) {
                continue;
            }

            $notes->push(['part' => $rule->part_number, 'message' => $message, 'box_numbers' => $numbers,
                'all_boxes' => $allBoxes, 'pending' => $pending]);

            if ($actor) {
                $snapshot = [$target->id, $surface, $context, $actor->id, $target->delivery_reference, $message, $numbers];
                $impressions[] = [
                    'identity' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)),
                    'target_id' => $target->id, 'surface' => $surface, 'context' => $context, 'user_id' => $actor->id,
                    'actor_name' => $actor->name, 'delivery_reference' => $target->delivery_reference,
                    'message' => $message, 'box_numbers' => json_encode($numbers, JSON_THROW_ON_ERROR),
                    'first_seen_at' => $now, 'last_seen_at' => $now,
                ];
            }
        }

        if ($impressions) {
            DB::table('delivery_announcement_impressions')->upsert($impressions, ['identity'], ['last_seen_at']);
        }

        return $notes;
    }
}
