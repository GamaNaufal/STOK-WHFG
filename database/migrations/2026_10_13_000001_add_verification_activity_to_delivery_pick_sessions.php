<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_pick_sessions', function (Blueprint $table) {
            $table->timestamp('verification_last_activity_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_pick_sessions', function (Blueprint $table) {
            $table->dropColumn('verification_last_activity_at');
        });
    }
};
