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
        Schema::create('stock_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pallet_id')->constrained('pallets')->onDelete('cascade');
            $table->foreignId('master_location_id')->nullable();
            $table->string('warehouse_location');
            $table->timestamp('stored_at');
            $table->timestamps();

            $table->index('warehouse_location');
            $table->index('pallet_id');
            $table->index('stored_at');
            $table->index('master_location_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_locations');
    }
};
