<?php

namespace App\Services;

use App\Models\ExportDeliveryPlan;

/** A part can require full boxes and one remainder box, with separate exact capacities. */
class ExportBoxRequirements
{
    public static function key(int $partId, int $pcs): string
    {
        return $partId.':'.$pcs;
    }

    public static function catalog(ExportDeliveryPlan $delivery): \Illuminate\Support\Collection
    {
        return $delivery->items->flatMap(function ($item) {
            $base = ['part_setting_id' => $item->part_setting_id, 'part_number' => $item->part_number];
            $rows = [];
            $full = intdiv($item->pcs_quantity, $item->pcs_per_box);
            if ($full > 0) {
                $rows[] = $base + ['pcs_per_box' => $item->pcs_per_box, 'required' => $full, 'is_not_full' => false];
            }
            if ($item->not_full_pcs > 0) {
                $rows[] = $base + ['pcs_per_box' => $item->not_full_pcs, 'required' => 1, 'is_not_full' => true];
            }

            return $rows;
        })->map(fn ($r) => $r + ['key' => self::key($r['part_setting_id'], $r['pcs_per_box'])])->keyBy('key');
    }
}
