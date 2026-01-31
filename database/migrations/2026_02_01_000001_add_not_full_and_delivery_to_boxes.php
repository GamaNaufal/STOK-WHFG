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
        Schema::table('boxes', function (Blueprint $table) {
            $table->boolean('is_not_full')->default(false)->after('is_withdrawn');
            $table->text('not_full_reason')->nullable()->after('is_not_full');
            $table->foreignId('assigned_delivery_order_id')
                ->nullable()
                ->after('not_full_reason')
                ->constrained('delivery_orders')
                ->nullOnDelete();

            $table->index('assigned_delivery_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('boxes', function (Blueprint $table) {
            $table->dropForeign(['assigned_delivery_order_id']);
            $table->dropIndex(['assigned_delivery_order_id']);
            $table->dropColumn(['is_not_full', 'not_full_reason', 'assigned_delivery_order_id']);
        });
    }
};
