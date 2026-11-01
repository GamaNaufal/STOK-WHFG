<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('export_delivery_plan_items', fn (Blueprint $t) => $t->unsignedInteger('not_full_pcs')->default(0));
        // Keep an index supporting the pallet FK throughout the MySQL index change.
        Schema::table('export_planned_pallet_items', fn (Blueprint $t) => $t->unique(['export_planned_pallet_id', 'part_setting_id', 'pcs_per_box'], 'export_pallet_part_capacity_unique'));
        Schema::table('export_planned_pallet_items', fn (Blueprint $t) => $t->dropUnique('export_planned_pallet_part_unique'));
        Schema::table('export_stock_boxes', function (Blueprint $t) {
            $t->boolean('is_not_full')->default(false);
            $t->unsignedInteger('standard_pcs_quantity')->nullable();
            $t->unsignedInteger('receipt_pcs_quantity')->nullable();
            $t->string('origin', 16)->default('external');
            $t->foreignId('source_box_id')->nullable()->constrained('export_stock_boxes')->restrictOnDelete();
        });
        Schema::create('export_not_full_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('export_delivery_plan_id')->constrained()->restrictOnDelete();
            $t->foreignId('export_delivery_plan_item_id')->nullable()->unique('export_nf_requirement_unique')->constrained()->nullOnDelete();
            $t->foreignId('part_setting_id')->constrained('part_settings')->restrictOnDelete();
            $t->string('part_number', 100);
            $t->unsignedInteger('pcs_quantity');
            $t->unsignedInteger('fixed_qty');
            $t->string('status', 16)->default('waiting');
            $t->unsignedInteger('revision')->default(1);
            $t->json('preparation')->nullable();
            $t->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('prepared_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('approved_at')->nullable();
            $t->foreignId('export_stock_box_id')->nullable()->constrained()->restrictOnDelete();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->index(['status', 'export_delivery_plan_id'], 'export_nf_status_plan');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Rollback Not Full ekspor memerlukan peninjauan histori, PCS dan reservasi.');
    }
};
