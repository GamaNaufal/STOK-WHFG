<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Merge delivery_completions ke delivery_pick_sessions
     * + Rename delivery_scan_issues ke delivery_issues
     */
    public function up(): void
    {
        // Step 1: Add completion fields ke delivery_pick_sessions
        Schema::table('delivery_pick_sessions', function (Blueprint $table) {
            // Tambah completion tracking
            if (!Schema::hasColumn('delivery_pick_sessions', 'completed_at')) {
                $table->datetime('completed_at')->nullable()->after('approved_at');
            }
            if (!Schema::hasColumn('delivery_pick_sessions', 'redo_until')) {
                $table->datetime('redo_until')->nullable()->after('completed_at');
            }
            if (!Schema::hasColumn('delivery_pick_sessions', 'completion_status')) {
                $table->enum('completion_status', ['pending', 'completed', 'redone'])
                    ->default('pending')
                    ->after('redo_until');
            }
        });

        // Step 2: Migrate data dari delivery_completions ke delivery_pick_sessions
        DB::statement('
            UPDATE delivery_pick_sessions dps
            JOIN delivery_completions dc ON dps.id = dc.pick_session_id
            SET 
                dps.completed_at = dc.completed_at,
                dps.redo_until = dc.redo_until,
                dps.completion_status = dc.status
            WHERE dc.status = "completed"
        ');

        // Step 3: Rename delivery_scan_issues → delivery_issues
        // (jika database support, atau drop & recreate)
        if (Schema::hasTable('delivery_scan_issues')) {
            // Create new table dengan nama baru
            Schema::create('delivery_issues', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pick_session_id')->constrained('delivery_pick_sessions')->onDelete('cascade');
                $table->foreignId('box_id')->nullable()->constrained('boxes')->nullOnDelete();
                $table->string('scanned_code');
                $table->enum('issue_type', ['scan_mismatch', 'box_damaged', 'box_withdrawn', 'quantity_mismatch', 'other'])
                    ->default('scan_mismatch');
                $table->enum('status', ['pending', 'approved', 'rejected', 'resolved'])->default('pending');
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->datetime('resolved_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                // Indexes
                $table->index('pick_session_id');
                $table->index('status');
                $table->index('created_at');
            });

            // Migrate data dari delivery_scan_issues
            DB::statement('
                INSERT INTO delivery_issues 
                    (pick_session_id, box_id, scanned_code, issue_type, status, resolved_by, resolved_at, notes, created_at, updated_at)
                SELECT 
                    pick_session_id,
                    box_id,
                    scanned_code,
                    CASE reason
                        WHEN "Box sudah withdrawn" THEN "box_withdrawn"
                        WHEN "Box tidak sesuai dengan daftar pengambilan" THEN "scan_mismatch"
                        ELSE "other"
                    END as issue_type,
                    status,
                    resolved_by,
                    resolved_at,
                    notes,
                    created_at,
                    updated_at
                FROM delivery_scan_issues
            ');
        }
    }

    public function down(): void
    {
        // Restore dari delivery_issues → delivery_scan_issues
        if (Schema::hasTable('delivery_issues')) {
            DB::statement('
                INSERT INTO delivery_scan_issues
                    (pick_session_id, box_id, scanned_code, reason, status, resolved_by, resolved_at, notes, created_at, updated_at)
                SELECT 
                    pick_session_id,
                    box_id,
                    scanned_code,
                    CASE issue_type
                        WHEN "box_withdrawn" THEN "Box sudah withdrawn"
                        WHEN "scan_mismatch" THEN "Box tidak sesuai dengan daftar pengambilan"
                        ELSE "Unknown issue"
                    END as reason,
                    status,
                    resolved_by,
                    resolved_at,
                    notes,
                    created_at,
                    updated_at
                FROM delivery_issues
            ');

            Schema::dropIfExists('delivery_issues');
        }

        Schema::table('delivery_pick_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_pick_sessions', 'completed_at')) {
                $table->dropColumn('completed_at');
            }
            if (Schema::hasColumn('delivery_pick_sessions', 'redo_until')) {
                $table->dropColumn('redo_until');
            }
            if (Schema::hasColumn('delivery_pick_sessions', 'completion_status')) {
                $table->dropColumn('completion_status');
            }
        });
    }
};
