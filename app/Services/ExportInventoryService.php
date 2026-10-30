<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ExportInventoryService
{
    public function transaction(callable $callback): mixed
    {
        return DB::transaction(function () use ($callback) {
            // Always before delivery/plan/part/location locks. This also orders FIFO
            // allocation against concurrent receipts and newly finalized demands.
            if (!DB::table('export_inventory_locks')->where('id', 1)->lockForUpdate()->first()) {
                throw new \RuntimeException('Lock inventory ekspor belum tersedia. Periksa migration.');
            }
            return $callback();
        }, 3);
    }
}
