<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('master_locations', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // e.g., A-1-1
            $table->boolean('is_occupied')->default(false); // Status terisi/kosong
            $table->foreignId('current_pallet_id')->nullable()->constrained('pallets')->nullOnDelete(); // Palet mana yang sedang menempati (opsional untuk double check)
            $table->timestamps();

            $table->index('is_occupied');
            $table->unique('current_pallet_id', 'master_locations_current_pallet_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_locations');
    }
};
