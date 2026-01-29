<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pick_session_id')->constrained('delivery_pick_sessions')->onDelete('cascade');
            $table->foreignId('box_id')->nullable()->constrained('boxes')->nullOnDelete();
            $table->string('scanned_code');
            $table->enum('issue_type', ['scan_mismatch', 'box_damaged', 'box_withdrawn', 'quantity_mismatch', 'other'])
                ->default('scan_mismatch');
            $table->enum('status', ['pending', 'approved', 'rejected', 'resolved'])->default('pending');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('pick_session_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_issues');
    }
};