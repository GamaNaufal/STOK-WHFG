<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['part_settings' => 'part_number', 'master_locations' => 'code'] as $tableName => $column) {
            // Defaults classify existing rows without replacing their IDs or relationships.
            if (! Schema::hasColumn($tableName, 'scope')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->enum('scope', ['domestic', 'export'])->default('domestic');
                });
            }

            $scopedIndex = $tableName.'_scope_'.$column.'_unique';
            if (! Schema::hasIndex($tableName, $scopedIndex)) {
                Schema::table($tableName, function (Blueprint $table) use ($column, $scopedIndex) {
                    $table->unique(['scope', $column], $scopedIndex);
                });
            }

            $previousIndex = $tableName.'_'.$column.'_unique';
            if (Schema::hasIndex($tableName, $previousIndex)) {
                Schema::table($tableName, function (Blueprint $table) use ($previousIndex) {
                    $table->dropUnique($previousIndex);
                });
            }
        }
    }

    public function down(): void
    {
        // Retain scoped masters: merging duplicate codes on rollback would destroy identity/history.
    }
};
