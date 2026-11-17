<?php

namespace App\Services;

use App\Models\PartSetting;

/** Keeps recorded mappings intact; old snapshots are enriched for display only. */
class StockOpnamePartNumbers
{
    private array $masters = [];

    public function reset(): void
    {
        $this->masters = [];
    }

    public function primeNumbers(iterable $numbers): void
    {
        $missing = [];
        foreach ($numbers as $number) {
            $number = (string) $number;
            if ($number !== '' && ! array_key_exists('part:'.$number, $this->masters)) {
                $missing['part:'.$number] = $number;
            }
        }
        foreach (array_chunk(array_values($missing), 500) as $chunk) {
            foreach ($chunk as $number) $this->masters['part:'.$number] = null;
            foreach (PartSetting::with(['documentNumbers' => fn ($query) => $query->orderBy('id')])
                ->whereIn('part_number', $chunk)->get(['id', 'part_number']) as $part) {
                $numbers = $part->documentNumbers->pluck('document_number')->map(fn ($number) => (string) $number)->all();
                $this->masters['part:'.$part->part_number] = [
                    'document_part_numbers' => $numbers ?: [(string) $part->part_number],
                    'document_part_mapping' => $numbers ? 'master' : 'same_as_label',
                ];
            }
        }
    }

    public function primeSnapshots(iterable $snapshots): void
    {
        $numbers = [];
        foreach ($snapshots as $snapshot) {
            if ($snapshot && ! $this->recorded($snapshot)) $numbers[] = $snapshot['part_number'] ?? '';
        }
        $this->primeNumbers($numbers);
    }

    public function capture(string $number): array
    {
        $this->primeNumbers([$number]);

        return $this->masters['part:'.$number] ?? [
            'document_part_numbers' => $number !== '' ? [$number] : [],
            'document_part_mapping' => 'unmapped',
        ];
    }

    private function recorded(array $snapshot): bool
    {
        return isset($snapshot['document_part_numbers']) && is_array($snapshot['document_part_numbers'])
            && ($snapshot['document_part_mapping'] ?? 'master') !== 'unmapped';
    }

    public function present(?array $snapshot): ?array
    {
        if ($snapshot === null) return null;
        $recorded = $this->recorded($snapshot);
        $mapping = $recorded ? $snapshot : $this->capture((string) ($snapshot['part_number'] ?? ''));
        $numbers = array_values(array_unique(array_map('strval', $mapping['document_part_numbers'])));
        $kind = $mapping['document_part_mapping'] ?? 'master';
        $note = $recorded ? 'Tercatat saat pemeriksaan' : 'Pemetaan Master Part saat ini';
        if ($kind === 'same_as_label') $note .= ', nomor dokumen sama dengan label / scan';
        if ($kind === 'unmapped') $note .= ', master belum tersedia, memakai nomor label / scan';

        return array_replace($snapshot, [
            'document_part_numbers' => $numbers,
            'document_part_number' => implode(' / ', $numbers),
            'document_number_note' => $note,
            'document_number_current_master' => ! $recorded,
            'document_part_mapping' => $kind,
        ]);
    }
}
