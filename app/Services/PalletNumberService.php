<?php

namespace App\Services;

use App\Models\Pallet;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class PalletNumberService
{
    public function create(): Pallet
    {
        return DB::transaction(function (): Pallet {
            for ($attempt = 0; $attempt < 10; $attempt++) {
                $sequence = DB::table('pallet_number_sequences')
                    ->where('id', 1)
                    ->lockForUpdate()
                    ->first();

                if (! $sequence) {
                    throw new \RuntimeException('Allocator nomor pallet belum tersedia. Jalankan migration terbaru.');
                }

                $number = (int) $sequence->next_number;
                $palletNumber = 'PLT-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT);

                DB::table('pallet_number_sequences')
                    ->where('id', 1)
                    ->update([
                        'next_number' => $number + 1,
                        'updated_at' => now(),
                    ]);

                try {
                    return Pallet::create(['pallet_number' => $palletNumber]);
                } catch (QueryException $e) {
                    if (! $this->isDuplicatePalletNumber($e)) {
                        throw $e;
                    }
                }
            }

            throw new \RuntimeException('Gagal membuat nomor pallet unik setelah beberapa percobaan.');
        });
    }

    private function isDuplicatePalletNumber(QueryException $e): bool
    {
        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        return $driverCode === 1062
            && str_contains(strtolower((string) ($e->errorInfo[2] ?? $e->getMessage())), 'pallet');
    }
}
