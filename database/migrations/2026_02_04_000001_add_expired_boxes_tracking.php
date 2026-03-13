<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('expired_box_reports')) {
            Schema::create('expired_box_reports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('box_id')->nullable()->constrained('boxes')->nullOnDelete();
                $table->string('box_number');
                $table->string('part_number');
                $table->foreignId('pallet_id')->nullable()->constrained('pallets')->nullOnDelete();
                $table->string('pallet_number')->nullable();
                $table->string('warehouse_location')->nullable();
                $table->dateTime('stored_at')->nullable();
                $table->unsignedInteger('age_months');
                $table->string('status');
                $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('handled_at')->nullable();
                $table->timestamps();

                $table->unique(['box_id', 'status'], 'expired_box_reports_box_status_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('expired_box_reports');
    }
};
