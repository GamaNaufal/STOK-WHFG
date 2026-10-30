<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\{FromQuery, WithColumnFormatting, WithColumnWidths, WithCustomValueBinder, WithHeadings, WithMapping, WithStyles};
use PhpOffice\PhpSpreadsheet\Cell\{Cell, DataType, DefaultValueBinder};
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\{Alignment, Fill};
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExportStockExport extends DefaultValueBinder implements FromQuery, WithMapping, WithHeadings, WithStyles, WithColumnWidths, WithColumnFormatting, WithCustomValueBinder
{
    public function __construct(private Builder $boxes) {}

    public function query(): Builder { return $this->boxes; }

    public function headings(): array
    {
        return ['Nomor box', 'Nomor part', 'Qty (PCS)', 'Pertama diterima', 'Pallet fisik', 'Lokasi fisik',
            'Status', 'Rencana delivery (reservasi)', 'Pallet tujuan', 'Lokasi tujuan'];
    }

    public function map($box): array
    {
        $target = $box->fulfillment?->plannedItem?->pallet;
        return [$box->box_number, $box->part_number, $box->pcs_quantity, Date::dateTimeToExcel($box->first_received_at),
            $box->currentPallet?->display_name ?? 'Menunggu penempatan', $box->currentPallet?->location_code ?? '-',
            $box->status_label, $target?->palletPlan?->deliveryPlan?->plan_code ?? '-',
            $target ? 'Pallet '.$target->position : '-', $target?->location_code ?? '-'];
    }

    public function bindValue(Cell $cell, $value): bool
    {
        // Keep leading zeroes and treat user-provided text as text, including leading '='.
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            return true;
        }
        return parent::bindValue($cell, $value);
    }

    public function columnFormats(): array { return ['D' => 'dd/mm/yyyy hh:mm']; }

    public function columnWidths(): array
    {
        return ['A' => 16, 'B' => 22, 'C' => 14, 'D' => 22, 'E' => 22, 'F' => 20, 'G' => 40, 'H' => 28, 'I' => 20, 'J' => 20];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $sheet->getHighestRow();
        $sheet->getStyle('A1:J'.$lastRow)->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
        $sheet->getStyle('A1:J1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0C7779']],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(32);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:J'.$lastRow);
    }
}
