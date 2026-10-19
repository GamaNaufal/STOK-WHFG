<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\Pallet;
use App\Models\PartSetting;
use App\Models\StockLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockViewNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_actions_follow_default_role_permissions(): void
    {
        foreach ([
            'admin' => [true, true],
            'admin_warehouse' => [true, true],
            'warehouse_operator' => [false, false],
            'supervisi' => [false, true],
            'ppc' => [false, false],
        ] as $role => [$edit, $delete]) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $response = $this->get(route('stock-view.index'));
            $response->assertOk();
            preg_match('/<script id="stockViewBootstrap" type="application\/json">(.*?)<\/script>/s', $response->getContent(), $matches);
            $bootstrap = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame($edit, $bootstrap['canEditBox'], $role);
            $this->assertSame($delete, $bootstrap['canDeleteStock'], $role);
        }
        $this->actingAs(User::factory()->create(['role' => 'sales']))
            ->get(route('stock-view.index'))->assertForbidden();
    }

    public function test_views_keep_filters_and_exports_are_downloads(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);
        PartSetting::create(['part_number' => 'PART-TEST', 'qty_box' => 100]);
        $pallet = Pallet::create(['pallet_number' => 'PLT-TEST-001']);
        StockLocation::create(['pallet_id' => $pallet->id, 'warehouse_location' => 'A-01', 'stored_at' => now()]);
        foreach ([['12345678', 100, false, 'active'], ['12345679', 50, true, 'active'], ['12345680', 100, false, 'expired']] as [$number,$pcs,$notFull,$status]) {
            $box = Box::create(['box_number' => $number, 'part_number' => 'PART-TEST', 'pcs_quantity' => $pcs, 'qty_box' => 100, 'qr_code' => $number.'|PART-TEST|'.$pcs, 'user_id' => $user->id, 'is_withdrawn' => false, 'is_not_full' => $notFull, 'expired_status' => $status]);
            $pallet->boxes()->attach($box->id);
        }

        foreach (['part', 'pallet', 'box_id', 'not_full'] as $mode) {
            $r = $this->get(route('stock-view.index', ['view_mode' => $mode, 'search' => 'PART-TEST', 'sort' => 'created_newest']));
            $r->assertOk()->assertViewHas('viewMode', $mode)->assertViewHas('search', 'PART-TEST')->assertViewHas('sortMode', 'created_newest');
            if ($mode !== 'not_full') {
                $r->assertSee('12345680', false);
            }
            $empty = $this->get(route('stock-view.index', ['view_mode' => $mode, 'search' => 'NONE']));
            $empty->assertOk()->assertViewHas('summaryTotalBox', 0);
        }
        foreach (['part', 'box-id', 'pallet'] as $mode) {
            $r = $this->get(route('stock-view.export-'.$mode, ['search' => 'PART-TEST']));
            $r->assertOk();
            $this->assertStringContainsString('attachment', $r->headers->get('content-disposition'));
            $this->assertStringStartsWith('PK', file_get_contents($r->baseResponse->getFile()->getPathname()));
        }
    }
}
