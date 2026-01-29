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
        Schema::create('stock_withdrawals', function (Blueprint $table) {
            $table->id();
            $table->uuid('withdrawal_batch_id')->nullable()->index();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('pallet_item_id')->nullable()->constrained('pallet_items')->onDelete('set null');
            $table->foreignId('box_id')->nullable()->constrained('boxes')->nullOnDelete();
            $table->string('part_number');
            $table->integer('pcs_quantity');
            $table->decimal('box_quantity', 8, 2)->default(0); // Store box quantity with decimal for partial boxes
            $table->string('warehouse_location');
            $table->enum('status', ['completed', 'reversed', 'cancelled'])->default('completed');
            $table->text('notes')->nullable();
            $table->dateTime('withdrawn_at')->useCurrent();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('part_number');
            $table->index('user_id');
            $table->index('withdrawn_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_withdrawals');
    }
};
