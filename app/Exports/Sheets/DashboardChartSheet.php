<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\WithCharts;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;

/** Native Excel charts keep warehouse data on the application server; no chart-image HTTP service. */
class DashboardChartSheet extends DashboardDataSheet implements WithCharts
{
    private int $count;

    public function __construct(string $name, private readonly string $unit, private readonly array $chart)
    {
        $rows = array_map(fn ($row) => [$row['label'], (int) $row['first'], (int) $row['second']], $chart['rows']);
        if (! $rows) {
            $rows = [['Tidak ada data', 0, 0]];
        }
        $this->count = count($rows);
        parent::__construct($name, ['Periode / jam', $chart['first_label'].' ('.$unit.')', $chart['second_label'].' ('.$unit.')'], $rows);
    }

    public function charts(): array
    {
        $sheet = "'".str_replace("'", "''", $this->title())."'";
        $last = $this->count + 1;
        $labels = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $sheet.'!$B$1', null, 1),
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $sheet.'!$C$1', null, 1)];
        $categories = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, $sheet.'!$A$2:$A$'.$last, null, $this->count)];
        $values = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, $sheet.'!$B$2:$B$'.$last, null, $this->count),
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, $sheet.'!$C$2:$C$'.$last, null, $this->count)];
        $series = new DataSeries(DataSeries::TYPE_BARCHART, DataSeries::GROUPING_CLUSTERED, [0, 1], $labels, $categories, $values);
        $chart = new Chart('dashboard', new Title($this->chart['title'].' ('.$this->unit.')'), new Legend(Legend::POSITION_BOTTOM), new PlotArea(null, [$series]), true, DataSeries::EMPTY_AS_GAP, null, new Title($this->unit));
        $chart->setTopLeftPosition('E2');
        $chart->setBottomRightPosition('Q20');

        return [$chart];
    }
}
