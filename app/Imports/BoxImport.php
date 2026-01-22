<?php

namespace App\Imports;

use App\Models\Box;
use App\Models\User;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithLimit;
use Picqer\Barcode\BarcodeGenerator;
use Picqer\Barcode\BarcodeGeneratorSVG;

class BoxImport implements ToModel, WithHeadingRow, WithChunkReading, WithLimit
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        // Skip jika row kosong
        if (empty($row['boxid'])) {
            return null;
        }

        // Skip jika box sudah ada
        if (Box::where('box_number', $row['boxid'])->exists()) {
            return null;
        }

        // Get or create default user for import
        $user = User::first() ?? User::create([
            'name' => 'System Import',
            'email' => 'import@system.local',
            'password' => bcrypt('password'),
        ]);

        // Generate Barcode (CODE128 format) - Ukuran lebih besar untuk scanning
        $boxNumber = (string)$row['boxid'];
        $barcodeGenerator = new BarcodeGeneratorSVG();
        // Width 3 untuk barcode lebih tebal dan mudah di-scan
        $barcode = $barcodeGenerator->getBarcode($boxNumber, BarcodeGenerator::TYPE_CODE_128, 3, 80);

        // Map kolom dari Excel ke database
        return new Box([
            'box_number'    => $boxNumber,
            'part_number'   => $row['partno'] ?? null,
            'part_name'     => $row['partname'] ?? null,
            'pcs_quantity'  => intval($row['qtybox'] ?? 0),
            'qty_box'       => intval($row['qtybox'] ?? 0),
            'type_box'      => $row['typebox'] ?? null,
            'wk_transfer'   => $row['wktransfer'] ?? null,
            'lot01'         => $row['lot01'] ?? null,
            'lot02'         => $row['lot02'] ?? null,
            'lot03'         => $row['lot03'] ?? null,
            'qr_code'       => $barcode, // Menyimpan barcode dalam format SVG
            'user_id'       => $user->id,
        ]);
    }

    public function chunkSize(): int
    {
        return 100;
    }

    public function limit(): int
    {
        return 50; // Import hanya 50 baris
    }
}
