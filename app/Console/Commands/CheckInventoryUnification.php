<?php

namespace App\Console\Commands;

use App\Support\InventoryUnificationCheck;
use Illuminate\Console\Command;

class CheckInventoryUnification extends Command
{
    protected $signature = 'inventory:unification-check';

    protected $description = 'Periksa kesiapan master part bersama, ID Box global dan kode delivery unik (hanya baca).';

    public function handle(): int
    {
        $report = InventoryUnificationCheck::report();
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        if (InventoryUnificationCheck::blocked($report)) {
            $this->error('Ada konflik. Data tidak diubah. Koreksi data yang tercantum sebelum migrasi.');

            return self::FAILURE;
        }
        $this->info('Pemeriksaan lulus. Tidak ada data yang diubah.');

        return self::SUCCESS;
    }
}
