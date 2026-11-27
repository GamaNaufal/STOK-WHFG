<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Snapshots also include legacy IDs from the wider boxes.box_number column.
        Schema::table('stock_opname_items', function (Blueprint $table) {
            $table->string('box_number', 255)->change();
        });
    }

    public function down(): void
    {
        $length = DB::getDriverName() === 'sqlite' ? 'length' : 'char_length';
        if (DB::table('stock_opname_items')->whereRaw("$length(box_number) > 8")->exists()) {
            throw new RuntimeException('Rollback ditolak karena histori opname menyimpan ID Box lebih dari 8 karakter.');
        }

        Schema::table('stock_opname_items', function (Blueprint $table) {
            $table->char('box_number', 8)->change();
        });
    }
};
