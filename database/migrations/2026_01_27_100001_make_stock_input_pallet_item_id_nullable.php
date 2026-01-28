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
        Schema::table('stock_inputs', function (Blueprint $table) {
            // Make pallet_item_id nullable since StockInput is a batch record
            $table->unsignedBigInteger('pallet_item_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_inputs', function (Blueprint $table) {
            $table->unsignedBigInteger('pallet_item_id')->nullable(false)->change();
        });
    }
};
