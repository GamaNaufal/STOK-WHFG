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
        Schema::create('not_full_box_requests', function (Blueprint $table) {
            $table->id();
            $table->string('box_number')->unique();
            $table->string('part_number');
            $table->integer('pcs_quantity');
            $table->integer('fixed_qty');
            $table->text('reason');

            $table->foreignId('delivery_order_id')->constrained('delivery_orders')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();

            $table->foreignId('target_pallet_id')->nullable()->constrained('pallets')->nullOnDelete();
            $table->foreignId('target_location_id')->nullable()->constrained('master_locations')->nullOnDelete();
            $table->string('target_location_code')->nullable();

            $table->string('status')->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('box_id')->nullable()->constrained('boxes')->nullOnDelete();

            $table->timestamps();

            $table->index(['status', 'delivery_order_id']);
            $table->index('part_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('not_full_box_requests');
    }
};
