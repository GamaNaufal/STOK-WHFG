<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        switch ($driver) {
            case 'mysql':
            case 'mariadb':
                DB::statement(
                    "ALTER TABLE part_settings MODIFY part_number VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL"
                );
                break;
            case 'sqlsrv':
                DB::statement(
                    "IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'part_settings_part_number_unique' AND object_id = OBJECT_ID('part_settings')) DROP INDEX part_settings_part_number_unique ON part_settings"
                );
                DB::statement(
                    "ALTER TABLE part_settings ALTER COLUMN part_number NVARCHAR(255) COLLATE Latin1_General_CS_AS NOT NULL"
                );
                DB::statement(
                    "CREATE UNIQUE INDEX part_settings_part_number_unique ON part_settings(part_number)"
                );
                break;
            case 'sqlite':
                DB::statement("DROP INDEX IF EXISTS part_settings_part_number_unique");
                DB::statement(
                    "CREATE UNIQUE INDEX part_settings_part_number_unique ON part_settings(part_number COLLATE BINARY)"
                );
                break;
            default:
                return;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        switch ($driver) {
            case 'mysql':
            case 'mariadb':
                DB::statement(
                    "ALTER TABLE part_settings MODIFY part_number VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL"
                );
                break;
            case 'sqlsrv':
                DB::statement(
                    "IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'part_settings_part_number_unique' AND object_id = OBJECT_ID('part_settings')) DROP INDEX part_settings_part_number_unique ON part_settings"
                );
                DB::statement(
                    "ALTER TABLE part_settings ALTER COLUMN part_number NVARCHAR(255) COLLATE Latin1_General_CI_AS NOT NULL"
                );
                DB::statement(
                    "CREATE UNIQUE INDEX part_settings_part_number_unique ON part_settings(part_number)"
                );
                break;
            case 'sqlite':
                DB::statement("DROP INDEX IF EXISTS part_settings_part_number_unique");
                DB::statement(
                    "CREATE UNIQUE INDEX part_settings_part_number_unique ON part_settings(part_number COLLATE NOCASE)"
                );
                break;
            default:
                return;
        }
    }
};
