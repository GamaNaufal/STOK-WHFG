<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Additive only: no stock, delivery, receipt, permission or history backfill.
        Schema::create('export_customers', function (Blueprint $t) {
            $t->id();
            $t->string('code', 100)->unique();
            $t->string('name');
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('revision')->default(1);
            $t->timestamps();
        });
        Schema::create('export_customer_aliases', function (Blueprint $t) {
            $t->id();
            $t->foreignId('export_customer_id')->constrained('export_customers')->restrictOnDelete();
            $t->string('label');
            $normalized = $t->string('normalized')->unique();
            if (DB::getDriverName() === 'mysql') $normalized->collation('utf8mb4_bin');
            $t->string('kind', 12);
        });
        Schema::create('export_delivery_customer_links', function (Blueprint $t) {
            $t->id();
            $t->foreignId('export_delivery_plan_id')->unique()->constrained('export_delivery_plans')->restrictOnDelete();
            $t->foreignId('export_customer_id')->constrained('export_customers')->restrictOnDelete();
            $t->string('source_name')->nullable();
            $t->timestamps();
        });
        Schema::create('export_box_customer_reservations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('export_stock_box_id')->unique()->constrained('export_stock_boxes')->restrictOnDelete();
            $t->foreignId('export_customer_id')->constrained('export_customers')->restrictOnDelete();
            $t->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $t->text('reason');
            $t->timestamps();
        });
        Schema::create('export_receiving_item_customers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('export_receiving_batch_item_id')->unique('eric_item_unique')->constrained('export_receiving_batch_items', indexName: 'eric_item_fk')->cascadeOnDelete();
            $t->foreignId('export_customer_id')->constrained('export_customers')->restrictOnDelete();
        });
        Schema::create('export_customer_stock_exceptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('export_stock_box_id')->unique()->constrained('export_stock_boxes')->restrictOnDelete();
            $t->foreignId('export_customer_id')->constrained('export_customers')->restrictOnDelete();
            $t->foreignId('export_delivery_plan_id')->constrained('export_delivery_plans')->restrictOnDelete();
            // Item rows may be removed by a legitimate revision; retain the historical ID.
            $t->unsignedBigInteger('export_planned_pallet_item_id')->index('ecse_item_idx');
            // No FK to a mutable commitment: retain its immutable ID after cancellation.
            $t->unsignedBigInteger('fulfillment_id')->nullable()->index();
            $t->unsignedInteger('identity_cycle');
            $t->string('status', 16)->default('active');
            $t->unsignedInteger('revision')->default(1);
            $t->foreignId('authorized_by')->constrained('users')->restrictOnDelete();
            $t->text('reason');
            $t->json('snapshot');
            $t->timestamps();
        });
        // Append-only snapshots survive later ownership changes, archive and label reuse.
        Schema::create('export_customer_stock_events', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('stock_box_id')->index();
            $t->string('box_number', 8);
            $t->unsignedInteger('identity_cycle');
            $t->string('action', 32);
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->json('before');
            $t->json('after');
            $t->text('reason');
            $t->timestamp('created_at')->useCurrent();
        });
        // Created last, also acts as the complete-schema readiness marker.
        Schema::create('export_customer_stock_requests', function (Blueprint $t) {
            $t->uuid('token')->primary();
            $t->string('request_hash', 64);
            $t->json('result');
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['export_customers', 'export_box_customer_reservations', 'export_customer_stock_exceptions', 'export_customer_stock_events', 'export_customer_stock_requests'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('Rollback ditolak, data customer/reservasi sudah digunakan. Pertahankan schema dan gunakan perbaikan maju.');
            }
        }
        foreach (['export_customer_stock_requests', 'export_customer_stock_events', 'export_customer_stock_exceptions', 'export_receiving_item_customers', 'export_box_customer_reservations', 'export_delivery_customer_links', 'export_customer_aliases', 'export_customers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
