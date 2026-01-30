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
        Schema::create('pallet_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pallet_id')->constrained('pallets')->onDelete('cascade');
            $table->string('part_number');
            $table->integer('box_quantity');
            $table->integer('pcs_quantity');
            $table->timestamps();

            $table->unique(['pallet_id', 'part_number']);
            $table->index('pallet_id');
            $table->index('part_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pallet_items');
    }
};
