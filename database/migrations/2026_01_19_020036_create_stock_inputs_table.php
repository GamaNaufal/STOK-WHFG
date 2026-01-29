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
        Schema::create('stock_inputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pallet_id')->constrained('pallets')->onDelete('cascade');
            $table->foreignId('pallet_item_id')->nullable()->constrained('pallet_items')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('warehouse_location');
            $table->integer('pcs_quantity');
            $table->decimal('box_quantity', 8, 2);
            $table->dateTime('stored_at');
            $table->timestamps();
            $table->softDeletes();

            $table->index('pallet_id');
            $table->index('user_id');
            $table->index('stored_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_inputs');
    }
};
