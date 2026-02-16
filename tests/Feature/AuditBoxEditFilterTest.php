<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditBoxEditFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_quick_filter_box_edit_only_shows_box_edit_audits(): void
    {
        $supervisi = User::factory()->create(['role' => 'supervisi']);
        $adminWarehouse = User::factory()->create(['role' => 'admin_warehouse']);

        AuditLog::create([
            'type' => 'other',
            'action' => 'box_updated_by_admin_warehouse',
            'model' => 'Box',
            'model_id' => 1,
            'description' => 'Edit detail box oleh admin warehouse',
            'user_id' => $adminWarehouse->id,
        ]);

        AuditLog::create([
            'type' => 'stock_input',
            'action' => 'created',
            'model' => 'StockInput',
            'model_id' => 10,
            'description' => 'Input stok biasa',
            'user_id' => $adminWarehouse->id,
        ]);

        $response = $this->actingAs($supervisi)->get(route('audit.index', [
            'quick_filter' => 'box_edit',
        ]));

        $response->assertOk();
        $response->assertViewHas('filters', function ($filters) {
            return ($filters['type'] ?? null) === 'other'
                && ($filters['action'] ?? null) === 'box_updated_by_admin_warehouse';
        });

        $response->assertViewHas('auditLogs', function ($auditLogs) {
            return $auditLogs->count() === 1
                && $auditLogs->first()->type === 'other'
                && $auditLogs->first()->action === 'box_updated_by_admin_warehouse';
        });
    }
}
