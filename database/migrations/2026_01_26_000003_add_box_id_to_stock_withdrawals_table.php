<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_withdrawals', function (Blueprint $table) {
            $table->foreignId('box_id')->nullable()->after('pallet_item_id')->constrained('boxes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_withdrawals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('box_id');
        });
    }
};