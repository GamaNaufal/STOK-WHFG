<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boxes', function (Blueprint $table) {
            $table->boolean('is_withdrawn')->default(false)->after('user_id');
            $table->dateTime('withdrawn_at')->nullable()->after('is_withdrawn');
        });
    }

    public function down(): void
    {
        Schema::table('boxes', function (Blueprint $table) {
            $table->dropColumn(['is_withdrawn', 'withdrawn_at']);
        });
    }
};