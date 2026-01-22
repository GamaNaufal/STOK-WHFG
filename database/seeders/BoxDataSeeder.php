<?php

namespace Database\Seeders;

use Maatwebsite\Excel\Facades\Excel;
use App\Imports\BoxImport;
use Illuminate\Database\Seeder;

class BoxDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Import data dari Excel
        Excel::import(new BoxImport, storage_path('app/excel/TempSTOCKFG2020.xlsx'));
        
        $this->command->info('Data Box berhasil diimport dari Excel!');
    }
}
