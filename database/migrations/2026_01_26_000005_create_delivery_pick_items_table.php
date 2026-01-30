<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_pick_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pick_session_id')->constrained('delivery_pick_sessions')->onDelete('cascade');
            $table->foreignId('box_id')->constrained('boxes')->onDelete('cascade');
            $table->string('part_number');
            $table->integer('pcs_quantity');
            $table->enum('status', ['pending', 'scanned', 'verified', 'failed'])->default('pending');
            $table->dateTime('scanned_at')->nullable();
            $table->foreignId('scanned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('pick_session_id');
            $table->index('status');
            $table->index('part_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_pick_items');
    }
};