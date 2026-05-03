<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;

class StockViewByBoxIdExport implements FromCollection, WithHeadings, WithStyles
{
    protected $stocks;
    protected $sortMode;
    protected $groupEndRows = [];

    public function __construct($stocks, ?string $sortMode = null)
    {
        $this->stocks = $stocks;
        $this->sortMode = $sortMode ?: 'box_id_asc';
    }

    public function collection()
    {
        $rows = collect($this->stocks)->sort(function ($left, $right) {
            return match ($this->sortMode) {
                'box_id_desc' => $this->compareNullableNumber($right['box_id'] ?? null, $left['box_id'] ?? null),
                'box_number_asc' => $this->compareNullableString($left['box_number'] ?? null, $right['box_number'] ?? null),
                'box_number_desc' => $this->compareNullableString($right['box_number'] ?? null, $left['box_number'] ?? null),
                'part_asc' => $this->compareNullableString($left['part_number'] ?? null, $right['part_number'] ?? null),
                'part_desc' => $this->compareNullableString($right['part_number'] ?? null, $left['part_number'] ?? null),
                'pallet_asc' => $this->compareNullableString($left['pallet_number'] ?? null, $right['pallet_number'] ?? null),
                'pallet_desc' => $this->compareNullableString($right['pallet_number'] ?? null, $left['pallet_number'] ?? null),
                'pcs_asc' => $this->compareNullableNumber($left['pcs_quantity'] ?? null, $right['pcs_quantity'] ?? null),
                'pcs_desc' => $this->compareNullableNumber($right['pcs_quantity'] ?? null, $left['pcs_quantity'] ?? null),
                'location_asc' => $this->compareNullableString($left['location'] ?? null, $right['location'] ?? null),
                'location_desc' => $this->compareNullableString($right['location'] ?? null, $left['location'] ?? null),
                'created_oldest' => $this->compareNullableTimestamp($left['created_at'] ?? null, $right['created_at'] ?? null),
                'created_newest' => $this->compareNullableTimestamp($right['created_at'] ?? null, $left['created_at'] ?? null),
                'updated_oldest' => $this->compareNullableTimestamp($left['updated_at'] ?? null, $right['updated_at'] ?? null),
                'updated_newest' => $this->compareNullableTimestamp($right['updated_at'] ?? null, $left['updated_at'] ?? null),
                default => $this->compareNullableNumber($left['box_id'] ?? null, $right['box_id'] ?? null),
            };
        })->values();

        $data = collect();
        $currentRow = 2;

        foreach ($rows as $stock) {
            $data->push([
                'ID Box' => $stock['box_id'] ?? 'Legacy',
                'No Box' => $stock['box_number'] ?? '-',
                'No Part' => $stock['part_number'] ?? '-',
                'No Pallet' => $stock['pallet_number'] ?? '-',
                'PCS' => (int) ($stock['pcs_quantity'] ?? 0),
                'Lokasi' => $stock['location'] ?? '-',
                'Tanggal' => $this->formatDate($stock['created_at'] ?? null),
            ]);
            $currentRow++;
            $this->groupEndRows[] = $currentRow - 1;
        }

        return $data;
    }

    public function headings(): array
    {
        return [
            'ID Box',
            'No Box',
            'No Part',
            'No Pallet',
            'PCS',
            'Lokasi',
            'Tanggal',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $this->stocks->count() + 1;

        $sheet->getStyle('A1:G' . $lastRow)->applyFromArray([
            'border' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

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

        $sheet->getStyle('2:' . $lastRow)->applyFromArray([
            'alignment' => [
                'vertical' => Alignment::VERTICAL_TOP,
                'wrapText' => true,
            ],
        ]);

        $sheet->getColumnDimension('A')->setWidth(12);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(20);
        $sheet->getColumnDimension('D')->setWidth(20);
        $sheet->getColumnDimension('E')->setWidth(12);
        $sheet->getColumnDimension('F')->setWidth(20);
        $sheet->getColumnDimension('G')->setWidth(18);

        foreach ($this->groupEndRows as $row) {
            $sheet->getStyle('A' . $row . ':G' . $row)->applyFromArray([
                'border' => [
                    'bottom' => [
                        'borderStyle' => Border::BORDER_MEDIUM,
                        'color' => ['rgb' => '000000'],
                    ],
                ],
            ]);
        }

        $sheet->freezePane('A2');
    }

    private function formatDate($value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('d M Y H:i');
        }

        return $value ? (string) $value : '-';
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
