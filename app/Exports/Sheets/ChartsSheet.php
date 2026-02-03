<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class ChartsSheet implements FromArray, WithTitle, WithDrawings, WithStyles, WithColumnWidths
{
    private array $titleRows = [];
    private array $headerRows = [];

    public function __construct(
        private readonly array $throughputRows,
        private readonly array $peakRows,
        private readonly array $deliveryTrendRows
    ) {
    }

    public function title(): string
    {
        return 'Charts';
    }

    public function array(): array
    {
        $throughput = $this->normalizeRows($this->throughputRows, ['-', 0, 0]);
        $peak = $this->normalizeRows($this->peakRows, ['-', 0, 0]);
        $delivery = $this->normalizeRows($this->deliveryTrendRows, ['-', 0, 0]);

        $rows = [];
        $currentRow = 1;

        $this->appendSection(
            $rows,
            $currentRow,
            'Inbound vs Outbound Trend',
            ['Date', 'Inbound PCS', 'Outbound PCS'],
            $throughput,
            15
        );

        $this->appendSection(
            $rows,
            $currentRow,
            'Peak Hours',
            ['Hour', 'Inbound PCS', 'Outbound PCS'],
            $peak,
            15
        );

        $this->appendSection(
            $rows,
            $currentRow,
            'Schedule Fulfillment Performance',
            ['Period', 'Planned Qty', 'Actual Qty'],
            $delivery,
            15
        );

        return $rows;
    }

    public function drawings(): array
    {
        $sheet = $this->title();

        $throughputCount = max(1, count($this->throughputRows));
        $peakCount = max(1, count($this->peakRows));
        $deliveryCount = max(1, count($this->deliveryTrendRows));

        $throughputTitleRow = 1;
        $throughputHeaderRow = 2;
        $throughputFirstDataRow = 3;
        $throughputLastDataRow = $throughputFirstDataRow + $throughputCount - 1;
        $throughputChartStartRow = $throughputLastDataRow + 2;
        $throughputChartEndRow = $throughputChartStartRow + 14;

        $peakTitleRow = $throughputChartEndRow + 2;
        $peakHeaderRow = $peakTitleRow + 1;
        $peakFirstDataRow = $peakHeaderRow + 1;
        $peakLastDataRow = $peakFirstDataRow + $peakCount - 1;
        $peakChartStartRow = $peakLastDataRow + 2;
        $peakChartEndRow = $peakChartStartRow + 14;

        $deliveryTitleRow = $peakChartEndRow + 2;
        $deliveryHeaderRow = $deliveryTitleRow + 1;
        $deliveryFirstDataRow = $deliveryHeaderRow + 1;
        $deliveryLastDataRow = $deliveryFirstDataRow + $deliveryCount - 1;
        $deliveryChartStartRow = $deliveryLastDataRow + 2;
        $deliveryChartEndRow = $deliveryChartStartRow + 14;

        $drawings = [];

        $drawings[] = $this->buildChartDrawing(
            'Inbound vs Outbound Trend',
            $this->buildLineChartConfig(
                'Inbound vs Outbound Trend',
                array_column($this->normalizeRows($this->throughputRows, ['-', 0, 0]), 0),
                array_column($this->normalizeRows($this->throughputRows, ['-', 0, 0]), 1),
                array_column($this->normalizeRows($this->throughputRows, ['-', 0, 0]), 2)
            ),
            'A' . $throughputChartStartRow,
            960,
            280
        );

        $drawings[] = $this->buildChartDrawing(
            'Peak Hours',
            $this->buildColumnChartConfig(
                'Peak Hours',
                array_column($this->normalizeRows($this->peakRows, ['-', 0, 0]), 0),
                array_column($this->normalizeRows($this->peakRows, ['-', 0, 0]), 1),
                array_column($this->normalizeRows($this->peakRows, ['-', 0, 0]), 2)
            ),
            'A' . $peakChartStartRow,
            960,
            280
        );

        $drawings[] = $this->buildChartDrawing(
            'Schedule Fulfillment Performance',
            $this->buildColumnChartConfig(
                'Schedule Fulfillment Performance',
                array_column($this->normalizeRows($this->deliveryTrendRows, ['-', 0, 0]), 0),
                array_column($this->normalizeRows($this->deliveryTrendRows, ['-', 0, 0]), 1),
                array_column($this->normalizeRows($this->deliveryTrendRows, ['-', 0, 0]), 2),
                ['Rencana Sales', 'Aktual Delivery']
            ),
            'A' . $deliveryChartStartRow,
            960,
            280
        );

        return $drawings;
    }

    private function normalizeRows(array $rows, array $fallback): array
    {
        if (count($rows) > 0) {
            return $rows;
        }

        return [$fallback];
    }

    private function buildLineChartConfig(string $title, array $labels, array $series1, array $series2): array
    {
        return [
            'type' => 'line',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Inbound PCS',
                        'data' => $series1,
                        'borderColor' => '#0C7779',
                        'backgroundColor' => 'rgba(12,119,121,0.2)',
                        'tension' => 0.3,
                    ],
                    [
                        'label' => 'Outbound PCS',
                        'data' => $series2,
                        'borderColor' => '#f97316',
                        'backgroundColor' => 'rgba(249,115,22,0.2)',
                        'tension' => 0.3,
                    ],
                ],
            ],
            'options' => [
                'plugins' => [
                    'legend' => ['position' => 'bottom'],
                    'title' => ['display' => true, 'text' => $title],
                ],
                'scales' => [
                    'y' => ['beginAtZero' => true, 'title' => ['display' => true, 'text' => 'QTY']],
                ],
            ],
        ];
    }

    private function buildColumnChartConfig(
        string $title,
        array $labels,
        array $series1,
        array $series2,
        array $seriesLabels = ['Inbound', 'Outbound']
    ): array {
        return [
            'type' => 'bar',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => $seriesLabels[0] ?? 'Series 1',
                        'data' => $series1,
                        'backgroundColor' => '#0C7779',
                    ],
                    [
                        'label' => $seriesLabels[1] ?? 'Series 2',
                        'data' => $series2,
                        'backgroundColor' => '#f97316',
                    ],
                ],
            ],
            'options' => [
                'plugins' => [
                    'legend' => ['position' => 'bottom'],
                    'title' => ['display' => true, 'text' => $title],
                ],
                'scales' => [
                    'y' => ['beginAtZero' => true, 'title' => ['display' => true, 'text' => 'QTY']],
                ],
            ],
        ];
    }

    private function buildChartDrawing(string $name, array $config, string $cell, int $width, int $height): Drawing
    {
        $url = $this->buildQuickChartUrl($config);
        $tempPath = tempnam(sys_get_temp_dir(), 'chart_');
        $image = file_get_contents($url);
        if ($image === false) {
            $image = '';
        }
        file_put_contents($tempPath, $image);

        $drawing = new Drawing();
        $drawing->setName($name);
        $drawing->setDescription($name);
        $drawing->setPath($tempPath);
        $drawing->setCoordinates($cell);
        $drawing->setWidth($width);
        $drawing->setHeight($height);

        return $drawing;
    }

    private function buildQuickChartUrl(array $config): string
    {
        $payload = json_encode($config, JSON_UNESCAPED_SLASHES);
        return 'https://quickchart.io/chart?c=' . urlencode($payload) . '&width=960&height=280&backgroundColor=white';
    }

    private function appendSection(
        array &$rows,
        int &$currentRow,
        string $title,
        array $headers,
        array $dataRows,
        int $chartSpaceRows
    ): void {
        $rows[] = [$title];
        $this->titleRows[] = $currentRow;
        $currentRow++;

        $rows[] = $headers;
        $this->headerRows[] = $currentRow;
        $currentRow++;

        foreach ($dataRows as $row) {
            $rows[] = $row;
            $currentRow++;
        }

        $rows[] = [];
        $currentRow++;

        for ($i = 0; $i < $chartSpaceRows; $i++) {
            $rows[] = [];
            $currentRow++;
        }

        $rows[] = [];
        $currentRow++;
    }

    public function styles(Worksheet $sheet): array
    {
        $styles = [];
        foreach ($this->titleRows as $row) {
            $styles[$row] = ['font' => ['bold' => true, 'size' => 12]];
        }
        foreach ($this->headerRows as $row) {
            $styles[$row] = ['font' => ['bold' => true]];
        }

        return $styles;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20,
            'B' => 14,
            'C' => 14,
        ];
    }
}
