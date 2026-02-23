<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('expired_box_reports')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            DB::statement(
                'DELETE FROM expired_box_reports
                 WHERE id IN (
                    SELECT newer.id
                    FROM expired_box_reports AS newer
                    INNER JOIN expired_box_reports AS older
                        ON newer.box_id = older.box_id
                       AND newer.status = older.status
                       AND newer.box_id IS NOT NULL
                       AND newer.id > older.id
                 )'
            );
        } else {
            DB::statement(
                'DELETE newer
                 FROM expired_box_reports AS newer
                 INNER JOIN expired_box_reports AS older
                    ON newer.box_id = older.box_id
                   AND newer.status = older.status
                   AND newer.box_id IS NOT NULL
                   AND newer.id > older.id'
            );
        }

        Schema::table('expired_box_reports', function (Blueprint $table) {
            $table->unique(['box_id', 'status'], 'expired_box_reports_box_status_unique');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('expired_box_reports')) {
            return;
        }

        Schema::table('expired_box_reports', function (Blueprint $table) {
            $table->dropUnique('expired_box_reports_box_status_unique');
        });
    }
};
