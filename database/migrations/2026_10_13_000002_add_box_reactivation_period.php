<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boxes', function (Blueprint $table) {
            $table->dateTime('reactivated_until')->nullable()->index();
        });
        Schema::table('expired_box_reports', function (Blueprint $table) {
            $table->index(['box_id', 'status']);
            $table->dropUnique('expired_box_reports_box_status_unique');
            $table->dateTime('reactivated_until')->nullable();
        });
        Schema::table('delivery_issues', function (Blueprint $table) {
            $table->enum('issue_type', ['scan_mismatch', 'box_damaged', 'box_withdrawn', 'box_expired', 'quantity_mismatch', 'other'])
                ->default('scan_mismatch')->change();
        });
    }

    public function down(): void
    {
        // Preserve repeated history and recorded box_expired issues on rollback;
        // do not restore constraints which would reject those existing records.
        Schema::table('expired_box_reports', function (Blueprint $table) {
            $table->dropColumn('reactivated_until');
        });
        Schema::table('boxes', function (Blueprint $table) {
            $table->dropColumn('reactivated_until');
        });
    }
};
