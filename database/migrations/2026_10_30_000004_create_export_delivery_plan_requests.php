<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_delivery_plan_requests', function (Blueprint $table) {
            $table->uuid('request_token')->primary();
            $table->char('request_hash', 64);
            $table->foreignId('export_delivery_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('action', 16);
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        if (DB::table('export_delivery_plan_requests')->exists()) {
            throw new RuntimeException('Rollback ditolak: token penyimpanan rencana sudah dipakai. Pertahankan riwayat agar retry tidak membuat permintaan ganda.');
        }
        Schema::dropIfExists('export_delivery_plan_requests');
    }
};
