<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;

class StockInputExport implements FromCollection, WithHeadings, WithStyles
{
    protected $stockInputs;
    protected $groupEndRows = [];

    public function __construct($stockInputs)
    {
        $this->stockInputs = $stockInputs;
    }

    public function collection()
    {
        $data = collect();
        $currentRow = 2;
        
        foreach ($this->stockInputs as $input) {
            // Load pallet dengan items untuk group by part_number
            $input->load('pallet.items');
            
            // Group items by part_number
            $itemsByPart = $input->pallet->items->groupBy('part_number');
            
            if ($itemsByPart->isNotEmpty()) {
                // Multiple rows jika ada multiple part numbers
                $isFirstRow = true;
                foreach ($itemsByPart as $partNumber => $items) {
                    $totalPcsForPart = $items->sum('pcs_quantity');
                    $totalBoxForPart = $items->sum('box_quantity');
                    
                    $data->push([
                        'Tanggal' => $isFirstRow ? $input->stored_at->format('d/m/Y') : '',
                        'Waktu' => $isFirstRow ? $input->stored_at->format('H:i:s') : '',
                        'Tipe Aksi' => $isFirstRow ? 'Input Stok' : '',
                        'Status' => $isFirstRow ? 'Completed' : '',
                        'Operator' => $isFirstRow ? ($input->user?->name ?? 'System') : '',
                        'Qty (PCS)' => (int)$totalPcsForPart,
                        'Qty (Box)' => (int)$totalBoxForPart,
                        'Part Number' => $partNumber ?? '-',
                        'Keterangan' => $isFirstRow ? '-' : '',
                        'Lokasi Simpan' => $isFirstRow ? ($input->warehouse_location ?? '-') : '',
                    ]);
                    $currentRow++;
                    $isFirstRow = false;
                }
            } else {
                // Fallback jika tidak ada items
                $data->push([
                    'Tanggal' => $input->stored_at->format('d/m/Y'),
                    'Waktu' => $input->stored_at->format('H:i:s'),
                    'Tipe Aksi' => 'Input Stok',
                    'Status' => 'Completed',
                    'Operator' => $input->user?->name ?? 'System',
                    'Qty (PCS)' => (int)$input->pcs_quantity,
                    'Qty (Box)' => (int)$input->box_quantity,
                    'Part Number' => '-',
                    'Keterangan' => '-',
                    'Lokasi Simpan' => $input->warehouse_location ?? '-',
                ]);
                $currentRow++;
            }
            
            // Track group end row
            $this->groupEndRows[] = $currentRow - 1;
        }

        return $data;
    }

    public function headings(): array
    {
        return [
            'Tanggal',
            'Waktu',
            'Tipe Aksi',
            'Status',
            'Operator',
            'Qty (PCS)',
            'Qty (Box)',
            'Part Number',
            'Keterangan',
            'Lokasi Simpan',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $this->stockInputs->count() + 1;

        // Apply borders to all cells
        $sheet->getStyle('A1:J' . $lastRow)->applyFromArray([
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
        $sheet->getColumnDimension('A')->setWidth(12);  // Tanggal
        $sheet->getColumnDimension('B')->setWidth(10);  // Waktu
        $sheet->getColumnDimension('C')->setWidth(15);  // Tipe Aksi
        $sheet->getColumnDimension('D')->setWidth(12);  // Status
        $sheet->getColumnDimension('E')->setWidth(15);  // Operator
        $sheet->getColumnDimension('F')->setWidth(10);  // Qty (PCS)
        $sheet->getColumnDimension('G')->setWidth(10);  // Qty (Box)
        $sheet->getColumnDimension('H')->setWidth(15);  // Part Number
        $sheet->getColumnDimension('I')->setWidth(25);  // Keterangan
        $sheet->getColumnDimension('J')->setWidth(20);  // Lokasi Simpan

        // Apply thicker border di akhir setiap group
        foreach ($this->groupEndRows as $row) {
            $sheet->getStyle('A' . $row . ':J' . $row)->applyFromArray([
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
