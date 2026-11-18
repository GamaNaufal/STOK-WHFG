<?php

namespace Tests\Feature;

use App\Models\ExportMasterLocation;
use App\Models\ExportStockPallet;
use App\Models\PartSetting;
use App\Models\User;
use App\Services\ExportPalletMoveService;
use App\Services\ExportStockHistoryService;
use App\Services\ExportStockReceivingService;
use App\Services\PalletNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExportPalletNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_codes_come_from_database_ids_and_are_independent_of_local_numbering(): void
    {
        $this->withoutVite();
        $local = app(PalletNumberService::class)->create();
        $firstActor = User::factory()->create(['role' => 'export_operator']);
        $secondActor = User::factory()->create(['role' => 'export_operator']);
        $service = app(ExportStockReceivingService::class);
        $pallets = [];
        foreach ([$firstActor, $secondActor] as $i => $actor) {
            $part = PartSetting::create(['part_number' => 'PALLET-CODE-'.$i, 'qty_box' => 10]);
            $location = ExportMasterLocation::create(['code' => 'PALLET-CODE-LOC-'.$i]);
            $box = $service->receive(['box_number' => '9698000'.$i, 'part_number' => $part->part_number,
                'receive_token' => (string) Str::uuid()], $actor);
            $data = ['revision' => $box->revision, 'request_token' => (string) Str::uuid(), 'location_id' => $location->id];
            $placed = $service->place($box->id, $data, $actor);
            $service->place($box->id, $data, $actor);
            $pallets[] = $placed->currentPallet;
        }
        $this->assertSame('PLT-001', $local->pallet_number);
        $this->assertSame(['PLT-EXP-001', 'PLT-EXP-002'], array_map(fn ($pallet) => $pallet->number, $pallets));
        $this->assertDatabaseCount('export_stock_pallets', 2);
        $this->assertDatabaseCount('export_stock_movements', 2);
        $this->assertSame('PLT-EXP-999', ExportStockPallet::formatNumber(999));
        $this->assertSame('PLT-EXP-1000', ExportStockPallet::formatNumber(1000));

        $this->actingAs($firstActor);
        foreach ([$pallets[0]->number, 'plt-exp-001', 'EX-000001'] as $code) {
            $this->getJson(route('export.move-pallet.search', ['code' => $code]))->assertOk()
                ->assertJsonPath('pallet.pallet_number', 'PLT-EXP-001')->assertJsonPath('pallet.id', $pallets[0]->id);
        }
        $this->getJson(route('export.move-pallet.search', ['code' => 'PLT-001']))->assertNotFound();
        $this->get(route('export.stock.index', ['q' => 'PLT-EXP-001']))->assertOk()
            ->assertViewHas('resultCount', 1)->assertSee('96980000')->assertDontSee('96980001');
    }

    public function test_pallet_move_preserves_export_code_and_history_uses_the_new_format(): void
    {
        $actor = User::factory()->create(['role' => 'export_operator']);
        $part = PartSetting::create(['part_number' => 'PALLET-CODE-MOVE', 'qty_box' => 10]);
        $location = ExportMasterLocation::create(['code' => 'PALLET-CODE-OLD']);
        $service = app(ExportStockReceivingService::class);
        $box = $service->receive(['box_number' => '96980002', 'part_number' => $part->part_number,
            'receive_token' => (string) Str::uuid()], $actor);
        $box = $service->place($box->id, ['revision' => $box->revision, 'request_token' => (string) Str::uuid(),
            'location_id' => $location->id], $actor);
        $pallet = $box->currentPallet;
        $destination = ExportMasterLocation::create(['code' => 'PALLET-CODE-NEW']);
        $move = app(ExportPalletMoveService::class);
        $move->move(['pallet_id' => $pallet->id, 'location_id' => $destination->id,
            'fingerprint' => $move->inspect($pallet)['fingerprint'], 'request_token' => (string) Str::uuid()], $actor);
        $this->assertSame('PLT-EXP-001', $pallet->fresh()->number);
        $this->assertSame($pallet->id, $destination->stockPallet->id);
        $this->assertTrue(app(ExportStockHistoryService::class)->events($box->fresh())->contains('detail', 'ID pallet tujuan PLT-EXP-001'));
    }
}
