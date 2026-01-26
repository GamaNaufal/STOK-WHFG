<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_order_id')->constrained('delivery_orders')->onDelete('cascade');
            $table->foreignId('pick_session_id')->constrained('delivery_pick_sessions')->onDelete('cascade');
            $table->uuid('withdrawal_batch_id')->index();
            $table->string('status')->default('completed'); // completed, redone
            $table->dateTime('completed_at');
            $table->dateTime('redo_until');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_completions');
    }
};