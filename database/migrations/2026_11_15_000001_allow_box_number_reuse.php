<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Existing rows are the first use. Keep every internal ID and historical FK.
        Schema::table('box_identities', fn (Blueprint $table) => $table->unsignedInteger('last_cycle')->default(1));
        foreach (['boxes', 'export_stock_boxes', 'export_fulfillment_boxes'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->unsignedInteger('identity_cycle')->default(1);
                $table->unique(['box_number', 'identity_cycle'], $name.'_number_cycle_unique');
            });
            Schema::table($name, fn (Blueprint $table) => $table->dropUnique(['box_number']));
        }
        Schema::table('expired_box_handlings', function (Blueprint $table) {
            // The immutable scope + source/replacement internal IDs remain unique.
            $table->index('source_box_number');
            $table->index('replacement_box_number');
            $table->dropUnique(['source_box_number']);
            $table->dropUnique(['replacement_box_number']);
        });
        Schema::table('not_full_box_requests', function (Blueprint $table) {
            $table->string('pending_box_number')->nullable()
                ->virtualAs("CASE WHEN status = 'pending' THEN box_number ELSE NULL END");
            $table->unique('pending_box_number', 'not_full_pending_box_unique');
            $table->index('box_number');
        });
        Schema::table('not_full_box_requests', fn (Blueprint $table) => $table->dropUnique(['box_number']));
        Schema::table('export_receiving_batch_items', fn (Blueprint $table) => $table->unsignedInteger('expected_identity_cycle')->nullable());
        DB::table('export_receiving_batch_items')->whereIn('export_receiving_batch_id',
            DB::table('export_receiving_batches')->where('status', 'draft')->select('id'))
            ->orderBy('id')->chunkById(500, function ($items) {
                $cycles = DB::table('box_identities')->whereIn('box_number', $items->pluck('box_number'))->pluck('last_cycle', 'box_number');
                foreach ($items as $item) {
                    DB::table('export_receiving_batch_items')->where('id', $item->id)->update([
                        'expected_identity_cycle' => (int) ($cycles[$item->box_number] ?? 0),
                    ]);
                }
            });
    }

    public function down(): void
    {
        throw new RuntimeException('Rollback ditolak: nomor box dapat memiliki beberapa riwayat penggunaan. Pulihkan melalui migration yang ditinjau tanpa menghapus histori.');
    }
};
