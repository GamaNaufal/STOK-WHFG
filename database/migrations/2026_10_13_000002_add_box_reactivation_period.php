
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Preflight: verify the existing ENUM before changing tables.
        $allowedTypes = [
            'scan_mismatch',
            'box_damaged',
            'box_withdrawn',
            'box_expired',
            'quantity_mismatch',
            'other',
        ];

        $existingTypes = [];
        if (DB::getDriverName() === 'mysql') {
            $column = DB::selectOne(
                'SELECT COLUMN_TYPE AS column_type
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?',
                ['delivery_issues', 'issue_type']
            );

            if (! $column || ! str_starts_with(
                strtolower($column->column_type),
                'enum('
            )) {
                throw new RuntimeException(
                    'delivery_issues.issue_type is not an ENUM.'
                );
            }

            preg_match_all(
                "/'((?:''|[^'])*)'/",
                $column->column_type,
                $matches
            );

            $existingTypes = array_map(
                fn ($value) => str_replace("''", "'", $value),
                $matches[1]
            );

            // Never silently remove existing ENUM values.
            $unexpectedTypes = array_diff(
                $existingTypes,
                $allowedTypes
            );

            if (! empty($unexpectedTypes)) {
                throw new RuntimeException(
                    'Unexpected issue_type values: '.
                    implode(', ', $unexpectedTypes)
                );
            }
        }

        // 1. Boxes: avoid duplicate column creation.
        if (! Schema::hasColumn('boxes', 'reactivated_until')) {
            Schema::table('boxes', function (Blueprint $table) {
                $table->dateTime('reactivated_until')
                    ->nullable()
                    ->index();
            });
        }

        // 2. Ensure the regular composite index exists.
        if (! Schema::hasIndex(
            'expired_box_reports',
            'expired_box_reports_box_id_status_index'
        )) {
            Schema::table(
                'expired_box_reports',
                function (Blueprint $table) {
                    $table->index(['box_id', 'status']);
                }
            );
        }

        // 3. Remove the old UNIQUE constraint only if present.
        if (Schema::hasIndex(
            'expired_box_reports',
            'expired_box_reports_box_status_unique'
        )) {
            Schema::table(
                'expired_box_reports',
                function (Blueprint $table) {
                    $table->dropUnique(
                        'expired_box_reports_box_status_unique'
                    );
                }
            );
        }

        // 4. Add reactivation period to expired-box reports.
        if (! Schema::hasColumn(
            'expired_box_reports',
            'reactivated_until'
        )) {
            Schema::table(
                'expired_box_reports',
                function (Blueprint $table) {
                    $table->dateTime('reactivated_until')
                        ->nullable();
                }
            );
        }

        // 5. Change the ENUM only when required.
        if (! empty(array_diff($allowedTypes, $existingTypes))) {
            Schema::table(
                'delivery_issues',
                function (Blueprint $table) use ($allowedTypes) {
                    $table->enum('issue_type', $allowedTypes)
                        ->default('scan_mismatch')
                        ->change();
                }
            );
        }
    }

    public function down(): void
    {
        // Intentionally prevent destructive rollback.
        // Removing reactivation columns could destroy history.
        throw new RuntimeException(
            'This migration requires a manually reviewed rollback.'
        );
    }
};
