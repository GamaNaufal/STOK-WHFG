<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This registry survives permanent QA rejection; a barcode is never reusable.
        Schema::create('box_identities', function (Blueprint $table) {
            $table->string('box_number')->primary();
        });
        DB::table('boxes')->orderBy('id')->chunkById(500, function ($boxes) {
            DB::table('box_identities')->insert($boxes->map(fn ($box) => ['box_number' => $box->box_number])->all());
        });
        Schema::create('expired_box_handlings', function (Blueprint $table) {
            $table->id();
            // Immutable references, deliberately without cascading foreign keys.
            $table->unsignedBigInteger('source_box_id')->unique();
            $table->string('source_box_number')->unique();
            $table->unsignedBigInteger('replacement_box_id')->nullable()->unique();
            $table->string('replacement_box_number')->nullable()->unique();
            $table->string('part_number');
            $table->unsignedInteger('pcs_quantity');
            $table->dateTime('original_stored_at');
            $table->string('status')->index();
            $table->json('source_snapshot');
            $table->json('history_snapshot')->nullable();
            $table->text('qa_notes')->nullable();
            foreach (['sent_qa', 'qa_decided', 'sent_packing', 'received'] as $event) {
                $table->dateTime($event.'_at')->nullable();
                $table->unsignedBigInteger($event.'_by')->nullable();
            }
            $table->dateTime('label_issued_at')->nullable();
            $table->dateTime('usable_until')->nullable();
            $table->string('destination_pallet')->nullable();
            $table->string('destination_location')->nullable();
            $table->timestamps();
        });
        // Old reactivations must go through QA. Preserve their former deadline for review.
        Schema::table('boxes', function (Blueprint $table) {
            $table->dateTime('legacy_reactivated_until')->nullable();
        });
        DB::table('boxes')->whereNotNull('reactivated_until')->where('is_withdrawn', false)
            ->whereNull('deleted_at')->update([
                'legacy_reactivated_until' => DB::raw('reactivated_until'),
                'reactivated_until' => now(),
            ]);
    }

    public function down(): void
    {
        if (DB::table('expired_box_handlings')->exists()) {
            throw new RuntimeException('Rollback ditolak: riwayat QA/packing sudah ada. Gunakan pemulihan yang ditinjau agar jejak tidak hilang.');
        }
        // Do not silently reactivate inventory on rollback.
        Schema::table('boxes', fn (Blueprint $table) => $table->dropColumn('legacy_reactivated_until'));
        Schema::dropIfExists('expired_box_handlings');
        Schema::dropIfExists('box_identities');
    }
};
