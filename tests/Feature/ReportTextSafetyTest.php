<?php

namespace Tests\Feature;

use App\Exports\{AuditTrailExport, StockInputExport, StockViewByBoxIdExport, StockViewByPalletExport, StockViewByPartExport};
use App\Models\{AuditLog, StockInput, User};
use PhpOffice\PhpSpreadsheet\Cell\{Cell, DataType};
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ReportTextSafetyTest extends TestCase
{
    public function test_legacy_workbooks_preserve_untrusted_text_identifiers_and_numeric_quantities(): void
    {
        // Unsaved fixtures: generating these workbooks needs no operational database.
        $name = '=1+1';
        $user = new User(['name' => $name]);
        $log = new AuditLog(['type' => 'master_part', 'action' => 'created', 'description' => $name,
            'new_values' => json_encode(['part_number' => $name, 'location' => $name, 'pcs_quantity' => 12])]);
        $log->created_at = now();
        $log->setRelation('user', $user);
        $input = new StockInput(['stored_at' => now(), 'part_numbers' => [$name], 'warehouse_location' => $name,
            'pcs_quantity' => 12, 'box_quantity' => 1]);
        $input->setRelation('boxes', collect());
        $input->setRelation('user', $user);
        $stock = collect([['part_number' => $name, 'total_box' => 1, 'total_pcs' => 12, 'location' => $name,
            'box_id' => 42, 'box_number' => '00123456', 'pallet_number' => 'PLT-01', 'pcs_quantity' => 12]]);
        $cases = [
            [new AuditTrailExport(collect([$log])), ['E2' => $name, 'H2' => $name, 'I2' => $name, 'J2' => $name], 'F2', 12],
            [new StockInputExport(collect([$input])), ['E2' => $name, 'H2' => $name, 'K2' => $name], 'F2', 12],
            [new StockViewByPartExport($stock), ['A2' => $name], 'C2', 12],
            [new StockViewByPalletExport($stock), ['B2' => $name], 'D2', 12],
            [new StockViewByBoxIdExport($stock), ['B2' => '00123456', 'C2' => $name, 'F2' => $name], 'E2', 12],
        ];
        foreach ($cases as [$export, $texts, $numericCell, $quantity]) {
            $previous = Cell::getValueBinder();
            $response = $export->download('dummy-security-report.xlsx');
            $file = $response->getFile()->getPathname();
            try {
                $this->assertSame($previous, Cell::getValueBinder());
                $book = IOFactory::load($file);
                $sheet = $book->getActiveSheet();
                foreach ($texts as $address => $expected) {
                    $this->assertSame(DataType::TYPE_STRING, $sheet->getCell($address)->getDataType());
                    $this->assertSame($expected, $sheet->getCell($address)->getValue());
                }
                $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell($numericCell)->getDataType());
                $this->assertEquals($quantity, $sheet->getCell($numericCell)->getValue());
                $book->disconnectWorksheets();
            } finally {
                Cell::setValueBinder($previous);
                if (is_file($file)) unlink($file);
            }
        }
    }

    public function test_failed_download_restores_the_previous_binder(): void
    {
        $previous = Cell::getValueBinder();
        \Maatwebsite\Excel\Facades\Excel::shouldReceive('download')->once()->andReturnUsing(function ($export) {
            Cell::setValueBinder($export);
            throw new \RuntimeException('Dummy export failure');
        });
        try {
            (new StockViewByPartExport(collect()))->download('dummy.xlsx');
            $this->fail('Expected dummy failure');
        } catch (\RuntimeException $error) {
            $this->assertSame('Dummy export failure', $error->getMessage());
        }
        $this->assertSame($previous, Cell::getValueBinder());
    }
}
