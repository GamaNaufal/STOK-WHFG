<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_receiving_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 16)->default('draft');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['created_by', 'status']);
        });
        Schema::create('export_receiving_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('export_receiving_batch_id')->constrained('export_receiving_batches')->restrictOnDelete();
            $table->unsignedInteger('position');
            $table->char('box_number', 8);
            $table->foreignId('part_setting_id')->constrained('part_settings')->restrictOnDelete();
            $table->string('part_number', 100);
            $table->unsignedInteger('pcs_quantity');
            $table->uuid('receive_token')->unique();
            $table->foreignId('export_stock_box_id')->nullable()->unique()->constrained('export_stock_boxes')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['export_receiving_batch_id', 'box_number'], 'export_batch_box_unique');
            $table->unique(['export_receiving_batch_id', 'position'], 'export_batch_position_unique');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Rollback batch penerimaan memerlukan peninjauan daftar scan dan histori penerimaan.');
    }
};
