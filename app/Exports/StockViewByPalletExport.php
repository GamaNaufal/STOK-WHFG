<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;

class StockViewByPalletExport implements FromCollection, WithHeadings, WithStyles
{
    protected $stocks;
    protected $sortMode;
    protected $groupEndRows = [];

    public function __construct($stocks, ?string $sortMode = null)
    {
        $this->stocks = $stocks;
        $this->sortMode = $sortMode ?: 'pallet_asc';
    }

    public function collection()
    {
        $rows = collect($this->stocks)->sort(function ($left, $right) {
            return match ($this->sortMode) {
                'pallet_desc' => $this->compareNullableString($right['pallet_number'] ?? null, $left['pallet_number'] ?? null),
                'location_asc' => $this->compareNullableString($left['location'] ?? null, $right['location'] ?? null),
                'location_desc' => $this->compareNullableString($right['location'] ?? null, $left['location'] ?? null),
                'total_box_asc' => $this->compareNullableNumber($left['total_box'] ?? null, $right['total_box'] ?? null),
                'total_box_desc' => $this->compareNullableNumber($right['total_box'] ?? null, $left['total_box'] ?? null),
                'total_pcs_asc' => $this->compareNullableNumber($left['total_pcs'] ?? null, $right['total_pcs'] ?? null),
                'total_pcs_desc' => $this->compareNullableNumber($right['total_pcs'] ?? null, $left['total_pcs'] ?? null),
                'created_oldest' => $this->compareNullableTimestamp($left['sort_created_at'] ?? null, $right['sort_created_at'] ?? null),
                'created_newest' => $this->compareNullableTimestamp($right['sort_created_at'] ?? null, $left['sort_created_at'] ?? null),
                'updated_oldest' => $this->compareNullableTimestamp($left['sort_updated_at'] ?? null, $right['sort_updated_at'] ?? null),
                'updated_newest' => $this->compareNullableTimestamp($right['sort_updated_at'] ?? null, $left['sort_updated_at'] ?? null),
                default => $this->compareNullableString($left['pallet_number'] ?? null, $right['pallet_number'] ?? null),
            };
        })->values();

        $data = collect();
        $currentRow = 2;
        
        foreach ($rows as $stock) {
            $data->push([
                'No Pallet' => $stock['pallet_number'] ?? '-',
                'Lokasi' => $stock['location'] ?? '-',
                'Total Box' => (int) ($stock['total_box'] ?? $stock['box_quantity'] ?? 0),
                'Total PCS' => (int) ($stock['total_pcs'] ?? $stock['pcs_quantity'] ?? 0),
            ]);
            $currentRow++;
            $this->groupEndRows[] = $currentRow - 1;
        }

        return $data;
    }

    public function headings(): array
    {
        return [
            'No Pallet',
            'Lokasi',
            'Total Box',
            'Total PCS',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $this->stocks->count() + 1;

        // Apply borders to all cells
        $sheet->getStyle('A1:D' . $lastRow)->applyFromArray([
            'border' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        // Header styling
        $sheet->getStyle('1:1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0C7779'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ]);

        // Data rows styling
        $sheet->getStyle('2:' . $lastRow)->applyFromArray([
            'alignment' => [
                'vertical' => Alignment::VERTICAL_TOP,
                'wrapText' => true,
            ],
        ]);

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(20);  // No Pallet
        $sheet->getColumnDimension('B')->setWidth(20);  // Lokasi
        $sheet->getColumnDimension('C')->setWidth(15);  // Total Box
        $sheet->getColumnDimension('D')->setWidth(15);  // Total PCS

        // Apply thicker border di akhir setiap entry
        foreach ($this->groupEndRows as $row) {
            $sheet->getStyle('A' . $row . ':D' . $row)->applyFromArray([
                'border' => [
                    'bottom' => [
                        'borderStyle' => Border::BORDER_MEDIUM,
                        'color' => ['rgb' => '000000'],
                    ],
                ],
            ]);
        }

        // Freeze header row
        $sheet->freezePane('A2');
    }

    private function compareNullableString($left, $right, string $direction = 'asc'): int
    {
        $leftValue = mb_strtolower(trim((string) ($left ?? '')));
        $rightValue = mb_strtolower(trim((string) ($right ?? '')));

        if ($leftValue === '' && $rightValue === '') {
            return 0;
        }

        if ($leftValue === '') {
            return 1;
        }

        if ($rightValue === '') {
            return -1;
        }

        $comparison = strcmp($leftValue, $rightValue);

        return $direction === 'desc' ? -$comparison : $comparison;
    }

    private function compareNullableNumber($left, $right, string $direction = 'asc'): int
    {
        $leftIsMissing = !is_numeric($left);
        $rightIsMissing = !is_numeric($right);

        if ($leftIsMissing && $rightIsMissing) {
            return 0;
        }

        if ($leftIsMissing) {
            return 1;
        }

        if ($rightIsMissing) {
            return -1;
        }

        $comparison = ((float) $left) <=> ((float) $right);

        return $direction === 'desc' ? -$comparison : $comparison;
    }

    private function compareNullableTimestamp($left, $right, string $direction = 'asc'): int
    {
        $leftTimestamp = $this->toTimestamp($left);
        $rightTimestamp = $this->toTimestamp($right);

        if ($leftTimestamp === null && $rightTimestamp === null) {
            return 0;
        }

        if ($leftTimestamp === null) {
            return 1;
        }

        if ($rightTimestamp === null) {
            return -1;
        }

        $comparison = $leftTimestamp <=> $rightTimestamp;

        return $direction === 'desc' ? -$comparison : $comparison;
    }

    private function toTimestamp($value): ?int
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        if (is_string($value) && trim($value) !== '') {
            $timestamp = strtotime($value);
            return $timestamp !== false ? $timestamp : null;
        }

        return null;
    }
}
