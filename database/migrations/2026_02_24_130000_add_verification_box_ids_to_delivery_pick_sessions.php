<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('delivery_pick_sessions')) {
            return;
        }

        Schema::table('delivery_pick_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('delivery_pick_sessions', 'verification_box_ids')) {
                $table->json('verification_box_ids')->nullable()->after('completion_status');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('delivery_pick_sessions')) {
            return;
        }

        Schema::table('delivery_pick_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_pick_sessions', 'verification_box_ids')) {
                $table->dropColumn('verification_box_ids');
            }
        });
    }
};
