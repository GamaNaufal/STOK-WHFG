<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expired_box_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('expired_box_transactions')->restrictOnDelete();
            $table->uuid('request_token')->unique();
            $table->string('payload_hash', 64);
            $table->unsignedBigInteger('received_by');
            $table->dateTime('received_at');
            $table->string('destination_pallet')->nullable();
            $table->string('destination_location')->nullable();
            $table->timestamps();
        });
        Schema::table('expired_box_handlings', function (Blueprint $table) {
            $table->foreignId('receipt_id')->nullable()->constrained('expired_box_receipts')->restrictOnDelete();
            $table->string('replacement_mapping')->nullable();
        });
        // Historical receipts retain their original deadlines and physical mappings.
    }

    public function down(): void
    {
        if (DB::table('expired_box_receipts')->exists()) {
            throw new RuntimeException('Rollback ditolak: riwayat penerimaan packing sudah ada.');
        }
        Schema::table('expired_box_handlings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('receipt_id');
            $table->dropColumn('replacement_mapping');
        });
        Schema::dropIfExists('expired_box_receipts');
    }
};
