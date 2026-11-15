<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\{WithMultipleSheets, WithEvents};
use Maatwebsite\Excel\Events\{BeforeExport, BeforeWriting};
use PhpOffice\PhpSpreadsheet\Cell\Cell;

class ShipmentReportExport implements WithMultipleSheets, WithEvents
{
    private $previousBinder;
    public function __construct(public readonly array $filters, public readonly ?string $shipment = null) {}

    public function sheets(): array
    {
        return array_map(fn ($kind) => new ShipmentReportSheet($this->filters, $kind, $this->shipment), ['summary', 'parts', 'boxes']);
    }

    public function registerEvents(): array
    {
        return [
            BeforeExport::class => function () { $this->previousBinder = Cell::getValueBinder(); },
            BeforeWriting::class => function () { if ($this->previousBinder) Cell::setValueBinder($this->previousBinder); },
        ];
    }
}
