<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('part_document_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('part_setting_id')->constrained('part_settings')->cascadeOnDelete();
            $number = $table->string('document_number', 100)->unique();
            if (DB::getDriverName() === 'mysql') {
                $number->collation('utf8mb4_bin');
            }
            $table->timestamps();
        });
        Schema::table('export_delivery_plan_items', function (Blueprint $table) {
            // Null means a historical row uses its existing part_number snapshot.
            $table->string('document_part_number', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('export_delivery_plan_items', function (Blueprint $table) {
            $table->dropColumn('document_part_number');
        });
        Schema::dropIfExists('part_document_numbers');
    }
};
