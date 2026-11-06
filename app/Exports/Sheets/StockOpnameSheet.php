<?php

namespace App\Exports\Sheets;

use Generator;
use Maatwebsite\Excel\Concerns\{FromGenerator, WithColumnFormatting, WithCustomValueBinder, WithHeadings, WithStrictNullComparison, WithStyles, WithTitle};
use PhpOffice\PhpSpreadsheet\Cell\{Cell, Coordinate, DataType, DefaultValueBinder};
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StockOpnameSheet extends DefaultValueBinder implements FromGenerator, WithColumnFormatting, WithCustomValueBinder, WithHeadings, WithStrictNullComparison, WithStyles, WithTitle
{
    public function __construct(
        private readonly string $title,
        private readonly array $headings,
        private readonly iterable $rows,
        private readonly array $widths = [],
        private readonly array $dateRanges = [],
    ) {}

    public function title(): string { return $this->title; }

    public function headings(): array { return $this->headings; }

    public function generator(): Generator
    {
        yield from $this->rows;
    }

    public function bindValue(Cell $cell, $value): bool
    {
        // IDs and raw scan input must keep their exact text, including leading zeroes and '='.
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function columnFormats(): array
    {
        return array_fill_keys($this->dateRanges, 'dd/mm/yyyy hh:mm:ss');
    }

    public function styles(Worksheet $sheet): array
    {
        foreach ($this->headings as $index => $heading) {
            $column = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->getColumnDimension($column)->setWidth($this->widths[$column] ?? max(15, mb_strlen($heading) + 4));
        }
        $sheet->freezePane('A2');
        $sheet->getRowDimension(1)->setRowHeight(22);
        if (count($this->headings) > 2) {
            $sheet->setAutoFilter($sheet->calculateWorksheetDimension());
        }

        return [1 => [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '0C7779']],
        ]];
    }
}
