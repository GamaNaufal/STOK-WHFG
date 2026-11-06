<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('export_stock_pallets', function (Blueprint $table) {
            $table->unsignedInteger('box_limit')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('export_stock_pallets', fn (Blueprint $table) => $table->dropColumn('box_limit'));
    }
};
