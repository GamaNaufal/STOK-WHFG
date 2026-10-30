<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_stock_allocation_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('export_pallet_plan_id')->constrained('export_pallet_plans')->restrictOnDelete();
            $table->unsignedInteger('plan_revision');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['export_pallet_plan_id', 'plan_revision'], 'export_allocation_notice_revision_unique');
        });
        Schema::table('export_fulfillment_boxes', function (Blueprint $table) {
            // NULL retains the queue behavior of all reservations created before this feature.
            $table->foreignId('allocation_notice_id')->nullable()->constrained('export_stock_allocation_notices')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Rollback tinjauan FIFO memerlukan rekonsiliasi reservasi dan antrean.');
    }
};
