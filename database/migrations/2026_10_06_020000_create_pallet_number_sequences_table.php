<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pallet_number_sequences', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('next_number');
            $table->timestamps();
        });

        $maxNumber = DB::table('pallets')
            ->pluck('pallet_number')
            ->map(function ($palletNumber) {
                preg_match('/-?(\d+)$/', (string) $palletNumber, $matches);

                return isset($matches[1]) ? (int) $matches[1] : 0;
            })
            ->max() ?? 0;

        DB::table('pallet_number_sequences')->insert([
            'id' => 1,
            'next_number' => $maxNumber + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('pallet_number_sequences');
    }
};
