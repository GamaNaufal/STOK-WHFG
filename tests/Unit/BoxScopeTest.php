<?php

namespace Tests\Unit;

use App\Models\Box;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoxScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_scope_excludes_withdrawn_and_handled_or_expired_boxes(): void
    {
        $user = User::factory()->create(['role' => 'warehouse_operator']);

        Box::create([
            'box_number' => 'BOX-ACTIVE',
            'part_number' => 'P-BOX-1',
            'pcs_quantity' => 10,
            'qty_box' => 1,
            'qr_code' => 'BOX-ACTIVE|P-BOX-1|10',
            'user_id' => $user->id,
            'is_withdrawn' => false,
            'expired_status' => 'active',
        ]);

        Box::create([
            'box_number' => 'BOX-WITHDRAWN',
            'part_number' => 'P-BOX-1',
            'pcs_quantity' => 10,
            'qty_box' => 1,
            'qr_code' => 'BOX-WITHDRAWN|P-BOX-1|10',
            'user_id' => $user->id,
            'is_withdrawn' => true,
            'expired_status' => 'active',
        ]);

        Box::create([
            'box_number' => 'BOX-HANDLED',
            'part_number' => 'P-BOX-1',
            'pcs_quantity' => 10,
            'qty_box' => 1,
            'qr_code' => 'BOX-HANDLED|P-BOX-1|10',
            'user_id' => $user->id,
            'is_withdrawn' => false,
            'expired_status' => 'handled',
        ]);

        $activeBoxNumbers = Box::active()->pluck('box_number')->all();
        $withdrawnBoxNumbers = Box::withdrawn()->pluck('box_number')->all();

        $this->assertSame(['BOX-ACTIVE'], $activeBoxNumbers);
        $this->assertContains('BOX-WITHDRAWN', $withdrawnBoxNumbers);
        $this->assertNotContains('BOX-ACTIVE', $withdrawnBoxNumbers);
    }
}
