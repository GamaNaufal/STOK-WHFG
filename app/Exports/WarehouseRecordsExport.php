<?php

namespace App\Exports;

use App\Services\WarehouseReportService;
use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\{FromQuery, WithMapping, WithHeadings, WithColumnFormatting, WithCustomValueBinder, WithStyles};
use PhpOffice\PhpSpreadsheet\Cell\{Cell, DataType, DefaultValueBinder, Coordinate};
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class WarehouseRecordsExport extends DefaultValueBinder implements FromQuery, WithMapping, WithHeadings, WithColumnFormatting, WithCustomValueBinder, WithStyles
{
    private array $columns;
    public function __construct(private Builder $records, private array $filters)
    {
        $this->columns=app(WarehouseReportService::class)->columns($filters);
    }
    public function query(){return $this->records;}
    public function headings(): array{return array_merge(['Lingkup'],array_values($this->columns));}
    public function map($row): array
    {
        $values=[$this->filters['scope']==='export'?'Ekspor':'Domestik'];
        foreach($this->columns as$key=>$label){
            $value=app(WarehouseReportService::class)->value($row,$key,$this->filters);
            if(in_array($key,['occurred_at','completed_at'])&&$value)$value=Date::dateTimeToExcel(\Illuminate\Support\Carbon::parse($value));
            elseif(in_array($key,['pcs_quantity','box_quantity','pallet_count','ready_pallets','required_boxes','fulfilled_boxes','reserved_boxes','remaining_boxes','required_pcs','fulfilled_pcs']))$value=(int)$value;
            $values[]=$value??'';
        }
        return $values;
    }
    public function bindValue(Cell $cell,$value): bool
    {
        $key=array_keys($this->columns)[Coordinate::columnIndexFromString($cell->getColumn())-2]??null;
        if($cell->getRow()>1&&in_array($key,['occurred_at','completed_at','pcs_quantity','box_quantity','pallet_count','ready_pallets',
            'required_boxes','fulfilled_boxes','reserved_boxes','remaining_boxes','required_pcs','fulfilled_pcs'])&&is_numeric($value)){
            $cell->setValueExplicit((float)$value,DataType::TYPE_NUMERIC);return true;
        }
        if(is_string($value)){$cell->setValueExplicit($value,DataType::TYPE_STRING);return true;}
        return parent::bindValue($cell,$value);
    }
    public function columnFormats(): array
    {
        $formats=[];foreach(array_keys($this->columns)as$i=>$key)if(in_array($key,['occurred_at','completed_at']))$formats[Coordinate::stringFromColumnIndex($i+2)]='dd/mm/yyyy hh:mm';
        return $formats;
    }
    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');$sheet->setAutoFilter($sheet->calculateWorksheetDimension());
        foreach(range(1,count($this->columns)+1)as$i)$sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth(23);
        return [1=>['font'=>['bold'=>true,'color'=>['rgb'=>'FFFFFF']],'fill'=>['fillType'=>'solid','startColor'=>['rgb'=>'0C7779']]]];
    }
}
