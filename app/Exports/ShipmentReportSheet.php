<?php

namespace App\Exports;

use App\Services\ShipmentReportService;
use Maatwebsite\Excel\Concerns\{FromQuery, WithMapping, WithHeadings, WithTitle, WithColumnFormatting, WithCustomValueBinder, WithStyles};
use PhpOffice\PhpSpreadsheet\Cell\{Cell, DataType, DefaultValueBinder, Coordinate};
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ShipmentReportSheet extends DefaultValueBinder implements FromQuery, WithMapping, WithHeadings, WithTitle, WithColumnFormatting, WithCustomValueBinder, WithStyles
{
    private array $columns;

    public function __construct(private array $filters, private string $kind, private ?string $shipment)
    {
        $common = ['scope' => 'Lingkup', 'event_key' => 'ID pengiriman', 'reference' => 'Delivery / batch', 'customer_name' => 'Customer',
            'occurred_at' => 'Selesai dikirim', 'status' => 'Status'];
        $this->columns = $common + match ($kind) {
            'summary' => ['order_id' => 'ID order/rencana', 'session_id' => 'Sesi / planning', 'delivery_deadline' => 'Tenggat delivery',
                'part_count' => 'Jumlah part', 'pallet_count' => 'Jumlah pallet', 'box_quantity' => 'Jumlah box', 'pcs_quantity' => 'Jumlah PCS',
                'shipped_boxes' => 'Box kirim netto', 'shipped_pcs' => 'PCS kirim netto', 'operator_name' => 'Operator tercatat',
                'started_at' => 'Mulai proses', 'changed_at' => 'Waktu Redo/perubahan', 'legacy' => 'Referensi delivery tersedia',
                'delivery_notes' => 'Catatan delivery / sumber dokumen', 'approval_notes' => 'Catatan approval', 'notes' => 'Catatan transaksi ringkas'],
            'parts' => ['part_number' => 'Nomor part label', 'document_number' => 'Nomor part dokumen', 'box_quantity' => 'Jumlah box', 'pcs_quantity' => 'Jumlah PCS'],
            default => ['box_number' => 'ID Box', 'part_number' => 'Nomor part label', 'document_number' => 'Nomor part dokumen',
                'box_quantity' => 'Jumlah box', 'pcs_quantity' => 'Jumlah PCS', 'pallet_number' => 'Pallet', 'location' => 'Lokasi tercatat',
                'operator_name' => 'Operator tercatat', 'received_at' => 'Pertama diterima', 'scanned_at' => 'Scan/verifikasi', 'scanner_name' => 'Petugas scan/verifikasi',
                'placed_at' => 'Penempatan', 'placer_name' => 'Petugas penempatan', 'changed_at' => 'Waktu Redo/perubahan',
                'notes' => 'Catatan transaksi', 'delivery_notes' => 'Catatan delivery / sumber dokumen', 'approval_notes' => 'Catatan approval',
                'identity_cycle' => 'Penggunaan ID Box'],
        };
    }

    public function title(): string
    {
        return ['summary' => 'Ringkasan Delivery', 'parts' => 'Rincian Part', 'boxes' => 'Rincian Box'][$this->kind];
    }

    public function query()
    {
        $service = app(ShipmentReportService::class);
        return match ($this->kind) {
            'summary' => $service->query($this->filters, $this->shipment)->orderBy('event_key'),
            'parts' => $service->parts($this->filters, $this->shipment),
            default => $service->selectedLines($this->filters, $this->shipment)->orderBy('event_key')->orderBy('pallet_number')->orderBy('box_number')->orderBy('id'),
        };
    }

    public function headings(): array { return array_values($this->columns); }

    public function map($row): array
    {
        return array_map(function ($key) use ($row) {
            $value = $row->$key ?? null;
            return match (true) {
                $key === 'scope' => $this->filters['scope'] === 'export' ? 'Ekspor' : 'Lokal',
                $key === 'status' => ShipmentReportService::STATUSES[$value] ?? $value,
                $key === 'legacy' => $value ? 'Tidak, catatan lama' : 'Ya',
                $key === 'reference' => ShipmentReportService::reference($row),
                in_array($key, $this->dates(), true) && (bool) $value => Date::dateTimeToExcel(\Carbon\Carbon::parse($value)),
                default => $value,
            };
        }, array_keys($this->columns));
    }

    private function dates(): array { return ['occurred_at', 'delivery_deadline', 'started_at', 'changed_at', 'received_at', 'scanned_at', 'placed_at']; }

    public function bindValue(Cell $cell, $value): bool
    {
        $key = array_keys($this->columns)[Coordinate::columnIndexFromString($cell->getColumn()) - 1] ?? '';
        if ((in_array($key, ['part_count', 'pallet_count', 'box_quantity', 'pcs_quantity', 'shipped_boxes', 'shipped_pcs', 'identity_cycle'], true)
            || in_array($key, $this->dates(), true)) && is_numeric($value)) {
            $cell->setValueExplicit((float) $value, DataType::TYPE_NUMERIC);
        } elseif ($value === null) {
            $cell->setValueExplicit(null, DataType::TYPE_NULL);
        } else {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);
        }
        return true;
    }

    public function columnFormats(): array
    {
        $formats = [];
        foreach (array_keys($this->columns) as $index => $key) {
            if (in_array($key, $this->dates(), true)) $formats[Coordinate::stringFromColumnIndex($index + 1)] = 'dd/mm/yyyy hh:mm';
        }
        return $formats;
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');
        $sheet->setAutoFilter($sheet->calculateWorksheetDimension());
        foreach (array_keys($this->columns) as $index => $key) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index + 1))->setWidth(str_contains($key, 'notes') ? 48 : 24);
        }
        $sheet->getStyle($sheet->calculateWorksheetDimension())->getAlignment()->setVertical('top')->setWrapText(true);
        return [1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '0C7779']]]];
    }
}
