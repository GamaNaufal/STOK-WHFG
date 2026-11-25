<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('part_settings', fn (Blueprint $table) => $table->softDeletes());
    }

    public function down(): void
    {
        if (DB::table('part_settings')->whereNotNull('deleted_at')->exists()) {
            throw new RuntimeException('Rollback ditolak karena akan mengaktifkan kembali Master Part yang diarsipkan.');
        }

        Schema::table('part_settings', fn (Blueprint $table) => $table->dropSoftDeletes());
    }
};
