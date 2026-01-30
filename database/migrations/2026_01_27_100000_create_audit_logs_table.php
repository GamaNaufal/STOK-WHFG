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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            
            // Tipe audit: 'stock_input', 'stock_withdrawal', 'delivery_pickup', dll
            $table->enum('type', ['stock_input', 'stock_withdrawal', 'delivery_pickup', 'delivery_redo', 'pallet_merged', 'box_pallet_moved', 'other']);
            
            // Model yang diaudit
            $table->string('model')->nullable(); // 'StockInput', 'StockWithdrawal', dll
            $table->unsignedBigInteger('model_id')->nullable(); // ID dari model
            
            // Aksi yang dilakukan: 'created', 'updated', 'completed', 'reversed', dll
            $table->string('action');
            
            // Detail perubahan data (JSON)
            $table->longText('old_values')->nullable(); // JSON
            $table->longText('new_values')->nullable(); // JSON
            $table->text('description')->nullable(); // Keterangan ringkas
            
            // Siapa yang melakukan
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            
            // Metadata request
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            
            // Timestamp
            $table->timestamps();
            
            // Indexes untuk performa query
            $table->index('type');
            $table->index('model');
            $table->index('action');
            $table->index('user_id');
            $table->index('created_at');
            $table->index(['type', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
