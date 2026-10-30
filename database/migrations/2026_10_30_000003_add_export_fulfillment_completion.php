<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('export_planned_pallets', function (Blueprint $table) {
            $table->unsignedInteger('fulfillment_revision')->default(1);
            $table->timestamp('ready_at')->nullable();
            $table->foreignId('ready_by')->nullable()->constrained('users')->restrictOnDelete();
        });
        Schema::table('export_pallet_plans', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->restrictOnDelete();
        });
        Schema::table('export_stock_boxes', fn (Blueprint $table) => $table->softDeletes());
        Schema::create('export_fulfillment_actions', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_token')->unique();
            $table->char('request_hash', 64);
            $table->string('action', 20);
            $table->foreignId('export_delivery_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Rekonsiliasi riwayat pengiriman ekspor sebelum rollback penyelesaian permintaan.');
    }
};
