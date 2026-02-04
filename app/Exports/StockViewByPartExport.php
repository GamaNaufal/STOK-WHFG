<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;

class StockViewByPartExport implements FromCollection, WithHeadings, WithStyles
{
    protected $stocks;
    protected $groupEndRows = [];

    public function __construct($stocks)
    {
        $this->stocks = $stocks;
    }

    public function collection()
    {
        $data = collect();
        $currentRow = 2;
        
        foreach ($this->stocks as $stock) {
            $data->push([
                'No Part' => $stock['part_number'] ?? '-',
                'Total Box' => (int)$stock['box_quantity'],
                'Total PCS' => (int)$stock['pcs_quantity'],
            ]);
            $currentRow++;
            $this->groupEndRows[] = $currentRow - 1;
        }

        return $data;
    }

    public function headings(): array
    {
        return [
            'No Part',
            'Total Box',
            'Total PCS',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = !empty($this->groupEndRows)
            ? max($this->groupEndRows)
            : 1;

        // Apply borders to all cells
        $sheet->getStyle('A1:C' . $lastRow)->applyFromArray([
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
        $sheet->getColumnDimension('A')->setWidth(20);  // No Part
        $sheet->getColumnDimension('B')->setWidth(15);  // Total Box
        $sheet->getColumnDimension('C')->setWidth(15);  // Total PCS

        // Apply thicker border di akhir setiap entry
        foreach ($this->groupEndRows as $row) {
            $sheet->getStyle('A' . $row . ':C' . $row)->applyFromArray([
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
}
