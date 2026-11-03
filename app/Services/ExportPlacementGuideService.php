<?php

namespace App\Services;

class ExportPlacementGuideService
{
    public function prepare(array $data, ?string $destination, array $assignments = []): array
    {
        $warnings = [];
        $assigned = collect($assignments)->keyBy('id');
        $resolve = function (?string $value) use ($data): ?array {
            if (! $value) {
                return null;
            }
            [$type, $id] = explode(':', $value, 2);
            $choice = $data[$type === 'pallet' ? 'pallets' : 'locations']->firstWhere('id', (int) $id);
            if (! $choice) {
                return null;
            }

            return ['pallet' => $type === 'pallet' ? $choice->display_name : 'Pallet cadangan',
                'location' => $type === 'pallet' ? $choice->location_code : $choice->code,
                'current' => $type === 'pallet' ? (int) $choice->boxes_count : 0];
        };
        $incoming = [];
        foreach ($data['groups'] as $group) {
            if ($group['planned']) {
                if ($id = $group['physical_pallet_id'] ?? null) {
                    $incoming['pallet:'.$id] = ($incoming['pallet:'.$id] ?? 0) + $group['load']['incoming'];
                }

                continue;
            }
            foreach ($group['pending'] as $box) {
                $value = $assigned->has($box->id) ? $assigned->get($box->id)['destination'] : $destination;
                if ($value) {
                    $incoming[$value] = ($incoming[$value] ?? 0) + 1;
                }
            }
        }
        $segments = [];
        if (($data['receipt']['redirected'] ?? 0) > 0) {
            $warnings[] = $data['receipt']['redirected'].' box mempunyai panduan tersendiri pada transaksi rekomendasi FIFO. Buka transaksi rekomendasi dari halaman penerimaan.';
        }
        foreach ($data['groups'] as $group) {
            foreach ($group['boxes'] as $box) {
                if (($group['redirected'] ?? collect())->contains('id', $box->id)) {
                    continue;
                }
                $pending = $group['pending']->contains('id', $box->id);
                if ($group['planned']) {
                    $key = $group['key'];
                    $targetId = $group['physical_pallet_id'] ?? null;
                    $load = $targetId ? $group['load']['current'] + ($incoming['pallet:'.$targetId] ?? 0) : $group['load']['total'];
                    $place = ['pallet' => 'Pallet '.$group['planned']->position, 'location' => $group['planned']->location_code, 'load' => $load];
                } elseif ($pending) {
                    $value = $assigned->has($box->id) ? $assigned->get($box->id)['destination'] : $destination;
                    $target = $resolve($value);
                    $key = 'pending-'.$group['key'].'-'.($value ?: 'unassigned');
                    $place = $target ? $target + ['load' => $target['current'] + ($incoming[$value] ?? 0)] : ['pallet' => 'Pallet cadangan', 'location' => null];
                    if (! $target) {
                        $warnings[] = $value
                            ? 'Tujuan cadangan tidak lagi tersedia. Buka halaman transaksi dan pilih kembali.'
                            : 'Tujuan stok cadangan belum dipilih. Tentukan lokasi di halaman transaksi agar panduan menunjukkan tujuan yang lengkap.';
                    }
                } else {
                    $key = 'stored-'.($box->current_pallet_id ?? 'unknown');
                    $place = ['pallet' => $box->currentPallet?->display_name ?? 'Pallet cadangan', 'location' => $box->currentPallet?->location_code];
                }
                $segments[$key] ??= $place + ['load' => null, 'delivery' => $group['delivery'], 'rows' => []];
                $segments[$key]['rows'][] = ['box' => $box, 'pending' => $pending,
                    'waiting_for_review' => $box->fulfillment?->awaitsQueueAcknowledgment() ?? false];
            }
        }

        return ['printGroups' => array_values($segments), 'guideWarning' => $warnings ? implode(' ', array_unique($warnings)) : null, 'printedAt' => now()];
    }
}
