<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->foreignId('parent_delivery_order_id')
                ->nullable()
                ->after('id')
                ->constrained('delivery_orders')
                ->nullOnDelete();

            $table->index('parent_delivery_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_delivery_order_id');
        });
    }
};