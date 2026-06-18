<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('delivery_pick_sessions', 'allow_partial')) {
            Schema::table('delivery_pick_sessions', function (Blueprint $table) {
                $table->dropColumn('allow_partial');
            });
        }
    }

    public function down(): void
    {
        //
    }
};
