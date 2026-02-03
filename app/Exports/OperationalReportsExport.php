<?php

namespace App\Exports;

use App\Exports\Sheets\ArraySheet;
use App\Exports\Sheets\ChartsSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class OperationalReportsExport implements WithMultipleSheets
{
    public function __construct(private readonly array $data)
    {
    }

    public function sheets(): array
    {
        return [
            new ArraySheet('Summary', $this->data['summary_headings'], $this->data['summary_rows']),
            new ChartsSheet(
                $this->data['throughput_rows'],
                $this->data['peak_rows'],
                $this->data['delivery_trend_rows']
            ),
            new ArraySheet('Current Handling', $this->data['current_headings'], $this->data['current_rows']),
            new ArraySheet('Matching Report', $this->data['matching_headings'], $this->data['matching_rows']),
            new ArraySheet('Processing Time', $this->data['processing_headings'], $this->data['processing_rows']),
            new ArraySheet('Throughput Daily', $this->data['throughput_headings'], $this->data['throughput_rows']),
            new ArraySheet('Peak Hours', $this->data['peak_headings'], $this->data['peak_rows']),
            new ArraySheet('Delivery Trend', $this->data['delivery_trend_headings'], $this->data['delivery_trend_rows']),
            new ArraySheet('Scan Mismatch', $this->data['mismatch_headings'], $this->data['mismatch_rows']),
            new ArraySheet('Fulfillment', $this->data['fulfillment_headings'], $this->data['fulfillment_rows']),
            new ArraySheet('Audit Logs', $this->data['audit_headings'], $this->data['audit_rows']),
        ];
    }
}
