<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class AuditTrailExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    protected $auditLogs;
    protected $groupEndRows = []; // Track the last row of each audit log group
    public function __construct($auditLogs)
    {
        $this->auditLogs = $auditLogs;
    }

    public function collection()
    {
        $data = collect();
        $currentRow = 2; // Start from row 2 (after header)
        
        foreach ($this->auditLogs as $log) {
            // Parse new values
            $newValues = !empty($log->new_values) ? json_decode($log->new_values, true) : [];
            
            // Check if ini batch withdrawal dengan multiple boxes
            if ($log->type === 'stock_withdrawal' && !empty($newValues['boxes']) && is_array($newValues['boxes'])) {
                // Create multiple rows - satu per box
                $totalPcs = $newValues['total_pcs_quantity'] ?? 0;
                $totalBox = $newValues['total_box_quantity'] ?? 0;
                
                foreach ($newValues['boxes'] as $index => $box) {
                    $data->push([
                        'Tanggal' => $log->created_at->format('d/m/Y'),
                        'Waktu' => $index === 0 ? $log->created_at->format('H:i:s') : '',
                        'Tipe Aksi' => $index === 0 ? $this->getTypeLabel($log->type) : '',
                        'Status' => $index === 0 ? ucfirst($log->action) : '',
                        'Operator' => $index === 0 ? ($log->user?->name ?? 'System') : '',
                        'Qty (PCS)' => (int)($box['pcs_quantity'] ?? 0),
                        'Qty (Box)' => 1,
                        'Part Number' => $box['part_number'] ?? '-',
                        'Keterangan' => $index === 0 ? ($log->description ?? '-') : '',
                        'Lokasi Simpan' => $box['warehouse_location'] ?? '-',
                    ]);
                    $currentRow++;
                }
                // Track the last row of this group
                $this->groupEndRows[] = $currentRow - 1;
            } elseif ($log->type === 'stock_input' && !empty($newValues['part_numbers']) && is_array($newValues['part_numbers'])) {
                // Stock input dengan multiple part numbers - expand ke multiple rows
                $partNumbers = $newValues['part_numbers'] ?? [];
                
                // Load actual pallet untuk ambil qty per part number
                $stockInput = \App\Models\StockInput::with('pallet.items')->find($log->model_id);
                
                $partQtys = [];
                if ($stockInput && $stockInput->pallet && $stockInput->pallet->items->isNotEmpty()) {
                    // Group items by part_number dan sum qty
                    $itemsByPart = $stockInput->pallet->items->groupBy('part_number');
                    foreach ($itemsByPart as $partNumber => $items) {
                        $partQtys[$partNumber] = [
                            'pcs' => (int)$items->sum('pcs_quantity'),
                            'box' => (int)$items->sum('box_quantity'),
                        ];
                    }
                }
                
                foreach ($partNumbers as $index => $partNumber) {
                    // Ambil qty actual dari grouping, atau fallback ke distribusi
                    $currentPcs = $partQtys[$partNumber]['pcs'] ?? 0;
                    $currentBox = $partQtys[$partNumber]['box'] ?? 0;
                    
                    $data->push([
                        'Tanggal' => $log->created_at->format('d/m/Y'),
                        'Waktu' => $index === 0 ? $log->created_at->format('H:i:s') : '',
                        'Tipe Aksi' => $index === 0 ? $this->getTypeLabel($log->type) : '',
                        'Status' => $index === 0 ? ucfirst($log->action) : '',
                        'Operator' => $index === 0 ? ($log->user?->name ?? 'System') : '',
                        'Qty (PCS)' => (int)$currentPcs,
                        'Qty (Box)' => (int)$currentBox,
                        'Part Number' => $partNumber ?? '-',
                        'Keterangan' => $index === 0 ? ($log->description ?? '-') : '',
                        'Lokasi Simpan' => $index === 0 ? ($newValues['warehouse_location'] ?? '-') : '',
                    ]);
                    $currentRow++;
                }
                // Track the last row of this group
                $this->groupEndRows[] = $currentRow - 1;
            } else {
                // Single row untuk non-batch atau single item
                $detail = $this->extractDetail($log->type, $newValues, $log);
                
                $data->push([
                    'Tanggal' => $log->created_at->format('d/m/Y'),
                    'Waktu' => $log->created_at->format('H:i:s'),
                    'Tipe Aksi' => $this->getTypeLabel($log->type),
                    'Status' => ucfirst($log->action),
                    'Operator' => $log->user?->name ?? 'System',
                    'Qty (PCS)' => (int)($detail['quantity'] ?? 0),
                    'Qty (Box)' => (int)($detail['box_qty'] ?? 0),
                    'Part Number' => $detail['part_number'] ?? '-',
                    'Keterangan' => $log->description ?? '-',
                    'Lokasi Simpan' => $detail['location'] ?? '-',
                ]);
                $currentRow++;
                // Track the last row of this group
                $this->groupEndRows[] = $currentRow - 1;
            }
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
        $lastRow = !empty($this->groupEndRows)
            ? max($this->groupEndRows)
            : 1;

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

        // Apply thicker border di akhir setiap group/aksi
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

        // Tidak perlu nambah row numbers lagi karena sudah hapus kolom 'No'

        return [];
    }

    private function extractDetail($type, $newValues, $log)
    {
        $detail = [];

        switch ($type) {
            case 'stock_input':
                $detail = [
                    'box_location' => $newValues['pallet_id'] ?? 'Input',
                    'quantity' => (int)($newValues['pcs_quantity'] ?? 0),
                    'box_qty' => (int)($newValues['box_quantity'] ?? 0),
                    'part_number' => $newValues['part_number'] ?? '-',
                    'location' => $newValues['warehouse_location'] ?? '-',
                ];
                break;

            case 'stock_withdrawal':
                // Single withdrawal (legacy)
                $detail = [
                    'box_location' => '-',
                    'quantity' => (int)($newValues['pcs_quantity'] ?? 0),
                    'box_qty' => (int)($newValues['box_quantity'] ?? 0),
                    'part_number' => $newValues['part_number'] ?? '-',
                    'location' => $newValues['warehouse_location'] ?? '-',
                ];
                break;

            case 'delivery_pickup':
                $detail = [
                    'box_location' => 'DO #' . ($newValues['delivery_order_id'] ?? '-'),
                    'quantity' => (int)($newValues['completed_boxes'] ?? 0),
                    'box_qty' => (int)($newValues['total_boxes'] ?? 0),
                    'part_number' => '-',
                    'location' => '-',
                ];
                break;

            case 'delivery_redo':
                $detail = [
                    'box_location' => 'Redo',
                    'quantity' => 0,
                    'box_qty' => 0,
                    'part_number' => '-',
                    'location' => '-',
                ];
                break;

            default:
                $detail = [
                    'box_location' => '-',
                    'quantity' => 0,
                    'box_qty' => 0,
                    'part_number' => '-',
                    'location' => '-',
                ];
        }

        return $detail;
    }

    private function getTypeLabel($type)
    {
        return match($type) {
            'stock_input' => 'Input Stok',
            'stock_withdrawal' => 'Ambil Stok',
            'delivery_pickup' => 'Pengiriman',
            'delivery_redo' => 'Redo Delivery',
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }
}

