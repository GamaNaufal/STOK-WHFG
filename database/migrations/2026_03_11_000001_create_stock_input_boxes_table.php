<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_input_boxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_input_id')->constrained('stock_inputs')->cascadeOnDelete();
            $table->foreignId('box_id')->constrained('boxes')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['stock_input_id', 'box_id']);
            $table->index('box_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_input_boxes');
    }
};
