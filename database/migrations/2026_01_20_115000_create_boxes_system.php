<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('boxes', function (Blueprint $table) {
            $table->id();
            $table->string('box_number')->unique(); // No Box unik
            $table->string('part_number'); // No Part
            $table->string('part_name')->nullable();
            $table->integer('pcs_quantity'); // Jumlah PCS dalam box
            $table->integer('qty_box')->nullable();
            $table->string('type_box')->nullable();
            $table->string('wk_transfer')->nullable();
            $table->string('lot01')->nullable();
            $table->string('lot02')->nullable();
            $table->string('lot03')->nullable();
            $table->longText('qr_code'); // QR Code (encoded)
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // Admin yang membuat
            $table->boolean('is_withdrawn')->default(false);
            $table->dateTime('withdrawn_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('part_number');
            $table->index('user_id');
            $table->index('created_at');
        });

        Schema::create('pallet_boxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pallet_id')->constrained('pallets')->onDelete('cascade');
            $table->foreignId('box_id')->constrained('boxes')->onDelete('cascade');
            $table->timestamps();

            // Composite unique key - satu pallet tidak boleh punya box yang sama 2x
            $table->unique(['pallet_id', 'box_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pallet_boxes');
        Schema::dropIfExists('boxes');
    }
};
