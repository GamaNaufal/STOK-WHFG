<?php

namespace App\Services;

class ExportPlacementGuideService
{
    public function prepare(array $data, ?string $destination): array
    {
        $target = null; $warning = null;
        $hasPendingSpare = $data['groups']->contains(fn ($group) => !$group['planned'] && $group['pending']->isNotEmpty());
        if ($hasPendingSpare && $destination) {
            [$type, $id] = explode(':', $destination, 2);
            $choice = $data[$type === 'pallet' ? 'pallets' : 'locations']->firstWhere('id', (int) $id);
            if ($choice) $target = ['pallet' => $type === 'pallet' ? $choice->display_name : 'Pallet cadangan',
                'location' => $type === 'pallet' ? $choice->location_code : $choice->code,
                'current' => $type === 'pallet' ? (int) $choice->boxes_count : 0];
            else $warning = 'Tujuan cadangan tidak lagi tersedia. Buka halaman transaksi dan pilih kembali.';
        }
        if ($hasPendingSpare && !$target) $warning ??= 'Tujuan stok cadangan belum dipilih. Tentukan lokasi di halaman transaksi agar panduan menunjukkan tujuan yang lengkap.';

        $segments = [];
        foreach ($data['groups'] as $group) {
            foreach ($group['boxes'] as $box) {
                $pending = $group['pending']->contains('id', $box->id);
                if ($group['planned']) {
                    $key = $group['key'];
                    $place = ['pallet' => 'Pallet '.$group['planned']->position, 'location' => $group['planned']->location_code, 'load' => $group['load']['total']];
                } elseif ($pending) {
                    $key = 'pending-spare'; $place = $target
                        ? $target + ['load' => $target['current'] + $group['pending']->count()]
                        : ['pallet' => 'Pallet cadangan', 'location' => null];
                } else {
                    $key = 'stored-'.($box->current_pallet_id ?? 'unknown');
                    $place = ['pallet' => $box->currentPallet?->display_name ?? 'Pallet cadangan', 'location' => $box->currentPallet?->location_code];
                }
                $segments[$key] ??= $place + ['load' => null, 'delivery' => $group['delivery'], 'rows' => []];
                $segments[$key]['rows'][] = ['box' => $box, 'pending' => $pending,
                    'waiting_for_review' => $box->fulfillment?->awaitsQueueAcknowledgment() ?? false];
            }
        }
        return ['printGroups' => array_values($segments), 'guideWarning' => $warning, 'printedAt' => now()];
    }
}
