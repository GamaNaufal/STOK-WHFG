<?php

namespace App\Services;

use App\Models\ExportDeliveryPlan;

/** Exact capacities are retained when physical pallets are merged. */
class ExportBoxRequirements
{
    public static function composition($item): array
    {
        if ($item->box_composition !== null) {
            return $item->box_composition;
        }
        $rows = [];
        $full = intdiv($item->pcs_quantity, $item->pcs_per_box);
        if ($full > 0) {
            $rows[] = ['pcs_per_box' => $item->pcs_per_box, 'box_quantity' => $full];
        }
        if ($item->not_full_pcs > 0) {
            $rows[] = ['pcs_per_box' => $item->not_full_pcs, 'box_quantity' => 1];
        }

        return $rows;
    }

    public static function notFullLabel($item): string
    {
        return collect(self::composition($item))->filter(fn ($r) => $r['pcs_per_box'] < $item->pcs_per_box)
            ->map(fn ($r) => $r['box_quantity'].' Not Full '.$r['pcs_per_box'].' PCS')->implode(', ');
    }

    public static function updateComposition($item, array $rows): void
    {
        $rows = collect($rows)->filter(fn ($r) => $r['box_quantity'] > 0)->sortByDesc('pcs_per_box')->values();
        $item->update(['box_composition' => $rows->all(), 'box_quantity' => $rows->sum('box_quantity'),
            'pcs_quantity' => $rows->sum(fn ($r) => $r['pcs_per_box'] * $r['box_quantity']),
            'not_full_pcs' => $rows->where('pcs_per_box', '<', $item->pcs_per_box)->sum(fn ($r) => $r['pcs_per_box'] * $r['box_quantity'])]);
    }

    public static function key(int $partId, int $pcs): string
    {
        return $partId.':'.$pcs;
    }

    public static function catalog(ExportDeliveryPlan $delivery): \Illuminate\Support\Collection
    {
        return $delivery->items->flatMap(function ($item) {
            $base = ['part_setting_id' => $item->part_setting_id, 'part_number' => $item->part_number];

            return collect(self::composition($item))->map(fn ($r) => $base + ['pcs_per_box' => $r['pcs_per_box'],
                'required' => $r['box_quantity'], 'is_not_full' => $r['pcs_per_box'] < $item->pcs_per_box]);
        })->map(fn ($r) => $r + ['key' => self::key($r['part_setting_id'], $r['pcs_per_box'])])->keyBy('key');
    }
}
