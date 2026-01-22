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
            // Cek dan tambah kolom yang belum ada
            if (!Schema::hasColumn('boxes', 'part_name')) {
                $table->string('part_name')->nullable();
            }
            if (!Schema::hasColumn('boxes', 'qty_box')) {
                $table->integer('qty_box')->nullable();
            }
            if (!Schema::hasColumn('boxes', 'type_box')) {
                $table->string('type_box')->nullable();
            }
            if (!Schema::hasColumn('boxes', 'wk_transfer')) {
                $table->string('wk_transfer')->nullable();
            }
            if (!Schema::hasColumn('boxes', 'lot01')) {
                $table->string('lot01')->nullable();
            }
            if (!Schema::hasColumn('boxes', 'lot02')) {
                $table->string('lot02')->nullable();
            }
            if (!Schema::hasColumn('boxes', 'lot03')) {
                $table->string('lot03')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('boxes', function (Blueprint $table) {
            if (Schema::hasColumn('boxes', 'qty_box')) {
                $table->dropColumn('qty_box');
            }
            if (Schema::hasColumn('boxes', 'type_box')) {
                $table->dropColumn('type_box');
            }
            if (Schema::hasColumn('boxes', 'wk_transfer')) {
                $table->dropColumn('wk_transfer');
            }
            if (Schema::hasColumn('boxes', 'lot01')) {
                $table->dropColumn('lot01');
            }
            if (Schema::hasColumn('boxes', 'lot02')) {
                $table->dropColumn('lot02');
            }
            if (Schema::hasColumn('boxes', 'lot03')) {
                $table->dropColumn('lot03');
            }
        });
    }
};
