<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Additive and resumable: never infer a shared transaction from close timestamps.
        if (! Schema::hasTable('expired_box_transactions')) {
            Schema::create('expired_box_transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasColumn('expired_box_handlings', 'transaction_id')) {
            Schema::table('expired_box_handlings', function (Blueprint $table) {
                $table->foreignId('transaction_id')->nullable()->constrained('expired_box_transactions')->restrictOnDelete();
            });
        }
        DB::table('expired_box_handlings')->whereNull('transaction_id')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                DB::transaction(function () use ($row) {
                    $handling = DB::table('expired_box_handlings')->where('id', $row->id)->lockForUpdate()->first();
                    if ($handling->transaction_id !== null) {
                        return;
                    }
                    $id = DB::table('expired_box_transactions')->insertGetId([
                        'created_by' => $handling->sent_qa_by,
                        'created_at' => $handling->created_at, 'updated_at' => $handling->updated_at,
                    ]);
                    DB::table('expired_box_handlings')->where('id', $row->id)->update(['transaction_id' => $id]);
                });
            }
        });
    }

    public function down(): void
    {
        if (DB::table('expired_box_transactions')->exists()) {
            throw new RuntimeException('Rollback ditolak: pengelompokan transaksi penanganan sudah tersimpan.');
        }
        Schema::table('expired_box_handlings', fn (Blueprint $table) => $table->dropConstrainedForeignId('transaction_id'));
        Schema::dropIfExists('expired_box_transactions');
    }
};
