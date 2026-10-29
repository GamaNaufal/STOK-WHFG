<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('export_delivery_plans', function (Blueprint $table) {
            // Existing plans keep a null code until the operator completes it.
            $table->string('plan_code', 100)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('export_delivery_plans', function (Blueprint $table) {
            $table->dropIndex(['plan_code']);
            $table->dropColumn('plan_code');
        });
    }
};
