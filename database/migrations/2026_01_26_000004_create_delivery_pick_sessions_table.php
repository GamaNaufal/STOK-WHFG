<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_pick_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_order_id')->constrained('delivery_orders')->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->enum('status', ['pending', 'scanning', 'blocked', 'approved', 'completed', 'cancelled'])->default('pending');
            $table->dateTime('started_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('redo_until')->nullable();
            $table->enum('completion_status', ['pending', 'completed', 'redone'])->default('pending');
            $table->timestamps();

            $table->index('status');
            $table->index('delivery_order_id');
            $table->index('created_by');
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_pick_sessions');
    }
};