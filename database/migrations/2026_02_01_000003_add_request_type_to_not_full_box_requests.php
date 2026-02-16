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
        if (!Schema::hasTable('not_full_box_requests')) {
            return;
        }

        Schema::table('not_full_box_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('not_full_box_requests', 'request_type')) {
                $table->string('request_type')->default('supplement')->after('reason');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('not_full_box_requests')) {
            return;
        }

        Schema::table('not_full_box_requests', function (Blueprint $table) {
            if (Schema::hasColumn('not_full_box_requests', 'request_type')) {
                $table->dropColumn('request_type');
            }
        });
    }
};
