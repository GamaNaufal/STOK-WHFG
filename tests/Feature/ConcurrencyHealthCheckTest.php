<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\DeliveryOrder;
use App\Models\DeliveryPickSession;
use App\Models\StockWithdrawal;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ConcurrencyHealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_without_a_box_reference_does_not_hide_a_real_withdrawal_duplicate(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // The existing verification check uses MySQL JSON_LENGTH.
            DB::connection()->getPdo()->sqliteCreateFunction('JSON_LENGTH', fn ($json) => $json === null ? null : count(json_decode($json, true)), 1);
        }

        $operator = User::factory()->create();
        $order = DeliveryOrder::create(['sales_user_id' => $operator->id, 'customer_name' => 'History',
            'delivery_date' => now(), 'status' => 'completed']);
        $session = DeliveryPickSession::create(['delivery_order_id' => $order->id, 'created_by' => $operator->id,
            'status' => 'completed', 'completion_status' => 'completed']);
        $history = ['pick_session_id' => $session->id, 'user_id' => $operator->id, 'part_number' => 'HISTORY',
            'pcs_quantity' => 12, 'box_quantity' => 1, 'warehouse_location' => 'TEST', 'status' => 'completed'];

        foreach (['94000011', '94000012'] as $number) {
            $box = Box::create(['box_number' => $number, 'part_number' => 'HISTORY', 'pcs_quantity' => 12,
                'qr_code' => $number, 'user_id' => $operator->id, 'is_withdrawn' => true]);
            StockWithdrawal::create($history + ['box_id' => $box->id]);
            $box->forceDelete();
        }
        $this->assertSame(2, StockWithdrawal::whereNull('box_id')->count());
        $this->artisan('health:concurrency')->expectsOutput('Duplicate withdrawals: 0')->assertSuccessful();

        // Simulate a damaged schema on this isolated test database only.
        Schema::table('stock_withdrawals', fn (Blueprint $table) => $table->dropUnique('stock_withdrawals_session_box_unique'));
        $box = Box::create(['box_number' => '94000013', 'part_number' => 'HISTORY', 'pcs_quantity' => 12,
            'qr_code' => '94000013', 'user_id' => $operator->id, 'is_withdrawn' => true]);
        StockWithdrawal::create($history + ['box_id' => $box->id]);
        StockWithdrawal::create($history + ['box_id' => $box->id]);
        $this->artisan('health:concurrency')->expectsOutput('Duplicate withdrawals: 1')->assertFailed();
        $this->assertDatabaseCount('stock_withdrawals', 4);
    }
}
